# Commerce Services and Authorization Foundation

## Record status

- **Module:** Commerce
- **Phase:** 2, authorization and transactional service foundation
- **Branch:** `feature/pos-product-management`
- **Implementation date:** 2026-07-16
- **Status:** Implemented and verified
- **Predecessor commit:** `1ad8886 feat(commerce): establish modular schema and domain foundation`
- **Related records:**
  - `pos-module-plan.md`
  - `commerce-data-model.md`
  - `commerce-module-architecture.md`
  - `commerce-module-diagrams.md`
  - `commerce-ulid-contract.md`
  - `commerce-schema-model-implementation.md`

This phase establishes the write, authorization, calculation, and failure
contracts required before catalog administration, storefront checkout, or the
POS terminal may be exposed. No Commerce route or user interface is activated
by this implementation.

## Planned edits

1. Keep all Commerce-specific permissions, policies, exceptions, data objects,
   sanitizers, and services under `app/Modules/Commerce`.
2. Aggregate the module permission catalogue into the existing CMS role
   composer and idempotent role seeder without duplicating either mechanism.
3. Add typed, user-safe domain exceptions for expected catalog, customer,
   inventory, order, payment, and till failures.
4. Calculate product prices, discounts, tax, delivery, and order totals from
   freshly loaded products using integer arithmetic.
5. Serialize inventory, order, payment, and till writes with database
   transactions and row locks.
6. Preserve immutable product, customer, price, cost, tax, and address
   snapshots while ignoring client price and total fields entirely.
7. Record successful mutations through `RecordsSystemActivity` without copying
   customer addresses, payment references, or payment metadata into activity
   properties.
8. Verify authorization, arithmetic, rollback, lifecycle, append-only, and
   reconciliation contracts with focused tests and an independent runtime
   probe.

## Implemented file structure

```text
app/Modules/Commerce/
|-- Catalog/
|   |-- Policies/
|   `-- Services/ProductService.php
|-- Customers/
|   |-- Policies/
|   `-- Services/CustomerService.php
|-- Exceptions/
|-- Inventory/
|   |-- Policies/
|   `-- Services/InventoryService.php
|-- Orders/
|   |-- Data/
|   |-- Policies/
|   |-- Services/
|   |   |-- CartCalculator.php
|   |   |-- OrderNumberService.php
|   |   |-- OrderService.php
|   |   `-- PaymentService.php
|   `-- Support/PaymentMetadataSanitizer.php
|-- PointOfSale/
|   |-- Policies/
|   `-- Services/
|       |-- PosCheckoutService.php
|       `-- TillService.php
`-- Support/CommercePermission.php
```

The only cross-module integration edit is `app/Support/CmsPermission.php`,
which includes `CommercePermission::catalogue()` in the existing code-owned
capability list. `RoleSeeder` therefore remains unchanged while automatically
creating the new permissions and assigning them to the system administrator.

## Permission contract

Nine capabilities are defined by the module:

| Permission | Boundary |
| --- | --- |
| `view-products` | View administrative products and categories |
| `manage-products` | Create, update, publish, archive, and organize catalog records |
| `view-inventory` | View stock projections and movement history |
| `manage-inventory` | Post manual stock adjustments |
| `access-pos` | Use POS sale and payment operations |
| `manage-tills` | Configure registers and open or close sessions |
| `view-orders` | View web orders, POS sales, and payments |
| `manage-orders` | Perform eligible order and payment administration |
| `manage-customers` | Maintain reusable customer identities |

Explicit policies protect `ProductCategory`, `Product`, `Stock`,
`StockMovement`, `Customer`, `Order`, `Payment`, `Register`, and `TillSession`.
They are registered by `CommerceServiceProvider`, avoiding reliance on root
namespace policy discovery.

Services assume their caller has already passed the applicable policy or route
middleware boundary. Public storefront placement remains intentionally free of
CMS permissions and will use dedicated validation, throttling, and signed guest
order access in its UI phase.

