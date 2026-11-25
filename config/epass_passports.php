<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Passport acceptance policy
    |--------------------------------------------------------------------------
    |
    | Keep this enabled by default so existing app and web integrations
    | continue to accept expired passports unless a relying service explicitly
    | decides otherwise.
    |
    */
    'allow_expired_passports' => env('EPASS_ALLOW_EXPIRED_PASSPORTS', true),
];
