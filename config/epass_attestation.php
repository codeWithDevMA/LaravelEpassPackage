<?php

// ─── Single source of truth ───────────────────────────────────────────────────
// EPASS_ATTESTATION_MODE=verified  → all strict security checks on (production)
// EPASS_ATTESTATION_MODE=standard  → all strict checks off (developer testing)
//
// Every setting below defaults based on this one variable.
// Individual overrides are still accepted via their own env keys if needed.
// ─────────────────────────────────────────────────────────────────────────────

$mode = strtolower(trim((string) env('EPASS_ATTESTATION_MODE', 'verified')));
$isVerified = $mode === 'verified';

$signingSecretB64 = env('EPASS_ATTESTATION_SIGNING_SECRET_B64');
$signingPublicB64 = env('EPASS_ATTESTATION_SIGNING_PUBLIC_B64');

if (!$signingSecretB64) {
    $secretPath = storage_path(env('EPASS_SIGNING_PRIVATE_KEY_PATH', 'app/epass/attestation_signing_secret.key'));
    if (is_file($secretPath)) {
        $signingSecretB64 = trim((string) file_get_contents($secretPath));
    }
}

if (!$signingPublicB64) {
    $publicPath = storage_path(env('EPASS_SIGNING_PUBLIC_KEY_PATH', 'app/epass/attestation_signing_public.key'));
    if (is_file($publicPath)) {
        $signingPublicB64 = trim((string) file_get_contents($publicPath));
    }
}

