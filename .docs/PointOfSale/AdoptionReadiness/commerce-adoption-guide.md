# Commerce Adoption Guide

**Status:** Phase 4.4 adopter reference for the implemented Commerce module.

**Updated:** 2026-08-18

**Branch:** `feature/commerce-adoption-readiness`

## 1. Purpose

This guide explains how an Aureon derivative application can enable, configure,
operate, disable, or deliberately remove the existing Commerce module without
reverse-engineering its internals. It documents the implementation currently
owned by `app/Modules/Commerce`; it is not a proposal for the deferred returns,
warehouse, gateway, or customer-account features.

Read this guide together with:

- `commerce-operations-handbook.md` for operator procedures;
- `commerce-glossary-and-scenarios.md` for vocabulary and complete workflows;
- `commerce-extension-guide.md` before adding a new domain capability;
- `commerce-screenshot-manifest.md` for visual evidence and release captures;
- `../commerce-phase-4-hardening-adoption-plan.md` for the remaining release gate.

## 2. Implemented Boundary

The module supplies one shared product, inventory, customer, order, payment,
and till engine to two transaction surfaces:

1. A public storefront under `/shop` with catalog discovery, product details,
   a session cart, guest checkout, signed order pages, and order documents.
2. An authenticated POS terminal under `/pos` with register and till
   administration under `/admin/commerce/pos`.

The administration workspace under `/admin/commerce` provides catalog,
barcode-label, inventory, customer, order, reporting, and operational-dashboard
surfaces. Both channels write to the same orders, items, payments, customers,
products, stock projections, and immutable movement ledger.

The initial release deliberately excludes online payment capture, returns and
refunds, suppliers and purchasing, warehouses and transfers, product variants,
customer accounts, loyalty, coupons, direct ESC/POS hardware, and advanced tax
or accounting engines.

## 3. Runtime Prerequisites

The current application contract is:

| Concern | Current requirement |
| --- | --- |
| Runtime | PHP `^8.2`; the verified local runtime is PHP 8.2.12 |
| Framework | Laravel 12 |
| Reactive UI | Livewire 4 with Bootstrap pagination |
| Access control | Spatie Laravel Permission |
| Product media | Spatie Media Library and a public storage link |
| Documents | Shared Aureon `RendersPdfReports` contract and DOMPDF |
| Barcode labels | `picqer/php-barcode-generator` |
| Assets | Vite 7 and the module-owned CSS/JavaScript entries |
| Mail | A configured Laravel mail transport |
| Asynchronous work | A production queue worker for operational mail |
| Branding | Database-backed Institution Details and Aureon brand assets |

The host application must also preserve normal `web` sessions, authentication,
verified-email middleware, the shared activity recorder, and the active-user
authorization contract.

## 4. Module Ownership Map

| Path | Responsibility |
| --- | --- |
| `app/Modules/Commerce/CommerceServiceProvider.php` | Sole host registration point |
| `Config/commerce.php` | Environment-backed defaults |
| `Database/Migrations` | Module schema and comments |
| `Database/Factories` | Test fixture construction |
| `Database/Seeders` | Optional local demonstration graph |
| `Catalog` | Categories, products, barcodes, pricing, and media |
| `Inventory` | Stock projection, movement ledger, and stock events |
| `Customers` | Reusable customer records and archival workflow |
| `Orders` | Cart calculation, snapshots, lifecycle, payments, and documents |
| `Storefront` | Public catalog, cart, checkout, and signed access |
| `PointOfSale` | Registers, tills, terminal, checkout, receipts, and printing |
| `Reporting` | Dashboard projections and filtered PDF register reports |
| `Notifications` | Event-specific listeners, recipients, and queued mail |
| `Resources` | Namespaced Blade, CSS, JavaScript, and demo media |
| `Routes` | Separate `admin.php`, `pos.php`, and `storefront.php` surfaces |
| `tests/Feature/Commerce` | Integration and regression coverage |

Models retain integer primary and foreign keys internally. Externally
addressable records use immutable ULIDs, public products use slugs, and guest
order pages require signed URLs in addition to the order ULID.

## 5. Host Integration Points

An adopting project must retain these intentional host-level links:

1. `bootstrap/providers.php` registers `CommerceServiceProvider`.
2. `app/Support/CmsPermission.php` includes
   `CommercePermission::catalogue()` in the code-owned capability list.
3. `database/seeders/RoleSeeder.php` reconciles that catalogue and grants all
   current capabilities to `system-admin`.
4. `resources/views/layouts/partials/sidebar-admin.blade.php` and
   `sidebar-role.blade.php` render permission-aware navigation only while the
   module is enabled.
