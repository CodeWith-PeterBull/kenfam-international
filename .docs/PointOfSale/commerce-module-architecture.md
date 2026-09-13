# Commerce Module Architecture Contract

**Status:** Approved implementation constraint for the POS and ecommerce module.

## Purpose

Commerce must remain traceable, removable, and adaptable like a first-party
plugin. The host application should know only that a Commerce service provider
is registered. Domain implementation must not be scattered across global
`app/Models`, `app/Livewire`, `app/Http/Controllers`, `resources/views`, or the
root `routes/web.php`.

## Canonical Root

```text
app/Modules/Commerce/
|-- CommerceServiceProvider.php
|-- Config/
|   `-- commerce.php
|-- Contracts/
|-- Support/
|   |-- Casts/
|   |-- Concerns/
|   `-- Data/
|-- Database/
|   |-- Factories/
|   |-- Migrations/
|   `-- Seeders/
|-- Routes/
|   |-- admin.php
|   |-- pos.php
|   `-- storefront.php
|-- Catalog/
|   |-- Enums/
|   |-- Models/
|   |-- Services/
|   |-- Http/Controllers/
|   `-- Livewire/
|-- Inventory/
|   |-- Enums/
|   |-- Models/
|   |-- Exceptions/
|   |-- Services/
|   `-- Livewire/
|-- Customers/
|   |-- Models/
|   |-- Services/
|   `-- Livewire/
|-- Orders/
|   |-- Enums/
|   |-- Models/
|   |-- Exceptions/
|   |-- Services/
|   |-- Notifications/
|   |-- Http/Controllers/
|   `-- Livewire/
|-- PointOfSale/
|   |-- Enums/
|   |-- Models/
|   |-- Exceptions/
|   |-- Services/
|   |-- Http/Controllers/
|   `-- Livewire/
|-- Storefront/
|   |-- Http/Controllers/
|   `-- Livewire/
`-- Resources/
    |-- views/
    |   |-- admin/
    |   |-- pos/
    |   |-- storefront/
    |   `-- livewire/
    |-- css/
    `-- js/
```

Root-level tests remain in `tests/Feature/Commerce` and `tests/Unit/Commerce` so
the existing PHPUnit discovery needs no custom runner. Module documentation
remains in `.docs/PointOfSale`.

## Provider Contract

`CommerceServiceProvider` is registered in `bootstrap/providers.php` and owns:

- `mergeConfigFrom()` for module configuration;
- `loadMigrationsFrom()` for module migrations;
- `loadViewsFrom()` under the `commerce::` namespace;
- loading separate admin, POS, and storefront route files;
- future interface bindings and Livewire component registration;
- optional publishing tags for adopters, without requiring published copies in
  ordinary operation.

The provider contains no domain decisions or mutations.

## Route Isolation

Route files have distinct ownership:

- `Routes/admin.php`: authenticated dashboard catalog, inventory, customer,
  order, and payment administration with Spatie permission middleware.
- `Routes/pos.php`: authenticated POS and till workflows with POS permissions.
- `Routes/storefront.php`: public catalog/cart/checkout routes and signed order
  confirmation/tracking routes.

Every route has a stable `commerce.*` name. Models bind by ULID for
administration, by slug for public catalog readability, and by ULID plus signed
URL authorization for guest order access.

## Namespace Contract

Bounded-context namespaces mirror folders exactly. Examples:

```php
App\Modules\Commerce\Catalog\Models\Product
App\Modules\Commerce\Inventory\Services\InventoryService
App\Modules\Commerce\Orders\Livewire\Storefront\Checkout
App\Modules\Commerce\PointOfSale\Http\Controllers\TerminalController
```

Cross-context access happens through services or explicit contracts. A Livewire
component may read query models but must call a service for transactional
mutation.

## Documentation Standard

- Every PHP file begins with `declare(strict_types=1);` after the opening tag.
- Every class has a docblock explaining ownership and important invariants.
- Public/protected methods have useful docblocks when intent, collection shape,
  side effects, or exceptions are not obvious from types.
- Array payloads use PHPStan/Psalm-compatible array-shape or generic annotations.
- Migrations describe every column through `->comment()`.
- Complex transactions receive short comments at lock/recalculation boundaries,
  not line-by-line narration.
- Enum cases use descriptive names and stable string values.

## Exception and Logging Standard

Expected domain failures use context-specific exceptions, for example:

- `InsufficientStockException`
- `InvalidOrderTransitionException`
- `TillSessionException`
- `PaymentMismatchException`

Components translate expected exceptions into concise user-safe validation or
management errors. Services do not catch an exception merely to rethrow it.

Unexpected failures pass through Laravel's exception pipeline. Where additional
operational context is valuable, log one structured event with resource ULIDs,
channel, operation, and exception class. Do not log full customer addresses,
notes, card data, OTPs, or payment-provider payloads.

Successful mutations are not application-error log entries. They use the
existing `RecordsSystemActivity` contract with stable activity names such as:

- `commerce.product.created`
- `commerce.stock.adjusted`
- `commerce.order.placed`
- `commerce.pos.sale_completed`
- `commerce.till.closed`

Activity properties contain changed field names and safe aggregate summaries,
not sensitive request bodies.

## Model and Persistence Standard

- Integer primary keys and integer foreign keys remain canonical internally.
- Separate immutable ULIDs identify externally addressable resources.
- Fillable arrays contain business input only; ULID, totals, balance projections,
  status timestamps, and actor fields are service controlled.
- Money is persisted as integer minor units.
- Models define casts, relationships, scopes, media collections, and derived
  attributes, but do not orchestrate multi-model writes.
- Database transactions, row locks, totals, status transitions, and activity
  recording belong to services.
- Stock movements and placed order-item snapshots are append-only by service
  contract.

## Phase Review Gate

Each implementation phase must provide:

1. an updated `.docs/PointOfSale` implementation record;
2. focused model/service/Livewire feature tests;
3. a full application regression run;
4. a temporary autoloaded PHP verification script or Tinker probe for the new
   runtime contracts;
5. Pint, migration, route, view-cache, and build verification as applicable;
6. a scope audit showing that implementation files remain under the module;
7. a conventional commit containing only that phase.
