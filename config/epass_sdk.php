<?php

return [
    'api_key' => env('EPASS_SDK_API_KEY'),
    'api_key_hash' => env('EPASS_SDK_API_KEY_HASH'),
    'platform' => env('EPASS_SDK_PLATFORM', 'android'),
    'package_name' => env('EPASS_SDK_PACKAGE_NAME', ''),
    'cert_hash' => env('EPASS_SDK_CERT_HASH', ''),
    'backend_url' => env('EPASS_SDK_BACKEND_URL', ''),
    'label' => env('EPASS_SDK_LABEL', 'ePass SDK'),
];