## Typed transaction vocabulary

The ordering context defines immutable data objects for:

- product ID and positive integer quantity requests;
- customer and fulfillment snapshots;
- server-owned placement options without client prices or totals;
- calculated line snapshots and complete cart totals;
- payment method, applied amount, tender, reference, and metadata.

Expected failures use module-specific exceptions:

- `CatalogException`
- `CustomerException`
- `InvalidCartException`
- `InsufficientStockException`
- `InvalidOrderTransitionException`
- `PaymentMismatchException`
- `TillSessionException`

These exceptions contain user-safe operational details. For example,
`InsufficientStockException` exposes product ULID and SKU rather than an
internal numeric route identifier.

## Calculation contract

`CartCalculator` accepts freshly fetched `Product` models and typed item
requests. It enforces publication state, publication time, minimum quantity,
maximum quantity, and product availability before calculating:

- active regular or sale unit price;
- line subtotal;
- proportional fixed discount allocation;
- deterministic residual-cent distribution by remainder and product ID;
- inclusive or exclusive tax in basis points;
- line total, order subtotal, tax, delivery, and payable total.

Multiplication and aggregation include integer-overflow guards. No persisted
money calculation uses floating-point database values. A delivery fee is valid
only for delivery fulfillment, and delivery orders require an address and city.

## Service ownership

| Service | Implemented responsibility |
| --- | --- |
| `ProductService` | Category/product creation and update, defaults, validation, cycle protection, actor fields, publication and archive transitions, initial zero-stock row, activity |
| `CustomerService` | Normalized reusable customer create/update/archive, actor fields, non-sensitive activity summaries |
| `InventoryService` | Product and stock row locking, manual adjustment, order commitment, cancellation restoration, projection and movement reconciliation |
| `CartCalculator` | Pure trusted price, discount, tax, delivery, and total calculation |
| `OrderNumberService` | Assign-once `WEB`/`POS` numbers derived from persisted IDs and date, avoiding race-prone count queries |
| `OrderService` | Web placement, POS hold, held-order repricing, POS order creation, immutable snapshots, numbering, stock orchestration, fulfillment transitions, unpaid web cancellation |
| `PaymentService` | Completed payment creation, split settlement projection, tender/change validation, metadata sanitization, POS till cash projection |
| `TillService` | Cashier/register serialization, one-open-session rules, float, authoritative cash recomputation, variance, held-order close guard |
| `PosCheckoutService` | Outer atomic transaction across order, line, stock, payment, till, and activity writes; exact settlement required |

Concrete services are resolved through Laravel's container without redundant
interfaces because they are final, stateless module-internal implementations.
Future external integrations should receive explicit contracts at the point a
second implementation actually exists.

## Transaction and locking order

The implemented lock order is stable:

1. Cashier user and register for till opening.
2. Till session for POS checkout or close.
3. Order header for order, payment, and cancellation operations.
4. Products sorted by integer ID.
5. One stock projection per tracked product.

Nested service transactions participate in the caller's outer Laravel
transaction. A failed stock check, payment mismatch, closed till, invalid hold,
or lifecycle transition therefore rolls back order headers, lines, payments,
movements, projections, till totals, and activity rows together.

## Enforced invariants

- Products are created as drafts with server defaults and a zero-stock
  projection.
- Category updates cannot create parent cycles or reference missing parents.
- Client prices and totals are never accepted by placement data.
- Published product state and quantity limits are revalidated at placement.
- Business order numbers are assigned once after persistence.
- Every tracked stock change produces one matching movement with exact before
  and after balances.
- Overselling is blocked unless `commerce.inventory.allow_oversell` is enabled.
- `StockMovement` rejects Eloquent updates and deletes as append-only history.
- A held order has no business number, placement timestamp, or stock commitment.
- Resuming a hold recalculates current products and prices while preserving its
  ID and ULID.
