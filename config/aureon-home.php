<?php

declare(strict_types=1);

return [
    'asset_version' => (string) env('AUREON_HOME_ASSET_VERSION', '20260908'),

    'product_name' => (string) env('AUREON_PRODUCT_NAME', 'Aureon CMS'),

    'provider' => [
        'name' => 'META SOFTWARE DEVELOPERS',
        'tagline' => 'Your bridge between software and utility.',
        'emails' => [
            ['label' => 'Projects and enquiries', 'address' => 'info@metasoftdevs.com'],
            ['label' => 'Product support', 'address' => 'support@metasoftdevs.com'],
        ],
        'phones' => [
            ['label' => 'Primary', 'display' => '+254 (0) 722 809 376', 'e164' => '+254722809376', 'whatsapp' => '254722809376'],
            ['label' => 'Alternative', 'display' => '+254 (0) 794 035 976', 'e164' => '+254794035976', 'whatsapp' => '254794035976'],
        ],
        'website' => 'https://www.metasoftdevs.com',
        'website_label' => 'www.metasoftdevs.com',
        'location' => 'Nairobi, Kenya',
    ],

    'seo' => [
        'title' => 'Aureon CMS | Modular Business Operations Platform',
        'description' => 'Aureon CMS brings commerce, point-of-sale, accommodation booking, operations, reporting, and access control into one adaptable Laravel platform.',
        'social_image' => 'aureon/assets/brand/twitter-card.png',
    ],

    /*
    | Module cards are presentation metadata only. Runtime availability is
    | resolved from the enabled flag and registered public route at request time.
    */
    'modules' => [
        [
            'key' => 'commerce',
            'number' => '01',
            'name' => 'Commerce',
            'label' => 'Commerce and point of sale',
            'status' => 'Ready and available',
            'enabled' => (bool) env('COMMERCE_ENABLED', true),
            'public_route' => 'commerce.storefront.catalog.index',
            'icon' => 'shopping-bag',
            'description' => 'Run a focused product catalog, online storefront, inventory workflow, customer and order desk, and cashier-led point of sale from one operational module.',
            'capabilities' => [
                'Public catalog, product discovery, cart, and checkout',
                'Inventory, orders, customers, registers, and till sessions',
                'Receipts, order documents, operational alerts, and reporting',
            ],
            'scenarios' => ['Retail stores', 'Boutiques', 'Hardware merchants', 'Pharmacies'],
            'pricing' => 'Custom quote',
            'pricing_note' => 'Scoped to catalog, checkout, payment, POS, and deployment requirements.',
            'screenshots' => [
                [
                    'src' => 'aureon/assets/images/home/modules/commerce-storefront.webp',
                    'alt' => 'Aureon Commerce hardware and construction storefront catalog on desktop',
                    'label' => 'Storefront catalog',
                    'role' => 'Public experience',
                ],
                [
                    'src' => 'aureon/assets/images/home/modules/commerce-product-detail.webp',
                    'alt' => 'Aureon Commerce hardware product detail with pricing stock and cart controls',
                    'label' => 'Product details',
                    'role' => 'Public experience',
                ],
                [
                    'src' => 'aureon/assets/images/home/modules/commerce-dashboard.webp',
                    'alt' => 'Aureon Commerce administration dashboard with sales trends and action queues',
                    'label' => 'Commerce overview',
                    'role' => 'Administrator',
                ],
                [
                    'src' => 'aureon/assets/images/home/modules/commerce-catalog-admin.webp',
                    'alt' => 'Aureon Commerce product catalog administration workspace',
                    'label' => 'Product catalog',
                    'role' => 'Administrator',
                ],
                [
                    'src' => 'aureon/assets/images/home/modules/commerce-inventory.webp',
                    'alt' => 'Aureon Commerce inventory levels and stock movement workspace',
                    'label' => 'Inventory control',
                    'role' => 'Administrator',
                ],
                [
                    'src' => 'aureon/assets/images/home/modules/commerce-demo-data.webp',
                    'alt' => 'Aureon Commerce merchant demo context selection dashboard',
                    'label' => 'Merchant contexts',
                    'role' => 'Administrator',
                ],
                [
                    'src' => 'aureon/assets/images/home/modules/commerce-pos.webp',
                    'alt' => 'Aureon Commerce point-of-sale terminal with hardware products and current sale',
                    'label' => 'Point of sale',
                    'role' => 'Cashier',
                ],
                [
                    'src' => 'aureon/assets/images/home/modules/commerce-cashier-sales.webp',
                    'alt' => 'Aureon Commerce cashier sales history for active and previous till sessions',
                    'label' => 'My sales history',
                    'role' => 'Cashier',
                ],
                [
                    'src' => 'aureon/assets/images/home/modules/commerce-registers.webp',
                    'alt' => 'Aureon Commerce point-of-sale register management page',
                    'label' => 'POS registers',
                    'role' => 'Supervisor',
                ],
                [
                    'src' => 'aureon/assets/images/home/modules/commerce-tills.webp',
                    'alt' => 'Aureon Commerce till session management and reconciliation page',
                    'label' => 'Till sessions',
                    'role' => 'Supervisor',
                ],
                [
                    'src' => 'aureon/assets/images/home/modules/commerce-barcodes.webp',
                    'alt' => 'Aureon Commerce product barcode generation and label printing workspace',
                    'label' => 'Barcode labels',
                    'role' => 'Catalog manager',
                ],
                [
                    'src' => 'aureon/assets/images/home/modules/commerce-role-access.webp',
                    'alt' => 'Aureon role and permission manager showing access controls used by Commerce',
                    'label' => 'Role access',
                    'role' => 'System administrator',
                ],
            ],
        ],
        [
            'key' => 'property-booking',
            'number' => '02',
            'name' => 'Property Management',
            'label' => 'Accommodation and booking',
            'status' => 'Ready and available',
            'enabled' => (bool) env('PROPERTY_BOOKING_ENABLED', true),
            'public_route' => 'property-booking.storefront.catalog.index',
            'icon' => 'building-2',
            'description' => 'Coordinate properties, room and unit inventory, rates, guest bookings, reception activity, availability, and stay documents through a purpose-built accommodation workflow.',
            'capabilities' => [
                'Public stay discovery, live availability, and guest checkout',
                'Properties, units, rates, bookings, guests, and readiness',
                'Reception shifts, walk-in booking, receipts, and notifications',
            ],
            'scenarios' => ['Hotels', 'Serviced apartments', 'Guest houses', 'Managed stays'],
            'pricing' => 'Custom quote',
            'pricing_note' => 'Scoped to property inventory, booking rules, reception, and deployment requirements.',
            'screenshots' => [
                [
                    'src' => 'aureon/assets/images/home/modules/property-booking-storefront.webp',
                    'alt' => 'Aureon Property Booking availability search and stay catalog',
                    'label' => 'Stay discovery',
                    'role' => 'Public experience',
                ],
                [
                    'src' => 'aureon/assets/images/home/modules/property-booking-unit.webp',
                    'alt' => 'Aureon Property Booking property presentation and accommodation details',
                    'label' => 'Property details',
                    'role' => 'Public experience',
                ],
                [
                    'src' => 'aureon/assets/images/home/modules/property-booking-checkout.webp',
                    'alt' => 'Aureon Property Booking guest checkout and stay summary',
                    'label' => 'Guest checkout',
                    'role' => 'Public experience',
                ],
                [
                    'src' => 'aureon/assets/images/home/modules/property-booking-dashboard.webp',
                    'alt' => 'Aureon accommodation operations dashboard with bookings revenue and readiness',
                    'label' => 'Operations overview',
                    'role' => 'Property manager',
                ],
                [
                    'src' => 'aureon/assets/images/home/modules/property-booking-properties.webp',
                    'alt' => 'Aureon accommodation property directory administration page',
                    'label' => 'Properties',
                    'role' => 'Property manager',
                ],
                [
                    'src' => 'aureon/assets/images/home/modules/property-booking-amenities.webp',
                    'alt' => 'Aureon accommodation amenity administration page in dark mode',
                    'label' => 'Amenities',
                    'role' => 'Property manager',
                ],
                [
                    'src' => 'aureon/assets/images/home/modules/property-booking-units.webp',
                    'alt' => 'Aureon accommodation unit and room inventory administration page',
                    'label' => 'Units and rooms',
                    'role' => 'Property manager',
                ],
                [
                    'src' => 'aureon/assets/images/home/modules/property-booking-bookings.webp',
                    'alt' => 'Aureon accommodation booking management page in dark mode',
                    'label' => 'Bookings',
                    'role' => 'Booking agent',
                ],
                [
                    'src' => 'aureon/assets/images/home/modules/property-booking-readiness.webp',
                    'alt' => 'Aureon accommodation housekeeping and unit readiness dashboard',
                    'label' => 'Unit readiness',
                    'role' => 'Housekeeping',
                ],
                [
                    'src' => 'aureon/assets/images/home/modules/property-booking-pob.webp',
                    'alt' => 'Aureon point-of-booking reception terminal with booking selection and stay summary',
                    'label' => 'Reception terminal',
                    'role' => 'Receptionist',
                ],
                [
                    'src' => 'aureon/assets/images/home/modules/property-booking-registers.webp',
                    'alt' => 'Aureon reception register configuration page',
                    'label' => 'Reception registers',
                    'role' => 'Property manager',
                ],
                [
                    'src' => 'aureon/assets/images/home/modules/property-booking-shifts.webp',
                    'alt' => 'Aureon reception shift management and reconciliation page in dark mode',
                    'label' => 'Reception shifts',
                    'role' => 'Property manager',
                ],
            ],
        ],
    ],
];
