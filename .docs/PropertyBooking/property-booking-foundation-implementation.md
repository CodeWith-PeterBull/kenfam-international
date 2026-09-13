# Property Booking Foundation Implementation

## Record

- **Module:** `PropertyBooking`
- **Delivery phase:** Phase 1, foundation
- **Implementation branch:** `feature/property-booking-foundation`
- **Base branch:** `feature/laravel-aureon-base-engine`
- **Base commit:** `414c492`
- **Implemented:** 30 August 2026
- **Status:** Complete and verified; no user-facing routes are published yet

This record describes the implemented persistence and domain foundation for the
Aureon accommodation module. Read it with the master plan, data model,
architecture, workflows, diagrams, and verification plan in this directory.

## Delivered Scope

Phase 1 establishes an independent, configurable module that can support hotel,
apartment, short-stay house, and BnB operations without importing Commerce
models or services. The implementation includes:

- a module service provider and environment-backed configuration;
- 20 ordered, fully commented migrations;
- 17 Eloquent models, including the custom booking-guest pivot;
- 21 backed enums for persisted domain state;
- immutable ULIDs alongside internal integer primary keys;
- 16 relationship-aware model factories;
- pricing, availability, allocation, identity, and numbering services;
- typed contracts, DTOs, exceptions, permissions, roles, and policies;
- optional, deterministic, rerunnable demonstration seeders;
- media collection and conversion contracts for catalog imagery;
- empty, module-owned admin, storefront, and Point of Booking route roots;
- focused schema, model, service, authorization, toggle, factory, and ULID tests.

The phase deliberately does not publish CRUD pages, booking checkout, booking
lifecycle orchestration, reception shift workflows, payments, receipts, or
notifications. Those capabilities remain assigned to later delivery phases.

## Module Boundary

The implementation lives under:

