<?php

namespace Ghazali\EPassPackage\Models\Concerns;

use Ghazali\EPassPackage\Services\FieldEncryptionService;

/**
 * Transparent field-level encryption for EpassUser.
 *
 * When EPASS_FIELD_ENCRYPTION_KEY is set:
 *   - retrieved: decrypts all encryptable fields using the row's wrapped DEK
 *   - saving:    encrypts all encryptable fields, regenerates wrapped DEK
 *   - saved:     restores plaintext on the in-memory model so it stays usable
 *
 * When EPASS_FIELD_ENCRYPTION_KEY is NOT set, the hook methods are no-ops.
 * This makes encryption fully opt-in with zero runtime overhead when disabled.
 */
trait HasEncryptedFields
{
    /**
     * Register model event hooks.
     * Laravel calls this automatically because of the boot{TraitName} convention.
     */
    public static function bootHasEncryptedFields(): void
    {
        /** @var FieldEncryptionService $svc */
        $svc = app(FieldEncryptionService::class);

        if (!$svc->isEnabled()) {
            return;
        }

        // After fetching from DB → decrypt to plaintext for application use.
        static::retrieved(function (self $model) use ($svc): void {
            $model->applyDecryption($svc);
        });

        // Before writing to DB → encrypt plaintext to ciphertext.
        static::saving(function (self $model) use ($svc): void {
            $model->applyEncryption($svc);
        });

        // After writing to DB → restore plaintext so the model stays usable
        // without requiring a fresh ->fresh() call from the caller.
        static::saved(function (self $model) use ($svc): void {
            $model->applyDecryption($svc);
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Internal helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Encrypt all encryptable fields from the current raw attributes.
     * Generates a fresh DEK per save; the wrapped DEK is stored in encrypted_dek.
     */
    private function applyEncryption(FieldEncryptionService $svc): void
    {
        $attrs = $this->getAttributes();
        $wrappedDek = $svc->encryptAttributes($attrs);
        $attrs['encrypted_dek'] = $wrappedDek;
        $this->setRawAttributes($attrs, false);
    }

    /**
     * Decrypt all encryptable fields using the wrapped DEK stored in the row.
     * No-ops gracefully when encrypted_dek is absent (e.g. rows written before
     * encryption was enabled — they simply remain as-is).
     */
    private function applyDecryption(FieldEncryptionService $svc): void
    {
        $attrs = $this->getAttributes();
        $wrappedDek = $attrs['encrypted_dek'] ?? null;

        if (empty($wrappedDek)) {
            // Row predates encryption — nothing to decrypt.
            return;
        }

        $svc->decryptAttributes($attrs, $wrappedDek);
        $this->setRawAttributes($attrs, false);
    }

    /**
     * Convenience accessor for callers that need to check whether this row
     * has field-level encryption applied.
     */
    public function isFieldEncrypted(): bool
    {
        return !empty($this->getAttributes()['encrypted_dek'] ?? null);
    }

    /**
     * Override Eloquent's dirty-check logic for encrypted fields.
     * Prevents Carbon from trying to parse encrypted ciphertexts as dates.
     */
    public function originalIsEquivalent($key)
    {
        if (in_array($key, FieldEncryptionService::encryptableFields(), true)) {
            $current = $this->getAttributes()[$key] ?? null;
            $original = $this->original[$key] ?? null;
            return $current === $original;
        }

        return parent::originalIsEquivalent($key);
    }
}
