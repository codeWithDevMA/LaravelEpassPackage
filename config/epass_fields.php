<?php

/**
 * ePass Fields — Single Source of Truth
 *
 * This file defines the package-owned columns for the epass_users table.
 * The package now keeps only core identity/auth fields and passport fields.
 */
return [

    // ── Core identity / auth fields ────────────────────────────────────────────
    // These are managed by the system, never directly edited by the user.
    'core_fields' => [
        'uuid'                  => ['fillable' => true],
        'api_token'             => ['fillable' => true, 'hidden' => true],
        'api_token_expires_at'  => ['fillable' => true, 'hidden' => true, 'cast' => 'datetime'],
        'encrypted_dek'         => ['fillable' => true, 'hidden' => true],
        'linked_accounts_count' => ['fillable' => true, 'cast' => 'integer'],
    ],

    // ── Passport fields ────────────────────────────────────────────────────────
    // Set during the ePass authenticator scan.
    'passport_fields' => [
        // Lookup / search indexes (hashed — never exposed to the user)
        'document_number_hash'           => ['fillable' => true, 'hidden' => true],
        'current_document_number_lookup' => ['fillable' => true, 'hidden' => true],
        'current_mrz_key_lookup'         => ['fillable' => true, 'hidden' => true],
        'holder_profile_lookup'          => ['fillable' => true, 'hidden' => true],
        'passport_identity_hash'         => ['fillable' => true, 'hidden' => true],

        // Biometric image fingerprint (encrypted blob + HMAC index)
        'image_hash'        => ['fillable' => true, 'hidden' => true, 'encrypt' => true],
        'image_hash_lookup' => ['fillable' => true, 'hidden' => true],

        // Plain passport data fields (encrypted at rest)
        'nationality'         => ['fillable' => true, 'encrypt' => true],
        'gender'              => ['fillable' => true, 'encrypt' => true],
        'surname'             => ['fillable' => true, 'encrypt' => true],
        'given_names'         => ['fillable' => true, 'encrypt' => true],
        'place_of_birth'      => ['fillable' => true, 'encrypt' => true],
        'birth_date'          => ['fillable' => true, 'encrypt' => true, 'cast' => 'date'],
        'current_expiry_date' => ['fillable' => true, 'encrypt' => true, 'cast' => 'date'],
        'face_image_path'     => ['fillable' => true, 'encrypt' => true],
        'device_id'           => ['fillable' => true, 'encrypt' => true],

        // Airport-only LDS evidence. These fields are never exposed in a profile payload.
        'sod_evidence'             => ['fillable' => true, 'hidden' => true, 'encrypt' => true],
        'dg1_evidence'             => ['fillable' => true, 'hidden' => true, 'encrypt' => true],
        'dg11_evidence'            => ['fillable' => true, 'hidden' => true, 'encrypt' => true],
        'sod_verification_record'  => ['fillable' => true, 'hidden' => true, 'encrypt' => true],

        // ePass credential fields
        'epass_registered_at' => ['fillable' => true, 'cast' => 'datetime'],

    ],
];
