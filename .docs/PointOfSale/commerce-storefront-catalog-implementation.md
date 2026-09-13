# Commerce Storefront Catalog Implementation

## Record status

- **Module:** Commerce
- **Master phase:** 2, storefront and web ordering
- **Increment:** Public shell, catalog discovery, and product detail
- **Branch:** `feature/pos-product-management`
- **Implementation date:** 2026-07-17
- **Status:** Implemented and verified
- **Predecessor commit:** `59cb353 feat(commerce): add catalog and inventory administration`

## Objective

Deliver the first public Commerce vertical slice without introducing cart or
checkout mutations prematurely. The increment must provide an Aureon-branded,
theme-responsive storefront shell, a Livewire catalog with URL-addressable
discovery controls, and a server-rendered product detail page backed only by
published catalog data.

The same increment completes the master Phase 1 demonstration-data gap with an
optional, module-owned, idempotent seeder. It supplies ten representative
products and coherent sample records across every current Commerce table while
remaining excluded from the host application's default seeding path.

## Implemented scope

1. Reconciled `pos-module-plan.md` and the root feature ledger with the actual
   implementation baseline and active master phase.
2. Added module-owned catalog and transaction demo seeders beneath
   `app/Modules/Commerce/Database/Seeders`.
3. Seeded deterministic categories, ten products, stock projections, opening
   movements, media galleries, customers, registers, till history, web/POS
   orders, order items, and payments without duplicating records on rerun.
4. Added reusable storefront visibility/effective-price query scopes and exact
   integer-money presentation support.
5. Activated public storefront routes with a thin controller and slug-based
   product resolution.
6. Added a Livewire 4 catalog browser using computed data, Bootstrap pagination,
   debounced search, URL-synchronized filters, and read-only published queries.
7. Built module-owned Blade layouts, desktop/tablet/mobile headers, mobile
   navigation, footer, theme controller, product cards, catalog, product detail,
   and empty states.
8. Added module-owned storefront CSS and JavaScript as Vite entry points while
   retaining the centralized Aureon Bootstrap, brand, font, theme, and icon
   assets.
9. Added feature, Livewire, seeder-idempotency, route-privacy, asset-build, and
   responsive browser QA coverage.

## Guardrails

- Cart, checkout, order placement UI, signed confirmation, and tracking remain
  outside this vertical slice.
- Public queries expose only published products assigned to active categories,
  or published uncategorized products.
- Draft, scheduled, archived, soft-deleted, and inactive-category products must
  return 404 from public detail URLs and never appear in catalog results.
- Product prices and filters operate on integer minor units; display formatting
  must not convert persisted values through binary floating point.
- Public product routes use readable slugs. Administrative route identity
  remains ULID-based.
- Seeder execution is explicit and must not be added to `DatabaseSeeder`.
- Seeder reruns must preserve one deterministic demonstration graph without
  duplicating products, media, stock movements, orders, or payments.
- All storefront resources remain inside the Commerce module except the minimal
  Vite input registration required by the host build pipeline.

## Demonstration data contract

`CommerceDemoSeeder` is an explicit, optional aggregate seeder. It is not
referenced by the host `DatabaseSeeder` and therefore cannot place sample
transactions into a normal installation accidentally. Run it only after the
host account seeder has created at least one user:

```powershell
php artisan db:seed --class="App\Modules\Commerce\Database\Seeders\CommerceDemoSeeder" --no-interaction
```

The aggregate invokes three focused seeders in dependency order:

- `CatalogDemoSeeder`: six active categories, ten published products, stock
  projections, opening movements, category imagery, and product galleries;
- `CustomerDemoSeeder`: three reusable customer profiles;
- `TransactionDemoSeeder`: two registers, one balanced closed till session,
  one web order, one completed POS order with two lines, and split cash/mobile
  payments created through the production domain services.

Stable slugs, SKUs, emails, register codes, and internal transaction markers
make reruns idempotent. Existing stock history is not reset or rewritten.

## Storefront contract

- `GET /shop` renders the public catalog shell and Livewire discovery surface.
- `GET /shop/products/{product:slug}` renders a published product detail page.
- Public visibility requires an active, published product and either no
  category or an active category. Draft, scheduled, archived, soft-deleted,
  and inactive-category products remain private.
