<?php

return [
    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'vendor/api/*',
        'vendor/auth/*',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:85',
        'https://vendor.avinaq.com',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];