5. `vite.config.js` includes the storefront, dashboard, and POS asset entries.
6. `AppServiceProvider` keeps the active system administrator super-user gate;
   inactive users remain denied.
7. The host binds `RecordsSystemActivity`, `ResolvesInstitutionProfile`, and
   `RendersPdfReports`, which Commerce consumes through contracts.

The provider merges configuration, loads module migrations and namespaced
views, registers policies and Livewire aliases, maps seven event listeners, and
loads all three route files. Domain mutation does not belong in the provider.

## 6. Adoption Procedure

### 6.1 Install application dependencies

From the Laravel application root:

```powershell
composer install
npm.cmd install
```

Do not install a second cart, money, authorization, media, PDF, or barcode
package for the existing workflows. The module already owns those boundaries.

### 6.2 Configure the environment

Copy the Commerce keys from `.env.example`, then choose production values
before caching configuration. Money-valued settings ending in `_MINOR` use the
smallest configured currency unit. Tax rates use basis points, where `1600`
means 16.00 percent.

#### Core, currency, tax, inventory, and media

| Key | Default | Purpose |
| --- | --- | --- |
| `COMMERCE_ENABLED` | `true` | Master module switch |
| `COMMERCE_CURRENCY_CODE` | `KES` | ISO-style currency code |
| `COMMERCE_CURRENCY_SYMBOL` | `KSh` | Presentation symbol |
| `COMMERCE_CURRENCY_DECIMALS` | `2` | Supported decimal precision, clamped by domain helpers |
| `COMMERCE_TAX_RATE_BPS` | `1600` | Default product tax rate in basis points |
| `COMMERCE_PRICES_INCLUDE_TAX` | `true` | Whether configured selling prices include tax |
| `COMMERCE_ALLOW_OVERSELL` | `false` | Permit tracked stock to fall below zero |
| `COMMERCE_LOW_STOCK_THRESHOLD` | `5` | Default threshold for new stock projections |
| `COMMERCE_PRODUCT_GALLERY_LIMIT` | `8` | Maximum persisted product gallery images |
| `COMMERCE_MEDIA_UPLOAD_MAX_KB` | `5120` | Maximum size of each JPEG, PNG, or WebP upload |

#### Checkout and signed access

| Key | Default | Purpose |
| --- | --- | --- |
| `COMMERCE_COUNTRY_CODE` | `KE` | Default customer country code |
| `COMMERCE_DELIVERY_FEE_MINOR` | `0` | Flat delivery fee in minor units |
| `COMMERCE_PAYMENT_METHODS` | `mobile_money,bank_transfer,cash_on_delivery` | Manual storefront payment choices |
| `COMMERCE_CART_SESSION_KEY` | `commerce.storefront.cart` | Host-session key containing product IDs and quantities |
| `COMMERCE_CONFIRMATION_LINK_MINUTES` | `120` | Signed confirmation lifetime |
| `COMMERCE_TRACKING_LINK_DAYS` | `90` | Signed tracking lifetime |
| `COMMERCE_DOCUMENT_LINK_DAYS` | `30` | Signed customer document lifetime |
| `COMMERCE_STOREFRONT_SHARING_ENABLED` | `true` | Product share-group switch |
| `COMMERCE_STOREFRONT_SHARING_PLATFORMS` | empty | Optional allow-list: `facebook,x,whatsapp,tiktok,instagram,copy` |
| `COMMERCE_STOREFRONT_WHATSAPP_ORDER_ENABLED` | `true` | Show direct WhatsApp ordering when Institution Details supplies a usable number |

#### POS, receipts, numbering, and barcodes

