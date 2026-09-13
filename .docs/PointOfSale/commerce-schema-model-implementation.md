# Commerce Schema and Domain Model Foundation

## Record status

- **Module:** Commerce, covering shared ecommerce and point-of-sale persistence
- **Phase:** 1, schema and domain model foundation
- **Branch:** `feature/pos-product-management`
- **Implementation date:** 2026-07-16
- **Status:** Implemented and verified
- **Approved planning records:**
  - `pos-module-plan.md`
  - `commerce-data-model.md`
  - `commerce-module-architecture.md`
  - `commerce-module-diagrams.md`
  - `commerce-ulid-contract.md`

This record describes the first executable Commerce phase. It does not claim
that the catalog administration, storefront checkout, or POS terminal user
interfaces are complete.

## Planned edits for this phase

1. Establish one traceable, first-party module under `app/Modules/Commerce`.
2. Register configuration, migrations, views, and three independent route
   surfaces through a single module service provider.
3. Create the catalog, inventory, customer, ordering, payment, register, and
   till-session schema required by both storefront and POS workflows.
4. Preserve integer primary and foreign keys while adding immutable ULIDs only
   to externally addressable resources.
5. Add typed enums, Eloquent models, relationships, factories, and derived
   domain helpers for the approved data model.
6. Document every migration field with `->comment()` and every non-obvious
   class or method with PHPDoc.
7. Verify registration, migrations, ULID behavior, relationships, enum casts,
   media compatibility, and core order invariants with focused tests and an
   independent Laravel bootstrap probe.

## Implemented module boundary

All Commerce-owned implementation lives below `app/Modules/Commerce`:

