<?php

return [
    'defaults' => [
        'name' => env('INSTITUTION_NAME', env('APP_NAME', 'Laravel Aureon')),
        'short_name' => env('INSTITUTION_SHORT_NAME', 'Aureon'),
        'descriptor' => env('INSTITUTION_DESCRIPTOR', 'Corporate administration and communications'),
        'primary_email' => env('INSTITUTION_EMAIL', env('MAIL_FROM_ADDRESS', 'hello@example.com')),
        'secondary_email' => env('INSTITUTION_SECONDARY_EMAIL'),
        'primary_phone' => env('INSTITUTION_PHONE'),
        'secondary_phone' => env('INSTITUTION_SECONDARY_PHONE'),
        'website' => env('INSTITUTION_WEBSITE', env('APP_URL', 'http://localhost')),
        'physical_address' => env('INSTITUTION_PHYSICAL_ADDRESS'),
        'city' => env('INSTITUTION_CITY'),
        'county' => env('INSTITUTION_COUNTY'),
        'postal_code' => env('INSTITUTION_POSTAL_CODE'),
        'postal_address' => env('INSTITUTION_POSTAL_ADDRESS'),
        'postal_city' => env('INSTITUTION_POSTAL_CITY'),
        'social_media' => [],
    ],

    'assets' => [
        'main_logo_url' => 'aureon/assets/brand/logo.png',
        'main_logo_path' => resource_path('aureon/assets/brand/logo.png'),
        'logo_icon_url' => 'aureon/assets/brand/logo-icon.png',
        'logo_icon_path' => resource_path('aureon/assets/brand/logo-icon.png'),
    ],
];
