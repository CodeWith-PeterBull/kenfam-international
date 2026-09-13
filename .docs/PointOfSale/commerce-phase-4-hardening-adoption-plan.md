# Commerce Phase 4 Hardening and Adoption Plan

**Status:** In progress. Chunks 4.1 through 4.3 are implemented. The Chunk 4.4
adopter handbook is delivered; fixture expansion is intentionally deferred and
Chunk 4.5 remains pending.

**Baseline branch:** `feature/laravel-aureon-base-engine`

**Current implementation baseline:** `c7aa6c9`

**Updated:** 2026-08-18

## 1. Objective

Phase 4 turns the operational Commerce engine delivered by Phases 1 through 3
into a clearly monitored, independently adoptable module. The first delivery is
a Commerce administration dashboard. Later chunks harden documents, introduce
activity-specific operational notifications, complete adoption material, and
close the release with one evidence-backed verification gate.

This phase must not reopen the transaction rules already proven by the catalog,
storefront, ordering, inventory, customer, payment, register, till, and POS
services.

## 2. Reconciled Baseline

| Concern | Current factual state | Phase 4 action |
| --- | --- | --- |
| General administrator dashboard | Controller-owned sample content only | Add a separate module-owned Commerce overview backed by real Commerce queries. Do not replace the host dashboard. |
| Order summaries | `OrderManager::statistics()` exposes basic global totals | Centralize dashboard-grade period and channel summaries without making the Livewire manager a reporting service. |
| Low stock | Stock thresholds, `Stock::isLow()`, inventory filters, badges, and counts already work | Reuse the same threshold semantics on the dashboard and add transition-based alerts. |
| Order PDF | `OrderDocumentService` already streams/downloads portrait or landscape summaries through `RendersPdfReports` | Harden view-data boundaries, privacy, orientation, filename, and multi-page tests. |
| POS receipt PDF | `PosReceiptService` already streams protected portrait receipts | Add adapter parity where useful and retain the verified light-paper print contract. |
| Customer mail | `OrderConfirmationNotification` is queued after committed storefront checkout | Keep it dedicated; do not replace it with a generic Commerce message. |
| Operational notifications | Seven explicit after-commit paths and a permission-aware recipient resolver are implemented | Retain focused queued mail and dashboard indicators; defer preferences and database inboxes. |
| In-app notification storage | No Laravel `notifications` table or notification center exists | Do not silently introduce a global notification center in the first Phase 4 increment. Use dashboard indicators and queued mail. |
| Fixtures | Optional idempotent Commerce seeders create six categories, ten published tracked in-stock products, three customers, two registers, one pending web order, one completed split-tender POS sale, and one balanced closed till | Document the current graph factually and keep it out of production/default seeding. Low/out/untracked stock, open-till, and variance fixtures remain deferred by the Phase 4.4 scope decision. |
| Browser evidence | Storefront and POS harnesses cover responsive layouts, themes, interactions, receipts, and print media | Add Commerce-dashboard and final cross-surface accessibility/release checks. |

## 3. Non-Negotiable Invariants

1. Monetary aggregation uses integer minor units. No reporting or notification
   path may introduce floats.
2. Dashboard data is read-only and built from existing source-of-truth tables.
   No aggregate persistence table is justified for the initial release.
3. Held POS orders are not sales. Cancelled orders do not contribute to active
   order value. Collected money is derived from completed payments and
   `paid_at`, not from an order merely being placed.
4. Low stock means a tracked product has `on_hand > 0` and
   `on_hand <= low_stock_threshold`. Out of stock means tracked and
   `on_hand <= 0`. Untracked products belong to neither group.
5. Every route, card, quick action, and recipient query is permission scoped.
   The active system-administrator super-user contract remains intact.
6. Notifications are dispatched only after their database transaction commits.
   Payloads carry stable ULIDs or scalar snapshots rather than serialized model
   graphs.
7. One operational activity has one named notification class and one focused
   message. A generic `CommerceActivityNotification` is explicitly rejected.