- Web cancellation restores committed stock once and is limited to unpaid,
  non-terminal orders while refunds are deferred.
- Completed POS sales cannot be silently cancelled.
- Completed payment sums drive `orders.paid_minor` and `payment_status`.
- Cash change is tender minus applied amount; it is not a negative payment.
- Non-cash tender must equal the applied payment amount.
- POS payments require the order's open till session.
- POS checkout requires exact settlement or rolls back in full.
- One cashier cannot open two sessions and one register cannot have two open
  sessions; both owner rows are locked before conflict inspection.
- A till cannot close while it owns held orders.
- Till close recomputes expected cash from opening float plus completed cash
  payments rather than trusting a stale projection.

## Activity and sensitive-data contract

Successful mutations use stable `commerce.*` activity names, including:

- `commerce.product.created`
- `commerce.product.status_changed`
- `commerce.stock.adjusted`
- `commerce.order.placed`
- `commerce.order.cancelled`
- `commerce.payment.completed`
- `commerce.pos.order_held`
- `commerce.pos.sale_completed`
- `commerce.till.opened`
- `commerce.till.closed`

Properties contain ULIDs, counts, enum states, and integer totals only. Payment
references and metadata are not copied into activities. Persisted payment
metadata is recursively bounded and redacts credential-like keys including
authorization, card number, CVV, PIN, password, private key, secret, and token.

Expected domain exceptions are not written as application errors. Unexpected
exceptions are not swallowed and continue through Laravel's exception and log
pipeline with the surrounding request or job context.

## Verification

Focused Commerce verification:

```text
php artisan test tests/Feature/Commerce
PASS  34 tests, 436 assertions
```

Complete application regression:

```text
php artisan test
PASS  154 tests, 1049 assertions
```

The suite covers permission aggregation, policies, inclusive/exclusive tax,
sale prices, deterministic discounts, catalog validation, customer lifecycle,
inventory reconciliation, insufficient-stock rollback, append-only movements,
web placement, business numbering, immutable snapshots, cancellation once,
paid-order cancellation rejection, state transitions, one-open-till rules,
split tenders, cash change, payment metadata redaction, underpayment rollback,
hold repricing, and till close guards.

A temporary PHP script bootstrapped Composer and Laravel with isolated array
cache and in-memory SQLite, ran `migrate:fresh`, then exercised catalog creation,
publication, opening stock, till opening, POS checkout, payment, stock movement,
cash change, and till close. It returned:

```json
{
    "order_number": "POS-20260716-00000001",
    "order_total_minor": 10000,
    "stock_balance": 4,
    "movement_count": 2,
    "cash_change_minor": 500,
    "till_variance_minor": 0
}
```

The temporary script was removed after verification and was never staged.

Additional integrity checks passed:

```text
vendor/bin/pint --dirty
composer validate --no-check-publish
php artisan view:cache
php artisan route:list --except-vendor
php artisan config:show commerce
php -l (all Commerce PHP files)
git diff --check
```

The route list remains at 43 host routes because the three Commerce route
files are intentionally unopened until their authorized interfaces exist.
This phase introduces no migration or schema change beyond the committed Phase
1 foundation.

## Deferred work

The following remain deliberately outside this backend phase:

1. Admin product/category, customer, inventory, order, payment, register, and
   till Livewire interfaces.
2. Product gallery upload and ordering UI.
3. Public storefront layout, catalog, product detail, session cart, checkout,
   signed confirmation, and tracking routes.
4. POS terminal, scanning interface, held-order browser, receipt, and reprint.
5. Storefront confirmation notification and PDF/order receipt adapters.
6. Returns, refunds, gateway callbacks, asynchronous payment confirmation,
   shipping carriers, multi-location stock, purchasing, and accounting.
7. Browser QA, accessibility review, and responsive light/dark validation for
   user-facing Commerce surfaces.

Every later mutation must call these services rather than writing order,
payment, stock, movement, or till state directly from Livewire or controllers.
