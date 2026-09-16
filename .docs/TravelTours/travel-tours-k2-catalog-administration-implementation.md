# TravelTours K2 Catalog Administration Implementation

Status: K2A implemented locally, 2026-09-16. K2B-K2F remain pending.

## 1. Increment Scope

K2A establishes the access and typed service boundary required before Livewire
catalog writes. It does not create placeholder manager pages and does not alter
the public catalog, departures, prices, availability, checkout, or booking desk.

Implemented:

- `publish-travel-catalog` as a distinct capability from draft maintenance;
- manager/editor/booking-agent role grants aligned with the K2 access matrix;
- context-owned policies for categories, destinations, and tours;
- separate publish/unpublish authorization for destinations and tours;
- policy registration for all three K2 aggregate roots;
- an authorization-scoped query service for staff catalog collections;
- controller use of the query service rather than an unguarded model query;
- immutable category, destination, tour, and assignment DTOs;
- named catalog, hierarchy-cycle, and publication-readiness exceptions;
- a focused K2A feature suite covering roles, policies, routes, queries, and
  typed inputs.

## 2. Decisions

### Catalog scope is capability-based

The schema has no tenant, organization, or per-editor ownership field. K2A does
not invent one. A user holding `view-travel-catalog` may inspect the staff
catalog collection. Write and publish actions are independently authorized.
If a future adopter introduces tenancy, it requires an explicit host/module
contract and additive persistence rather than an undocumented query filter.

### Editing does not imply publishing

Tour editors hold `view-travel-catalog` and `manage-travel-catalog`. Travel
managers additionally hold `publish-travel-catalog`. Booking agents retain
view-only catalog access. The host system-administrator bypass remains the
single global super-user mechanism.

### No placeholder routes

The existing catalog overview is the only implemented administration route in
K2A. Category, destination, tour-create, tour-edit, and preview routes are
asserted absent until K2B/K2C supply complete Livewire components and tests.

### Typed inputs precede Forms

Livewire Forms arriving in K2B/K2C will normalize UI values into the K2A DTOs.
Services will accept those DTOs rather than request arrays. Relational
assignments use documented array shapes pending service-owned normalization in
K2C.

## 3. Files

Primary implementation:

- `Catalog/Data/{TourCategoryData,DestinationData,TourData,TourAssignmentData}.php`
- `Catalog/Exceptions/{CatalogException,HierarchyCycle,PublicationBlocked}.php`
- `Catalog/Policies/{TourCategoryPolicy,DestinationPolicy,TourPolicy}.php`
- `Catalog/Services/CatalogQueryService.php`
- `Catalog/Http/Controllers/CatalogAdminController.php`
- `Support/TravelToursPermission.php`
- `Database/Seeders/TravelToursAccessSeeder.php`
- `TravelToursServiceProvider.php`
- `tests/Feature/TravelTours/TravelToursCatalogAccessTest.php`

The former root `Policies/TourPolicy.php` moved into its owning Catalog context.

## 4. Verification

Focused K2A evidence:

```text
php artisan test tests/Feature/TravelTours/TravelToursCatalogAccessTest.php --compact
PASS: 5 tests, 33 assertions
```

Module/full regression, formatting, documentation audit, Blade, routes, and
diff checks:

```text
php artisan test tests/Feature/TravelTours --compact
PASS: 25 tests, 1,755 assertions

php artisan test --compact
PASS: 148 tests, 2,414 assertions

php vendor/bin/pint --test app/Modules/TravelTours tests/Feature/TravelTours
PASS

php scripts/audit-travel-tours-docblocks.php
PASS

php artisan view:cache
PASS

php artisan route:list --name=travel-tours.admin --except-vendor
PASS: five implemented administration routes; no premature K2B/K2C routes
```

The local PHP runtime continues to report the known Imagick 1808/1810 binary
version warning. K2A performs no image conversion, and the warning does not
make its test or compilation gates fail.

## 5. Next Increment: K2B

K2B implements category and destination services, Livewire 4 Forms and manager
components, media handling, hierarchy validation, geographic validation, and
their dashboard routes. It must use the K2A DTOs, policies, query service, and
exceptions. It must not add tour editing, departure writes, or pricing writes.
