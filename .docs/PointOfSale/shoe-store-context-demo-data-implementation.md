# Shoe Store Context Demo Data

## 1. Purpose

This delivery adds `shoe-store` as an eleventh selectable Commerce demonstration
context. It extends the established contextual fixture path only: one trusted
data manifest, deterministic module-owned media, the existing
`commerce:demo-seed` command, and the permission-gated Livewire interface at
`/admin/commerce/demo-data`.

No migration, model, service, job, ownership table, or parallel seeder was
introduced.

## 2. Catalog Contract

The context provides 12 products across six categories:

| Category | Products |
| --- | --- |
| Lifestyle Sneakers | Nike Air Force 1 '07 White; Nike Air Max 270 Black Anthracite |
| Court Classics | Adidas Samba OG Black White; Puma Suede Classic XXI Navy |
| Canvas Shoes | Converse Chuck Taylor All Star High Top Black; Vans Old Skool Black White |
| Running Shoes | Nike Pegasus 41 Road Running; Adidas Ultraboost 5 Core Black |
| Football Boots | Nike Mercurial Vapor 16 Academy FG/MG; Adidas Predator League Firm Ground |
| Boots and Smart Casual | Timberland Premium 6-Inch Boot Wheat; Clarks Desert Boot Beeswax Leather |

Every product includes a unique `AUR-SHO-*` SKU, unique EAN-style demonstration
barcode, Kenyan shilling price and cost in integer minor units, stock and
low-stock threshold, physical dimensions, weight, sale policy where applicable,
search metadata, and grouped footwear specifications. Inventory uses `pair` as
the unit label.

The contextual transaction graph uses:

- `AUR-SHO-LS01` for the demonstration web order;
- `AUR-SHO-CV05` and `AUR-SHO-FB09` for the split-tender POS sale; and
- the existing customer, register, till, order, payment, and stock services.

## 3. Media Contract

Each product has three product-specific gallery images in deterministic order:

1. three-quarter feature view;
2. side profile; and
3. rear three-quarter view.

The 36 source assets live under:

```text
app/Modules/Commerce/Resources/demo/products/contexts/shoe-store/
```

All are indexed PNG, `640x640`, sRGB, metadata-stripped, and compressed with the
same PNG8 profile as the existing contextual fixtures. Their combined size is
2,003,381 bytes, averaging approximately 55.6 KB per image.

Six category thumbnails reuse the feature view of the first relevant product.
The demo-data selector uses the 4:3 catalog contact sheet at:

```text
resources/img/commerce/demo-contexts/shoe-store.jpg
```

The Vite static-copy contract publishes that source without additional runtime
code. `CatalogDemoSeeder::syncMedia()` retains SHA-256 reconciliation, so an
existing copied gallery is rebuilt when any trusted source image changes.

## 4. Image Generation And Review

The built-in OpenAI image generator produced six category source sheets. Each
sheet requested two specific footwear silhouettes in a three-column angle set,
on a consistent light-neutral ecommerce studio background, with no people,
boxes, text labels, or watermarks. ImageMagick split and optimized those reviewed
sheets into the final 36 assets. Some silhouettes contain generated brand-like
design cues; they remain demonstration imagery rather than official photography.

The permanent featured sheet, full three-angle gallery sheet, and
machine-readable SHA-256 manifest are:

```text
.docs/dev/commerce-context-image-qa/shoe-store.jpg
.docs/dev/commerce-context-image-qa/shoe-store-gallery.jpg
.docs/dev/commerce-context-image-qa/shoe-store-asset-manifest.json
```

Product and brand names are demonstration catalog data, not a claim that the
generated unbranded imagery is official manufacturer photography. Adopters must
replace demonstration names and media with authorized catalog content before a
production launch and verify pricing, sizing, availability, trademark use, and
tax treatment.

## 5. Interface And Command Use

Authorized administrators can select **Shoe store** in the existing demo-data
workspace and choose the unchanged keep or archive behavior. The equivalent CLI
commands are:

```powershell
php artisan commerce:demo-seed shoe-store
php artisan commerce:demo-seed shoe-store --archive-existing
```

Keep mode is the default and can coexist with the other ten contexts. Archive
mode changes existing product publication state but does not delete products,
media, inventory history, orders, payments, or till records.

## 6. Changed Surface

- `app/Modules/Commerce/Database/Seeders/Data/shoe-store.php`
- `app/Modules/Commerce/Database/Seeders/CatalogDemoSeeder.php`
- `app/Modules/Commerce/DemoData/Livewire/Admin/DemoDataManager.php`
- `app/Modules/Commerce/Resources/demo/products/contexts/shoe-store/*.png`
- `resources/img/commerce/demo-contexts/shoe-store.jpg`
- `tests/Feature/Commerce/CommerceContextualDemoSeederTest.php`
- `tests/Feature/Commerce/CommerceDemoDataInterfaceTest.php`
- `scripts/qa-commerce-demo-data.mjs`
- related adoption, extension, interface, and screenshot documentation

## 7. Verification Contract

Automated coverage verifies:

- the new key is allowlisted and its definition passes preflight;
- identifiers remain unique across all 11 contexts;
- all 130 products and 68 categories can coexist;
- shoe-store seeding creates 12 products, six categories, 12 stocks, 12 opening
  movements, 36 product media rows, six category media rows, and two coherent
  demonstration orders;
- every shoe product receives exactly three ordered gallery records;
- the Livewire interface exposes the eleventh thumbnail and executes the same
  guarded command path; and
- normal reruns retain stock balances and checksum-based media reconciliation.