8. Public order links remain temporary signed URLs. Staff document links remain
   authenticated and policy protected.
9. The optional module can be adopted or disabled without leaving broken route
   calls in host navigation.
10. Existing storefront, POS, dark-mode, print, and transaction tests remain
    regression gates for every Phase 4 chunk.

## 4. Ordered Delivery Map

| Order | Chunk | Suggested branch | Primary output | Release importance |
| --- | --- | --- | --- | --- |
| 4.1 | Commerce administration dashboard | `feature/commerce-admin-dashboard` | Real cross-module statistics, trends, action queues, low-stock panel, and authorized links | Immediate and highest |
| 4.2 | Document adapter hardening | `feature/commerce-document-adapters` | Stable order/receipt view data, adapter parity, privacy and orientation tests | High, short closeout |
| 4.3 | Operational events and notifications | `feature/commerce-operational-notifications` | Dedicated queued messages for approved order, stock, and till activities | High |
| 4.4 | Fixtures and adopter handbook | `feature/commerce-adoption-readiness` | Terms, scenarios, setup, screenshots, enable/omit, and extension guidance | Handbook delivered; fixture expansion deferred |
| 4.5 | Final hardening and release evidence | `test/commerce-release-gate` | Migration, regression, accessibility, browser, theme, print, and deployment evidence | Final gate |

Each chunk should be reviewed and committed independently. Chunk 4.1 is the
only implementation that should begin before the remainder of this plan is
accepted.

## 5. Chunk 4.1: Commerce Administration Dashboard

### 5.1 Route and authorization

- Add `view-commerce-dashboard` to `CommercePermission` and its code-owned
  catalogue.
- Grant it to `system-admin` through the existing idempotent permission seeder.
  Other roles receive it only through explicit composition.
- Add `GET /admin/commerce` named `commerce.admin.dashboard` inside the existing
  `web`, `auth`, and `verified` group with the new permission middleware.
- Put `Commerce overview` first in the Commerce sidebar group and keep every
  downstream link wrapped in its own existing permission check.
- When `commerce.enabled` is false, do not load Commerce routes and do not render
  Commerce navigation. The plan must account for route absence before the
  enable/omit guide is declared complete.

### 5.2 Module-owned architecture

```text
app/Modules/Commerce/
|-- Http/Controllers/Admin/CommerceDashboardController.php
|-- Reporting/
|   |-- Data/CommerceDashboardSnapshot.php
|   |-- Data/SalesSeriesPoint.php
|   |-- Enums/CommerceDashboardRange.php
|   `-- Services/CommerceDashboardService.php
|-- Resources/views/admin/dashboard/index.blade.php
|-- Routes/admin.php
`-- Support/CommercePermission.php
```

The controller validates a small GET range (`7`, `30`, or `90` days), authorizes
the surface, asks one reporting service for an immutable snapshot, and renders
the view. Livewire is not needed for a read-only page whose filters can remain
shareable query parameters.

The reporting service owns all query semantics. Controllers and Blade files do
not build ad hoc Eloquent aggregates, and existing transaction services do not
gain reporting responsibilities.

### 5.3 Metric contract

| Metric | Exact definition | Destination link |
| --- | --- | --- |
| Payments collected | Sum of completed `payments.amount_minor` whose `paid_at` falls in the selected period | Order workspace; partial payments mean a paid-only order filter is not equivalent |
| Order value | Sum of `orders.total_minor` for numbered, placed, non-held, non-cancelled orders placed in the period | Order workspace |
| Orders received | Count of numbered orders placed in the period, split by web and POS | Order workspace |
| Average order value | Non-cancelled order value divided using integer-safe presentation by its contributing order count | Order workspace |
| Orders requiring action | Web orders in pending, confirmed, processing, or ready states | Actionable order filter |
| Low stock | Tracked stocks above zero and at or below their threshold | Inventory `low` filter when it is the only exception state |
| Out of stock | Tracked stocks at or below zero | Inventory `out` filter when it is the only exception state; mixed alerts open all inventory |
| Active tills | Till sessions whose status is open | Till sessions |
| Active customers | Non-archived reusable customer records | Customer directory |

