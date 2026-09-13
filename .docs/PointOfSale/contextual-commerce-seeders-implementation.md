# Contextual Commerce Seeders Implementation

**Status:** Implemented; featured imagery complete, secondary views deferred

**Branches:** `feature/commerce-context-seeders`,
`feature/commerce-context-product-images`

**Plan:** `todo/seeders_todo/contextual-commerce-seeders-plan.md`

**Implemented:** 2026-09-05

## 1. Scope

This delivery extends the existing optional Commerce demonstration seeder chain
with ten merchant contexts. The seeder architecture deliberately avoids new
persistence or background runtime machinery. A later, separately documented
Livewire launcher adds only a guarded web control surface over the same command.

The implementation provides:

- the original mixed catalog as the unchanged default;
- nine additional merchant-context data files;
- 118 products and 62 categories across all contexts;
- an exact selected-context transaction product mapping;
- keep-existing behavior by default;
- explicit non-destructive archive-existing behavior;
- one context-aware Artisan command;
- preflight validation before catalog archival or writes; and
- one optimized generated featured PNG and one correctly named branded gallery
  fallback for each of the 108 new contextual products.

## 2. Architecture

### 2.1 Existing chain retained

`CommerceDemoSeeder` still runs access, catalog, customer, and transaction
fixtures in dependency order. It now accepts `context` and `archiveExisting`
parameters and forwards them through Laravel's native `Seeder::callWith()`
mechanism.

`CatalogDemoSeeder` still owns category/product upserts, publication, stock
projection, opening movements, and Spatie media attachment. It now loads one
allowlisted trusted PHP data file and validates the complete context before
writing.

`TransactionDemoSeeder` still creates orders, payments, till activity, and stock
effects through the production domain services. Its former hard-coded product
SKUs are now parameters selected by the context data. Existing internal markers
continue making the two demonstration orders exactly-once records.

### 2.2 Command contract

```powershell
# Original catalog; existing products remain active
php artisan commerce:demo-seed

# Selected catalog; existing products remain active
php artisan commerce:demo-seed computers-it

# Selected catalog replaces the active product presentation
php artisan commerce:demo-seed computers-it --archive-existing
```

The command rejects unknown context keys before invoking a seeder. The root
`DatabaseSeeder` remains independent from optional Commerce fixtures.

### 2.3 Archive semantics

`--archive-existing` updates every non-deleted existing product to the archived
status before the selected context is upserted and published. The operation:

- does not delete product rows;
- does not reset existing stock;
- does not duplicate opening movements;
- does not delete media, orders, order items, payments, customers, registers,
  till sessions, or stock movements;
- leaves soft-deleted non-selected products untouched; and
- preserves historical demonstration transactions when switching context.

Because the lean implementation adds no ownership table, archival cannot
distinguish demo products from adopter products. It is therefore opt-in and
documented as a controlled demonstration operation.

## 3. Context Inventory

| Context | Products | Categories | Gallery source state |
| --- | ---: | ---: | --- |
| `default-mixed` | 10 | 6 | Original product imagery |
| `computers-it` | 12 | 6 | Generated featured + branded fallback |
| `hardware-construction` | 12 | 7 | Generated featured + branded fallback |
| `boutique-fashion` | 12 | 6 | Generated featured + branded fallback |
| `pharmacy-health` | 12 | 6 | Generated featured + branded fallback |
| `supermarket-fmcg` | 12 | 7 | Generated featured + branded fallback |
| `beauty-personal-care` | 12 | 6 | Generated featured + branded fallback |
| `automotive-parts` | 12 | 6 | Generated featured + branded fallback |
| `agrovet-farm` | 12 | 6 | Generated featured + branded fallback |
| `office-bookshop` | 12 | 6 | Generated featured + branded fallback |
| **Total** | **118** | **62** | **108 generated + 108 fallback files** |

The primary sources are realistic 640 x 640 studio-style product renders. They
were stripped of metadata, converted to sRGB indexed PNGs, and compressed to a
10,622,942-byte total (98,361-byte mean). The secondary sources are 256 x 256
copies of the centralized Aureon logo icon with SHA-256
`5CC0AC971C1147D8BC619C76B374AF0187621CB7E36AAB3A8B557A2BA070F7B8`.
Both deterministic file names remain part of the gallery contract.

Seeder media synchronization compares each copied media file with its module
source by SHA-256. A changed generated source therefore replaces stale stored
media on an idempotent rerun without creating duplicate media or stock activity.

## 4. Preflight Contract

Before archival or database writes, the selected context verifies:

