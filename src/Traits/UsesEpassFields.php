<?php

namespace Ghazali\EPassPackage\Traits;

/**
 * Reads field metadata from config/epass_fields.php and provides helper
 * methods that return derived lists for the package-owned core/passport
 * columns.
 *
 * Used by the EpassUser model and FieldEncryptionService so every component
 * reads from the same single source of truth.
 */
trait UsesEpassFields
{
    /**
     * Return the full merged field definitions from all config sections.
     *
     * @return array<string, array>
     */
    public static function epassAllFieldDefs(): array
    {
        $config = config('epass_fields', []);

        return array_merge(
            $config['core_fields'] ?? [],
            $config['passport_fields'] ?? [],
        );
    }

    /**
     * List of field names marked as fillable in the config.
     *
     * @return string[]
     */
    public static function epassFillable(): array
    {
        $fillable = [];
        foreach (static::epassAllFieldDefs() as $field => $meta) {
            if (!empty($meta['fillable'])) {
                $fillable[] = $field;
            }
        }

        return $fillable;
    }

    /**
     * List of field names marked as hidden in the config.
     *
     * @return string[]
     */
    public static function epassHidden(): array
    {
        $hidden = [];
        foreach (static::epassAllFieldDefs() as $field => $meta) {
            if (!empty($meta['hidden'])) {
                $hidden[] = $field;
            }
        }

        return $hidden;
    }

    /**
     * Cast definitions derived from the config.
     *
     * @return array<string, string>
     */
    public static function epassCasts(): array
    {
        $casts = [];
        foreach (static::epassAllFieldDefs() as $field => $meta) {
            if (isset($meta['cast'])) {
                $casts[$field] = $meta['cast'];
            }
        }

        return $casts;
    }

    /**
     * List of field names that should be encrypted at rest.
     *
     * @return string[]
     */
    public static function epassEncryptable(): array
    {
        $encryptable = [];
        foreach (static::epassAllFieldDefs() as $field => $meta) {
            if (!empty($meta['encrypt'])) {
                $encryptable[] = $field;
            }
        }

        return $encryptable;
    }

    /**
     * Shared validation rules for every endpoint that receives passport-scan
     * input from the client (loginEpass, registerEpass, linkEpass, updateEpass).
     *
     * Both prefixed (passport_surname) and un-prefixed (surname) variants are
     * accepted so the controller is compatible with any client version.
     *
     * @return array<string, array>
     */
    public static function epassPassportInputRules(): array
    {
        return [
            'passport_document_number'    => ['nullable', 'string', 'max:120'],
            'passport_birth_date'         => ['required', 'string', 'max:40'],
            'passport_current_expiry_date'=> ['required', 'string', 'max:40'],

            // surname — accept either key
            'surname'                     => ['required_without:passport_surname', 'nullable', 'string', 'max:120'],
            'passport_surname'            => ['required_without:surname',          'nullable', 'string', 'max:120'],

            // given names — accept either key
            'given_names'                 => ['required_without:passport_given_names', 'nullable', 'string', 'max:191'],
            'passport_given_names'        => ['required_without:given_names',          'nullable', 'string', 'max:191'],

            // place of birth — accept either key
            'place_of_birth'              => ['required_without:passport_place_of_birth', 'nullable', 'string', 'max:191'],
            'passport_place_of_birth'     => ['required_without:place_of_birth',          'nullable', 'string', 'max:191'],

            // optional passport fields — accept either key
            'device_id'                   => ['nullable', 'string', 'max:191'],
            'passport_device_id'          => ['nullable', 'string', 'max:191'],
            'gender'                      => ['nullable', 'string', 'max:30'],
            'passport_gender'             => ['nullable', 'string', 'max:30'],
            'nationality'                 => ['nullable', 'string', 'max:30'],
            'passport_nationality'        => ['nullable', 'string', 'max:30'],
            'image_Hash'                  => ['required', 'string'],
        ];
    }
}