| Key | Default | Purpose |
| --- | --- | --- |
| `COMMERCE_POS_PAYMENT_METHODS` | `cash,mobile_money,card,bank_transfer` | Allowed terminal tenders |
| `COMMERCE_POS_MAX_TENDERS` | `4` | Maximum split-payment rows |
| `COMMERCE_POS_PRODUCT_RESULTS` | `12` | Bounded terminal search result count |
| `COMMERCE_POS_RECEIPT_PRINT_DRIVER` | `browser` | Default driver for newly created registers |
| `COMMERCE_POS_RECEIPT_PRINT_MODE` | `manual` | `manual` or `auto_prompt` default |
| `COMMERCE_POS_RECEIPT_PAPER_WIDTH_MM` | `80` | `58` or `80` millimetre default |
| `COMMERCE_POS_ADD_TO_CART_SOUND` | `true` | Browser-only cashier confirmation tone |
| `COMMERCE_POS_SALES_HISTORY_TERMINAL` | `true` | Mount the foldable cashier-owned history below held sales |
| `COMMERCE_POS_SALES_HISTORY_DASHBOARD` | `true` | Mount cashier-owned history on eligible role and Commerce dashboards |
| `COMMERCE_POS_SALES_HISTORY_PER_PAGE` | `8` | History rows per page, clamped to 5 through 25 |
| `COMMERCE_WEB_ORDER_PREFIX` | `WEB` | Web order number prefix |
| `COMMERCE_POS_ORDER_PREFIX` | `POS` | POS order number prefix |
| `COMMERCE_BARCODE_INTERNAL_PREFIX` | `MM` | Auto-generated internal barcode prefix |
| `COMMERCE_BARCODE_INTERNAL_PAD` | `10` | Numeric suffix padding length |
| `COMMERCE_BARCODE_LABEL_COLUMNS` | `3` | Default A4 label columns |
| `COMMERCE_BARCODE_LABEL_STORE_NAME` | `true` | Default store-name visibility |
| `COMMERCE_BARCODE_LABEL_PRODUCT_NAME` | `true` | Default product-name visibility |
| `COMMERCE_BARCODE_LABEL_PRICE` | `true` | Default selling-price visibility |
| `COMMERCE_BARCODE_LABEL_MAX` | `300` | Maximum labels in one synchronous run |

Register records own their active print driver, mode, width, and optional
printer label. The environment values are creation and fallback defaults, not a
global override of existing register records.

#### Operational notifications

| Key | Default | Purpose |
| --- | --- | --- |
| `COMMERCE_OPERATIONAL_NOTIFICATIONS_ENABLED` | `true` | Global operational-mail switch |
| `COMMERCE_NOTIFY_WEB_ORDER_PLACED` | `true` | Staff new-web-order alert |
| `COMMERCE_NOTIFY_PAYMENT_CONFIRMED` | `true` | Customer completed-payment message |
| `COMMERCE_NOTIFY_ORDER_READY` | `true` | Customer ready-for-fulfillment message |
| `COMMERCE_NOTIFY_ORDER_CANCELLED` | `true` | Customer cancellation message |
| `COMMERCE_NOTIFY_LOW_STOCK` | `true` | Staff threshold-crossing alert |
| `COMMERCE_NOTIFY_OUT_OF_STOCK` | `true` | Staff depletion alert |
| `COMMERCE_NOTIFY_TILL_VARIANCE` | `true` | Staff material-variance alert |
| `COMMERCE_TILL_VARIANCE_ALERT_THRESHOLD_MINOR` | `10000` | Absolute minor-unit alert threshold |
| `COMMERCE_NOTIFICATION_QUEUE` | `default` | Queue used by dedicated Commerce notifications |

Only configured enum values should be exposed for payment methods, receipt
modes, and paper widths. Invalid values fail validation or are rejected by the
service boundary.

### 6.3 Apply persistence and media setup

For an existing adopting database:

```powershell
php artisan migrate --force
php artisan storage:link
php artisan db:seed --class="Database\Seeders\RoleSeeder" --force
```

Do not use `migrate:fresh` on a retained environment. The Commerce provider
loads module-owned migrations automatically while enabled. The storage link is
required for product and category media served from the public disk.

### 6.4 Build assets and caches

```powershell
npm.cmd run build
php artisan optimize:clear
php artisan optimize
```

Clear configuration and route caches whenever an adopter changes a Commerce
environment value, route prefix integration, permission, or provider state.

### 6.5 Configure Institution Details

Set the adopting organization's name, short name, email, phone, address,
website, logo, and document identity through Institution Details. Storefront
contact actions, notification presentation, order documents, reports, and POS
receipts consume this shared profile. Do not hard-code adopter branding inside
Commerce views or notification classes.

### 6.6 Run queue workers

The storefront acknowledgement and operational notifications implement queued
delivery. Production must continuously process the configured connection and
queue, for example:

```powershell
php artisan queue:work --queue=default --tries=3 --timeout=90
```

Use the deployment platform's process supervisor. Inspect `failed_jobs`, the
application log viewer, and the system activity trail when delivery does not
match a committed transaction. Queue failure does not roll back an already
committed order, payment, stock mutation, or till closure.

## 7. Route and Access Matrix

All administration and POS routes require `web`, `auth`, and `verified` unless
noted otherwise.

