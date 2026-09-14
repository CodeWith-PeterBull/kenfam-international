<?php

declare(strict_types=1);

use App\Modules\Commerce\PointOfSale\Printing\Drivers\BrowserReceiptPrinterDriver;

/**
 * Commerce module defaults.
 *
 * Values are environment-overridable so an adopter can change currency,
 * taxation, delivery, inventory, and numbering without editing module code.
 */
return [
    'enabled' => (bool) env('COMMERCE_ENABLED', false),

    'currency' => [
        'code' => (string) env('COMMERCE_CURRENCY_CODE', 'KES'),
        'symbol' => (string) env('COMMERCE_CURRENCY_SYMBOL', 'KSh'),
        'decimal_places' => (int) env('COMMERCE_CURRENCY_DECIMALS', 2),
    ],

    'tax' => [
        'default_rate_bps' => (int) env('COMMERCE_TAX_RATE_BPS', 1600),
        'prices_include_tax' => (bool) env('COMMERCE_PRICES_INCLUDE_TAX', true),
    ],

    'inventory' => [
        'allow_oversell' => (bool) env('COMMERCE_ALLOW_OVERSELL', false),
        'default_low_stock_threshold' => (int) env('COMMERCE_LOW_STOCK_THRESHOLD', 5),
    ],

    'media' => [
        'product_gallery_limit' => (int) env('COMMERCE_PRODUCT_GALLERY_LIMIT', 8),
        'upload_max_kilobytes' => (int) env('COMMERCE_MEDIA_UPLOAD_MAX_KB', 5120),
    ],

    'checkout' => [
        'country_code' => (string) env('COMMERCE_COUNTRY_CODE', 'KE'),
        'flat_delivery_fee_minor' => (int) env('COMMERCE_DELIVERY_FEE_MINOR', 0),
        'payment_methods' => array_values(array_filter(array_map(
            static fn (string $method): string => trim($method),
            explode(',', (string) env('COMMERCE_PAYMENT_METHODS', 'mobile_money,bank_transfer,cash_on_delivery')),
        ))),
    ],

    'storefront' => [
        'cart_session_key' => (string) env('COMMERCE_CART_SESSION_KEY', 'commerce.storefront.cart'),
        'confirmation_link_minutes' => (int) env('COMMERCE_CONFIRMATION_LINK_MINUTES', 120),
        'tracking_link_days' => (int) env('COMMERCE_TRACKING_LINK_DAYS', 90),
        'document_link_days' => (int) env('COMMERCE_DOCUMENT_LINK_DAYS', 30),

        // Product-page social share group. Individual platforms can be pruned
        // with the allow-list; an empty list means "all supported platforms".
        'sharing' => [
            'enabled' => (bool) env('COMMERCE_STOREFRONT_SHARING_ENABLED', true),
            'platforms' => array_values(array_filter(array_map(
                static fn (string $platform): string => trim(strtolower($platform)),
                explode(',', (string) env('COMMERCE_STOREFRONT_SHARING_PLATFORMS', '')),
            ))),
        ],

        // Direct "order over WhatsApp" button using the institution's number.
        'whatsapp_order' => [
            'enabled' => (bool) env('COMMERCE_STOREFRONT_WHATSAPP_ORDER_ENABLED', true),
        ],
    ],

    'pos' => [
        'payment_methods' => array_values(array_filter(array_map(
            static fn (string $method): string => trim($method),
            explode(',', (string) env('COMMERCE_POS_PAYMENT_METHODS', 'cash,mobile_money,card,bank_transfer')),
        ))),
        'max_tenders' => (int) env('COMMERCE_POS_MAX_TENDERS', 4),
        'product_results' => (int) env('COMMERCE_POS_PRODUCT_RESULTS', 12),
        'receipt_printing' => [
            'default_driver' => (string) env('COMMERCE_POS_RECEIPT_PRINT_DRIVER', 'browser'),
            'default_mode' => (string) env('COMMERCE_POS_RECEIPT_PRINT_MODE', 'manual'),
            'default_paper_width_mm' => (int) env('COMMERCE_POS_RECEIPT_PAPER_WIDTH_MM', 80),
            'drivers' => [
                'browser' => BrowserReceiptPrinterDriver::class,
            ],
        ],

        // Optional cashier feedback sounds played entirely in the browser.
        'sound' => [
            'add_to_cart_enabled' => (bool) env('COMMERCE_POS_ADD_TO_CART_SOUND', true),
        ],

        // Read-only cashier history surfaces. These switches control placement;
        // the underlying orders remain available to authorized operations.
        'sales_history' => [
            'terminal_enabled' => (bool) env('COMMERCE_POS_SALES_HISTORY_TERMINAL', true),
            'dashboard_enabled' => (bool) env('COMMERCE_POS_SALES_HISTORY_DASHBOARD', true),
            'per_page' => (int) env('COMMERCE_POS_SALES_HISTORY_PER_PAGE', 8),
        ],
    ],

    'numbering' => [
        'web_prefix' => (string) env('COMMERCE_WEB_ORDER_PREFIX', 'WEB'),
        'pos_prefix' => (string) env('COMMERCE_POS_ORDER_PREFIX', 'POS'),
    ],

    'barcode' => [
        // Internal store barcode auto-assigned when a product is created without
        // one: <prefix><zero-padded product id>, e.g. MM0000000145.
        'internal_prefix' => (string) env('COMMERCE_BARCODE_INTERNAL_PREFIX', 'MM'),
        'internal_pad_length' => (int) env('COMMERCE_BARCODE_INTERNAL_PAD', 10),
        'symbology' => 'code128',

        // Printable A4 label-sheet defaults; overridable per print run in the UI.
        'label' => [
            'columns' => (int) env('COMMERCE_BARCODE_LABEL_COLUMNS', 3),
            'show_store_name' => (bool) env('COMMERCE_BARCODE_LABEL_STORE_NAME', true),
            'show_product_name' => (bool) env('COMMERCE_BARCODE_LABEL_PRODUCT_NAME', true),
            'show_price' => (bool) env('COMMERCE_BARCODE_LABEL_PRICE', true),
            'max_labels_per_run' => (int) env('COMMERCE_BARCODE_LABEL_MAX', 300),
        ],
    ],

    'notifications' => [
        'enabled' => (bool) env('COMMERCE_OPERATIONAL_NOTIFICATIONS_ENABLED', true),
        'queue' => (string) env('COMMERCE_NOTIFICATION_QUEUE', 'default'),
        'till_variance_threshold_minor' => (int) env('COMMERCE_TILL_VARIANCE_ALERT_THRESHOLD_MINOR', 10_000),
        'events' => [
            'web_order_placed' => (bool) env('COMMERCE_NOTIFY_WEB_ORDER_PLACED', true),
            'payment_confirmed' => (bool) env('COMMERCE_NOTIFY_PAYMENT_CONFIRMED', true),
            'order_ready' => (bool) env('COMMERCE_NOTIFY_ORDER_READY', true),
            'order_cancelled' => (bool) env('COMMERCE_NOTIFY_ORDER_CANCELLED', true),
            'low_stock' => (bool) env('COMMERCE_NOTIFY_LOW_STOCK', true),
            'out_of_stock' => (bool) env('COMMERCE_NOTIFY_OUT_OF_STOCK', true),
            'till_variance' => (bool) env('COMMERCE_NOTIFY_TILL_VARIANCE', true),
        ],
    ],
];