- Search, category, availability, minimum/maximum price, and sort values are
  URL synchronized. Price filtering and ordering use one portable SQL `CASE`
  expression that matches the model's effective-price accessor.
- Money stays in integer minor units through persistence, filtering, sorting,
  calculation, and display formatting.
- The shell uses the centralized institution resolver, brand assets, loader,
  Bootstrap 5, Lucide icons, theme state, and light/dark logo variants.
- Desktop, tablet, and mobile headers are distinct responsive states. Mobile
  navigation is an offcanvas with a right-aligned close action.
- Product cards and detail pages use module-owned demonstration media through
  Spatie Media Library. The detail gallery has an accessible primary-image
  interaction and responsive related-product recommendations.

## File inventory

Updated integration files:

- `README.md`
- `.docs/PointOfSale/pos-module-plan.md`
- `app/Modules/Commerce/Catalog/Models/Product.php`
- `app/Modules/Commerce/CommerceServiceProvider.php`
- `app/Modules/Commerce/Routes/storefront.php`
- `package.json`
- `vite.config.js`

Added module implementation:

- `app/Modules/Commerce/Database/Seeders/{CommerceDemoSeeder,CatalogDemoSeeder,CustomerDemoSeeder,TransactionDemoSeeder}.php`
- `app/Modules/Commerce/Storefront/Http/Controllers/CatalogController.php`
- `app/Modules/Commerce/Storefront/Livewire/CatalogBrowser.php`
- `app/Modules/Commerce/Support/MoneyFormatter.php`
- `app/Modules/Commerce/Resources/views/layouts/storefront.blade.php`
- `app/Modules/Commerce/Resources/views/storefront/partials/*.blade.php`
- `app/Modules/Commerce/Resources/views/storefront/catalog/*.blade.php`
- `app/Modules/Commerce/Resources/views/livewire/storefront/catalog-browser.blade.php`
- `app/Modules/Commerce/Resources/css/storefront.css`
- `app/Modules/Commerce/Resources/js/storefront.js`
- `app/Modules/Commerce/Resources/demo/products/*`

Added verification assets:

- `tests/Feature/Commerce/CommerceDemoSeederTest.php`
- `tests/Feature/Commerce/CommerceStorefrontTest.php`
- `scripts/qa-commerce.mjs`
- `.docs/dev/commerce-qa/*`

## Verification results

| Gate | Result |
| --- | --- |
| `vendor\bin\pint.bat --test app\Modules\Commerce ...` | Passed |
| `composer validate --no-check-publish` | Valid |
| `php artisan view:cache` | Blade templates cached successfully |
| `php artisan route:list --name=commerce.storefront` | Two expected public routes registered |
| `node --check app/Modules/Commerce/Resources/js/storefront.js` | Passed |
| `npm.cmd run build` | Vite production build passed; 64 modules transformed |
| `php artisan test tests/Feature/Commerce` | 45 tests, 658 assertions passed |
| `php artisan test` | 165 tests, 1,271 assertions passed |
| Temporary autoloaded Laravel runtime probe | Routes, slug binding, Livewire alias, ten visible products, effective-price order, and money format passed |
| `npm.cmd run qa:commerce` | Six browser diagnostic checkpoints passed with five desktop/tablet/mobile screenshots |

Browser QA verifies the three responsive header states, no horizontal overflow,
loader settlement, complete image loading, product card rendering, Livewire
search and URL synchronization, mobile navigation and close alignment, theme
persistence, light/dark logo selection, product gallery interaction, and the
absence of browser runtime or HTTP errors. Evidence is stored under
`.docs/dev/commerce-qa`.

The environment emits a pre-existing Imagick compile/runtime version warning
(`1808` compiled versus `1810` loaded). It did not affect media, PDF, seeder, or
test behavior in this increment.

## Deferred boundary

The next Phase 2 increment owns the session-backed cart, server-authoritative
quantity validation, checkout, guest order placement UI, confirmation, and
tracking. This increment intentionally creates no public mutation endpoint and
does not weaken the already-tested `OrderService`, stock-locking, or payment
contracts.