| Surface | Route | Additional access |
| --- | --- | --- |
| Storefront catalog | `/shop` | Public `web` middleware |
| Product detail | `/shop/products/{slug}` | Public published product |
| Cart and checkout | `/shop/cart`, `/shop/checkout` | Public session |
| Confirmation, tracking, document | `/shop/orders/{order}/...` | Temporary signed URL and web-order check |
| Commerce overview | `/admin/commerce` | `view-commerce-dashboard` |
| Product catalog | `/admin/commerce/catalog` | `view-products` |
| Barcode labels | `/admin/commerce/catalog/barcodes` | `view-products` |
| Inventory | `/admin/commerce/inventory` | `view-inventory` |
| Customers | `/admin/commerce/customers` | `manage-customers` |
| Orders and admin documents | `/admin/commerce/orders` | `view-orders` |
| POS terminal | `/pos` | `access-pos` and the operator's open till to transact |
| Register administration | `/admin/commerce/pos/registers` | `manage-tills` |
| Till administration | `/admin/commerce/pos/tills` | `manage-tills` |
| POS receipt | `/pos/receipts/{order-ulid}` | Owning cashier, `view-orders`, `manage-tills`, or active system administrator |

Route middleware protects page entry. Policies and every mutating Livewire
action repeat authorization after re-fetching the selected record.

Cashier sales history is an embedded, read-only Livewire projection rather than
a new route. It requires `access-pos`, scopes every aggregate and row to the
authenticated user's immutable `cashier_id`, and delegates receipt access to the
existing protected receipt route.

## 8. Permission Composition

| Permission | Intended capability |
| --- | --- |
| `view-commerce-dashboard` | View aggregate operational indicators |
| `view-products` | Browse products, categories, and barcode labels |
| `manage-products` | Create, edit, publish, archive, and organize catalog records |
| `view-inventory` | Inspect stock projections and movement history |
| `manage-inventory` | Post controlled manual stock adjustments |
| `view-orders` | Inspect web orders, POS sales, payments, and documents |
| `manage-orders` | Advance, cancel, and record eligible order payments |
| `manage-customers` | Create, edit, archive, and restore reusable customers |
| `access-pos` | Operate the cashier terminal and owned receipts |
| `manage-tills` | Configure registers and open or close till sessions |

Recommended compositions:

| Responsibility | Minimum permissions |
| --- | --- |
| Catalog editor | `view-products`, `manage-products` |
| Inventory controller | `view-inventory`, `manage-inventory` |
| Order operator | `view-orders`, `manage-orders` |
| POS cashier | `access-pos` |
| Till manager | `manage-tills` |
| Cashier supervisor | `access-pos`, `manage-tills`, optionally `view-orders` |
| Commerce analyst | `view-commerce-dashboard` plus only the destinations they may open |

The optional `pos-cashier` role deliberately contains only `access-pos`.
System administrators receive the complete code-owned catalogue and have an
active-account `Gate::before` bypass. Operational notification recipients are
still selected through explicit permissions; the global gate bypass does not
silently subscribe an administrator.

## 9. Optional Demonstration Data

`CommerceDemoSeeder` is intentionally absent from `DatabaseSeeder`. It is for
local demonstrations and disposable QA databases only. This documentation
phase neither changed nor executed it.

After the host's base accounts exist, an adopter may opt in locally. Omitting
the context preserves the original `default-mixed` catalog:

```powershell
php artisan commerce:demo-seed
php artisan commerce:demo-seed computers-it
```

Supported keys are `default-mixed`, `computers-it`,
`hardware-construction`, `boutique-fashion`, `pharmacy-health`,
`supermarket-fmcg`, `beauty-personal-care`, `automotive-parts`,
`agrovet-farm`, `office-bookshop`, and `shoe-store`.

Existing products are retained by default. A disposable or controlled
demonstration installation may explicitly archive every current product before
publishing the selected context:

```powershell
php artisan commerce:demo-seed hardware-construction --archive-existing
```

Archival changes product status only. It does not delete products, media,
stocks, movements, order items, payments, or till history. The selected
context's products are then upserted and published. Because the lean seeder has
no demo-ownership table, this flag archives adopter-created products too; never
use it casually on a populated production catalog.

The deterministic graph contains:

- 10 products and six categories for the default context, or 12 products and
  six/seven categories for each focused context;
- regular and active sale-price examples with product/category media;
- three reusable customers;
- two active registers with 80 mm auto-prompt and 58 mm manual print examples;
- one pending pickup web order with committed stock;
- one completed split-tender POS sale;
- one balanced closed till session; and
- `cashier@aureon.test` / `password`, with `viewer` plus least-privileged
  `pos-cashier` roles.

It does not currently prebuild low-stock, out-of-stock, untracked-stock,
open-till, or variance records. The operational scenarios in
`commerce-glossary-and-scenarios.md` explain how those states arise without
claiming they are present in the seed graph.

