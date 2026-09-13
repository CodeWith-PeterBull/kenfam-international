# Property Booking Catalog And Availability Implementation

## 1. Delivery Identity

- **Module:** `App\Modules\PropertyBooking`
- **Phase:** 2, catalog and availability administration
- **Branch:** `feature/property-booking-catalog-availability`
- **Foundation commit:** `b9370b8 feat(property-booking): establish modular booking foundation`
- **Status:** Complete; implementation, CRUD media closeout, fixtures, and all Phase 2 acceptance gates passed
- **Phase 2 data safety:** only the opt-in module demonstration seeders were extended; `DatabaseSeeder` and the developer database were not migrated, fresh-migrated, or reseeded during Phase 2

This phase converts the Phase 1 persistence and policy foundation into an
authorized administration vertical slice. It intentionally does not place a
booking, hold inventory, create a guest, collect payment, or publish public/POB
routes.

## 2. Delivered Workspaces

| Route | Permission | Livewire managers |
| --- | --- | --- |
| `/admin/accommodation/properties` | `view-booking-properties` | property and hierarchical category managers |
| `/admin/accommodation/amenities` | `view-booking-properties` | reusable amenity manager |
| `/admin/accommodation/units` | `view-booking-properties` | sellable unit-type and concrete-unit managers |
| `/admin/accommodation/rates` | `view-booking-rates` | rate-plan and non-overlapping override managers |
| `/admin/accommodation/availability` | `view-booking-availability` | advisory quote and concrete-unit block manager |

Every mutation reauthorizes through its policy. Non-global operators query only
explicitly assigned property IDs through `PropertyAccessService`. The active
system administrator and users with `manage-booking-properties` retain global
portfolio scope. Sidebars also check the module flag and `Route::has()` so a
disabled module cannot leave dead links.

## 3. Catalog Services

The phase adds module-owned services for categories, amenities, properties,
unit types, concrete units, and rate plans. They normalize input, lock aggregate
roots where concurrent writes matter, reject invalid lifecycle changes, record
system activity, and return refreshed typed models.

Important invariants include:

- category parents must exist and may not create self-links or cycles;
- published properties prevent their category from being hidden;
- amenity scope cannot be narrowed while incompatible assignments remain;
- property country, currency, timezone, coordinates, and operating windows are
  validated again at the service boundary;
- property assignment accepts only active users and exactly one optional
  assigned default user;
- property/unit-type publication and archive actions preserve active booking
  and reception-shift dependencies;
- unit types exhaustively validate occupancy and bed configuration;
- concrete units cannot cross property/type ownership, deactivate/archive with
  active assignments, or skip the documented readiness state machine;
- rate plans preserve exact integer minor units, property currency, occupancy,
  duration, advance, tax, cancellation, and exactly one deposit representation;
- rate overrides use property-local half-open date ranges and reject overlaps.

## 4. Media Contract

Spatie Media Library remains the persistence owner. Phase 2 exposes:

- one replaceable `category_image`;
- one replaceable `property_cover` and an ordered `property_gallery`;
- one replaceable `unit_type_cover` and an ordered `unit_type_gallery`.

JPEG, PNG, and WebP MIME types and the configured upload size are enforced by
both Livewire and domain services. Alternative text is required, captions are
bounded, collection limits are configuration-driven, and reorder/update/remove
actions accept only media owned by the selected aggregate. Metadata and media
actions produce property-booking system activity records. No raw public file
path is accepted from the browser.

Property and unit-type create/edit dialogs accept a cover and multiple gallery
files directly. Each pending gallery image has its own editable alternative
text and caption, while a wide stable preview canvas shows the pending or
current cover. The separate Manage images workspace remains authoritative for
post-save metadata changes, ordering, replacement, and deletion. Concrete
numbered units intentionally inherit the photography of their sellable unit
type instead of duplicating media across every room record.

## 5. Demonstration Fixtures

`PropertyBookingDemoSeeder` remains optional, root-seeder independent, and safe
to rerun. Its catalog segment now attaches five optimized WebP photographs from
`Resources/demo/accommodation/` to category, property-cover, property-gallery,
and unit-type-cover collections. Usable adopter replacements are preserved on
rerun; only absent or physically missing demo collections are repaired.

`PropertyBookingOperationsDemoSeeder` adds deterministic records across every
operational model: three rate overrides, one active availability block, two
guests, one reconciled reception shift, one confirmed web booking, one
completed POB booking, stay snapshots, primary-guest pivots, active/released
unit assignments, an ancillary charge, and completed payments. The fixtures
exercise future availability and historical reception workflows without
leaving a register or receptionist locked in an open shift.

### Demonstration setup commands

Run the module migrations additively, then call the single aggregate demo
seeder. The seeder is independent of the root `DatabaseSeeder` and is designed
to reconcile its stable fixtures when rerun.

```powershell
php artisan migrate --no-interaction
php artisan db:seed --class="App\Modules\PropertyBooking\Database\Seeders\PropertyBookingDemoSeeder" --no-interaction
```

The aggregate command invokes `PropertyBookingAccessDemoSeeder`,
`PropertyBookingCatalogDemoSeeder`, and `PropertyBookingOperationsDemoSeeder`
in that order. Use `migrate:fresh` only for a deliberately disposable database;
it is not part of the normal demo adoption command.