- required category, product, and transaction sections;
- the approved 10-12 product count;
- required category and product fields;
- unique category slugs, SKUs, product slugs, and barcodes;
- product-to-category references;
- one web and exactly two POS transaction SKUs present in the context;
- safe relative media paths with every source file present; and
- database slug, barcode, and manufacturer-barcode collisions against a
  different SKU.

An invalid context, malformed definition, missing asset, or identifier collision
fails before `archiveExisting` can alter current product status.

## 5. Files

### Application

- `app/Console/Commands/SeedCommerceDemo.php`
- `app/Modules/Commerce/Database/Seeders/CatalogDemoSeeder.php`
- `app/Modules/Commerce/Database/Seeders/CommerceDemoSeeder.php`
- `app/Modules/Commerce/Database/Seeders/TransactionDemoSeeder.php`
- `app/Modules/Commerce/Database/Seeders/Data/default-mixed.php`
- `app/Modules/Commerce/Database/Seeders/Data/computers-it.php`
- `app/Modules/Commerce/Database/Seeders/Data/hardware-construction.php`
- `app/Modules/Commerce/Database/Seeders/Data/boutique-fashion.php`
- `app/Modules/Commerce/Database/Seeders/Data/pharmacy-health.php`
- `app/Modules/Commerce/Database/Seeders/Data/supermarket-fmcg.php`
- `app/Modules/Commerce/Database/Seeders/Data/beauty-personal-care.php`
- `app/Modules/Commerce/Database/Seeders/Data/automotive-parts.php`
- `app/Modules/Commerce/Database/Seeders/Data/agrovet-farm.php`
- `app/Modules/Commerce/Database/Seeders/Data/office-bookshop.php`
- `app/Modules/Commerce/Resources/demo/products/contexts/<context>/*.png`
- `app/Modules/Commerce/DemoData/`
- `resources/img/commerce/demo-contexts/*.jpg`

### Tests and documentation

- `tests/Feature/Commerce/CommerceContextualDemoSeederTest.php`
- `README.md`
- `.docs/PointOfSale/AdoptionReadiness/commerce-adoption-guide.md`
- `.docs/PointOfSale/AdoptionReadiness/commerce-operations-handbook.md`
- `.docs/PointOfSale/AdoptionReadiness/commerce-extension-guide.md`
- `.docs/PointOfSale/todo/seeders_todo/contextual-commerce-seeders-plan.md`
- `.docs/PointOfSale/contextual-commerce-seeders-implementation.md`
- `.docs/PointOfSale/contextual-commerce-demo-data-interface.md`

## 6. Verification Record

### Completed

- PHP syntax lint: all changed seeders, command, and ten data files passed.
- Laravel Pint: changed PHP files formatted cleanly.
- Focused test run: 7 tests, 2,325 assertions passed.
- Original compatibility test: default counts, transactions, media, and rerun
  idempotency remain green.
- Aggregate fixture test: 118 products, 62 categories, 118 stocks, 118 opening
  movements, and 290 media records coexist.
- Alternate-context command test: Computers and IT creates 12 products, six
  categories, 30 media rows, and context-correct web/POS order items.
- Archive test: ten original products become archived, twelve selected products
  remain published, and all existing orders/order items survive.
- Collision test: conflicting adopter data remains published after preflight
  rejects the context.

### Final gate

- Complete Commerce regression: 123 tests and 3,399 assertions passed.
- Complete application regression: 333 tests and 5,544 assertions passed.
- Production asset build: 114 modules transformed and the Vite build completed.
- Artisan discovery: `commerce:demo-seed` exposes the expected context argument
  and `--archive-existing` option.
- Disposable install verification: `migrate:fresh --seed`, followed by
  `commerce:demo-seed computers-it`, completed against an isolated SQLite
  database and isolated storage root.
- Storefront browser QA: 13 diagnostic cases and nine screenshots passed across
  desktop, laptop, tablet, mobile, light, and dark presentation.
- POS/admin browser QA: nine diagnostic cases and nine screenshots passed across
  the same responsive/theme matrix with an isolated admin-owned till.
- Browser diagnostics reported zero runtime errors, failed requests, broken or
  unloaded images, unlabeled controls, duplicate IDs, text overflow, or
  document-width overflow.
- Evidence is retained under
  `.docs/dev/commerce-context-seeder-qa/{storefront,pos}`; temporary databases,
  storage, router scripts, browser profiles, and local server processes were
  removed after verification.

## 7. Deferred Work

- Replace the 108 branded secondary fallbacks with product-specific alternate
  views if an adopter requires a complete two-image photography set.
- Revisit durable run ownership, previews, queueing, and granular permissions
  only if the browser workflow creates a demonstrated need.
- Production pharmacy/agrovet adoption still requires applicable regulated
  product, tax, batch, expiry, and professional review.