Contextual product media uses deterministic module-owned paths, and a seeder
rerun refreshes copied media when a source checksum changes. The original focused
contexts retain one optimized featured PNG plus a branded fallback. `shoe-store`
demonstrates the richer supported contract with three product-specific catalog
angles for every pair.

The same allowlisted command is available to authorized administrators at
`/admin/commerce/demo-data`. Grant `manage-commerce-demo-data` sparingly: keep
mode is the default, while archive mode changes every existing product to the
archived state before republishing the selected context. The interface does not
delete historical products, stock movements, media, orders, or payments.

For a disposable local database only, the complete reset sequence is:

```powershell
php artisan migrate:fresh --seed
php artisan commerce:demo-seed computers-it
```

`migrate:fresh` destroys all application tables. Never run that sequence on a
shared, staging, or production database.

## 10. Enable, Disable, and Remove

### 10.1 Disable without deleting data

Set:

```dotenv
COMMERCE_ENABLED=false
```

Then run:

```powershell
php artisan optimize:clear
```

While disabled, `CommerceServiceProvider` registers no policies, Livewire
aliases, event listeners, migrations, views, or routes. Commerce sidebar links
are omitted and its URLs return 404. Existing database tables, media, role
permissions, and compiled assets remain intact; disabling is reversible and is
not data deletion.

### 10.2 Physical removal

Physical removal is a separate, reviewed migration project:

1. Back up Commerce tables and product media.
2. Disable the module and verify the host dashboards still boot.
3. Remove provider registration, module Vite entries, sidebar integrations,
   and the `CommercePermission` merge from `CmsPermission`.
4. Remove module source and its tests only after host references are gone.
5. Use an explicit removal migration if data must be dropped. Do not rely on a
   broad batch rollback that may include unrelated host migrations.
6. Remove obsolete permission/role rows only after checking assignments.
7. Rebuild assets and clear all application caches.

Preserving disabled tables is the safer default when an adopter may later
restore the module or must retain transaction records for audit purposes.

## 11. Deployment Smoke Checks

After adoption or configuration changes, verify:

```powershell
php artisan route:list --name=commerce --except-vendor
php artisan event:list --event=Commerce
php artisan config:cache
php artisan route:cache
php artisan view:cache
npm.cmd run build
```

Then clear generated caches if the environment is not meant to remain
optimized. Confirm the public catalog, one signed order path, each authorized
admin workspace, a cashier-owned till, mail queue processing, product media,
order PDFs, and receipt print media. The complete release matrix belongs to
Phase 4.5 and `commerce-screenshot-manifest.md`.

## 12. Security and Data Rules

- Never trust browser-submitted prices, tax, totals, stock balances, or change.
- Never use ULID possession as authorization.
- Keep order-item and customer snapshots immutable after placement.
- Do not expose cost price, internal notes, payment metadata, integer IDs, or
  reusable customer identifiers in customer documents or receipts.
- Keep mutations inside domain services with transactions and row locks.
- Send events only after the outer transaction commits.
- Store only sanitized payment references; no card credentials belong here.
- Keep receipt bridge payloads limited to immutable display projections.
- Use the system activity trail for successful business mutations and Laravel
  logs for unexpected failures, without duplicating sensitive payloads.

## 13. Web-Accessible Documentation Auxiliary

Laradocs is a suitable future publication layer because it can serve a Markdown
tree from Laravel, supports nested navigation, and allows route middleware and
an environment master switch. It is intentionally not installed in this phase.

The current blocker is runtime compatibility: the application supports PHP
`^8.2` and is presently running PHP 8.2.12, while Laradocs currently requires
PHP 8.3 or newer. Before adoption:

1. Approve and verify the application-wide PHP 8.3 upgrade.
2. Review the official [Laradocs requirements](https://laradocs.dev/docs/system-requirements)
   and [configuration](https://laradocs.dev/docs/configuration) against the
   deployed package version.
3. Decide whether `LARADOCS_PATH` should expose all `.docs` material or a curated
   publication tree. Engineering diagnostics, local credentials, screenshots,
   and internal handoffs should not become public by accident.
4. Add a dedicated code-owned documentation permission and configure `web`,
   `auth`, `verified`, and permission middleware for every docs route.
5. Disable public SEO, robots, sitemap, search API, and other auxiliary routes
   unless their authorization behavior is proven under feature tests.
6. Test disabled mode, anonymous access, inactive users, insufficient roles,
   authorized roles, route caching, search, nested navigation, and production
   cache invalidation before enabling hosted documentation.

The package installation, document curation, middleware permission, styling,
and deployment checks must remain a separate reviewable feature branch.