During the Phase 3 continuation, the same additive migration and aggregate
seeder commands were run against the currently configured database at the
user's explicit request. No destructive reset and no edit to the Phase 2
seeders was made.

## 6. Availability And Quote Semantics

`BookingQuoteService` composes the existing authoritative
`BookingRateCalculator` and `AvailabilitySearchService`. A quote contains the
property, unit type, rate plan, UTC interval, occupancy, current concrete-unit
count, exact price/tax/deposit projection, generation time, and configured
expiry time.

The quote is advisory and side-effect free. It does not create a booking,
assignment, hold, block, guest, or payment. Future placement must recalculate
the rate and allocate a concrete unit inside its own transaction.

`AvailabilityBlockService` locks the concrete unit, rejects active assignment
or active block overlap, persists a typed reason and optional internal note,
and retains released records as audit history. Availability search applies the
property turnover buffer and includes only active, ready concrete units.

## 7. Livewire And Presentation

Typed Livewire form objects own validation and normalization. Locked component
IDs, per-request `boot()` authorization, computed property-scoped queries,
Bootstrap pagination, file-upload validation, explicit loading states, and
service-only writes follow the established Aureon/Livewire 4 patterns.

`Resources/css/admin.css` uses only Aureon theme tokens for surfaces, text, and
borders. It defines stable statistic cards, restrained property cards,
responsive filter grids, contained table overflow, ordered galleries, quote
feedback, modal forms, dark-mode badges, visible focus, and reduced-motion
behavior. `Resources/js/admin.js` restores Bootstrap tooltips after Livewire
navigation. Both are explicit Vite inputs.

## 8. Host Integration

The only host-level edits are the approved integration points:

- `vite.config.js` for module CSS/JS inputs;
- admin and role sidebars for guarded Accommodation links;
- `.env.example` for `PROPERTY_BOOKING_QUOTE_MINUTES`;
- `package.json` for `qa:property-booking`;
- `README.md` for routes, commands, and current phase status.

Controllers, routes, Livewire aliases, views, CSS, JavaScript, services, forms,
and domain exceptions otherwise remain inside `App\Modules\PropertyBooking`.
The no-Commerce-import architecture test remains mandatory.

## 9. Verification Coverage

Focused Phase 2 tests cover:

- route authentication and exact read permissions;
- view-only mutation denial and system-administrator operations;
- Livewire category, amenity, property, unit-type, concrete-unit, rate-plan,
  rate-override, and availability-block creation;
- category cycles, amenity scope, publication, readiness, currency, occupancy,
  deposit, and activity invariants;
- create/edit and post-save Livewire cover/gallery uploads, per-image accessible
  metadata, ownership, complete ordering, collection count, MIME, and size boundaries;
- complete optional fixture counts, media attachment, operational relations,
  active/released assignment examples, and rerun idempotence;
- expiring quote output, exact rates, no persistence side effects, current
  concrete availability, active-block overlap, release, and replacement;
- disabled module route, view, alias/binding boundary;
- module PHPDoc, schema comments, ULID, model, factory, authorization, guest
  privacy, rate calculator, and allocation regression.

Commands used by the phase gate:

```powershell
php artisan test tests/Feature/PropertyBooking --compact
php artisan test --compact
vendor\bin\pint --test
php artisan route:list --name=property-booking
php artisan config:cache
php artisan route:cache
php artisan view:cache
npm.cmd run build
npm.cmd run qa:property-booking
```

Browser evidence and machine-readable diagnostics are written to
`.docs/PropertyBooking/qa/catalog-availability/`.

Final observed results:

| Gate | Result |
| --- | --- |
| Focused Property Booking suite | 39 tests, 895 assertions passed |
| Full application regression | 276 tests, 2,752 assertions passed |
| Pint | Module and focused tests passed `--test` |
| Laravel compilation | Configuration, routes, and Blade views cached successfully; caches then cleared |
| Route boundary | Five protected `property-booking.admin.*` routes registered |
| Vite production build | Passed with module-owned CSS and JavaScript entries |
| Runtime service and fixture probes | Quote, exact money, concrete availability, and block persistence passed before rollback; isolated fixture storage exposed 11 media records, one property cover, four property gallery images, three unit-type covers, and zero missing files |
| Chromium browser QA | Eight captures passed across desktop, tablet, mobile, light, dark, reduced motion, property CRUD media, and unit-type CRUD media |
| Browser diagnostics | Seeded property cover, four-image gallery, and unit-type cover rendered; zero overflow, shell overlap, sidebar occlusion, duplicate IDs, unlabeled controls, runtime errors, or failed network responses |

The browser run used a disposable isolated SQLite database populated through
the existing root and module demonstration seeders. The developer database was
not migrated, fresh-migrated, or reseeded.

## 10. Deferred To Phase 3 And Later

- public property search, listing/detail, session selection, and checkout;
- booking/guest creation, quote acceptance, locked placement, and signed pages;
- booking confirmation mail and booking summary documents;
- reception registers, shifts, full-width Point of Booking, tenders, and
  receipt printing;
- booking operations, check-in/out, dashboards, alerts, and adoption reports.

The next branch remains `feature/property-booking-storefront`. It must consume
the Phase 2 quote as disposable feedback and must never treat quote expiry or
available count as a reservation.
