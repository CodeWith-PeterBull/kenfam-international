# POS and Ecommerce Module Plan

**Status:** Approved and in implementation. The master Phase 1, Phase 2, and
Phase 3 gates have passed. The shared catalog, stock, public storefront, web
ordering, customer/order administration, customer confirmation, document, and
POS terminal workflows are operational. Phase 4 Chunks 4.1 through 4.3, the
Commerce administration dashboard and privacy-safe document/receipt-printing
adapters plus event-specific operational notifications, are implemented and
covered by automated verification. The Chunk 4.4 adopter handbook is delivered
without changing fixtures. Cashier-owned active and cross-session sales history
is now embedded in the POS and eligible dashboards; the final Chunk 4.5 release
matrix remains pending.

**Branch:** `feature/commerce-cashier-sales-history`

**Updated:** 2026-09-06

**Implementation prerequisite:** Satisfied. This branch was reconciled with
`feature/laravel-aureon-base-engine` after `daaab2d` and retains the current
role dashboards, permissions, user management, activity logging, institutional
details, mail, PDF, and error-page foundations.

Supporting review documents:

- `commerce-data-model.md`: model responsibilities, columns, constraints, and
  ordered migration plan.
- `commerce-module-diagrams.md`: context, component, ER, state, and transaction
  diagrams.
- `commerce-module-architecture.md`: plugin-style folder, provider, route,
  namespace, documentation, and error-handling conventions.
- `commerce-ulid-contract.md`: Aureon-specific ULID generation, persistence,
  route-binding, and test rules adapted from `.docs/model-ulid/`.
- `commerce-phase-4-hardening-adoption-plan.md`: reconciled Phase 4 dashboard,
  document, notification, fixture, adoption, and release-gate plan.
- `commerce-cashier-sales-history-implementation.md`: actor-scoped active and
  historical sales projection, configurable placements, UI, security, and QA.
- `AdoptionReadiness/commerce-adoption-guide.md`: provider, configuration, deployment, access,
  demonstration-data, enable/disable, removal, and documentation-publication
  contract.
- `AdoptionReadiness/commerce-operations-handbook.md`: catalog, inventory, web-order, payment,
  customer, register, till, POS, receipt, notification, and incident procedures.
- `AdoptionReadiness/commerce-glossary-and-scenarios.md`: canonical terms, lifecycle diagrams,
  complete operator scenarios, and seeded-versus-supported truth table.
- `AdoptionReadiness/commerce-extension-guide.md`: invariant-preserving gateway, variant,
  warehouse, return, tax, hardware, webhook, reporting, and notification paths.
- `todo/features_todo.md`: researched, dependency-ordered post-baseline feature
  ledger with immutable IDs, status rules, implementation gates, and primary
  source rationale for future Commerce delivery.
- `AdoptionReadiness/commerce-screenshot-manifest.md`: current evidence inventory and final
  responsive, theme, accessibility, print, and PDF capture contract.

Implementation records:

- `commerce-schema-model-implementation.md`: complete shared schema, models,
  factories, enums, configuration, and ULID foundation.
- `commerce-services-authorization-implementation.md`: complete authorization,
  calculation, inventory, ordering, payment, till, and POS service foundation.
- `commerce-catalog-inventory-admin-implementation.md`: complete authorized
  product, category, media, and inventory administration.
- `commerce-storefront-catalog-implementation.md`: Phase 2 storefront shell,
  public catalog discovery, product detail, and demonstration data increment.
- `commerce-storefront-ordering-implementation.md`: completed session cart,
  Livewire checkout, signed order access, customer/order administration,
  confirmation mail, order PDF, and browser-QA increment.
- `commerce-pos-terminal-implementation.md`: completed register/till
  administration, full-width cashier terminal, hold/resume/discard, split
  tender, browser/PDF receipt, and responsive browser-QA increment.
- `commerce-admin-dashboard-implementation.md`: real Commerce reporting,
  authorization, module-disable behavior, responsive chart UI, and QA.
- `commerce-document-adapters-implementation.md`: immutable report view data,
  adapter parity, privacy rules, institutional branding, and register-owned
  browser receipt printing.
- `commerce-operational-notifications-implementation.md`: after-commit events,
  explicit recipients, dedicated queued messages, threshold semantics, and
  delivery verification.

