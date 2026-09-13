# Commerce Catalog and Inventory Administration

## Record status

- **Module:** Commerce
- **Phase:** 3, authorized catalog and inventory administration
- **Branch:** `feature/pos-product-management`
- **Implementation date:** 2026-07-16
- **Status:** Implemented and verified
- **Predecessor commit:** `692f247 feat(commerce): add transactional services and authorization`
- **Related records:**
  - `pos-module-plan.md`
  - `commerce-module-architecture.md`
  - `commerce-data-model.md`
  - `commerce-schema-model-implementation.md`
  - `commerce-services-authorization-implementation.md`

This phase exposes the first user-facing Commerce workflows: product and
category administration plus current inventory and immutable stock history.
It deliberately does not activate the POS terminal or public storefront.

## Planned edits

1. Keep all Commerce controllers, Livewire classes, form objects, views, and
   support code inside `app/Modules/Commerce`.
2. Expose only permission-protected catalog and inventory administration routes.
3. Register stable Livewire aliases through `CommerceServiceProvider` rather
   than depending on root namespace discovery.
4. Preserve policy checks at route, component boot, and mutating action levels.
5. Keep product, category, media, publication, and inventory writes inside the
   existing `ProductService` and `InventoryService` boundaries.
6. Convert human-readable decimal inputs to integer minor units without using
   floating-point arithmetic.
7. Add responsive Bootstrap 5 workspaces that follow the existing Aureon
   dashboard, theme controller, modal, table, and icon conventions.
8. Verify HTTP access, Livewire actions, media, exact pricing, stock
   reconciliation, responsive containment, and the full application baseline.

All eight edits were completed. The implementation stayed within this phase's
approved boundary.

## Implemented structure

```text
app/Modules/Commerce/
|-- Catalog/
|   |-- Livewire/
|   |   |-- Admin/
|   |   |   |-- ProductCatalog.php
|   |   |   `-- ProductCategoryManager.php
|   |   `-- Forms/
|   |       |-- CategoryForm.php
|   |       `-- ProductForm.php
|   |-- Services/ProductService.php
|-- Http/Controllers/Admin/
|   |-- CatalogController.php
|   `-- InventoryController.php
|-- Inventory/Livewire/
|   |-- Admin/InventoryManager.php
|   `-- Forms/StockAdjustmentForm.php
|-- Resources/views/
|   |-- admin/{catalog,inventory}/index.blade.php
|   `-- livewire/admin/
|       |-- product-catalog.blade.php
|       |-- product-category-manager.blade.php
|       `-- inventory-manager.blade.php
|-- Routes/admin.php
`-- Support/ScaledDecimal.php
```

`CommerceServiceProvider` remains the module's only host registration point.
It loads module configuration, migrations, namespaced views, separate route
files, policies, and the three explicit Livewire aliases.

## Route and authorization contract

| Route | Name | Required capability |
| --- | --- | --- |
| `GET /admin/commerce/catalog` | `commerce.admin.catalog.index` | `view-products` |
| `GET /admin/commerce/inventory` | `commerce.admin.inventory.index` | `view-inventory` |

Both routes also require `web`, `auth`, and `verified`. Guests are redirected
to login, authenticated users without the relevant capability receive HTTP
403, and users with view-only permissions can render the workspaces but cannot
invoke management actions.

Mutating Livewire actions authorize the affected policy again after reloading
the selected numeric ID from the database. Selected IDs use `#[Locked]` and
are never trusted as hydrated models. The module's existing policies map
management actions to `manage-products` or `manage-inventory`.

## Livewire behavior

### Product catalog

`ProductCatalog` provides:

- URL-backed search, status, category, and page-size filters;
- computed statistics, category options, and paginated product queries;
- create and edit dialogs backed by `ProductForm`;
- separate draft, published, and archived lifecycle actions;
- category assignment, descriptions, SKU, barcode, quantity limits, tax,
  dimensions, specifications, featured state, and SEO metadata;
