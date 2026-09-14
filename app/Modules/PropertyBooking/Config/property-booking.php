<?php

declare(strict_types=1);

use App\Modules\PropertyBooking\PointOfBooking\Printing\Drivers\BrowserReceiptPrinterDriver;

/**
 * Property Booking module defaults.
 *
 * Money is stored in integer minor units and all persisted date-times are UTC.
 * Property-local calendars are interpreted with the IANA timezone stored on
 * each property and snapshotted onto every booking.
 */
return [
    'enabled' => (bool) env('PROPERTY_BOOKING_ENABLED', false),

    'defaults' => [
        'country_code' => (string) env('PROPERTY_BOOKING_COUNTRY_CODE', 'KE'),
        'currency' => (string) env('PROPERTY_BOOKING_CURRENCY_CODE', 'KES'),
        'currency_symbol' => (string) env('PROPERTY_BOOKING_CURRENCY_SYMBOL', 'KSh'),
        'currency_decimals' => (int) env('PROPERTY_BOOKING_CURRENCY_DECIMALS', 2),
        'timezone' => (string) env('PROPERTY_BOOKING_TIMEZONE', 'Africa/Nairobi'),
        'turnover_minutes' => (int) env('PROPERTY_BOOKING_TURNOVER_MINUTES', 60),
        'tax_rate_bps' => (int) env('PROPERTY_BOOKING_TAX_RATE_BPS', 0),
        'prices_include_tax' => (bool) env('PROPERTY_BOOKING_PRICES_INCLUDE_TAX', true),
    ],

    'booking' => [
        'hold_minutes' => (int) env('PROPERTY_BOOKING_HOLD_MINUTES', 15),
        'pending_minutes' => (int) env('PROPERTY_BOOKING_PENDING_MINUTES', 60),
        'quote_minutes' => (int) env('PROPERTY_BOOKING_QUOTE_MINUTES', 10),
        'maximum_units_per_booking' => (int) env('PROPERTY_BOOKING_MAXIMUM_UNITS', 10),
        'availability_candidate_limit' => (int) env('PROPERTY_BOOKING_AVAILABILITY_CANDIDATES', 100),
    ],

    'operations' => [
        'confirmation_payment_policy' => (string) env('PROPERTY_BOOKING_CONFIRMATION_PAYMENT_POLICY', 'deposit'),
        'check_in_payment_policy' => (string) env('PROPERTY_BOOKING_CHECK_IN_PAYMENT_POLICY', 'deposit'),
        'check_out_payment_policy' => (string) env('PROPERTY_BOOKING_CHECK_OUT_PAYMENT_POLICY', 'full'),
        'check_in_early_minutes' => (int) env('PROPERTY_BOOKING_CHECK_IN_EARLY_MINUTES', 0),
        'check_in_late_minutes' => (int) env('PROPERTY_BOOKING_CHECK_IN_LATE_MINUTES', 180),
        'check_out_early_minutes' => (int) env('PROPERTY_BOOKING_CHECK_OUT_EARLY_MINUTES', 0),
        'check_out_late_minutes' => (int) env('PROPERTY_BOOKING_CHECK_OUT_LATE_MINUTES', 180),
        'no_show_after_minutes' => (int) env('PROPERTY_BOOKING_NO_SHOW_AFTER_MINUTES', 120),
    ],

    'media' => [
        'property_gallery_limit' => (int) env('PROPERTY_BOOKING_PROPERTY_GALLERY_LIMIT', 12),
        'unit_type_gallery_limit' => (int) env('PROPERTY_BOOKING_UNIT_GALLERY_LIMIT', 10),
        'upload_max_kilobytes' => (int) env('PROPERTY_BOOKING_MEDIA_UPLOAD_MAX_KB', 5120),
    ],

    'storefront' => [
        'selection_session_key' => (string) env('PROPERTY_BOOKING_SELECTION_SESSION_KEY', 'property_booking.selection'),
        'confirmation_link_minutes' => (int) env('PROPERTY_BOOKING_CONFIRMATION_LINK_MINUTES', 120),
        'tracking_link_days' => (int) env('PROPERTY_BOOKING_TRACKING_LINK_DAYS', 90),
        'document_link_days' => (int) env('PROPERTY_BOOKING_DOCUMENT_LINK_DAYS', 30),
        'catalog_limit' => (int) env('PROPERTY_BOOKING_CATALOG_LIMIT', 24),
        'guest_identity_required' => (bool) env('PROPERTY_BOOKING_STOREFRONT_GUEST_IDENTITY_REQUIRED', false),
        'payment_methods' => array_values(array_filter(array_map(
            static fn (string $method): string => trim($method),
            explode(',', (string) env('PROPERTY_BOOKING_STOREFRONT_PAYMENT_METHODS', 'mobile_money,card,bank_transfer')),
        ))),
    ],

    'notifications' => [
        'enabled' => (bool) env('PROPERTY_BOOKING_NOTIFICATIONS_ENABLED', true),
        'queue' => (string) env('PROPERTY_BOOKING_NOTIFICATION_QUEUE', 'notifications'),
        'events' => [
            'web_booking_customer' => (bool) env('PROPERTY_BOOKING_NOTIFY_WEB_BOOKING_CUSTOMER', true),
            'web_booking_staff' => (bool) env('PROPERTY_BOOKING_NOTIFY_WEB_BOOKING_STAFF', true),
            'booking_confirmed' => (bool) env('PROPERTY_BOOKING_NOTIFY_BOOKING_CONFIRMED', true),
            'booking_modified' => (bool) env('PROPERTY_BOOKING_NOTIFY_BOOKING_MODIFIED', true),
            'booking_cancelled_customer' => (bool) env('PROPERTY_BOOKING_NOTIFY_BOOKING_CANCELLED_CUSTOMER', true),
            'booking_cancelled_staff' => (bool) env('PROPERTY_BOOKING_NOTIFY_BOOKING_CANCELLED_STAFF', true),
            'booking_no_show' => (bool) env('PROPERTY_BOOKING_NOTIFY_BOOKING_NO_SHOW', true),
            'booking_checked_in' => (bool) env('PROPERTY_BOOKING_NOTIFY_BOOKING_CHECKED_IN', true),
            'booking_checked_out' => (bool) env('PROPERTY_BOOKING_NOTIFY_BOOKING_CHECKED_OUT', true),
            'unit_turnover_required' => (bool) env('PROPERTY_BOOKING_NOTIFY_UNIT_TURNOVER_REQUIRED', true),
            'booking_payment_confirmed' => (bool) env('PROPERTY_BOOKING_NOTIFY_BOOKING_PAYMENT_CONFIRMED', true),
            'reception_shift_variance' => (bool) env('PROPERTY_BOOKING_NOTIFY_RECEPTION_SHIFT_VARIANCE', true),
        ],
    ],

    'numbering' => [
        'web_prefix' => (string) env('PROPERTY_BOOKING_WEB_PREFIX', 'WEB'),
        'pob_prefix' => (string) env('PROPERTY_BOOKING_POB_PREFIX', 'POB'),
        'admin_prefix' => (string) env('PROPERTY_BOOKING_ADMIN_PREFIX', 'ADM'),
        'padding' => (int) env('PROPERTY_BOOKING_NUMBER_PADDING', 8),
    ],

    'identity' => [
        // A dedicated key supports rotation without changing APP_KEY. When it
        // is blank the service derives a keyed HMAC from the application key.
        'hash_key' => (string) env('PROPERTY_BOOKING_IDENTITY_HASH_KEY', ''),
        'types' => [
            'national_id' => 'National ID',
            'passport' => 'Passport',
        ],
    ],

    'pob' => [
        'maximum_tenders' => (int) env('PROPERTY_BOOKING_MAXIMUM_TENDERS', 4),
        'search_results' => (int) env('PROPERTY_BOOKING_POB_SEARCH_RESULTS', 18),
        'guest_identity_required' => (bool) env('PROPERTY_BOOKING_POB_GUEST_IDENTITY_REQUIRED', true),
        'enforce_advance_notice' => (bool) env('PROPERTY_BOOKING_POB_ENFORCE_ADVANCE_NOTICE', false),
        'walk_in_past_grace_minutes' => (int) env('PROPERTY_BOOKING_POB_WALK_IN_PAST_GRACE_MINUTES', 15),
        'shift_variance_threshold_minor' => (int) env('PROPERTY_BOOKING_SHIFT_VARIANCE_THRESHOLD_MINOR', 10_000),
        'payment_methods' => array_values(array_filter(array_map(
            static fn (string $method): string => trim($method),
            explode(',', (string) env('PROPERTY_BOOKING_PAYMENT_METHODS', 'cash,mobile_money,card,bank_transfer')),
        ))),
        'receipt_printing' => [
            'default_driver' => (string) env('PROPERTY_BOOKING_RECEIPT_PRINT_DRIVER', 'browser'),
            'default_mode' => (string) env('PROPERTY_BOOKING_RECEIPT_PRINT_MODE', 'manual'),
            'default_paper_width_mm' => (int) env('PROPERTY_BOOKING_RECEIPT_PAPER_WIDTH_MM', 80),
            'drivers' => [
                'browser' => BrowserReceiptPrinterDriver::class,
            ],
        ],
    ],
];