The phase numbers used inside implementation records describe their delivery
sequence. The four master phases in this plan remain the product-delivery gates.

| Master phase | Status | Current boundary |
| --- | --- | --- |
| 1. Shared catalog and stock | Gate passed | Catalog/inventory administration, shared persistence, permissions, media, services, ledger reconciliation, tests, and responsive QA are operational. |
| 2. Storefront and web ordering | Gate passed | Server-repriced session cart, Livewire pickup/delivery checkout, pending manual-payment preference, signed confirmation/tracking/PDF access, customer/order administration, mail, responsive dark-mode UI, tests, and browser QA are operational. |
| 3. POS terminal | Gate passed | Register/till administration, cashier-owned terminal, server-repriced cart, hold/resume/discard, exact split tender, stock mutation, receipts, configurable active/all-session cashier history, tests, and responsive dark-mode QA are operational. |
| 4. Hardening and adoption | Chunks 4.1 through 4.3 implemented; 4.4 handbook delivered | Commerce overview, document/receipt hardening, operational mail, and adopter guidance are complete; fixture expansion is deferred and the final release matrix remains. |

## 1. Objective

Build one small commerce engine with two transaction surfaces:

1. **Ecommerce storefront:** public product listing, product detail, session
   cart, Livewire checkout, order confirmation, and basic order tracking.
2. **Point of sale:** staff product search or barcode entry, cart management,
   customer selection, hold/resume, payment capture, stock deduction, and
   receipt generation.

Both surfaces must read the same products, prices, categories, media, stock,
customers, orders, order items, and payments. A POS sale is an order whose
`channel` is `pos`; a storefront purchase is an order whose channel is `web`.
This avoids parallel sales tables and keeps reporting and inventory behavior
consistent.

## 2. Scope Principles

- Keep version one limited to **simple products**.
- Keep one stock balance per product. Multi-location inventory is deferred.
- Store money as integer minor units. Never persist binary floating-point
  prices or totals.
- Keep the storefront visually rich but operationally small.
- Keep carts transient in Livewire/session state. Persist only held POS carts
  and placed orders.
- Recalculate prices, discounts, tax, and totals on the server before every
  order is written.
- Deduct stock atomically when an order is placed or a POS sale is completed.
- Restore stock through a compensating movement when an order is cancelled.
- Use the existing activity recorder for administrative and transactional
  audit events.
- Add no payment gateway, shipping carrier, accounting integration, or direct
  printer driver in version one.

## 3. WoodMart Benchmark Adaptation

The visual and interaction benchmark is the WoodMart Electronics 3 demo:

- <https://woodmart.xtemos.com/electronics-3/>
- <https://woodmart.xtemos.com/electronics-3/product/audioengine-a2bt/>

The benchmark currently exposes category navigation, search, product grids,
prices, sale presentation, add-to-cart actions, galleries, SKU, quantity,
delivery choices, buy-now behavior, related products, cart, and account entry.

### Adopt in version one

- responsive storefront header with search, category access, account, and cart;
- category-led product grid with search, category, availability, and price
  filters;
- compact product cards with image, category, name, current price, sale price,
  stock state, and add-to-cart action;
- product detail with breadcrumb, gallery, SKU, price, stock state, short and
  full descriptions, quantity, add-to-cart, buy-now, fulfillment summary, and
  related products;
- cart drawer or page with quantity updates, remove action, totals, and checkout;
- Livewire checkout with customer, address, fulfillment, payment preference,
  order review, validation, and placement feedback;
- order confirmation containing order number and an unguessable public tracking
  token.

### Explicitly defer

- variable products and attribute combinations;
- reviews and ratings;
- wishlists and product comparison;
- quick view;
- coupons, deal timers, recommendation engines, and complex merchandising;
- customer account order history;
- live card or mobile-money gateway capture;
- courier rates and carrier APIs;
- multi-currency and multi-language commerce;
- multi-location stock, transfers, purchasing, and suppliers;
- returns portal and partial line refunds;
- product subscriptions, digital downloads, and bundles.

The schema preserves ordinary additive extension points, but version one will
not create speculative tables for these deferred capabilities.

### Mandatory module packaging

All Commerce implementation code and resources live below
`app/Modules/Commerce`. The module owns its provider, configuration, migrations,
seeders, bounded-context models, services, controllers, Livewire components,
notifications, views, and three route files. The host application registers
only `CommerceServiceProvider`; it must not accumulate Commerce controllers,
models, routes, or views in the existing global folders.

