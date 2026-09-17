# TravelTours K2 Catalog Administration Implementation

Status: K2A and K2B committed previously. K2C implemented and locally verified
on 2026-09-17, uncommitted pending user review. K2D-K2F remain pending.

## 1. Increment Scope

K2A establishes the access and typed service boundary required before Livewire
catalog writes. K2B delivers the first complete staff-facing write surfaces for
tour categories and destinations. K2C adds tour basics and route assignments.
None of these increments alters departures, prices, availability, checkout,
booking desk operations, or public publication rules.

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

K2A exposed only the existing catalog overview. K2B registered category and
destination routes after their services, Forms, managers, views, authorization,
and tests existed. K2C registers tour create and edit; preview remains absent
until K2E supplies its governed publication and privacy behavior.

### Typed inputs precede Forms

K2B Livewire Forms normalize category and destination UI values into the K2A
DTOs. K2C TourForm and TourAssignmentForm now do the same for tours and routes.
Services accept those DTOs rather than request arrays. Route rows reject
unrecognized pivot identifiers before the service replaces assignments.

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

## 5. K2C Scope Boundary

K2C implements the paginated tour index, base tour editor, and category and
destination assignment services. It preserves typed DTO/service writes,
enforces exactly one primary category when assignments exist, validates active
targets and route sequence, and rejects client-supplied pivot identifiers.
It does not own itinerary/content children (K2D), publication/media readiness
(K2E), or any departure/pricing behavior (K3).

## 6. K2C Tour Catalog And Editor, Awaiting Review

### Delivered behavior

- `TourService` creates drafts and updates base tour metadata through an
  immutable `TourData` contract. It normalizes code, slug, optional text, and
  languages, validates participant/duration/coordinate boundaries, enforces
  retained-row uniqueness, and attributes writes to the acting user. It does
  not change publication status or publish a new tour.
- `TourAssignmentService` validates a complete category/destination replacement
  before deleting any existing pivot rows. Categories are distinct and have
  one primary when nonempty; destinations are distinct, active, ordered from
  one without gaps, and public when an already published tour depends on them.
  Client-provided pivot IDs and unknown row keys are rejected.
- `TourCatalog` exposes literal-safe name/code search, status/type/category/
  destination filters, stable bounded Bootstrap pagination, publication-state
  counts, cover thumbnails, and permission-aware edit/public actions. Drafts
  do not receive misleading public links.
- `TourEditor` uses module-owned Livewire Forms and typed services for Basics
  and Route. It reauthorizes on hydration and before writes, locks the tour ID,
  supports route sequencing, and shows only implemented tabs. New records
  redirect to their ULID edit URL; K2E still owns status transitions.
- The destination manager and catalog render original media when queued Spatie
  conversions are not yet available, preventing broken first-load images.
  Existing taxonomy/destination dialogs now use visible theme-aware close icons.

### Changed areas

- New services: `Catalog/Services/{TourService,TourAssignmentService}.php`.
- New Forms: `Catalog/Livewire/Forms/{TourForm,TourAssignmentForm}.php`.
- New components: `Catalog/Livewire/Admin/{TourCatalog,TourEditor}.php`.
- New page controller: `Catalog/Http/Controllers/TourEditorController.php`.
- New page/Livewire views under `Resources/views/admin/catalog/` and
  `Resources/views/livewire/admin/catalog/`; shared `admin.css` extended.
- `Routes/admin.php`, `TravelToursServiceProvider.php`, catalog controller,
  existing destination/category dialogs, package QA script registration, and
  catalog access test were updated.
- New focused suite `TravelToursTourEditorTest.php` and browser harness
  `scripts/qa-travel-tours-admin.mjs`.

### Verification evidence

| Gate | Result |
| --- | --- |
| `php artisan test tests/Feature/TravelTours/TravelToursTourEditorTest.php --compact` | 8 passed, 37 assertions |
| `php artisan test tests/Feature/TravelTours --compact` | 37 passed, 1,823 assertions |
| `php artisan test --compact` | 160 passed, 2,482 assertions |
| `php vendor/bin/pint --test app/Modules/TravelTours tests/Feature/TravelTours` | Passed |
| `php scripts/audit-travel-tours-docblocks.php` | Passed |
| `php scripts/probe-travel-tours-foundation.php` | 34 tables, 645 documented columns, no missing comments |
| `php artisan view:cache` | Passed |
| `npm.cmd run build` | Passed, eight static-copy targets; inherited runtime-resolved asset warnings remain |
| `npm.cmd run qa:travel-tours-admin` | Seven authenticated captures, no recorded runtime/network errors or failed assertions |

