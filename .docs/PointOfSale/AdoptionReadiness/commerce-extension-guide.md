# Commerce Extension Guide

**Status:** Engineering contract for derivative Commerce capabilities.

**Updated:** 2026-08-18

## 1. Purpose

This guide identifies the stable implementation boundaries an engineer must
preserve while extending Aureon Commerce. Proposed examples in this document
are extension strategies, not features that currently ship.

Read the implementation records in `.docs/PointOfSale` and the relevant tests
before changing a domain contract. A new capability should remain module-owned,
independently configurable, permission-safe, and removable with Commerce.

## 2. Non-Negotiable Invariants

Every extension must preserve these existing rules:

1. **One transaction model:** web orders and POS sales remain `Order`
   aggregates distinguished by `OrderChannel`.
2. **Integer money:** store amounts in minor units; never multiply PHP floats.
3. **Server authority:** browser/API clients never author price, tax, stock,
   totals, payment state, change, or till balances.
4. **Service-owned mutation:** Livewire, controllers, jobs, and webhooks call
   domain services for multi-model changes.
5. **Transactional locking:** lock the aggregate and mutable projections before
   recalculation and write all related effects atomically.
6. **Immutable history:** placed order items/customer snapshots and stock
   movements are not edited to reflect current catalog state.
7. **Separate external identity:** integer keys remain internal; ULIDs identify
   externally addressable records but never grant access.
8. **Layered authorization:** retain route middleware, policies/action gates,
   ownership checks, active-account rules, and service preconditions.
9. **After-commit side effects:** mail, webhooks, and other operational effects
   must not escape a transaction that can still roll back.
10. **Privacy projections:** documents, notifications, reports, receipt bridges,
    and webhooks receive purpose-built DTOs rather than Eloquent graphs.
11. **Structured observability:** successful mutations record one stable system
    activity; unexpected failures use structured logs without sensitive data.
12. **Module ownership:** implementation remains in `app/Modules/Commerce`
    except explicit host integration points, tests, Vite entries, and docs.

## 3. Existing Extension Surfaces

| Surface | Current owner | Extension rule |
| --- | --- | --- |
| Configuration | `Config/commerce.php` | Add typed/defaulted settings and `.env.example` keys |
| Host registration | `CommerceServiceProvider` | Register explicit bindings, policies, listeners, views, routes, and aliases only |
| Authorization | `CommercePermission`, policies | Add code-owned capabilities before UI/routes |
| Pricing/calculation | `CartCalculator`, `ScaledDecimal` | Extend deterministic integer calculations with tests |
| Catalog mutation | `ProductService` | Keep media/lifecycle writes transactional and audited |
| Stock mutation | `InventoryService` | Preserve projection/ledger parity and lock order |
| Order lifecycle | `OrderService` | Add explicit legal transitions, not ad hoc status writes |
| Payment capture | `PaymentService` | Preserve balance checks, idempotency, and sanitized metadata |
| Web checkout | `StorefrontCheckoutService` | Keep cart clearing after committed placement only |
| POS checkout | `PosCheckoutService` | Preserve full settlement and atomic order/payment/stock/till writes |
| Receipt printing | `ReceiptPrinterDriver` | Pass immutable receipt/settings DTOs only |
| Operational mail | event/listener/notification classes | One approved activity per dedicated path |
| Dashboard | `CommerceDashboardService` | Return bounded immutable projections; no Eloquent in Blade |
| PDF registers | `Reporting/Filters`, `Data/Rows`, `Reports` | Mirror list filters and enforce safe row bounds |

## 4. Standard Delivery Shape

A substantial extension should use this sequence:

1. Write or amend the module plan and diagrams.
2. Define terms, lifecycle states, permission boundaries, and non-goals.
3. Design commented migrations, indexes, constraints, and rollback behavior.
4. Add enums, DTOs, exceptions, models, relationships, factories, and policies.
5. Implement service transactions and lock order before interface code.
6. Emit scalar/ULID after-commit events only where an operational consumer is
   approved.
7. Add controllers/routes and Livewire forms/components inside the relevant
   module context.
8. Add module-owned Blade/CSS/JavaScript and explicit Vite entries if needed.
9. Add focused model, service, authorization, interface, privacy, rollback, and
   module-disabled tests.
10. Run migration, full regression, build, browser, accessibility, dark-mode,
    and cache gates appropriate to the change.
11. Record final file ownership, commands, evidence, residual boundaries, and a
    conventional commit.

Do not combine unrelated extensions in one migration or review unit.

## 5. Payment Gateway Extension

### 5.1 Current boundary

The current storefront records a manual payment preference. Administrators
confirm completed payments, and POS records locally observed tenders. No
external provider is called and no card credential is stored.