The bounded contexts are `Catalog`, `Inventory`, `Customers`, `Orders`,
`PointOfSale`, and `Storefront`. Shared module code lives in `Support` or
`Contracts`, never a generic global helper directory.

### ULID route identity

Externally addressable aggregate models use a separate immutable `ulid` column
while retaining auto-incrementing integer primary and foreign keys. This keeps
Spatie Media Library, Eloquent joins, and package polymorphic relations on
ordinary integer identifiers.

ULIDs are required for categories, products, customers, registers, till
sessions, orders, payments, and stock movements. Internal one-to-one stock
projections and order-item children do not receive redundant ULIDs. Public
catalog routes may bind products/categories by slug; administrative routes bind
by ULID. An order ULID identifies the resource but does not authorize access;
guest confirmation links must also be signed.

## 4. Unified Domain Modules

### 4.1 Catalog

Owns categories, products, pricing, publication state, SKU/barcode identity, and
Spatie Media Library product images.

Primary models:

- `ProductCategory`
- `Product`

Primary services and Livewire components:

- `ProductService`
- `Admin\ProductCatalog`
- `Forms\ProductForm`
- `Admin\ProductCategoryManager`
- `Storefront\ProductIndex`
- `Storefront\ProductShow`

### 4.2 Inventory

Owns the current product balance and an append-only movement history. Every
stock mutation must pass through `InventoryService`.

Primary models:

- `Stock`
- `StockMovement`

Primary services and Livewire components:

- `InventoryService`
- `Admin\InventoryManager`
- `Admin\StockMovementHistory`

### 4.3 Ordering

Owns customers, orders, immutable line snapshots, totals, status transitions,
and actual or pending payment records. It is shared by web checkout and POS.

Primary models:

- `Customer`
- `Order`
- `OrderItem`
- `Payment`

Primary services and Livewire components:

- `CartCalculator`
- `OrderService`
- `PaymentService`
- `Storefront\CartDrawer`
- `Storefront\Checkout`
- `Storefront\OrderConfirmation`
- `Storefront\OrderTracker`
- `Admin\OrderManager`
- `Admin\OrderShow`

### 4.4 Point of Sale

Owns registers, till sessions, cashier workflow, and POS-specific orchestration.
It does not own separate products, stock, customers, or sales records.

Primary models:

- `Register`
- `TillSession`

Primary services and Livewire components:

- `TillService`
- `PosCheckoutService`
- `Pos\Terminal`
- `Pos\HeldOrders`
- `Pos\TillSessionManager`
- `Admin\CustomerManager`

### 4.5 Documents and Notifications

Reuses the existing institutional identity, PDF renderer, and mail template
foundation.

- browser-printable POS receipt;
- optional PDF receipt reprint;
- storefront order-confirmation mail;
- simple A4 order summary for administration.

## 5. Essential Workflows

### Storefront self-ordering

1. Browse or search published products with available stock.
2. Add product IDs and quantities to a session-backed Livewire cart.
3. Re-fetch products and recalculate current prices on every cart mutation.
4. Collect customer and fulfillment details in Livewire checkout.
5. Submit only IDs, quantities, customer data, fulfillment choice, and payment
   preference to `OrderService`.
6. Lock stock rows, recalculate totals, create the web order and line snapshots,
   append stock movements, and decrement balances in one transaction.
7. Return an order number plus public tracking token and dispatch the
   confirmation notification after commit.

Version one supports pickup and delivery. Delivery uses a simple configured
flat fee or zero; no carrier calculation is included.

### POS sale

1. Cashier opens a register session with an opening float.
2. Scan a barcode or search products by SKU/name.
3. Maintain quantities, customer, and optional order-level discount in Livewire
   state.
4. Accept one or more payments and calculate cash change.
5. `PosCheckoutService` delegates pricing and persistence to the same order and
   inventory services used by web checkout.
6. Persist a completed `pos` order, items, payments, stock movements, and till
   totals atomically.
7. Present a printable receipt.

### Hold and resume

A held POS cart is persisted as an `orders` row with `channel=pos` and
`status=held`. It contains items but does not reduce stock and must be repriced
and revalidated when resumed. It receives the final order number only at
checkout.

