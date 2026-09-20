<?php

return [
    'defaults' => [
        'name' => env('INSTITUTION_NAME', env('APP_NAME', 'Kenfam International')),
        'short_name' => env('INSTITUTION_SHORT_NAME', 'Kenfam'),
        'descriptor' => env('INSTITUTION_DESCRIPTOR', 'Corporate administration and communications'),
        'founded_year' => (int) env('INSTITUTION_FOUNDED_YEAR', 1994),
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
        // Public profiles shown until Institution Details records its own list; empty URLs are dropped.
        'social_media' => array_values(array_filter([
            ['platform' => 'Facebook', 'handle' => '', 'url' => (string) env('INSTITUTION_FACEBOOK_URL', '')],
            ['platform' => 'Instagram', 'handle' => '', 'url' => (string) env('INSTITUTION_INSTAGRAM_URL', '')],
            ['platform' => 'TikTok', 'handle' => '', 'url' => (string) env('INSTITUTION_TIKTOK_URL', '')],
            ['platform' => 'X', 'handle' => '', 'url' => (string) env('INSTITUTION_X_URL', '')],
        ], static fn (array $social): bool => $social['url'] !== '')),
    ],

    'assets' => [
        'main_logo_url' => 'kenfam/assets/brand/logo-dark.png',
        'main_logo_path' => resource_path('kenfam/assets/brand/logo-dark.png'),
        'light_logo_url' => 'kenfam/assets/brand/logo-light.png',
        'social_image_url' => 'kenfam/assets/brand/social-card.webp',
        'travel_hero_url' => 'kenfam/assets/images/kenfam-travel-hero.webp',
        'logo_icon_url' => 'kenfam/assets/brand/favicon.png',
        'logo_icon_path' => resource_path('kenfam/assets/brand/favicon.png'),
    ],
];