### 5.2 Recommended architecture

Introduce a module-owned payment integration context, for example:

```text
app/Modules/Commerce/Payments/
|-- Contracts/PaymentGateway.php
|-- Data/GatewayIntentData.php
|-- Data/GatewayResultData.php
|-- Events/
|-- Exceptions/
|-- Gateways/
|-- Http/Controllers/Webhooks/
|-- Services/PaymentGatewayManager.php
`-- Services/WebhookPaymentService.php
```

The gateway contract should create an external intent from an immutable order
projection and return provider identifiers/status, never mutate Eloquent state
itself. A service should lock the order, verify outstanding balance/currency,
apply an idempotency key, and call `PaymentService` only after provider truth is
verified.

Webhook handling must:

- verify signature, timestamp, endpoint secret, and expected provider;
- persist or otherwise enforce provider event idempotency;
- reject currency/order/amount mismatch;
- tolerate duplicate and out-of-order delivery;
- store only an allow-listed metadata projection;
- return safely when the order is already settled; and
- emit customer/operational events only after the local transaction commits.

Never send card PAN, CVV, access token, webhook secret, raw provider payload, or
customer credentials into payments, logs, activity, notifications, or PDFs.

## 6. Product Variant Extension

### 6.1 Current boundary

SKU, barcodes, price, quantity limits, and stock policy currently belong to the
product. Specifications are presentation metadata, not saleable variants.

### 6.2 Required model change

Do not encode variants as unchecked JSON or duplicate products without an
explicit business decision. A real variant extension should introduce a
`ProductVariant` aggregate with its own SKU, internal/manufacturer barcodes,
price overrides, publication/saleability state, and stock ownership.

That change must also revise:

- cart item identity from product ID to a stable saleable-item reference;
- unique indexes for SKU and both barcode namespaces;
- inventory projection and movement ownership;
- checkout and POS lookup/repricing/lock order;
- order-item snapshots to preserve variant label/options;
- media rules when variants own images;
- labels, storefront selection, reports, and search; and
- migrations for existing products to one default variant when required.

Avoid branching product-vs-variant behavior throughout views. Introduce one
saleable-item contract or projection consumed by calculators and interfaces.

## 7. Warehouse and Multi-Location Stock

### 7.1 Current boundary

There is one stock projection per product. Registers have location labels but
do not select a stock location.

### 7.2 Recommended design

Add explicit `StockLocation`, location-owned balances, and source/destination
movement semantics. A transfer should be its own aggregate with requested,
dispatched, received, cancelled, and reconciled states rather than two unrelated
manual adjustments.

Required decisions include:

- which location storefront orders allocate from;
- which location each register/till uses;
- reservation versus immediate commitment timing;
- partial fulfillment and backorder policy;
- deterministic lock ordering across multiple balances;
- transfer-in-transit accounting;
- per-location low-stock thresholds and recipients; and
- historical location snapshots in orders/reports.

Do not add `warehouse_id` to only the stock table and leave checkout, POS,
ledger references, reports, and cancellation restoration ambiguous.

## 8. Returns and Refunds

### 8.1 Current boundary

An unpaid web order can be cancelled and restocked before completion. Paid
cancellation, returns, exchanges, and refunds are not implemented. Enum values
reserved for refunded state are not proof of an operational refund workflow.

### 8.2 Recommended design

Use separate return/refund aggregates. Do not rewrite original order items,
completed payments, or stock commitments.

A complete design needs:

- return authorization, reason, actor, channel, and lifecycle;
- return lines bounded by original fulfilled quantities minus prior returns;
- disposition such as restock, damaged, quarantine, or write-off;
- positive inventory movements tied to the return line where appropriate;
- refund records tied to original payments and provider idempotency;
- partial and mixed-tender allocation policy;
- receipt/credit-note document DTOs;
- permissions separating approval, receipt, and money release;
- events and notifications after commit; and
- reports that distinguish gross sales, returns, refunds, and net collections.

Exchanges should compose a return and a new sale rather than mutate the
original order.

## 9. Tax Engine Extension

### 9.1 Current boundary

Products own one tax rate in basis points and a tax-inclusive flag. Order-item
snapshots preserve calculated line tax. There are no jurisdictions, exemptions,
compound rates, or remittance reports.

### 9.2 Safe extension

Introduce a deterministic tax quotation contract that accepts immutable cart,
customer/fulfillment, and jurisdiction data and returns integer line-level
allocations. Persist enough rule identity and labels on the order snapshot to
explain a historical total without re-running current rules.

Specify rounding at line and order level, inclusive/exclusive behavior,
discount allocation order, delivery tax, exemption evidence, and currency
precision. Never recompute a placed order from a newly edited tax table.

## 10. Delivery and Fulfillment Extension

The current delivery charge is one flat configured amount. Carrier, zone, or
distance support should be introduced through a quote DTO and service contract.
The accepted quote must be snapshotted on the order with provider/service name,
amount, currency, and relevant delivery promise.

An external quote is advisory until server validation at checkout. Define
expiry and failure behavior and do not allow client-submitted delivery amounts.

## 11. Receipt Hardware Extension

### 11.1 Current browser contract

`BrowserReceiptPrinterDriver` returns a safe instruction and dispatches a
cancelable `commerce:receipt-print` event before `window.print()`. It explicitly
reports `supportsSilentPrinting=false`.

### 11.2 Trusted driver process

To add a kiosk, network, or ESC/POS bridge:

1. Implement `ReceiptPrinterDriver`.
2. Register a stable key/class under
   `commerce.pos.receipt_printing.drivers`.
3. Validate the key in register administration and `RegisterService`.
4. Add a separately authenticated client integration that intercepts
   `commerce:receipt-print` and calls `preventDefault()` only when it accepts
   responsibility.
5. Pass only `ReceiptPrinterSettingsData`, immutable `PosReceiptData`, and the
   after-checkout reason.
6. Define connection timeout, retry, duplicate-print protection, offline state,
   cutter/drawer policy, device allow-list, and operator fallback.
7. Preserve manual browser/PDF printing when the bridge is unavailable.

Device credentials and raw payment metadata must stay outside browser event
details and receipt DTOs. Silent printing requires a trusted deployment model,
not a promise made by ordinary web JavaScript.

## 12. Webhook and External Event Delivery

Outbound webhooks should use an outbox-style record written in the same
transaction as the business event, then delivered by a queued worker. The
payload should be versioned, signed, bounded, privacy-reviewed, and derived from
a dedicated projection.

Each delivery needs endpoint ownership, secret rotation, attempt history,
bounded exponential backoff, disable/circuit-breaker behavior, response
truncation, and replay tooling. A failed webhook must not make a committed sale
appear failed to the operator.

Inbound webhooks follow the payment-gateway rules: authenticate first,
deduplicate, validate current state, mutate through services, and reply without
leaking internal exceptions.

## 13. Reporting Extension

### 13.1 Operational dashboard

Add bounded, immutable DTOs from `CommerceDashboardService`. Define metric
semantics before writing SQL, use integer aggregates, zero-fill time series, and
provide an accessible table fallback for every chart. Measure indexes/query
plans on the adopter's MySQL/MariaDB data volume before introducing caching.

### 13.2 Register PDF

Follow the existing pattern:

```text
Reporting/Filters/<Concern>ReportFilters.php
Reporting/Data/Rows/<Concern>Row.php
Reporting/Reports/<Concern>Report.php
Resources/views/reports/pdf/<concern>.blade.php
```

Mirror the screen's live filters, authorize the underlying read capability,
exclude internal/private fields by construction, cap synchronous row count,
use `RendersPdfReports`, and record one export activity.

Move large reports to queued generation with protected temporary storage rather
than increasing the synchronous 2,000-row cap without measurement.

## 14. Notification Extension

For an approved activity, add:

1. One scalar/ULID event implementing after-commit dispatch.
2. One explicit listener registered by `CommerceServiceProvider`.
3. One dedicated queued notification with a stable event key.
4. A recipient rule based on explicit permission or immutable order snapshot.
5. A `shouldSend()` current-state guard.
6. Global and per-event configuration switches.
7. Tests for success, disablement, missing recipient, inactive recipient, stale
   subject, rollback, serialization, retries, and privacy.

Do not create a generic "Commerce activity" email. Database read/unread state,
user channel preferences, SMS, WhatsApp, and scheduled digests are separate
modules with their own persistence and consent requirements.

## 15. Customer Account Extension

The current storefront is guest-first and signed-link based. A customer portal
must explicitly link application users to reusable customers and authorize each
order through account ownership. Do not replace existing signed guest access or
infer ownership from a mutable matching email alone.

Define registration/claiming, duplicate resolution, email changes, delegated
organization access, archived customers, and historical guest orders before
adding an order-history page.

## 16. Search, Caching, and Scale

Current catalog, dashboard, and PDF queries are bounded and use database
filters. Before adding external search or aggregate caches:

- measure real query plans and cardinality;
- define cache key dimensions and invalidation events;
- preserve authoritative service checks at checkout;
- handle disabled/unpublished/depleted products in stale indexes;
- prevent private cost/customer/payment fields entering public indexes; and
- keep a database fallback for operational recovery.

A fast stale catalog result must still fail safely during locked checkout.

## 17. Authorization Extension

Add new capabilities to `CommercePermission::catalogue()` and let
`CmsPermission` expose them. Update `RoleSeeder` expectations, but do not grant
new operational powers to existing non-admin roles implicitly.

For each new surface, test:

- guest redirect or public boundary;
- unverified and inactive users;
- authenticated user without capability;
- view-only versus mutation permissions;
- record ownership where applicable;
- active system administrator behavior;
- sidebar/link visibility; and
- module-disabled 404/boot behavior.

Notification distribution must still use explicit stored permissions instead
of relying on the system-administrator gate bypass.

## 18. Migration and Compatibility Rules

- Add fields through new module-owned migrations; never edit an already shipped
  migration for an adopted database.
- Add a meaningful `->comment()` to every new column.
- State nullability, default, index, unique, foreign-key, and delete behavior.
- Use a nullable/backfill/constrain sequence for new required fields on populated
  tables.
- Preserve integer foreign keys and separate ULIDs unless a reviewed contract
  changes the whole module.
- Do not drop or reinterpret historical snapshot/ledger values silently.
- Test fresh migration, upgrade migration, rollback behavior, SQLite baseline,
  and target MySQL/MariaDB behavior.

## 19. UI and Asset Rules

New administrative screens reuse the dashboard shell, Bootstrap 5, Tabler
icons, theme tokens, loader, responsive table containment, and current Livewire
4 patterns. Storefront work reuses the module storefront layout and design
tokens. POS work preserves the full-width terminal layout.

Create a dedicated Vite entry only when the feature needs page-specific CSS or
JavaScript. Verify light/dark themes, reduced motion, keyboard operation,
visible focus, loading/error/empty states, mobile overflow, long content, and
browser runtime/network errors.

## 20. Anti-Patterns to Reject

- Writing `status`, totals, stock, payment, or till projections directly from a
  Livewire component.
- Adding a second web-order or POS-sale table for a new channel.
- Storing major-unit decimals or floats alongside integer minor units.
- Recomputing historical order documents from current product/customer data.
- Editing/deleting stock movements to "fix" on-hand values.
- Treating an unnumbered hold as reserved stock or collected revenue.
- Using ULID or an email query parameter as the only authorization check.
- Sending Eloquent models or raw request/provider payloads through queues,
  webhooks, documents, or printer bridges.
- Catching an exception only to log and rethrow it repeatedly.
- Registering module routes or listeners outside `CommerceServiceProvider`
  without an explicit host-level reason.
- Adding hidden permissions or hard-coded role names inside views.
- Claiming direct/silent browser printing without a trusted kiosk or bridge.

## 21. Contextual Demo Dataset Authoring

Contextual catalogs intentionally reuse `CatalogDemoSeeder`; do not add a
parallel fixture service for another merchant sector.

1. Add one trusted PHP array under
   `app/Modules/Commerce/Database/Seeders/Data`.
2. Register its stable key and file in `CatalogDemoSeeder::CONTEXT_FILES`.
3. Define 10-12 complete products, resolvable categories, one web-order SKU,
   and exactly two POS-order SKUs.
4. Keep category slugs, product slugs, SKUs, and barcodes unique across every
   built-in context. Prefix non-default category slugs with the context key.
5. Use integer minor-unit money and review tax, sale windows, quantities,
   dimensions, and regulated-domain wording.
6. Add at least one product image and one valid category image; contextual
   product releases should provide two or three gallery paths per product.
7. Run the definition, command, idempotency, archive, media, and full Commerce
   tests before documenting the context as available.
8. Do not edit transaction services, create a new catalog model, or encode
   variants, expiry, batch, serial, or variable-measure behavior in fixture
   specifications.

Keep source paths module-owned and deterministic; a seeder rerun reconciles the
copied media by checksum. Existing focused contexts use one generated image and
a fallback, while `shoe-store` is the reference for three real product angles.

## 22. Extension Review Checklist

Before merge, confirm:

- [ ] Scope and non-goals are documented.
- [ ] Schema comments, indexes, constraints, and backfill are reviewed.
- [ ] Money, tax, identifier, snapshot, and ledger invariants remain intact.
- [ ] Service transaction and lock order are documented and tested.
- [ ] Routes, policies, ownership, permissions, and inactive users are covered.
- [ ] Events occur after commit and queued payloads are scalar/immutable.
- [ ] Customer/payment/cost/internal fields are excluded from presentation DTOs.
- [ ] Activity names and safe properties are stable.
- [ ] Module-disabled boot and host navigation still work.
- [ ] Focused and full tests pass without stale caches.
- [ ] Production assets, browser accessibility, themes, and responsive states pass.
- [ ] Adoption, operations, glossary, screenshot, and implementation docs are updated.
- [ ] Deferred risks are explicit rather than represented by placeholder behavior.
