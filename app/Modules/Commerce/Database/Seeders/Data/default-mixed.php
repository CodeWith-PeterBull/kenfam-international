<?php

declare(strict_types=1);

use App\Modules\Commerce\Database\Seeders\CatalogDemoSeeder;

return [
    'key' => CatalogDemoSeeder::DEFAULT_CONTEXT,
    'categories' => [
        ['slug' => 'computing', 'name' => 'Computing', 'description' => 'Portable computing selected for focused work and dependable everyday performance.', 'sort_order' => 10, 'image' => 'aureon-air-14.png'],
        ['slug' => 'mobile', 'name' => 'Mobile', 'description' => 'Connected devices that keep communication, capture, and productivity close at hand.', 'sort_order' => 20, 'image' => 'pulse-phone-front.png'],
        ['slug' => 'audio', 'name' => 'Audio', 'description' => 'Personal and room audio designed for clear calls, considered listening, and flexible spaces.', 'sort_order' => 30, 'image' => 'arc-headphones.png'],
        ['slug' => 'wearables', 'name' => 'Wearables', 'description' => 'Compact technology for movement, health signals, notifications, and daily routines.', 'sort_order' => 40, 'image' => 'halo-watch.png'],
        ['slug' => 'workspace', 'name' => 'Workspace', 'description' => 'Displays and furniture that make long working sessions calmer and more productive.', 'sort_order' => 50, 'image' => 'canvas-monitor.png'],
        ['slug' => 'lifestyle', 'name' => 'Lifestyle', 'description' => 'Well-made essentials for travel, movement, and work beyond the desk.', 'sort_order' => 60, 'image' => 'stride-trainer.png'],
    ],
    'products' => [
        CatalogDemoSeeder::productDefinition('computing', 'Aureon Air 14', 'aureon-air-14', 'AUR-LT-1401', '616400140001', 129_900_00, 119_900_00, 96_000_00, 24, 5, true, 1380, [314, 221, 16], ['aureon-air-14.png', 'air-laptop.png'], 'A quiet, lightweight 14-inch notebook built for hybrid work.', ['Display' => ['Size' => '14 inch', 'Resolution' => '1920 x 1200'], 'Performance' => ['Memory' => '16 GB', 'Storage' => '512 GB SSD'], 'Support' => ['Warranty' => '24 months']]),
        CatalogDemoSeeder::productDefinition('computing', 'StudioBook Pro 16', 'studiobook-pro-16', 'AUR-LT-1602', '616400160002', 189_500_00, null, 148_000_00, 16, 4, true, 1920, [356, 248, 18], ['studio-laptop.png'], 'A larger performance notebook for design, analysis, and demanding project work.', ['Display' => ['Size' => '16 inch', 'Resolution' => '2560 x 1600'], 'Performance' => ['Memory' => '32 GB', 'Storage' => '1 TB SSD'], 'Support' => ['Warranty' => '24 months']]),
        CatalogDemoSeeder::productDefinition('mobile', 'Pulse X1 Smartphone', 'pulse-x1-smartphone', 'AUR-PH-X101', '616401010003', 89_900_00, 79_900_00, 63_000_00, 38, 6, true, 184, [147, 72, 8], ['pulse-phone-front.png', 'pulse-phone-back.png'], 'A fast 5G smartphone with a bright OLED display and confident all-day battery.', ['Display' => ['Size' => '6.4 inch OLED', 'Refresh rate' => '120 Hz'], 'Performance' => ['Memory' => '8 GB', 'Storage' => '256 GB'], 'Camera' => ['Main' => '50 MP']]),
        CatalogDemoSeeder::productDefinition('wearables', 'Halo S4 Smartwatch', 'halo-s4-smartwatch', 'AUR-WR-S404', '616402040004', 24_900_00, null, 17_500_00, 44, 8, false, 48, [45, 38, 11], ['halo-watch.png'], 'A restrained everyday smartwatch for activity, calls, and useful notifications.', ['Display' => ['Type' => 'AMOLED'], 'Health' => ['Sensors' => 'Heart rate, sleep, activity'], 'Protection' => ['Water resistance' => '5 ATM']]),
        CatalogDemoSeeder::productDefinition('audio', 'Arc ANC Headphones', 'arc-anc-headphones', 'AUR-AU-ANC5', '616403050005', 18_500_00, 15_900_00, 11_000_00, 29, 5, true, 268, [190, 165, 82], ['arc-headphones.png'], 'Comfortable over-ear headphones with active noise control and clear voice pickup.', ['Audio' => ['Drivers' => '40 mm', 'Modes' => 'ANC, transparency'], 'Battery' => ['Listening time' => 'Up to 38 hours'], 'Connectivity' => ['Wireless' => 'Bluetooth 5.3']]),
        CatalogDemoSeeder::productDefinition('audio', 'Echo Mini Speaker', 'echo-mini-speaker', 'AUR-AU-SP06', '616403060006', 12_900_00, null, 8_200_00, 52, 7, false, 340, [100, 100, 45], ['echo-speaker.png'], 'A compact connected speaker for desks, kitchens, and smaller shared spaces.', ['Audio' => ['Configuration' => '360-degree mono'], 'Connectivity' => ['Wireless' => 'Wi-Fi and Bluetooth'], 'Controls' => ['Voice' => 'Supported']]),
        CatalogDemoSeeder::productDefinition('workspace', 'Canvas 27 Display', 'canvas-27-display', 'AUR-DS-2707', '616404270007', 42_900_00, 39_900_00, 31_500_00, 21, 5, true, 4900, [613, 205, 455], ['canvas-monitor.png'], 'A crisp 27-inch display with practical connectivity for modern workstations.', ['Display' => ['Size' => '27 inch', 'Resolution' => '2560 x 1440'], 'Connectivity' => ['Ports' => 'USB-C, HDMI, DisplayPort'], 'Ergonomics' => ['Stand' => 'Height and tilt adjustable']]),
        CatalogDemoSeeder::productDefinition('workspace', 'Form Executive Chair', 'form-executive-chair', 'AUR-WS-CH08', '616404080008', 58_000_00, null, 39_000_00, 12, 3, false, 18_500, [760, 720, 1120], ['form-chair.png'], 'A supportive leather-finish chair with considered adjustments for focused work.', ['Materials' => ['Upholstery' => 'Leather finish', 'Frame' => 'Powder-coated steel'], 'Adjustments' => ['Support' => 'Height, tilt, lumbar'], 'Support' => ['Warranty' => '12 months']]),
        CatalogDemoSeeder::productDefinition('lifestyle', 'Stride Performance Trainer', 'stride-performance-trainer', 'AUR-LF-ST09', '616405090009', 14_800_00, 12_500_00, 8_600_00, 34, 6, false, 720, [340, 220, 130], ['stride-trainer.png'], 'A responsive everyday trainer designed for movement between work and travel.', ['Construction' => ['Upper' => 'Breathable mesh', 'Sole' => 'Responsive foam'], 'Fit' => ['Sizing' => 'True to size'], 'Care' => ['Cleaning' => 'Hand clean']]),
        CatalogDemoSeeder::productDefinition('lifestyle', 'Nomad Leather Slip-On', 'nomad-leather-slip-on', 'AUR-LF-NM10', '616405100010', 16_900_00, null, 10_200_00, 18, 4, false, 810, [330, 210, 120], ['nomad-slip-on.png'], 'A polished slip-on pair for travel days, meetings, and relaxed office settings.', ['Construction' => ['Upper' => 'Full-grain leather', 'Lining' => 'Soft textile'], 'Fit' => ['Profile' => 'Regular'], 'Care' => ['Finish' => 'Neutral leather cream']]),
    ],
    'transactions' => [
        'web_order_sku' => 'AUR-PH-X101',
        'pos_order_skus' => ['AUR-AU-ANC5', 'AUR-AU-SP06'],
    ],
];