All period boundaries use the application timezone and are converted
consistently for database comparison. Trend queries group completed payment
amounts by calendar day and order channel. Empty days are zero-filled so chart
dimensions do not shift.

### 5.4 Page composition

1. A compact Commerce welcome band with the selected date range and authorized
   quick actions for orders, inventory, catalog, POS, and tills.
2. Stable KPI cards for collected payments, order value, order count, average
   order value, actionable orders, low/out stock, and open tills.
3. A responsive Chart.js sales trend with web/POS series, backed by an
   accessible tabular summary rather than a canvas-only experience.
4. An order-channel and payment-method summary using explicit labels and exact
   amounts.
5. A recent-orders table with business number, channel, customer, total,
   payment state, fulfillment state, time, and authorized detail action.
6. A low-stock panel ordered by urgency with on-hand, threshold, SKU, and a
   direct inventory action.
7. An active-till panel showing register, cashier, opening time, expected cash,
   and a till-management action.

The page reuses the dashboard shell, theme controller, loader, cards, tables,
badges, typography, and spacing. It must not nest cards, use decorative landing
page composition, or expose links the current user cannot open.

### 5.5 Query and performance rules

- Use aggregate queries and bounded recent lists; do not hydrate every order or
  payment to calculate totals.
- Eager-load only relationships displayed by recent-order and till panels.
- Audit existing indexes with actual query plans before adding a migration.
  The current order channel/status/time, payment `paid_at`, till status, and
  stock relationship indexes are the starting point.
- Keep the first release uncached. Add caching only after measurements show a
  need and invalidation ownership is explicit.
- Verify SQLite and MySQL/MariaDB-compatible date grouping because local and
  Hostinger-style deployments use different database engines.

### 5.6 Tests and gate

- Feature tests: guest redirect, unauthorized 403, authorized 200, active
  system-admin override, sidebar visibility, and disabled-module behavior.
- Service tests: exact minor-unit totals, channel splits, status exclusions,
  date boundaries, empty periods, low/out/untracked stock, open tills, and
  average-order calculations.
- Interface tests: stable card count, valid links, no duplicate IDs, accessible
  chart fallback, empty states, and safe money formatting.
- Browser QA: desktop, laptop, tablet, and mobile in light/dark themes with no
  runtime errors, failed assets, overflow, clipped labels, or inaccessible
  controls.

**Chunk 4.1 gate:** An authorized administrator can understand current Commerce
performance and operational exceptions from `/admin/commerce`, and every value
links to the correct permission-protected working surface.

**Gate status (2026-07-19): Passed.** The permission-scoped overview now uses a
module-owned immutable snapshot service for exact 7/30/90-day payment, order,
channel, stock, customer, and till projections. It includes six operational
panels, eight stable KPI cards, a Chart.js trend with a complete tabular
fallback, permission-filtered links, module-disable behavior, focused feature
coverage, and authenticated Chromium evidence across four responsive
light/dark states. The completed boundary is recorded in
`commerce-admin-dashboard-implementation.md`.

## 6. Chunk 4.2: Receipt and Order-Summary Adapters

This chunk hardens existing services; it does not create a second PDF engine.

- Retain `RendersPdfReports`, `OrderDocumentService`, `PosReceiptService`, and
  the institutional profile resolver.
- Map Eloquent aggregates into immutable, document-specific view data so report
  templates cannot accidentally expose internal notes, cost price, raw payment
  metadata, credential data, or integer database identifiers.
- Keep order summaries portrait/landscape capable and POS receipts portrait by
  design. Reject unsupported orientations explicitly.
- Give both adapters clear stream/download methods where their callers need
  parity, sanitized deterministic filenames, safe local branding, generated-at
  context, and named generators.