```text
app/Modules/Commerce/
|-- Catalog/
|   |-- Enums/
|   `-- Models/
|-- Customers/Models/
|-- Inventory/
|   |-- Enums/
|   `-- Models/
|-- Orders/
|   |-- Enums/
|   `-- Models/
|-- PointOfSale/
|   |-- Enums/
|   `-- Models/
|-- Config/commerce.php
|-- Database/
|   |-- Factories/
|   `-- Migrations/
|-- Resources/views/
|-- Routes/
|   |-- admin.php
|   |-- pos.php
|   `-- storefront.php
|-- Support/Concerns/HasUlid.php
`-- CommerceServiceProvider.php
```

`CommerceServiceProvider` is the only application-level registration point.
The host changes are intentionally limited to:

- `bootstrap/providers.php`, which registers the module provider.
- `.env.example`, which documents adopter-controlled Commerce defaults.
- `tests/Feature/Commerce`, which verifies the module from the host
  application's integration boundary.

The route files and namespaced view root are present but deliberately empty in
this phase. Controllers, Livewire components, notifications, policies,
services, jobs, and user interfaces belong to their respective later phases
and must remain inside this module.

## Configuration contract

`Config/commerce.php` exposes environment-overridable defaults for:

| Area | Default | Storage rule |
| --- | --- | --- |
| Currency | `KES`, `KSh`, 2 decimals | Monetary values use integer minor units |
| Tax | 1600 basis points | Represents 16 percent; prices include tax by default |
| Inventory | Overselling disabled | Default low-stock threshold is 5 |
| Checkout | Country `KE` | Flat delivery fee defaults to zero minor units |
| Numbering | `WEB` and `POS` prefixes | Final numbering is deferred to an atomic service |

The `COMMERCE_ENABLED` flag is configuration groundwork. Route exposure and
module behavior must enforce it when those surfaces are implemented.

## Persistence contract

Ten module-owned migrations create the first-release schema:

| Table | Aggregate responsibility | External ULID |
| --- | --- | --- |
| `product_categories` | Hierarchical catalog navigation and publication state | Yes |
| `products` | Sellable catalog record, prices, tax, SEO, specifications, and media owner | Yes |
| `stocks` | One current inventory projection per product | No |
| `customers` | Optional account link and reusable contact/address details | Yes |
| `registers` | Named POS device or checkout point | Yes |
| `till_sessions` | Cashier shift, float, expected cash, count, and variance | Yes |
| `orders` | Unified web/POS commercial aggregate and customer/address snapshot | Yes |
| `order_items` | Immutable product, quantity, cost, price, discount, and tax snapshot | No |
| `payments` | Applied/tendered amount, change, method, status, and sanitized reference | Yes |
| `stock_movements` | Append-only inventory ledger with before/after balances | Yes |

All declared migration columns include descriptive `->comment()` metadata.
Indexes and uniqueness rules cover ULIDs, slugs, SKUs, barcodes, register codes,
order numbers, stock ownership, common status filters, and foreign-key lookup
paths. Products and customers use soft deletion; transactional records retain
their historical identity.

## Identifier strategy

The adapted ULID contract is implemented by
`Support/Concerns/HasUlid.php`:

- The numeric `id` remains the canonical primary key and relationship key.
- A valid 26-character ULID is generated before insertion.
- Controlled factory-supplied ULIDs are validated and normalized to uppercase.
- ULIDs are excluded from model `$fillable` arrays.
- Persisted ULIDs are guarded against mutation through Eloquent updates.
- Implicit administrative route binding uses `ulid`.
- Public product/category routes may explicitly bind by `slug`.
- `stocks` and `order_items` do not receive redundant public identifiers.

An order ULID is an identifier, not an authorization mechanism. Guest order
access must use a signed, expiring URL or an equivalent verified access flow in
the storefront phase.

## Domain types and models

The module defines enum-backed state for product publication, stock movement,
order channel, order status, payment projection, fulfillment, payment method,
payment status, and till-session status. Models cast those fields directly to
their enum classes and cast JSON, boolean, timestamp, and numeric state as
appropriate.

The ten module models provide:

- Explicit Eloquent relationships across categories, products, stock,
  customers, registers, tills, orders, items, payments, and stock movements.
- Module-local `newFactory()` definitions so no root factory namespace is
  required.
- Derived helpers for effective sale price, sale-window state, inventory
  availability, low-stock state, customer display name, outstanding balance,
  paid state, completed payment state, and open till state.
- Spatie Media Library support on `Product`, using the existing integer
  polymorphic owner key for the `product_gallery` collection.
- Guarded write surfaces: projection and transaction models are not broadly
  mass assignable and are intended to be mutated by later application services.

Ten module-local factories create coherent data and include focused states for
published products, products on sale, stocked products, held orders, completed
payments, and closed till sessions.

## Enforced invariants

- Monetary amounts are stored as integer minor units; floating-point database
  columns are not used for prices or totals.
- Tax rates are stored as integer basis points.
- Web and POS sales share one `orders` aggregate and are distinguished by a
  typed channel.
- Held POS orders are unnumbered and do not imply placement or stock
  commitment.
- Current stock is a projection; `stock_movements` is the auditable ledger.
- Order items retain product, SKU, pricing, cost, tax, and discount snapshots
  so later catalog changes do not rewrite history.
- Orders retain customer and fulfillment snapshots for the same reason.
- Payment metadata is intended for sanitized gateway context only; secrets and
  raw payment credentials must never be persisted there.
- Number assignment, stock mutation, order totals, payment application, and
  till closure are intentionally deferred to transactional services.

## Verification performed

The focused Commerce suite covers provider registration, configuration,
schema ownership, required columns, migration comments, selective ULIDs,
generated and controlled ULIDs, ULID immutability, route binding, model casts,
relationships, factories, product media, held-order semantics, and a complete
connected model graph.

```text
php artisan test tests/Feature/Commerce
PASS  10 tests, 274 assertions
```

Every module PHP file was checked with `php -l`, and `vendor/bin/pint --dirty`
was applied to the implementation.

A temporary PHP verification script bootstrapped Composer and Laravel, replaced
the database connection with in-memory SQLite, ran `migrate:fresh`, and created
a category, product, stock projection, and held order. Its observed contract was:

```text
tables: 10
product_id_type: integer
effective_price_minor: 7500
stock_on_hand: 9
held_order_status: held
```

The temporary script was removed after it passed and is not part of the module.
The complete application suite also passes with `130 tests` and `878
assertions`. Build-level checks are recorded in the phase commit after final
validation.

## Deferred implementation phases

The following are explicitly out of scope for this foundation commit:

1. Transactional catalog, stock, order, payment, numbering, and till services.
2. Module permissions, policies, middleware, activity-log events, and seeders.
3. Admin catalog and inventory Livewire screens.
4. WoodMart-referenced storefront catalog, product detail, cart, and checkout.
5. POS terminal, cart, held-sale retrieval, receipt, and till reconciliation.
6. Notifications, receipts, low-stock alerts, jobs, and external payment
   integrations.
7. Route handlers and user-facing Blade or Livewire views.

Each phase must extend this module rather than introducing parallel root-level
Commerce classes. Its implementation record belongs in `.docs/PointOfSale/` and
must update the root feature ledger in the same change.
