# Commerce Cashier Sales History Implementation

## Status

Implemented and verified on `feature/commerce-cashier-sales-history`.

## Purpose

Provide each POS operator with a readable, responsive history of sales they
personally completed without widening access to the global order-management
surface. The same module-owned Livewire component is designed for the full-width
POS terminal, the existing role dashboard used by cashier accounts, and the
Commerce administration overview when its current operator also uses POS.

## Verified Context

- Cashier capability is granted by `access-pos`; cashier is not a separate
  `UserType` and must not introduce a parallel account or dashboard hierarchy.
- A completed POS sale is an `orders` record with `channel=pos`,
  `status=completed`, immutable `cashier_id`, `till_session_id`, and
  `register_id` ownership, plus immutable customer and total snapshots.
- Held orders and cancelled records are not sales and must never contribute to
  cashier history totals.
- The existing `commerce.pos.receipts.show` route already permits the owning
  cashier and authorized supervisors. History links reuse that boundary rather
  than exposing order-management details.
- The application already uses Livewire 4, Bootstrap pagination, integer minor
  currency units, theme tokens, and permission checks on every hydrated
  component request.

## Approved Design

### Projection

`CashierSalesHistory` always filters by the authenticated actor's immutable
`cashier_id`, completed status, POS channel, and a non-null business order
number. The active tab additionally filters by the actor's current open till.
The history tab includes all of the actor's sessions and supports all-time,
today, 7-day, 30-day, and 90-day ranges.

The projection shows only operationally useful snapshots:

- receipt/order number and completion time;
- customer display name or walk-in fallback;
- line count, completed tender method labels, and register name;
- order total and a protected receipt action;
- transaction count, gross sales, and average sale for the active filter.

Email, phone, payment references, payment metadata, cost price, and internal
notes are intentionally excluded.

### Livewire Contract

- `surface` and active tab state are locked against client mutation.
- `boot()` reauthorizes `access-pos` on the initial and every subsequent
  Livewire request.
- Pagination uses a named paginator with URL tracking disabled so a nested POS
  component does not rewrite the terminal URL.
- Filter changes canonicalize input, reset pagination, and invalidate computed
  projections.
- The terminal renders the component in a collapsed disclosure below held
  orders. Dashboard surfaces render a standard Aureon panel.

This follows the current Livewire 4 guidance for
[pagination](https://livewire.laravel.com/docs/4.x/pagination),
[nested components](https://livewire.laravel.com/docs/4.x/nesting), and
[component security](https://livewire.laravel.com/docs/4.x/security).

### Configuration

```dotenv
COMMERCE_POS_SALES_HISTORY_TERMINAL=true
COMMERCE_POS_SALES_HISTORY_DASHBOARD=true
COMMERCE_POS_SALES_HISTORY_PER_PAGE=8
```

The terminal and dashboard mounts are independently switchable. The bounded
page size is shared by both surfaces. Disabling presentation does not alter or
delete order history.

### Storefront Links

The public `commerce.storefront.catalog.index` route is exposed as a new-tab
shortcut from the Commerce overview, POS top bar, admin Commerce sidebar,
permission-aware role sidebar, and shared role-dashboard quick links. It remains
a public route and receives no synthetic staff permission.

## Security And Data Invariants

1. Every query derives the user identifier from `auth()->user()` on the server.
2. No public property accepts a cashier, till, order, register, or monetary ID.
3. Another cashier's sale cannot enter counts, totals, pagination, or markup.
4. Active-session results require the authenticated cashier's currently open
   till; an absent till produces an explicit empty state.
5. History is read-only. Receipt authorization remains independently enforced
   by `ReceiptController`.
6. All money is aggregated as integer minor units and formatted only at the
   presentation edge.

## Implemented Files

- `app/Modules/Commerce/PointOfSale/Livewire/CashierSalesHistory.php`
- `app/Modules/Commerce/Resources/views/livewire/pos/cashier-sales-history.blade.php`
- `app/Modules/Commerce/Resources/views/livewire/pos/partials/cashier-sales-history-content.blade.php`
- `app/Modules/Commerce/Resources/css/cashier-sales-history.css`
- `tests/Feature/Commerce/CommerceCashierSalesHistoryTest.php`

Registration, configuration, and presentation integration:

- `app/Modules/Commerce/CommerceServiceProvider.php`
- `app/Modules/Commerce/Config/commerce.php`
- `app/Modules/Commerce/Resources/views/layouts/pos.blade.php`
- `app/Modules/Commerce/Resources/views/livewire/pos/terminal.blade.php`
- `app/Modules/Commerce/Resources/views/admin/dashboard/index.blade.php`
- `resources/views/dashboards/partials/role-overview.blade.php`
- `resources/views/dashboards/content-manager/index.blade.php`
- `resources/views/dashboards/editor/index.blade.php`
- `resources/views/dashboards/viewer/index.blade.php`
- `resources/views/layouts/partials/sidebar-admin.blade.php`
- `resources/views/layouts/partials/sidebar-role.blade.php`
- `vite.config.js`, `.env.example`, and `README.md`

Documentation and repeatable browser evidence:

- `.docs/PointOfSale/pos-module-plan.md`
- `.docs/PointOfSale/AdoptionReadiness/commerce-adoption-guide.md`
- `.docs/PointOfSale/AdoptionReadiness/commerce-operations-handbook.md`
- `.docs/PointOfSale/AdoptionReadiness/commerce-screenshot-manifest.md`
- `scripts/qa-commerce-pos.mjs`
- `scripts/qa-commerce-dashboard.mjs`
- `.docs/dev/commerce-pos-qa/` and `.docs/dev/commerce-dashboard-qa/`

## Acceptance Evidence

Completed on 2026-09-06:

- `CommerceCashierSalesHistoryTest`: 6 tests and 49 assertions passed. Coverage
  includes authorization, immutable cashier scope, active/history tabs, range
  canonicalization, named bounded pagination, no-open-till behavior,
  independent presentation switches, receipt links, dashboard integration, and
  storefront shortcuts.
- Focused POS, Commerce-dashboard, and role-dashboard regression: 29 tests and
  211 assertions passed.
- Full Commerce regression: 134 tests and 3,493 assertions passed.
- Full application regression: 344 tests and 5,639 assertions passed.
- Pint completed successfully; PHP syntax and both changed Node QA scripts
  passed their syntax checks.
- The production Vite build completed successfully and emitted the dedicated
  `cashier-sales-history` stylesheet entry.
- Blade views compiled successfully with `php artisan view:cache`.
- POS browser QA passed 11 diagnostic cases; Commerce-dashboard browser QA
  passed four. Both runs recorded zero runtime errors, network errors, duplicate
  identifiers, accessibility-label failures, viewport overflows, component
  overflows, or failed assertions.
- Dedicated visual evidence:
  `.docs/dev/commerce-pos-qa/cashier-sales-history-desktop-light.png` and
  `.docs/dev/commerce-pos-qa/cashier-sales-history-mobile-dark.png`.

The feature adds no migration, table, model, route, seeder, role, or permission.
It reads the existing immutable POS order projection and reuses the established
receipt authorization contract. The local PHP runtime emitted its pre-existing
Imagick warning because Imagick was compiled against ImageMagick 1808 while
1810 is loaded; it did not affect the test or browser results.
