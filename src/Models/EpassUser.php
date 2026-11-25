<?php

namespace Ghazali\EPassPackage\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Ghazali\EPassPackage\Models\Concerns\HasEncryptedFields;
use Ghazali\EPassPackage\Traits\UsesEpassFields;
use Ghazali\UniversalProbabilisticEngine\Traits\ShieldsWithUpe;

class EpassUser extends Authenticatable
{
    use HasFactory, Notifiable, HasEncryptedFields, UsesEpassFields, ShieldsWithUpe;

    protected $table = 'epass_users';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected array $upeShieldedColumns = [
        'holder_profile_lookup',
        'current_mrz_key_lookup',
        'current_document_number_lookup',
        'passport_identity_hash',
        'image_hash_lookup',
        'api_token',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->getAttribute('uuid'))) {
                $model->setAttribute('uuid', (string) Str::uuid());
            }
        });

        static::created(function (self $model): void {
            Log::info('EpassUser.created', [
                'uuid' => $model->getKey(),
            ]);
        });

        static::updated(function (self $model): void {
            Log::info('EpassUser.updated', [
                'uuid'  => $model->getKey(),
                'dirty' => array_keys($model->getChanges()),
            ]);
        });

        static::saving(function (self $model): void {
            Log::info('EpassUser.saving', [
                'uuid'  => $model->getKey(),
                'dirty' => array_keys($model->getDirty()),
            ]);
        });
    }

    /**
     * Boot-time initialisation: derive $fillable and $hidden from the
     * central config so they are never out of sync with other components.
     */
    public function initializeUsesEpassFields(): void
    {
        // Merge config-derived fillable into whatever the model already declares.
        $this->fillable = static::epassFillable();
        $this->hidden   = static::epassHidden();
    }

    protected function casts(): array
    {
        return static::epassCasts();
    }

    public function verifyPassport(string $documentNumber, string $birthDate, string $expiryDate): bool
    {
        $mrzBirth = strlen($birthDate) === 6 ? $birthDate : date('ymd', strtotime($birthDate));
        $mrzExpiry = strlen($expiryDate) === 6 ? $expiryDate : date('ymd', strtotime($expiryDate));

        $storedBirth = $this->birth_date ? $this->birth_date->format('ymd') : null;
        $storedExpiry = $this->current_expiry_date ? $this->current_expiry_date->format('ymd') : null;

        return !empty($this->document_number_hash)
            && !empty($storedBirth)
            && !empty($storedExpiry)
            && Hash::check($documentNumber, $this->document_number_hash)
            && hash_equals($storedBirth, $mrzBirth)
            && hash_equals($storedExpiry, $mrzExpiry);
    }

    public function generateToken(): string
    {
        do {
            $plainToken = bin2hex(random_bytes(40));
            $hashedToken = hash('sha256', $plainToken);
        } while (static::query()->where('api_token', $hashedToken)->exists());

        $this->api_token = $hashedToken;
        $this->api_token_expires_at = null;
        $this->save();

        return $plainToken;
    }

    public function revokeToken(): void
    {
        $this->api_token = null;
        $this->api_token_expires_at = null;
        $this->save();
    }

    /**
     * Increment the linked accounts reference counter when an additional host model links this passport.
     */
    public function incrementLinkedAccounts(): void
    {
        if (Schema::hasColumn($this->getTable(), 'linked_accounts_count')) {
            $this->increment('linked_accounts_count');
            Log::info('[epass:user] incremented linked_accounts_count', [
                'uuid' => $this->getKey(),
                'linked_accounts_count' => (int) $this->fresh()->linked_accounts_count,
            ]);
        }
    }

    /**
     * Decrement the linked accounts reference counter, or delete the biometric record
     * if the counter reaches zero (or was 1).
     *
     * @return bool True if the record was deleted, false if only decremented.
     */
    public function decrementOrDelete(): bool
    {
        if (Schema::hasColumn($this->getTable(), 'linked_accounts_count')) {
            $this->refresh();
            $count = (int) ($this->linked_accounts_count ?? 1);
            if ($count > 1) {
                $this->decrement('linked_accounts_count');
                $remaining = max(0, $count - 1);
                Log::info('[epass:teardown] decremented linked_accounts_count for EpassUser', [
                    'uuid' => $this->getKey(),
                    'remaining_linked_accounts' => $remaining,
                ]);
                return false;
            }
        }

        $this->delete();
        Log::info('[epass:teardown] deleted package EpassUser record (linked_accounts_count reached 0)', [
            'uuid' => $this->getKey(),
        ]);
        return true;
    }

    /**
     * Static helper to decrement or delete an EpassUser by UUID.
     */
    public static function decrementOrDeleteUuid(string $uuid): bool
    {
        $epassUser = static::query()->where('uuid', $uuid)->first();
        if ($epassUser) {
            return $epassUser->decrementOrDelete();
        }

        return false;
    }

    /**
     * Returns a structured array of passport/biometric fields.
     * The package no longer exposes host-editable profile fields.
     */
    public function visibleProfilePayload(): array
    {
        $payload = [];

        $payload['profile_photo_path'] = $this->face_image_path;

        $payload['full_name'] = trim(($this->given_names ?? '') . ' ' . ($this->surname ?? '')) ?: null;
        $payload['gender'] = $this->gender;
        $payload['nationality'] = $this->nationality;
        $payload['place_of_birth'] = $this->place_of_birth;
        $payload['age'] = $this->birth_date ? (int) now()->diffInYears($this->birth_date, true) : null;
        $payload['passport_expiry_date'] = optional($this->current_expiry_date)->toDateString();

        return $payload;
    }

    /**
     * Ensure we only save the relative path in the database, stripping any full URL prefixes.
     */
    public function setFaceImagePathAttribute($value)
    {
        if (is_string($value) && $value !== '') {
            // Normalize slashes
            $value = str_replace('\\', '/', $value);
            
            // Strip any URL prefixes containing 'storage/' to get the relative path
            if (str_contains($value, 'storage/')) {
                $value = substr($value, strpos($value, 'storage/') + 8);
            } else {
                // If it's a full URL but doesn't have 'storage/', parse and take the path
                $parsed = parse_url($value);
                if (isset($parsed['scheme']) && isset($parsed['path'])) {
                    $value = ltrim($parsed['path'], '/');
                }
            }
        }
        $this->attributes['face_image_path'] = $value;
    }

    /**
     * Dynamically resolve the absolute URL for the face image path to use the current host.
     */
    public function getFaceImagePathAttribute($value)
    {
        if (empty($value)) {
            return null;
        }

        // If stored as an absolute URL with '/storage/', extract the relative path and build it dynamically.
        if (str_contains($value, '/storage/')) {
            $relativePath = substr($value, strpos($value, '/storage/') + 9);
            return asset('storage/' . $relativePath);
        }

        // If it's a relative path, resolve it to the storage asset.
        if (!str_starts_with($value, 'http://') && !str_starts_with($value, 'https://')) {
            return asset('storage/' . $value);
        }

        return $value;
    }

    // ── Passport Accessors ────────────────────────────────────────────────────
    public function getPassportFullNameAttribute(): ?string
    {
        return trim(($this->given_names ?? '') . ' ' . ($this->surname ?? '')) ?: null;
    }

    public function getPassportSurnameAttribute(): ?string
    {
        return $this->surname;
    }

    public function getPassportGivenNamesAttribute(): ?string
    {
        return $this->given_names;
    }

    public function getPassportGenderAttribute(): ?string
    {
        return $this->gender;
    }

    public function getPassportNationalityAttribute(): ?string
    {
        return $this->nationality;
    }

    public function getPassportPlaceOfBirthAttribute(): ?string
    {
        return $this->place_of_birth;
    }

    public function getPassportBirthDateAttribute()
    {
        return $this->birth_date;
    }

    public function getPassportCurrentExpiryDateAttribute()
    {
        return $this->current_expiry_date;
    }


    /**
     * Resolve the package landing page.
     *
     * The package no longer owns a host dashboard surface. Its own welcome
     * page is the stable landing point after package-auth flows complete.
     */
    public static function configuredDashboardUrl(?string $alias = null): ?string
    {
        return \Ghazali\EPassPackage\Support\EpassDashboardRouteResolver::resolveUrl($alias);
    }

    public static function configuredDashboardPath(): ?string
    {
        $path = parse_url(self::configuredDashboardUrl(), PHP_URL_PATH);
        return is_string($path) && $path !== '' ? $path : null;
    }

    /**
     * Resolve the default redirect target for authenticated ePass users.
     *
     * This now resolves to the package landing page.
     */
    public static function defaultDashboardUrl(): string
    {
        return self::configuredDashboardUrl();
    }
}
