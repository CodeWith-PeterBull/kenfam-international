<?php

declare(strict_types=1);

return [
    'name' => env('KENFAM_NAME', 'Kenfam International'),
    'legal_name' => env('KENFAM_LEGAL_NAME', 'Kenfam International Limited'),
    'tagline' => env('KENFAM_TAGLINE', 'Travel farther. Return richer.'),
    'founded' => 1994,
    'email' => env('KENFAM_EMAIL', 'info@kenfam.co.ke'),
    'phone' => env('KENFAM_PHONE', '+254 714 884 069'),
    'phone_link' => env('KENFAM_PHONE_LINK', '+254714884069'),
    'whatsapp' => env('KENFAM_WHATSAPP', '254714884069'),
    'address' => 'Purvi House, Wing B, 2nd Floor, Room 202, Westlands, Nairobi',
    'postal_address' => 'P.O. Box 14350-00800, Nairobi, Kenya',
    'brand' => [
        'logo_dark' => 'kenfam/assets/brand/logo-dark.png',
        'logo_light' => 'kenfam/assets/brand/logo-light.png',
        'favicon' => 'kenfam/assets/brand/favicon.png',
        'social_image' => 'kenfam/assets/brand/social-card.webp',
        'hero' => 'kenfam/assets/images/kenfam-travel-hero.webp',
    ],
];