- Test line totals, tax, discount, delivery, payment/change, long names,
  multi-page output, both order orientations, receipt portrait output, policy
  boundaries, cache headers, and privacy exclusions.
- Preserve the browser receipt print rule proven at `b2699ab`: dark screen state
  must still produce a white paper canvas and readable table.

**Chunk 4.2 gate:** The two concrete Commerce document adapters are thin,
privacy-safe consumers of the shared PDF engine and produce stable output for
web-order and POS scenarios.

**Automated gate status (2026-07-19): Passed.** Immutable order and receipt
projections now exclude internal notes, cost snapshots, customer contact fields,
raw payment metadata, ULIDs, and database identifiers by construction. The two
adapters provide safe deterministic filenames and stream/download parity; order
summaries render portrait and landscape, receipt PDFs enforce portrait, and
multi-page output is proven. Register-owned browser printing adds config-resolved
drivers, manual/post-sale modes, optional printer labels, and 58/80 mm layouts
without changing the shared PDF engine. The complete boundary and browser
security limitations are recorded in
`commerce-document-adapters-implementation.md`.

## 7. Chunk 4.3: Operational Events and Notifications

### 7.1 Approved initial catalogue

| Activity | Domain event | Dedicated notification | Recipient |
| --- | --- | --- | --- |
| New web order committed | `WebOrderPlaced` | `NewWebOrderReceivedNotification` | Active users with `manage-orders` |
| Customer payment confirmed | `OrderPaymentConfirmed` | `CustomerPaymentConfirmedNotification` | Order snapshot email |
| Order ready for pickup/delivery | `OrderReady` | `CustomerOrderReadyNotification` | Order snapshot email |
| Order cancelled and stock released | `OrderCancelled` | `CustomerOrderCancelledNotification` | Order snapshot email when present |
| Stock crosses from healthy to low | `StockBecameLow` | `LowStockNotification` | Active users with `manage-inventory` |
| Stock reaches zero from a positive balance | `StockDepleted` | `OutOfStockNotification` | Active users with `manage-inventory` |
| Closed till has material variance | `TillVarianceDetected` | `TillVarianceNotification` | Active users with `manage-tills` |

The existing `OrderConfirmationNotification` remains the independent customer
acknowledgement for order placement.

### 7.2 Reliability rules

- Dispatch events from `StorefrontCheckoutService`, `OrderService`,
  `PaymentService`, `InventoryService`, and `TillService` only after successful
  transaction commit.
- Queue every mail notification with bounded retries and backoff. Re-query by
  ULID and fail closed when the subject no longer satisfies the event contract.
- Resolve staff recipients in one `CommerceNotificationRecipientResolver`
  using active users and explicit permissions. Do not hard-code system-admin
  email addresses.
- Fire stock alerts only on threshold transitions while the stock row is
  locked. Repeated sales while already low do not produce repeated low-stock
  mail. Replenishment above the threshold naturally re-arms the transition.
- Make the till-variance threshold configurable in integer minor units. Zero or
  immaterial variance does not produce noise.
- Keep notification content focused on one action, one subject, one amount or
  state, and one authorized call to action. Internal exceptions and customer
  secrets never enter mail.
- Record the domain mutation once through the existing system activity service;
  notification delivery must not create a misleading duplicate business event.
- Test `Notification::fake()` dispatch, recipients, channels, after-commit
  behavior, retry-safe payloads, threshold deduplication, missing email, and
  disabled notification configuration.

Initial channels are queued mail plus dashboard indicators. A database-backed
notification center, user delivery preferences, SMS, WhatsApp, and scheduled
digest are later Phase 4 follow-ons and must not be smuggled into this chunk.

**Chunk 4.3 gate:** Each approved operational activity sends one clear,
permission-scoped, retryable notification after commit without duplicate or
cross-activity wording.