### Cancellation

Only eligible `web` orders may be cancelled in version one. Cancellation is a
transactional state transition that appends positive stock movements equal to
the original committed quantities. Completed POS sales require a future return
workflow and are not silently cancelled.

## 6. Pricing and Inventory Rules

- `price_minor`, `sale_price_minor`, `cost_price_minor`, and all order/payment
  totals are signed 64-bit integers.
- `sale_price_minor` is used only while the optional sale start/end window is
  active.
- Quantity is an integer in version one. Weighted or fractional units are
  deferred.
- Product tax is either exempt or uses `tax_rate_bps`; `1600` means 16 percent.
- Tax-inclusive behavior is controlled by `config/commerce.php` and copied onto
  the order and every order item as a historical snapshot.
- An order-level fixed discount is allocated across lines deterministically.
- Cart totals shown by Livewire are previews. `OrderService` independently
  recalculates them inside the write transaction.
- `stocks.on_hand` is the fast projection; `stock_movements` is the audit
  ledger. Successful committed movements must reconcile to the projection.
- `InventoryService` locks the stock row with `lockForUpdate()` before checking
  availability and updating it.
- Overselling is disabled by default.

## 7. Public Storefront Structure

The storefront will use a dedicated modular Blade layout, not the dashboard
layout:

```text
app/Modules/Commerce/Resources/views/layouts/storefront.blade.php
app/Modules/Commerce/Resources/views/storefront/partials/header.blade.php
app/Modules/Commerce/Resources/views/storefront/partials/mobile-navigation.blade.php
app/Modules/Commerce/Resources/views/storefront/partials/footer.blade.php
app/Modules/Commerce/Resources/views/storefront/catalog/index.blade.php
app/Modules/Commerce/Resources/views/storefront/catalog/show.blade.php
app/Modules/Commerce/Resources/views/storefront/cart/index.blade.php
app/Modules/Commerce/Resources/views/storefront/checkout/index.blade.php
app/Modules/Commerce/Resources/views/storefront/orders/confirmation.blade.php
app/Modules/Commerce/Resources/views/storefront/orders/track.blade.php
app/Modules/Commerce/Resources/views/livewire/storefront/*.blade.php
```

It should adapt the Aureon corporate frontend's header, theme tokens, loader,
brand assets, and footer while adopting the benchmark's commerce information
architecture. The visual result is an Aureon storefront, not a WoodMart clone.

## 8. Administration and POS Structure

```text
app/Modules/Commerce/CommerceServiceProvider.php
app/Modules/Commerce/Config/commerce.php
app/Modules/Commerce/Routes/{admin,pos,storefront}.php
app/Modules/Commerce/Database/{Migrations,Factories,Seeders}/
app/Modules/Commerce/Catalog/{Enums,Models,Services,Http,Livewire}/
app/Modules/Commerce/Inventory/{Enums,Models,Services,Livewire}/
app/Modules/Commerce/Customers/{Models,Services,Livewire}/
app/Modules/Commerce/Orders/{Enums,Models,Services,Notifications,Http,Livewire}/
app/Modules/Commerce/PointOfSale/{Enums,Models,Services,Http,Livewire}/
app/Modules/Commerce/Storefront/{Http,Livewire}/
app/Modules/Commerce/Resources/views/{admin,pos,storefront,livewire}/
app/Modules/Commerce/Support/{Casts,Concerns,Data}/
tests/Feature/{Catalog,Inventory,Orders,Storefront,Pos}/*.php
```

Controllers remain thin route-to-view adapters. Validation lives in typed
Livewire form objects or dedicated form requests. Transactional rules live in
services, not components.

Every PHP file and class receives a useful docblock. Public and protected
methods document intent, invariants, parameters, return values, and thrown
domain exceptions where the signature is not fully self-explanatory. Every
migration column uses `->comment()`. Expected business failures use typed domain
exceptions and user-safe Livewire errors; unexpected failures are logged with
structured context and allowed to reach Laravel's exception pipeline. Successful
business mutations use the existing system activity recorder. Sensitive payment
or customer fields are never copied into logs or activity properties.

## 9. Permissions

Add the smallest useful permission set to `CmsPermission` and `RoleSeeder`:

| Permission | Purpose |
| --- | --- |
| `view-products` | View the administrative catalog |
| `manage-products` | Create, update, publish, and archive products/categories |
| `view-inventory` | View balances and movements |
| `manage-inventory` | Post manual stock adjustments |
| `access-pos` | Use the POS terminal |
| `manage-tills` | Configure registers and open, reconcile, or close till sessions |
| `view-orders` | View web and POS orders |
| `manage-orders` | Update eligible order and payment states |
| `manage-customers` | Maintain reusable customer records |

Public catalog, cart, checkout, confirmation, and tokenized tracking routes do
not use CMS permissions. Confirmation must use a signed URL or public token and
must never expose sequential IDs.

The initial operational role composition is intentionally narrow:

| Operator | Required capability | Allowed surfaces |
| --- | --- | --- |
| System administrator | Platform super-user contract | Terminal, registers, till sessions, receipts, and all Commerce administration |
| POS cashier | `access-pos` | Terminal and receipts for sales owned by that cashier |
| Till manager | `manage-tills` | Register configuration, till assignment, reconciliation, closure, and supervisory receipts |
| Cashier supervisor | `access-pos` + `manage-tills` | Both operational terminal and till-management surfaces |
| Order reviewer | `view-orders` | Order administration and supervisory POS receipt access |

`manage-tills` does not implicitly grant terminal use, and `access-pos` does not
grant register or till administration. The optional Commerce demonstration
seeder creates a `pos-cashier` role with only `access-pos`; adopters compose a
supervisor role when one person must perform both responsibilities.

## 10. Implementation Phases

### Phase 1: shared catalog and stock

- merge/reconcile the current base-engine branch;
- register the self-contained `CommerceServiceProvider`, module config,
  namespaced views, migrations, and separate route files;
- add the ULID concern, enums, money helper/cast, models, migrations, and
  factories for the complete approved schema;
- add product/category administration and stock adjustment/history;
- configure the `product_gallery` Media Library collection;
- add permissions, navigation, audit records, seed examples, and tests.

**Gate:** Administrators can publish simple products and adjust stock while the
movement ledger reconciles exactly to the product balance.

### Phase 2: storefront and web ordering

- add customers, orders, items, and payments;
- add the shared calculator and transactional order service;
- build storefront layout, product grid, product detail, cart, checkout,
  confirmation, and tracking;
- add pickup/delivery and manual payment preferences;
- add confirmation mail and basic order administration;
- test guest checkout, stock locking, pricing snapshots, cancellation/restock,
  authorization, and token privacy.

**Gate:** A guest can place a valid order from a published in-stock product and
receive a confirmation without trusting any client-submitted price.

**Gate status (2026-07-17): Passed.** Checkout accepts product IDs and integer
quantities only, re-fetches and locks current products/stock, recalculates all
money on the server, writes order/payment/stock state transactionally, and
clears the cart only after success. Temporary signed URLs protect confirmation,
tracking, and portrait/landscape order documents. The completed boundary is
recorded in `commerce-storefront-ordering-implementation.md`.

### Phase 3: POS terminal

- add registers and till sessions;
- build the full-width Livewire POS terminal;
- support barcode/SKU/name lookup, cart edits, customer selection, hold/resume,
  fixed discount, split tender, cash change, and receipt printing;
- persist POS transactions through the shared order/inventory services;
- test till ownership, held-order repricing, split payments, atomic stock
  deduction, insufficient-stock rollback, and receipt totals.

**Gate:** A till manager can assign and open a cashier's till; the assigned
cashier can complete and print a sale; and a till manager can close the session
with an expected-versus-counted cash variance.

**Gate status (2026-07-17): Passed.** Till managers can configure registers and
open, reconcile, or close cashier sessions. Cashiers use only their own open
till, search the shared published catalog, maintain a server-repriced cart,
select customers, hold/resume/discard, settle exact split tenders, and receive
an ownership-protected browser/PDF receipt. The completed boundary is recorded
in `commerce-pos-terminal-implementation.md`.

### Phase 4: hardening and adoption

- add a permission-scoped Commerce administration dashboard with explicit
  sales, order, stock, customer, and till semantics plus authorized deep links;
- harden the completed receipt and order-summary adapters around immutable view
  data, orientation rules, safe filenames, and privacy assertions;
- add one dedicated queued notification per approved operational activity,
  including threshold-crossing stock alerts and till-variance alerts;
