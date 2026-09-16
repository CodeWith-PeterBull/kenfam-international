# TravelTours K2 Catalog Administration Implementation

Status: K2A and K2B implemented and verified on the phase branch, 2026-09-16.
K2C-K2F remain pending.

## 1. Increment Scope

K2A establishes the access and typed service boundary required before Livewire
catalog writes. K2B delivers the first complete staff-facing write surfaces for
tour categories and destinations. Neither increment alters departures, prices,
availability, checkout, booking desk operations, or public publication rules.

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

K2B adds:

- `TourCategoryService` with DTO-only create/update input, normalized identity,
  unique slug checks, locked hierarchy traversal, cycle prevention, active-parent
  enforcement, and dependency-aware activation;
- `DestinationService` with DTO-only create/update input, actor attribution,
  hierarchy-level and country consistency, ISO country normalization, IANA
  timezone validation, coordinate pairing/ranges, locked cycle checks, and
  draft/review editorial-state ownership;
- `CatalogMediaService` for destination cover and ordered gallery writes,
  configured upload/count constraints, required alternative text, optional
  captions, metadata maintenance, ownership, and reorder completeness;
- Livewire 4 category and destination Forms that translate validated UI values
  into the K2A immutable DTOs;
- policy-authorized manager components with per-request `viewAny`, per-action
  authorization, locked selected identifiers, pagination, filters, accessible
  dialogs, and user-safe domain failures;
- module-owned dashboard pages, routes, component aliases, role/admin sidebar
  links, Aureon token-based light/dark styles, and Vite registration;
- focused service, media, route, role, and Livewire test coverage.

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

### Routes follow completed behavior

K2A exposed only the existing catalog overview. K2B registers category and
destination routes after their services, Forms, managers, views, authorization,
and tests exist. Tour-create, tour-edit, and preview routes remain asserted
absent until K2C/K2E provide their complete behavior.

### Typed inputs precede Forms

K2B Livewire Forms normalize category and destination UI values into the K2A
DTOs. K2C Forms will do the same for tours. Services accept those DTOs rather
than request arrays. Relational assignments use documented array shapes pending
service-owned normalization in K2C.

### Metadata editing cannot publish

Destination Forms expose draft and review transitions only. A previously
published or archived destination can retain that state while authorized staff
correct metadata, but the form renders the state as read-only. The service
rejects any promotion, demotion, or archive transition. K2E owns readiness,
preview, publication, unpublication, and archive behavior.

### Activation is a retained-state operation

K2B does not destructively delete categories or destinations. A category cannot
be hidden while active children or published tours depend on it. A destination
cannot be hidden while published, while active children depend on it, or while
published tours use it. Editing the active switch is checked by the same service
invariant as the dedicated toggle action, so Forms cannot bypass dependency
rules.

### Media ownership is service-enforced

Spatie Media Library remains the storage engine, but Livewire never calls it
directly. The service resolves every existing image through the selected
destination relation. Cross-destination IDs, incomplete reorder lists,
unsupported files, oversized uploads, missing alt text, and gallery overflow
produce user-safe catalog failures.

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

K2B implementation:

- `Catalog/Services/{TourCategoryService,DestinationService,CatalogMediaService}.php`
- `Catalog/Livewire/Forms/{TourCategoryForm,DestinationForm}.php`
- `Catalog/Livewire/Admin/{TourCategoryManager,DestinationManager}.php`
- `Catalog/Http/Controllers/{TourCategoryAdminController,DestinationAdminController}.php`
- `Resources/views/admin/catalog/{categories,destinations}.blade.php`
- `Resources/views/livewire/admin/catalog/{tour-category-manager,destination-manager}.blade.php`
- `Resources/assets/css/admin.css`
- `Routes/admin.php`, `TravelToursServiceProvider.php`, and `vite.config.js`
- admin and role-aware sidebar navigation
- `tests/Feature/TravelTours/TravelToursCatalogManagementTest.php`

The former root `Policies/TourPolicy.php` moved into its owning Catalog context.

## 4. Verification

Focused K2A/K2B evidence:

```text
php artisan test tests/Feature/TravelTours/TravelToursCatalogAccessTest.php \
  tests/Feature/TravelTours/TravelToursCatalogManagementTest.php
PASS: 9 tests, 62 assertions
```

Module/full regression, formatting, documentation audit, Blade, routes, and
diff checks:

```text
php artisan test tests/Feature/TravelTours --compact
PASS: 29 tests, 1,784 assertions

php artisan test --compact
PASS: 152 tests, 2,443 assertions

php vendor/bin/pint --test app/Modules/TravelTours tests/Feature/TravelTours
PASS

php scripts/audit-travel-tours-docblocks.php
PASS

php artisan view:cache
PASS

php artisan route:list --name=travel-tours.admin.catalog
PASS: overview, categories, and destinations routes registered; no premature
tour-create, tour-edit, or preview route

npm.cmd run build
PASS: production assets built in 40.09 seconds; Travel admin CSS emitted as a
separate manifest entry and all eight static-copy targets completed
```

The local PHP runtime continues to report the known Imagick 1808/1810 binary
version warning. K2B media conversion and tests still complete successfully,
but production media acceptance remains conditional on environment alignment.

Authenticated multi-viewport browser acceptance is intentionally not claimed
by K2B. It remains a named K2F gate after the tour editor exists, avoiding a
throwaway harness that cannot inspect the full catalog workflow.

## 5. Next Increment: K2C

K2C implements the paginated tour index, base tour editor, and category and
destination assignment services. It must preserve typed DTO/service writes,
enforce exactly one primary category when assignments exist, validate related
IDs and destination order against the current tour, and reject cross-tour or
cross-resource tampering. It does not yet own itinerary/content children (K2D),
publication/media readiness (K2E), or any departure/pricing behavior (K3).
