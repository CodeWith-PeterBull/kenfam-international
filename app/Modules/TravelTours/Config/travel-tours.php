<?php

/**
 * Declares environment-backed TravelTours module configuration and defaults.
 */

declare(strict_types=1);

/** Travel & Tours defaults. Money uses minor units and persisted times use UTC. */
return [
    'enabled' => (bool) env('TRAVEL_TOURS_ENABLED', true),
    'defaults' => [
        'country_code' => (string) env('TRAVEL_TOURS_COUNTRY_CODE', 'KE'),
        'currency' => (string) env('TRAVEL_TOURS_CURRENCY_CODE', 'KES'),
        'currency_symbol' => (string) env('TRAVEL_TOURS_CURRENCY_SYMBOL', 'KSh'),
        'currency_decimals' => (int) env('TRAVEL_TOURS_CURRENCY_DECIMALS', 2),
        'timezone' => (string) env('TRAVEL_TOURS_TIMEZONE', 'Africa/Nairobi'),
        'tax_rate_bps' => (int) env('TRAVEL_TOURS_TAX_RATE_BPS', 0),
        'prices_include_tax' => (bool) env('TRAVEL_TOURS_PRICES_INCLUDE_TAX', true),
        'confirmation_mode' => (string) env('TRAVEL_TOURS_CONFIRMATION_MODE', 'hybrid'),
    ],
    'booking' => [
        'hold_minutes' => (int) env('TRAVEL_TOURS_HOLD_MINUTES', 15),
        'pending_minutes' => (int) env('TRAVEL_TOURS_PENDING_MINUTES', 1440),
        'maximum_participants' => (int) env('TRAVEL_TOURS_MAX_PARTICIPANTS', 100),
        'confirmation_link_minutes' => (int) env('TRAVEL_TOURS_CONFIRMATION_LINK_MINUTES', 120),
        'tracking_link_days' => (int) env('TRAVEL_TOURS_TRACKING_LINK_DAYS', 180),
        'document_link_days' => (int) env('TRAVEL_TOURS_DOCUMENT_LINK_DAYS', 30),
    ],
    'storefront' => [
        'catalog_limit' => (int) env('TRAVEL_TOURS_CATALOG_LIMIT', 12),
        'page_size' => (int) env('TRAVEL_TOURS_PAGE_SIZE', 12),
        'maximum_page_size' => (int) env('TRAVEL_TOURS_MAXIMUM_PAGE_SIZE', 48),
        'founded_year' => env('TRAVEL_TOURS_FOUNDED_YEAR'),
        'fallback_logo' => (string) env('TRAVEL_TOURS_FALLBACK_LOGO', 'build/img/logo.svg'),
        'fallback_icon' => (string) env('TRAVEL_TOURS_FALLBACK_ICON', 'build/img/favicon.png'),
        'light_logo' => (string) env('TRAVEL_TOURS_LIGHT_LOGO', ''),
        'social_image' => (string) env('TRAVEL_TOURS_SOCIAL_IMAGE', ''),
        'hero_image' => (string) env('TRAVEL_TOURS_HERO_IMAGE', ''),
        'meta_description' => (string) env('TRAVEL_TOURS_META_DESCRIPTION', 'Professionally planned tours, departures, and travel experiences.'),
        'payment_methods' => array_values(array_filter(array_map(
            static fn (string $method): string => trim($method),
            explode(',', (string) env('TRAVEL_TOURS_PAYMENT_METHODS', 'mobile_money,card,bank_transfer,cash')),
        ))),
    ],
    'media' => [
        'tour_gallery_limit' => (int) env('TRAVEL_TOURS_TOUR_GALLERY_LIMIT', 18),
        'tour_document_limit' => (int) env('TRAVEL_TOURS_TOUR_DOCUMENT_LIMIT', 12),
        'destination_gallery_limit' => (int) env('TRAVEL_TOURS_DESTINATION_GALLERY_LIMIT', 12),
        'upload_max_kilobytes' => (int) env('TRAVEL_TOURS_MEDIA_UPLOAD_MAX_KB', 6144),
    ],
    'notifications' => [
        'enabled' => (bool) env('TRAVEL_TOURS_NOTIFICATIONS_ENABLED', true),
        'queue' => (string) env('TRAVEL_TOURS_NOTIFICATION_QUEUE', 'notifications'),
    ],
    'inquiries' => [
        'consent_purpose' => (string) env('TRAVEL_TOURS_INQUIRY_CONSENT_PURPOSE', 'Respond to this travel inquiry'),
        'consent_version' => (string) env('TRAVEL_TOURS_INQUIRY_CONSENT_VERSION', '2026-09'),
    ],
    'numbering' => [
        'web_prefix' => (string) env('TRAVEL_TOURS_WEB_PREFIX', 'WEB-TOUR'),
        'pob_prefix' => (string) env('TRAVEL_TOURS_POB_PREFIX', 'POB-TOUR'),
        'admin_prefix' => (string) env('TRAVEL_TOURS_ADMIN_PREFIX', 'ADM-TOUR'),
        'padding' => (int) env('TRAVEL_TOURS_NUMBER_PADDING', 8),
    ],
    'pob' => [
        'variance_threshold_minor' => (int) env('TRAVEL_TOURS_SHIFT_VARIANCE_THRESHOLD_MINOR', 10000),
        'receipt_printing' => [
            'default_driver' => (string) env('TRAVEL_TOURS_RECEIPT_PRINT_DRIVER', 'browser'),
            'default_mode' => (string) env('TRAVEL_TOURS_RECEIPT_PRINT_MODE', 'manual'),
            'default_paper_width_mm' => (int) env('TRAVEL_TOURS_RECEIPT_PAPER_WIDTH_MM', 80),
        ],
    ],
];
