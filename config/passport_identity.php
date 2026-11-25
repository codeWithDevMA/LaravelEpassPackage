<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Passport Identity Lookup Secret
    |--------------------------------------------------------------------------
    |
    | We keep bcrypt hashes for high-cost verification where needed, but we
    | also need deterministic lookups for the current passport and the
    | stable "same human" renewal profile. A dedicated secret avoids baking
    | that lookup derivation directly into business logic.
    |
    */
    'lookup_key' => env('PASSPORT_IDENTITY_HMAC_KEY', env('APP_KEY')),
];
