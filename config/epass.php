<?php

$dotenvAntiQcp = null;
$envFilePath = function_exists('base_path') ? base_path('.env') : __DIR__ . '/../../.env';
if (env('APP_ENV') !== 'testing' && file_exists($envFilePath)) {
    $envLines = @file($envFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($envLines as $line) {
        $trimmed = trim($line);
        if (str_starts_with($trimmed, '#')) {
            continue;
        }
        if (str_starts_with($trimmed, 'ANTI_QCP=')) {
            $rawVal = strtolower(trim(substr($trimmed, strlen('ANTI_QCP='))));
            $dotenvAntiQcp = filter_var($rawVal, FILTER_VALIDATE_BOOLEAN);
            break;
        }
    }
}
$resolvedAntiQcp = $dotenvAntiQcp ?? filter_var(env('ANTI_QCP', false), FILTER_VALIDATE_BOOLEAN);

return [
    'route_prefix' => trim((string) env('EPASS_ROUTE_PREFIX', 'epass'), '/'),
    'route_middleware' => ['api'],
     'auth_guard' => env('EPASS_AUTH_GUARD', 'epass'),
    'signing' => [
        'private_key_path' => env('EPASS_SIGNING_PRIVATE_KEY_PATH', 'app/epass/attestation_signing_secret.key'),
        'public_key_path' => env('EPASS_SIGNING_PUBLIC_KEY_PATH', 'app/epass/attestation_signing_public.key'),
    ],
    'anti_qcp' => $resolvedAntiQcp,

    // Field-level encryption master key for sensitive EpassUser data.
    // Must be a base64-encoded 256-bit (32-byte) key.
    // Generate with: php -r "echo base64_encode(random_bytes(32));"
    'field_encryption_key' => env('EPASS_FIELD_ENCRYPTION_KEY', null),

    // Optional deployment-level HKDF salt (RFC 5869 §3.1).
    // When set, it is mixed into KEK derivation making the encryption unique
    // to this deployment even if the same master key is reused elsewhere.
    // If omitted, HKDF uses an implicit all-zeros salt (backward-compatible).
    // Generate with: php -r "echo base64_encode(random_bytes(32));"
    'field_encryption_salt' => env('EPASS_FIELD_ENCRYPTION_SALT', null),

    /*
    |--------------------------------------------------------------------------
    | Dashboard Route Map (used by EpassDashboardRouteResolver)
    |--------------------------------------------------------------------------
    |
    | Maps each callback host model alias to the named route of its dashboard.
    | This gives you explicit control over where each model is redirected to
    | after ePass login/register — no naming convention is assumed.
    |
    | Example (multi-model):
    |   'dashboard_routes' => [
    |       'user_alias' => 'developer.dashboard',
    |       'customer'   => 'customer.dashboard',
    |   ],
    */
    'dashboard_routes' => [
        // 'user_alias' => 'developer.dashboard',
    ],
];