- reconcile the optional fixtures and publish adopter setup, glossary,
  scenario, screenshot, and extension guidance;
- run the complete migration, regression, build, browser, accessibility,
  responsive, dark-theme, and print verification gates.

The ordered chunks, exact baseline, file ownership, metric definitions,
notification catalogue, and acceptance gates are specified in
`commerce-phase-4-hardening-adoption-plan.md`.

**Gate:** The module is documented, theme-responsive, role-gated, test-covered,
and ready to be enabled or omitted as an Aureon engine module.

**Chunk 4.1 status (2026-07-19): Passed.** `/admin/commerce` now provides
permission-scoped, real cross-module indicators, a zero-filled web/POS payment
trend, fulfillment and stock action panels, recent orders, active tills,
authorized links, responsive light/dark presentation, and module-disable
coverage. See `commerce-admin-dashboard-implementation.md`.

**Chunk 4.2 status (2026-07-19): Automated gate passed.** Order summaries and
POS receipts now render immutable display-only projections through the shared
institutional PDF engine. Order documents retain both orientations; receipt PDFs
reject landscape explicitly. Register settings select a config-resolved driver,
manual or post-sale prompting, and 58 mm or 80 mm roll layout. The browser driver
emits a cancelable integration event and never claims silent hardware access.
See `commerce-document-adapters-implementation.md`. Fresh compiled-asset browser
captures remain part of the final Phase 4 release evidence.

**Chunk 4.3 status (2026-07-23): Commerce regression gate passed.** Seven
service-owned ULID/scalar events now dispatch only after successful outer
transaction commit. Dedicated queued notifications route to active users with
explicit Commerce permissions or immutable order snapshot emails, while stock
transition and till materiality rules prevent repeated noise. Disabled,
missing-recipient, stale-state, and rollback paths fail closed. See
`commerce-operational-notifications-implementation.md`.

**Chunk 4.4 documentation status (2026-08-18): Delivered.** The five-document
adopter set now covers setup and removal, daily operations, canonical terms and
end-to-end scenarios, safe extension contracts, and existing/final screenshot
evidence. A read-only fixture audit is recorded accurately: the optional graph
has ten published tracked in-stock products, one web order, one completed POS
sale, and one balanced closed till. This increment did not execute or change
seeders; low/out/untracked stock, open-till, and variance fixtures remain
deferred. See `AdoptionReadiness/commerce-adoption-guide.md` and its linked
handbook set.

**Cashier sales-history increment status (2026-09-06): Implemented.** A reusable
Livewire component now exposes the authenticated cashier's completed active-till
sales and paginated cross-session history with bounded date filters, minor-unit
aggregates, protected receipt links, independent placement switches, and public
shop shortcuts. The projection excludes held, cancelled, web, and other-cashier
orders by construction. See
`commerce-cashier-sales-history-implementation.md`.

## 11. Explicit Non-goals for Initial Implementation

- no supplier or purchase-order module;
- no warehouses or stock transfers;
- no product variations;
- no online payment integration;
- no returns/refunds engine;
- no direct ESC/POS integration;
- no loyalty points, coupons, wishlists, comparison, or reviews;
- no advanced tax classes or jurisdiction engine;
- no customer account portal;
- no analytics warehouse or advanced reporting.

These can be added as separate modules after the shared order and movement
contracts have proven stable.

## 12. Review Decisions Required

1. Confirm `KES` as the default currency and whether catalog prices are tax
   inclusive by default.
2. Confirm the initial tax rate, if any. Proposed default: configurable 16
   percent with individual products allowed to be exempt.
3. Confirm web fulfillment choices: pickup plus flat-fee delivery.
4. Confirm web payment preferences: cash on delivery, manual M-Pesa, and bank
   transfer, with no automated gateway confirmation.
5. Confirm POS tenders: cash, M-Pesa, and card, including split payments.
6. Confirm that simple products and integer quantities are sufficient for
   version one.
7. Confirm stock deduction at web order placement, with cancellation restoring
   stock, rather than a separate reservation subsystem.
8. Confirm browser printing as the initial receipt mechanism.
9. Confirm the modular `app/Modules/Commerce` package and selective ULID matrix
   defined in the companion architecture documents.

Implementation must not begin until these scope decisions and the companion
data model are reviewed.