```text
app/Modules/PropertyBooking/
|-- Availability/
|-- Bookings/
|-- Catalog/
|-- Config/
|-- Contracts/
|-- Database/
|-- Exceptions/
|-- Guests/
|-- PointOfBooking/
|-- Pricing/
|-- Resources/
|-- Routes/
|-- Support/
`-- PropertyBookingServiceProvider.php
```

The module does not reference `App\Modules\Commerce`. It reuses only legitimate
host contracts such as users, authorization, system activity, Spatie Media
Library, and Laravel framework facilities. This keeps later adoption or removal
possible without coupling accommodation records to product, order, or till
tables.

## Runtime Registration And Configuration

`PropertyBookingServiceProvider` is registered in `bootstrap/providers.php`.
When `PROPERTY_BOOKING_ENABLED=false`, it does not load module migrations,
views, routes, policy mappings, or service bindings. When enabled, it:

1. merges the module configuration;
2. loads module migrations and namespaced views;
3. loads the three module-owned route roots;
4. maps each aggregate to its policy;
5. binds `CalculatesBookingRates` to `BookingRateCalculator`;
6. binds `AllocatesUnits` to `UnitAllocationService`.

Configuration is defined in
`app/Modules/PropertyBooking/Config/property-booking.php`. `.env.example`
documents the switch, locale/currency defaults, timezone, turnover, tax,
booking expiry, search limits, media limits, number prefixes, identity HMAC key,
accepted payment methods, and receipt-print defaults.

## Persistence Inventory

Every business table uses an internal integer primary key and an immutable,
unique ULID for external references. Foreign keys remain integer-based. Every
migration field has an explanatory `->comment()` and all module tables use the
`property_booking_` prefix.

| Order | Table | Responsibility |
| --- | --- | --- |
| 1 | `property_booking_categories` | Hierarchical, publishable accommodation classifications |
| 2 | `property_booking_properties` | Property identity, address, locale, policies, contacts, and operating defaults |
| 3 | `property_booking_property_user` | Explicit user-to-property operating assignments |
| 4 | `property_booking_amenities` | Reusable property/unit feature definitions |
| 5 | `property_booking_property_amenity` | Property-level amenity assignments |
| 6 | `property_booking_unit_types` | Sellable room/house/apartment types, capacity, size, rules, and content |
| 7 | `property_booking_unit_type_amenity` | Unit-type amenity assignments |
| 8 | `property_booking_units` | Concrete numbered accommodation inventory and readiness state |
| 9 | `property_booking_rate_plans` | Base price, duration unit, occupancy, taxes, deposits, and restrictions |
| 10 | `property_booking_rate_overrides` | Non-overlapping date/time price and restriction overrides |
| 11 | `property_booking_availability_blocks` | Maintenance, housekeeping, owner-use, and manual unit closures |
| 12 | `property_booking_guests` | Protected guest identity, contact, preference, and audit-safe search data |
| 13 | `property_booking_registers` | Named Point of Booking endpoints and receipt defaults |
| 14 | `property_booking_shifts` | Receptionist ownership, cash position, close, and reconciliation snapshots |
| 15 | `property_bookings` | Booking aggregate, immutable guest/property snapshots, totals, and lifecycle state |
| 16 | `property_booking_stays` | One requested unit type, interval, occupancy, and pricing snapshot per stay line |
| 17 | `property_booking_unit_assignments` | Exact concrete-unit allocation and release history |
| 18 | `property_booking_guest_assignments` | Booking occupants and the guarded primary guest pivot |
| 19 | `property_booking_charges` | Immutable lodging, fee, tax, discount, damage, and adjustment ledger entries |
| 20 | `property_booking_payments` | Payment/refund records and gateway-ready references |

The exhaustive field contract, types, nullable rules, indexes, and foreign-key
semantics are maintained in `property-booking-data-model.md` and enforced by
`PropertyBookingSchemaTest`.

### Database guards

Nullable guard columns make three critical active-state invariants portable:

- one open shift per reception register;
- one active assignment for a concrete unit during a stay allocation record;
- one primary guest per booking.

Services set or clear those guard values inside transactions. Interval overlap
checks remain service-owned because a portable relational unique constraint
cannot represent half-open time-range overlap.

## Model And Media Contracts

All route-facing aggregate models use `HasUlid`, resolve routes by ULID, reject
ULID mass assignment, and prevent mutation after persistence. Models define
explicit tables, enum/date/decimal casts, relationships, soft deletion where
the domain permits archival, and conservative fillable boundaries.

Pricing snapshots, booking totals, protected identity material, allocation
guards, and other service-owned state cannot be mass-assigned.

Spatie Media Library collections are prepared as follows:

| Aggregate | Collections | Conversions |
| --- | --- | --- |
| Property category | `category_image` | Non-upscaled `thumb` at 480 x 360 |
| Property | `property_cover`, `property_gallery` | Non-upscaled `thumb`, `card`, and `detail` |
| Unit type | `unit_type_cover`, `unit_type_gallery` | Non-upscaled `thumb`, `card`, and `detail` |

The cover collections are single-file. Gallery limits and upload limits are
configuration-owned and will be enforced by Phase 2 Livewire forms.

## Foundation Services

### BookingRateCalculator

- accepts a typed rate plan, half-open interval, occupancy, and optional extras;
- resolves applicable date overrides and rejects ambiguous overlapping rules;
- supports nightly, daily, day-use, and hourly durations;
- rounds hourly duration upward to a whole billable hour;
- enforces plan capacity and minimum/maximum duration constraints;
- calculates using integer minor units and basis points, never binary floats;
- returns an immutable `BookingRateCalculation` DTO containing subtotal, tax,
  total, deposit, duration, and rate snapshot data.

### RateOverrideService

- creates, updates, and removes overrides transactionally;
- normalizes bounded date/time input;
- rejects overlapping override windows for the same rate plan;
- records sanitized activity without leaking confidential data.

### AvailabilitySearchService

- applies property, unit type, publish/readiness, capacity, and interval rules;
- uses half-open intervals so a departure and later eligible arrival can share a
  boundary only after configured turnover;
- excludes active assignments and active availability blocks;
- returns candidate concrete units for quote and allocation workflows.

### UnitAllocationService

- locks the requested stay, candidate units, and relevant allocation/block rows;
- allocates exact concrete units rather than decrementing a scalar inventory;
- enforces capacity, readiness, property ownership, requested quantity, overlap,
  and turnover constraints;
- is idempotent for an already fully allocated stay;
- releases assignments by clearing their active guards and recording timestamps;
- throws typed availability exceptions and rolls back partial allocations.

### AvailabilityBlockService

- creates bounded operational blocks against a concrete unit;
- rejects invalid intervals and collisions with active allocations or blocks;
- releases blocks without deleting their operational history.

### GuestService

- creates, updates, archives, and restores guest profiles transactionally;
- normalizes email, phone, country, and identity input;
- encrypts identity/passport numbers at rest;
- stores a keyed HMAC fingerprint for exact identity lookup;
- validates email and rejects future dates of birth;
- sends only redacted metadata to system activity records.

### BookingNumberService

- creates channel-aware booking references using configured web, POB, and admin
  prefixes;
- uses a deterministic padded internal identifier while public routing remains
  ULID-based.

Full booking placement/amendment/cancellation, shift lifecycle, check-in/out,
payment, receipt, and notification orchestration intentionally remain pending.

## Authorization

`PropertyBookingPermission` contributes 16 capabilities to the shared
`CmsPermission` catalogue. They cover the dashboard, properties, rates,
availability, bookings, guests, payments, POB access, shifts, check-in,
check-out, and unit readiness.

Four stable role names are module-owned:

- `property-booking-manager`
- `booking-receptionist`
- `booking-agent`
- `property-housekeeping`

Policies require both the relevant permission and explicit membership of the
record's property. Active system administrators retain the host-wide
`Gate::before` override. Inactive users are denied. Receptionists can operate
only their own assigned open shift; supervisors use `manage-reception-shifts`.
No new host `UserType` value was introduced.

## Optional Demonstration Fixture

The module seed graph is deliberately excluded from `DatabaseSeeder`. It can be
installed into a development database with:

```powershell
php artisan db:seed --class="App\Modules\PropertyBooking\Database\Seeders\PropertyBookingDemoSeeder" --no-interaction
```

The rerunnable graph provides:

- three accommodation categories;
- four amenities;
- one property;
- three unit types;
- seven concrete units;
- three rate plans;
- one reception register;
- one booking manager and one receptionist assigned to the property.

| Role | Email | Password |
| --- | --- | --- |
| Property booking manager | `booking.manager@aureon.test` | `password` |
| Booking receptionist | `receptionist@aureon.test` | `password` |

These accounts and records are for development only. The seeders use stable
business keys and update-or-create semantics so rerunning them does not duplicate
the graph. No root seeder was changed.

## Host Integration Files

Only three existing runtime files are changed:

- `.env.example` documents the module settings;
- `bootstrap/providers.php` registers the guarded module provider;
- `app/Support/CmsPermission.php` merges the module permission catalogue.

`README.md` and `.docs/PropertyBooking/` are updated as project documentation.

## Verification Evidence

The focused test command is:

```powershell
php artisan test tests\Feature\PropertyBooking
```

The Phase 1 gate currently passes **25 tests and 711 assertions**. Coverage
includes:

- exact columns for all 20 tables;
- a non-empty comment for every declared migration column;
- PHPDoc on every named module type and method;
- provider configuration and the disabled-module boundary;
- absence of Commerce namespace dependencies;
- open-shift, active-unit-assignment, and primary-guest guard constraints;
- all model factories and the rerunnable demonstration seeder;
- ULID generation, route lookup semantics, immutability, and mass-assignment
  protection;
- typed enum/date/model relationships and soft-delete behavior;
- media collection compatibility with integer polymorphic keys;
- encrypted identity storage, HMAC lookup, and privacy-safe activity context;
- integer pricing, overrides, tax, deposits, duration, and occupancy checks;
- exact allocation, exhaustion, idempotence, turnover, and release;
- permission catalogue, property scoping, shift ownership, and inactive-user
  denial.

An isolated in-memory `migrate:fresh` run has also applied the host schema and
all 20 module migrations successfully. A temporary Composer-autoloaded Laravel
container probe then ran the module seeder twice, confirmed unchanged catalog
counts and all four roles, and resolved both service contracts. The temporary
script was removed after the successful probe.

The complete application regression passes **262 tests and 2,568 assertions**.
Laravel production optimization succeeds for configuration, events, routes,
and views. The Vite production build and Pint both pass.

## Known Limits And Next Phase

SQLite verifies schema portability and deterministic service behavior but does
not emulate MySQL/PostgreSQL row-lock contention. Allocation concurrency must be
rerun against the deployment database before production release.

The next branch is `feature/property-booking-catalog-availability`. Its scope is
the authorized Livewire catalog, media, rate, block, quote, and availability
administration vertical slice. The three route roots remain empty until their
corresponding user-facing phase has complete authorization and tests.