- regular, sale, and internal-cost price entry in configured major units;
- validated multi-file JPEG, PNG, and WebP gallery uploads;
- service-owned image removal with ownership validation;
- visible, token-aware badges in light and dark modes.

Products are still created as drafts with a zero-stock projection. Publication
is an explicit action and continues through `ProductService::transition()`.

### Product categories

`ProductCategoryManager` provides:

- ordered hierarchical category listing;
- create and edit dialogs backed by `CategoryForm`;
- parent selection with service-level cycle protection;
- active/hidden toggling;
- optional single category image replacement or removal;
- description, sort order, and SEO metadata.

Category writes and media changes are recorded as `commerce.category.*`
system activities. A category is hidden rather than destructively removed by
this initial administration workflow.

### Inventory

`InventoryManager` provides:

- URL-backed product search, stock-state, movement-type, and page-size filters;
- current projection totals for products, units, low stock, and out of stock;
- current stock rows with threshold and tracking state;
- an increase/decrease adjustment dialog backed by `StockAdjustmentForm`;
- required positive quantity and human-readable adjustment reason;
- append-only movement history with before/after balances, source, and actor;
- separate pagination for projections and movements.

The component never changes `stocks.on_hand` directly. It calls
`InventoryService::adjust()`, which locks the product and stock row, prevents
disallowed negative balances, writes the projection and matching movement in
one transaction, and records system activity.

## Exact decimal contract

`ScaledDecimal` converts unsigned decimal strings to scaled integers by string
decomposition. It does not multiply PHP floats. It validates precision,
supports zero through six decimal places, guards integer overflow, and formats
persisted scaled integers back to stable input strings.

For the default two-decimal currency:

```text
1250.50 -> 125050 minor units
```

The same helper converts percentage input to tax basis points, so `16.00`
becomes `1600`. `ProductForm` validates the configured currency precision
before creating its service payload.

## Media contract

Commerce media limits are centralized in `Config/commerce.php`:

- `commerce.media.product_gallery_limit`, default `8`;
- `commerce.media.upload_max_kilobytes`, default `5120`.

Uploads must be valid JPEG, PNG, or WebP images. Product gallery additions
enforce the total persisted-image cap inside `ProductService`; removal verifies
that the media row belongs to the supplied product and collection. Category
images use the model's existing single-file collection.

Stable activity names added by this phase are:

- `commerce.product.media_added`
- `commerce.product.media_removed`
- `commerce.category.media_replaced`
- `commerce.category.media_removed`

## Presentation and responsive behavior

The views use the shared dashboard layout, Bootstrap 5 controls, Tabler icons,
existing theme tokens, and the dashboard's state-driven modal pattern. No new
page shell or JavaScript widget framework was introduced.

Minimum-width data tables remain horizontally scrollable, but their containing
cards use `contain: inline-size` so table intrinsic width cannot expand the
mobile layout viewport. The same correction was applied to the existing user
and role tables after the strengthened QA assertion exposed their identical
behavior. At 390px, the CSS and document viewports remain 390px and the page
content does not overlap the fixed dashboard shell.

The dashboard QA harness now captures and validates:

- catalog desktop and mobile;
- inventory desktop and mobile;
- exact expected viewport width;
- no document-level horizontal overflow;
- contained table scrollers;
- expected Livewire component counts;
- catalog/category controls and inventory history;
- Commerce sidebar navigation;
- loader completion.

Captures and machine-readable diagnostics are stored under
`.docs/dev/dashboard-qa/`.

## Files added

