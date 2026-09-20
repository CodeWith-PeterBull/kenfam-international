<?php

declare(strict_types=1);

/*
 * A brand colour from the environment must be #rrggbb. An unquoted "#…" value
 * in .env reads as a comment and arrives empty, and the empty string is not
 * null, so env() alone would not fall back; this does.
 */
$brandColour = static function (string $key, string $default): string {
    $value = (string) env($key, '');

    return preg_match('/^#[0-9a-f]{6}$/i', $value) === 1 ? $value : $default;
};

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
    /*
     * The brand palette every server-rendered surface reads: PDF reports, mail,
     * and the default ("olive") entry of the dashboard and storefront theme
     * controllers. Visitors may still pick another palette in the browser.
     * Kept apart from `brand`, which lists asset paths only. Quote the values
     * in .env (KENFAM_BRAND_PRIMARY="#6a753d"); a malformed one is ignored.
     */
    'colors' => [
        'primary' => $brandColour('KENFAM_BRAND_PRIMARY', '#6a753d'),
        'primary_dark' => $brandColour('KENFAM_BRAND_PRIMARY_DARK', '#4e572d'),
        'secondary' => $brandColour('KENFAM_BRAND_SECONDARY', '#70233a'),
        'accent' => $brandColour('KENFAM_BRAND_ACCENT', '#b28a4b'),
        'on_primary' => $brandColour('KENFAM_BRAND_ON_PRIMARY', '#ffffff'),
    ],
];
