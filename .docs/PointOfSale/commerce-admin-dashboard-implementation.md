# Commerce Administration Dashboard Implementation

**Status:** Complete. Phase 4 Chunk 4.1 gate passed.

**Branch:** `feature/commerce-admin-dashboard`

**Baseline:** `b2699ab`

**Completed:** 2026-07-19

## Objective

Provide a dedicated Commerce administration landing page backed by real module
data. An authorized operator must be able to understand current sales,
fulfillment pressure, inventory exceptions, customer reach, and till activity
without treating the host application's sample administration dashboard as a
Commerce report.

This delivery is read-only. It does not change pricing, ordering, payment,
inventory, customer, register, till, or POS transaction behavior.

## Delivered Route and Access Contract

- `GET /admin/commerce` is named `commerce.admin.dashboard` and uses `web`,
  `auth`, `verified`, and `permission:view-commerce-dashboard` middleware.
- `view-commerce-dashboard` is part of the code-owned `CommercePermission` and
  shared `CmsPermission` catalogues. The idempotent `RoleSeeder` grants the
  complete catalogue to `system-admin`; custom roles receive it only through
  explicit composition.
- The active system-administrator `Gate::before` contract remains the stale
  database safety net without weakening inactive-account or non-admin checks.
- `Commerce overview` is the first Commerce sidebar destination. The Commerce
  group is absent when `commerce.enabled` is false.
- `CommerceServiceProvider` does not register routes, views, or module resources
  when disabled. The disabled route surface returns 404 instead of leaving
  broken host links.
- Every quick action, KPI destination, queue badge, table action, and panel link
  is evaluated against its own downstream permission. Dashboard access alone
  does not disclose an unusable order, inventory, catalog, customer, POS, or
  till link.

## Reporting Architecture

`CommerceDashboardController` validates the optional `range` query parameter
against `7`, `30`, and `90`, resolves the enum, and requests one immutable
snapshot. Invalid values redirect back with a validation error.

`CommerceDashboardService` is the sole aggregate-query owner. It returns
`CommerceDashboardSnapshot` plus narrow typed projections for series points,
channels, payment methods, order statuses, recent orders, stock alerts, and
active tills. Controllers and Blade do not perform Eloquent reporting queries,
and transaction services remain unchanged.

The service uses aggregate SQL and bounded display queries:

- recent orders are limited to eight;
- stock exceptions are ordered by urgency and limited to eight;
- active tills are limited to six;
- only displayed till relationships are eager loaded;
- daily series are zero-filled to keep chart dimensions stable;
- no aggregate cache or reporting table is introduced.

Existing indexes cover `orders.placed_at`, order channel/status, payment status
and `payments.paid_at`, till register/cashier status, till opening time, product
stock ownership, and stock on-hand lookup. No unmeasured migration was added.
The `DATE()` grouping contract is supported by the SQLite baseline and the
target MySQL/MariaDB family; production query plans should still be measured as
adopter data volume grows.

## Metric Semantics

| Projection | Definition |
| --- | --- |
| Payments collected | Sum of completed payment amounts by `paid_at` in the selected inclusive period |
| Order value | Sum of numbered, placed, non-held, non-cancelled order totals in the period |
| Orders received | Count of numbered, placed, non-held orders in the period, including later cancellations |
| Average order value | Integer division of contributing non-cancelled order value by contributing order count |
| Orders requiring action | Current web orders in pending, confirmed, processing, or ready state |
| Stock alerts | Tracked stock at or below threshold, split into low (`on_hand > 0`) and out (`on_hand <= 0`) |
| Open tills | Open sessions with no closure timestamp |
| Active customers | Non-archived reusable customer rows |

All monetary values remain integer minor units through storage and aggregation.
Formatting occurs only at the view boundary through `MoneyFormatter`. Held POS
orders are not counted as sales; cancellations do not contribute order value;
an order being placed does not imply collected payment.

## Interface Delivery

The module-owned page reuses the existing dashboard layout, loader, theme
controller, sidebar, footer, cards, tables, badges, and typography. It adds:

1. A compact Aureon Commerce band with permission-aware Orders, Inventory,
   Catalog, POS, and Tills actions.
2. Eight stable KPI cards with operational labels and authorized destinations.
3. A web/POS Chart.js collection trend for the selected 7, 30, or 90 days.
4. An accessible daily-data table containing every chart point.
5. A current web-order action queue in lifecycle order.
6. A bounded recent-order table with immutable customer and order snapshots.
7. A low/out-of-stock exception panel ordered by on-hand urgency.
8. Channel and completed-payment method summaries.
9. A current till panel with register, cashier, opening time, and expected cash.

The date range is a URL-backed segmented navigation, so filtered views can be
bookmarked without adding Livewire state. Dedicated Vite CSS and vanilla
JavaScript entries preserve module ownership. The chart re-renders when Aureon
theme settings change and disables animation under reduced motion.

## Verification

- Feature-owned dashboard and disabled-module suites: 6 tests, 60 assertions.
- Complete uncached Laravel regression: 193 tests, 1,549 assertions.
- PHP syntax, JavaScript syntax, Pint, route discovery, view compilation,
  configuration caching, route caching, and the Vite production build pass.
- Vite 7.3.6 transforms 68 modules and emits dedicated Commerce dashboard CSS
  and JavaScript bundles.
- Authenticated Chromium QA passes desktop `1440`, laptop `1080`, tablet `820`,
  and mobile `390` layouts across light and dark modes.
- The mobile 90-day case runs with reduced motion. All cases verify eight KPI
  cards, six panels, chart pixel output, exact fallback row count, permission
  links, accessible names, unique IDs, loader settlement, text containment,
  stable card dimensions, and zero document/sidebar overlap.
- Browser diagnostics report zero runtime errors and zero failed requests.

Evidence is stored under `.docs/dev/commerce-dashboard-qa/`. The harness is
available as `npm.cmd run qa:commerce-dashboard` and accepts the established
`AUREON_QA_URL`, `AUREON_QA_EMAIL`, `AUREON_QA_PASSWORD`, and
`AUREON_QA_PORT` overrides.

## File Ownership

```text
app/Modules/Commerce/Http/Controllers/Admin/CommerceDashboardController.php
app/Modules/Commerce/Reporting/Data/*.php
app/Modules/Commerce/Reporting/Enums/CommerceDashboardRange.php
app/Modules/Commerce/Reporting/Services/CommerceDashboardService.php
app/Modules/Commerce/Resources/views/admin/dashboard/index.blade.php
app/Modules/Commerce/Resources/css/admin-dashboard.css
app/Modules/Commerce/Resources/js/admin-dashboard.js
app/Modules/Commerce/Routes/admin.php
app/Modules/Commerce/Support/CommercePermission.php
app/Modules/Commerce/CommerceServiceProvider.php
tests/Feature/Commerce/CommerceDashboardTest.php
tests/Feature/Commerce/CommerceDisabledModuleTest.php
scripts/qa-commerce-dashboard.mjs
```

Shared shell integration is limited to the admin sidebar, a layout style stack,
Vite entry registration, the package QA command, and the README feature ledger.

## Deferred Boundaries

Comparison periods, profit and cost-of-goods reporting, exports, warehouse
analytics, dashboard caching, scheduled digests, and a database-backed
notification center remain later work. Phase 4 Chunk 4.2 next hardens the
existing order-summary and POS-receipt adapters; it must not reopen this
dashboard's read-only query or permission contracts without measured evidence.