- `app/Modules/Commerce/Catalog/Livewire/Admin/ProductCatalog.php`
- `app/Modules/Commerce/Catalog/Livewire/Admin/ProductCategoryManager.php`
- `app/Modules/Commerce/Catalog/Livewire/Forms/ProductForm.php`
- `app/Modules/Commerce/Catalog/Livewire/Forms/CategoryForm.php`
- `app/Modules/Commerce/Inventory/Livewire/Admin/InventoryManager.php`
- `app/Modules/Commerce/Inventory/Livewire/Forms/StockAdjustmentForm.php`
- `app/Modules/Commerce/Http/Controllers/Admin/CatalogController.php`
- `app/Modules/Commerce/Http/Controllers/Admin/InventoryController.php`
- `app/Modules/Commerce/Resources/views/admin/catalog/index.blade.php`
- `app/Modules/Commerce/Resources/views/admin/inventory/index.blade.php`
- `app/Modules/Commerce/Resources/views/livewire/admin/product-catalog.blade.php`
- `app/Modules/Commerce/Resources/views/livewire/admin/product-category-manager.blade.php`
- `app/Modules/Commerce/Resources/views/livewire/admin/inventory-manager.blade.php`
- `app/Modules/Commerce/Support/ScaledDecimal.php`
- `tests/Feature/Commerce/CommerceAdminInterfaceTest.php`
- `.docs/PointOfSale/commerce-catalog-inventory-admin-implementation.md`
- four Commerce desktop/mobile QA captures under `.docs/dev/dashboard-qa/`
- four access-management desktop/mobile captures produced by the strengthened
  table-containment QA gate

## Files changed

- `app/Modules/Commerce/Catalog/Services/ProductService.php`
- `app/Modules/Commerce/CommerceServiceProvider.php`
- `app/Modules/Commerce/Config/commerce.php`
- `app/Modules/Commerce/Routes/admin.php`
- `.env.example`
- `resources/css/aureon-dashboard.css`
- `resources/views/layouts/partials/sidebar-admin.blade.php`
- `resources/views/livewire/admin/user-management.blade.php`
- `resources/views/livewire/admin/roles-and-permissions-manager.blade.php`
- `scripts/qa-dashboard.mjs`
- `README.md`
- dashboard QA diagnostics and refreshed captures

The untracked `.docs/model-ulid/` reference supplied before this phase was not
modified or included in the implementation.

## Verification

Focused Commerce suite:

```text
php artisan test tests/Feature/Commerce
PASS  40 tests, 486 assertions
```

Complete application regression:

```text
php artisan test
PASS  160 tests, 1099 assertions
```

Static and presentation checks:

```text
vendor/bin/pint app/Modules/Commerce tests/Feature/Commerce/CommerceAdminInterfaceTest.php
php artisan view:cache
php artisan route:list --name=commerce.admin
node --check scripts/qa-dashboard.mjs
npm.cmd run build
npm.cmd run qa:dashboard
```

All passed. Vite retained its pre-existing unresolved-at-build-time absolute
asset URL notices; the static-copy pipeline published those assets and browser
QA confirmed they render. PHP emitted the local Imagick/ImageMagick 1808/1810
version warning; no image or test operation failed.

An independent temporary PHP script bootstrapped Composer and Laravel, opened
a rollback-only database transaction, resolved the seeded administrator,
created and published a product at exactly `125050` minor units, posted an
opening adjustment of seven units, and verified both routes and all three
Livewire aliases. Its observed result was:

```json
{
    "routes": {"catalog": true, "inventory": true},
    "livewire_aliases": {
        "product_catalog": true,
        "category_manager": true,
        "inventory_manager": true
    },
    "product": {"status": "published", "price_minor": 125050, "stock_balance": 7},
    "movement": {"delta": 7, "before": 0, "after": 7}
}
```

The transaction was rolled back and the temporary script was deleted.

## Deferred boundaries

The following remain intentionally deferred to later Commerce phases:

- public catalog and single-product pages;
- storefront cart, checkout, signed order tracking, and notifications;
- customer and order administration screens;
- POS register setup, till UI, terminal, receipt, and held-sale workflows;
- product deletion/restoration and category deletion rules;
- multi-location inventory, purchase orders, reservations, and transfers;
- general-purpose CMS media library browsing.

The existing services, policies, typed data objects, ULIDs, route files, and
module folder structure remain the extension points for those phases.