The browser harness used a newly created SQLite database under `storage/qa`,
seeded with the opt-in TravelTours demonstration catalog and operator roles.
It captured desktop 1440/1280, tablet 820, and mobile 390 widths in light/dark
modes, a reduced-motion route tab, an open category dialog, and a destination
media dialog with a visibly loaded cover. Evidence is under `qa/admin/`.
It did not migrate or reseed the development database. Browser QA did use the
host's existing public media disk, so future harness isolation should consider
a dedicated media root before repeated seeded runs.

The local PHP binary still reports the pre-existing Imagick/ImageMagick
1808/1810 warning. It did not fail the verified gates. No K2C commit or push
has been made; user review is the next gate. K2D itinerary/content work must
wait for that approval and the subsequent K2C commit.

## 7. Category Manager UX Enhancement, 2026-09-18

Status: implemented and locally verified; uncommitted. Scope is the committed
K2B category manager only. No destination, tour, pricing, or storefront file
changed.

### What changed and why

| Change | Rationale |
| --- | --- |
| Numeric `Order` column replaced by up/down arrows | Operators reorder by intent, not by editing integers. `TourCategoryService::move()` mirrors the module's existing content/itinerary ordering: service-owned transaction, siblings locked, swap with the neighbour, dense 1..n renumbering. Arrows are disabled at each boundary. The numeric field stays in the form as the manual override. |
| Hierarchy rendered depth-first with indentation | The previous flat `sort_order` sort interleaved levels, so a child could render above its own parent and the arrows appeared not to work. `categories()` now flattens the tree so siblings stay contiguous; each row carries `data-depth`. |
| Visibility became a switch that states its blocker | The switch keeps the service guard (active children, published tours) and shows the reason inline before the service refuses, closing UAT finding 8. `wire:click.prevent` stops the browser's optimistic flip so a refused change never leaves the control out of step with the server. |
| Eye icon now opens a read-only details dialog | Follows the parent's `openDetails()` + `Gate::authorize('view')` pattern. Shows identity, SEO, description, child categories, and assigned tours with publication state and an editor link where permitted. Viewers can inspect; only editors see the edit affordance. |
| Row actions laid out inline | Uses the existing `.travel-admin-row-actions` flex container; rows dropped from 77 px to 62 px. Closes UAT finding 5. |
| `novalidate` on the form | Parent parity; Livewire's inline error path now answers a blank submit instead of the browser tooltip. Closes UAT finding 2. |
| Focus moves into dialogs on open; `aria-invalid` and `aria-describedby` on invalid fields | Partial closure of UAT findings 6 and 7 for this component. Focus trapping and a live region remain K2F items. |
| Dark-mode close-button rule in `admin.css` | Carries the parent's `[data-bs-theme="dark"] .btn-close` filter fix. Closes UAT finding 3 for every `.travel-admin` dialog. |

### Files

- `Catalog/Services/TourCategoryService.php`: `move()`.
- `Catalog/Livewire/Admin/TourCategoryManager.php`: tree-ordered `categories()`,
  `positions()`, `selectedCategory()`, `openDetails()`, `move()`, `refreshHierarchy()`.
- `Resources/views/livewire/admin/catalog/tour-category-manager.blade.php`: rewritten.
- `Resources/assets/css/admin.css`: ordering, switch, detail-grid, tree, and dark close-button rules.
- `tests/Feature/TravelTours/TravelToursCategoryOrderingTest.php`: seven cases.

### Verification

```text
php vendor/bin/phpunit tests/Feature/TravelTours/TravelToursCategoryOrderingTest.php
OK (7 tests, 28 assertions)

php vendor/bin/phpunit tests/Feature/TravelTours
OK (64 tests, 1973 assertions)

php artisan test
187 passed (2632 assertions)

Pint, PHPDoc audit, view:cache, npm run build, git diff --check: pass
```

Browser evidence: `qa/catalog-taxonomy/category-manager-enhancement/` (nine
authenticated captures as tour editor and booking agent; tree order, move,
boundary arrows, refused hide, child hide, details, form validation a11y,
dark close button, mobile, and viewer isolation all asserted; zero assertion,
runtime, or network failures).

A defect was found and fixed during verification: the first switch
implementation let the browser flip the checkbox before the server answered,
so a refused hide showed "Active" beside an unchecked control. `.prevent`
resolved it and the harness asserts the restored state.

### Still open for this component

- Row actions sit off-screen on phones (UAT finding 4); a card layout below
  576 px is a K2F decision that should apply to every admin table at once.
- Focus trapping and a validation live region (UAT findings 6 and 7).
- Search and filter URL state does not apply here; the category table is not
  filtered.