**Implementation status (2026-07-23): Gate passed.** The focused suite passes
8 tests and 77 assertions, the complete Commerce coverage passes 113 tests and
1,214 assertions, and the repository regression passes 233 tests and 1,828
assertions. See `commerce-operational-notifications-implementation.md`.

## 8. Chunk 4.4: Fixtures and Adopter Handbook

### 8.1 Fixture completion

**Scope decision (2026-08-18):** This documentation increment must not execute,
reseed, or alter the optional seeders. A read-only audit found that the current
graph contains ten published, tracked, in-stock products, one pending pickup web
order, one completed split-tender POS sale, and one balanced closed till. It does
not prebuild low/out/untracked stock, an open till, or a variance. The handbook
therefore labels those as supported operator scenarios rather than seeded data.
The fixture-expansion bullets below remain deferred unless a later release
decision explicitly reopens them.

- Keep `CommerceDemoSeeder` optional, local-only, idempotent, and excluded from
  `DatabaseSeeder`.
- Preserve ten representative products and ensure the graph demonstrates
  healthy, low, out-of-stock, untracked, regular-price, sale-price, web-order,
  POS-sale, open-till, closed-till, and variance scenarios.
- Add focused seeder assertions for stable business identifiers and scenario
  counts without relying on fragile integer IDs.
- Document safe reset and seed commands and the local-only cashier credentials.

### 8.2 Required documents

| Document | Status | Required content |
| --- | --- | --- |
| `AdoptionReadiness/commerce-adoption-guide.md` | Delivered | Provider/config setup, environment values, migration, storage, queues, permissions, seeders, enable/disable, and removal boundaries |
| `AdoptionReadiness/commerce-operations-handbook.md` | Delivered | Daily web-order, inventory, POS, till, payment, cancellation, and document procedures |
| `AdoptionReadiness/commerce-glossary-and-scenarios.md` | Delivered | Definitions and end-to-end scenario walkthroughs |
| `AdoptionReadiness/commerce-extension-guide.md` | Delivered | Safe extension points for gateways, variants, warehouses, returns, tax, hardware, webhooks, and reporting |
| `AdoptionReadiness/commerce-screenshot-manifest.md` | Delivered | Curated storefront, checkout, order, dashboard, POS, till, receipt, mobile, and dark-mode evidence |

The glossary must define at least product, SKU, barcode, tracked stock, on hand,
low-stock threshold, movement ledger, order, sale, channel, fulfillment state,
payment state, immutable snapshot, register, till session, opening float,
expected cash, counted cash, variance, held sale, split tender, cash change,
ULID, and signed order link.

Scenario walkthroughs must cover guest pickup, guest delivery, payment
confirmation, fulfillment transitions, cancellation/restock, walk-in POS,
known-customer POS, barcode lookup, hold/resume, split tender, cash change,
till open/close/variance, and low-stock response.

## 9. Chunk 4.5: Final Verification and Release Gate

### 9.1 Automated checks

1. Fresh migration, rollback, and re-migration with comments/constraints intact.
2. Optional demo seed twice to prove idempotency.
3. Focused Commerce model, schema, service, authorization, storefront, admin,
   POS, notification, document, and seeder suites.
4. Complete uncached Laravel regression suite.
5. Pint check, JavaScript syntax checks, route cache, configuration cache, view
   cache, and Vite production build.
6. SQLite baseline plus MySQL/MariaDB migration and reporting-query verification
   when the deployment database is available.

### 9.2 Browser, accessibility, and theme matrix

- Public catalog, product, cart, checkout, confirmation, and tracking.
- Commerce dashboard, catalog, inventory, orders, customers, registers, tills,
  terminal, browser receipt, and PDF/print paths.
- Desktop `1440`, laptop `1080`, tablet `820`, and mobile `390` viewports.
- Light and dark modes, persisted theme, reduced motion, empty/loading/error
  states, long content, and realistic seeded content.
- Keyboard traversal, focus visibility, dialog focus return, accessible names,
  labels, landmarks, heading order, table headers, chart fallback, contrast,
  duplicate IDs, horizontal overflow, runtime errors, failed requests, broken
  media, and loader settlement.