return [
    // Mode — controls all defaults below
    'mode' => $mode,
    'version' => env('EPASS_ATTESTATION_VERSION', 'epass-attest-v1'),

    'challenge_ttl_seconds'   => (int) env('EPASS_ATTESTATION_TTL_SECONDS', 300),
    'challenge_clock_skew_ms' => (int) env('EPASS_ATTESTATION_CLOCK_SKEW_MS', 60000),

    'challenge_signing_secret_b64' => $signingSecretB64,
    'challenge_signing_public_b64' => $signingPublicB64,

    'expected_package_name' => env(
        'EPASS_ATTESTATION_PACKAGE_NAME',
        'io.github.codewithdevma.epassauthenticator',
    ),

    'expected_signing_digests' => array_values(
        array_filter(
            array_map(
                'trim',
                explode(
                    ',',
                    (string) env('EPASS_ATTESTATION_SIGNING_DIGESTS', ''),
                ),
            ),
        ),
    ),

    'allowed_security_levels' => ['TrustedEnvironment', 'StrongBox', 'Software'],

    // In standard mode these all default to false — no strict hardware requirements
    'accept_google_rooted_chains' => filter_var(
        env('EPASS_ATTESTATION_ACCEPT_GOOGLE_ROOTED_CHAINS', true),
        FILTER_VALIDATE_BOOL,
    ),
    'accept_rkp_provisioned_chains' => filter_var(
        env('EPASS_ATTESTATION_ACCEPT_RKP_PROVISIONED_CHAINS', true),
        FILTER_VALIDATE_BOOL,
    ),
    'require_device_locked' => filter_var(
        env('EPASS_ATTESTATION_REQUIRE_DEVICE_LOCKED', $isVerified),
        FILTER_VALIDATE_BOOL,
    ),
    'require_verified_boot' => filter_var(
        env('EPASS_ATTESTATION_REQUIRE_VERIFIED_BOOT', $isVerified),
        FILTER_VALIDATE_BOOL,
    ),
    'require_patch_level_checks' => filter_var(
        env('EPASS_ATTESTATION_REQUIRE_PATCH_LEVEL_CHECKS', $isVerified),
        FILTER_VALIDATE_BOOL,
    ),
    'min_os_patch_level'     => (int) env('EPASS_ATTESTATION_MIN_OS_PATCH_LEVEL', 202401),
    'min_vendor_patch_level' => (int) env('EPASS_ATTESTATION_MIN_VENDOR_PATCH_LEVEL', 202401),
    'min_boot_patch_level'   => (int) env('EPASS_ATTESTATION_MIN_BOOT_PATCH_LEVEL', 202401),
    'check_revocation_status' => filter_var(
        env('EPASS_ATTESTATION_CHECK_REVOCATION_STATUS', $isVerified),
        FILTER_VALIDATE_BOOL,
    ),
    'require_revocation_status_fetch' => filter_var(
        env('EPASS_ATTESTATION_REQUIRE_REVOCATION_STATUS_FETCH', $isVerified),
        FILTER_VALIDATE_BOOL,
    ),
    'revocation_status_url' => env(
        'EPASS_ATTESTATION_STATUS_URL',
        'https://android.googleapis.com/attestation/status',
    ),
    'revocation_cache_ttl_seconds' => (int) env(
        'EPASS_ATTESTATION_REVOCATION_CACHE_TTL_SECONDS',
        86400,
    ),

    'play_integrity' => [
        // Defaults to true only in verified mode — no separate env var needed
        'enabled' => filter_var(
            env('EPASS_PLAY_INTEGRITY_ENABLED', $isVerified),
            FILTER_VALIDATE_BOOL,
        ),
        'request_mode' => env('EPASS_PLAY_INTEGRITY_REQUEST_MODE', 'classic'),
        'cloud_project_number' => env('EPASS_PLAY_INTEGRITY_CLOUD_PROJECT_NUMBER'),
        'service_account_json_path' => env('EPASS_PLAY_INTEGRITY_SERVICE_ACCOUNT_JSON_PATH'),
        'service_account_json' => env('EPASS_PLAY_INTEGRITY_SERVICE_ACCOUNT_JSON'),
        'oauth_token_uri' => env('EPASS_PLAY_INTEGRITY_OAUTH_TOKEN_URI', 'https://oauth2.googleapis.com/token'),
        'decode_endpoint_base' => env(
            'EPASS_PLAY_INTEGRITY_DECODE_ENDPOINT_BASE',
            'https://playintegrity.googleapis.com/v1',
        ),
        'access_token_cache_ttl_seconds' => (int) env('EPASS_PLAY_INTEGRITY_ACCESS_TOKEN_CACHE_TTL_SECONDS', 3300),
        'require_play_recognized' => filter_var(
            env('EPASS_PLAY_INTEGRITY_REQUIRE_PLAY_RECOGNIZED', $isVerified),
            FILTER_VALIDATE_BOOL,
        ),
        'require_licensed' => filter_var(
            env('EPASS_PLAY_INTEGRITY_REQUIRE_LICENSED', $isVerified),
            FILTER_VALIDATE_BOOL,
        ),
        'allowed_device_verdicts' => array_values(
            array_filter(
                array_map(
                    'trim',
                    explode(
                        ',',
                        env(
                            'EPASS_PLAY_INTEGRITY_ALLOWED_DEVICE_VERDICTS',
                            'MEETS_DEVICE_INTEGRITY,MEETS_STRONG_INTEGRITY',
                        ),
                    ),
                ),
            ),
        ),
        'max_token_age_ms' => (int) env('EPASS_PLAY_INTEGRITY_MAX_TOKEN_AGE_MS', 300000),
        'allowed_play_protect_verdicts' => array_values(
            array_filter(
                array_map(
                    'trim',
                    explode(
                        ',',
                        env(
                            'EPASS_PLAY_INTEGRITY_ALLOWED_PLAY_PROTECT_VERDICTS',
                            'NO_ISSUES,NO_DATA,PLAY_PROTECT_VERDICT_UNSPECIFIED,UNEVALUATED',
                        ),
                    ),
                ),
            ),
        ),
        'require_decode' => filter_var(
            env('EPASS_PLAY_INTEGRITY_REQUIRE_DECODE', $isVerified),
            FILTER_VALIDATE_BOOL,
        ),
    ],

    'trusted_roots' => (static function (): array {
        // Works whether this file is in the package or published to the app's config/ dir
        $packagePath = __DIR__ . '/../resources/security/google_attestation_roots.php';
        $publishedPath = __DIR__ . '/../vendor/ghazali/epass-package/resources/security/google_attestation_roots.php';
        if (is_file($packagePath)) {
            return require $packagePath;
        }
        if (is_file($publishedPath)) {
            return require $publishedPath;
        }
        // Fallback: search vendor for the package
        $vendorPath = base_path('vendor/ghazali/epass-package/resources/security/google_attestation_roots.php');
        if (is_file($vendorPath)) {
            return require $vendorPath;
        }
        return [];
    })(),
];