- Add axe-core checks to the existing CDP harness rather than replacing the
  working browser infrastructure solely for accessibility automation.

### 9.3 Release evidence

- Store diagnostics and curated captures under `.docs/dev/commerce-*-qa/`.
- Record exact test/assertion totals, build module count, browser matrix, known
  warnings, and explicitly deferred boundaries.
- Update the root README feature ledger and setup commands only from verified
  results.
- Verify the module-disabled host dashboard boots without Commerce routes,
  sidebar links, seed dependencies, or asset errors.

**Master Phase 4 gate:** The Commerce module is understandable, observable,
permission-safe, independently configurable, theme-responsive, migration-safe,
fully tested, and documented well enough for another engineer to adopt or omit
without reverse-engineering its internals.

## 10. Later Phase 4 Follow-Ons

These items are useful but do not block Chunk 4.1 and should remain separate
review units:

- database-backed notification center and per-user channel preferences;
- scheduled low-stock and daily sales digests;
- CSV/Excel exports and advanced comparison periods;
- profit, cost-of-goods, tax-remittance, and accounting reports;
- automated screenshot-diff thresholds in CI;
- external SMS, WhatsApp, payment-gateway, and webhook delivery.

Returns/refunds, suppliers, purchasing, warehouses, transfers, product
variations, loyalty, coupons, customer accounts, and a bundled direct ESC/POS
driver remain outside the initial Commerce release. Chunk 4.2 supplies only the
approved driver contract and cancelable browser event for a separately trusted
kiosk or local-bridge adoption.

## 11. First Implementation Outcome

Chunk 4.1 was implemented on `feature/commerce-admin-dashboard` from baseline
`b2699ab`. Its scope remained isolated to reporting, route and permission
registration, the module-aware sidebar, dashboard presentation, focused tests,
browser QA, and documentation. Notification delivery, document DTO hardening,
fixture expansion, adopter handbooks, and the final release gate remain in their
separate approved chunks.

## 12. Second Implementation Outcome

Chunk 4.2 was implemented on `feature/commerce-document-adapters` from the
locally merged dashboard baseline `1299681`. Its scope is isolated to immutable
document data, the two existing document services and views, register receipt
preferences, the printer-driver extension boundary, browser print behavior,
focused tests, QA-harness checks, and adoption documentation. Broader fixture
scenarios, adopter handbooks, and the final cross-browser release gate remain
in their separately approved chunks.

## 13. Third Implementation Outcome

Chunk 4.3 was implemented on `feature/commerce-operational-notifications` from
the reconciled base `140ca0a`. Seven ULID/scalar events implement Laravel's
after-commit contract and map explicitly to seven dedicated queued mail
notifications. Staff recipients are active users with explicit Commerce
permissions; customer recipients come from immutable order snapshots. Stock
alerts are transition-deduplicated, till variance is materially configurable,
and final-delivery guards suppress stale or disabled messages. No database
notification center or duplicate activity records were introduced. The full
boundary is recorded in
`commerce-operational-notifications-implementation.md`.

## 14. Fourth Implementation Outcome

The Chunk 4.4 documentation increment was prepared on
`feature/commerce-adoption-readiness` from merged base `c7aa6c9`. It delivers
the adopter setup/removal guide, operator handbook, canonical glossary and
scenario truth table, extension contracts, and screenshot/release-evidence
manifest. All statements were reconciled against module routes, configuration,
permissions, services, event mappings, implementation records, and existing QA
artifacts.

No application code, migration, factory, seeder, database data, or generated
fixture was changed. The Laradocs publication layer remains a separate auxiliary
task because its current PHP 8.3 minimum is above Aureon's verified PHP 8.2
runtime; raising the deployment floor requires an explicit compatibility pass.
Chunk 4.5 remains the final migration, regression, build, browser,
accessibility, theme, print, database-family, and deployment gate.
