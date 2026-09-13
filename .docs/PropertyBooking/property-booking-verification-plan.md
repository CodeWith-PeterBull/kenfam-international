# Property Booking Verification And Delivery Gates

**Status:** Active mandatory verification contract; Phases 1 and 2 are complete.

## 1. Verification Principles

- Every implementation phase adds focused tests before it is committed.
- Every phase runs its focused tests, the complete regression suite, Pint, and
  an autoloaded temporary PHP/Tinker probe for the runtime contract it adds.
- Temporary probes live outside committed module code and are removed after
  their output is recorded in the phase implementation document.
- SQLite tests validate portable behavior, but row-lock contention receives an
  additional adopter-database probe because SQLite does not emulate
  MySQL/PostgreSQL row-level pessimistic locking.
- Browser QA uses opt-in demonstration data only in an isolated QA database.
  Verification never migrates, truncates, or reseeds the developer database.
- A phase is not complete when only the happy path renders; authorization,
  rollback, privacy, disabled-module, dark-mode, responsive, and accessibility
  behavior are part of the gate.

## 2. Planned Test Files

### Foundation/schema

```text
tests/Feature/PropertyBooking/PropertyBookingDisabledModuleTest.php
tests/Feature/PropertyBooking/PropertyBookingSchemaTest.php
tests/Feature/PropertyBooking/PropertyBookingModelTest.php
tests/Feature/PropertyBooking/PropertyBookingUlidTest.php
tests/Feature/PropertyBooking/PropertyBookingAuthorizationTest.php
tests/Feature/PropertyBooking/PropertyBookingFactoryTest.php
```

Coverage:

- provider/config/resource toggle and absent routes when disabled;
- every field, type, nullability, default, foreign key, unique/index, and
  migration comment expected by the data model;
- casts, fillable exclusions, relations, scopes, media collections, route keys,
  immutable ULIDs, soft deletion, and snapshot behavior;
- permission catalogue integration, system-admin override, inactive-user deny,
  assigned-property scope, receptionist restrictions, and cross-property deny;
- factories create valid graphs and do not bypass ULID/business invariants.

### Catalog/media/rates

```text
tests/Feature/PropertyBooking/PropertyCatalogServiceTest.php
tests/Feature/PropertyBooking/PropertyCatalogAdminTest.php
tests/Feature/PropertyBooking/PropertyMediaTest.php
tests/Feature/PropertyBooking/AccommodationUnitServiceTest.php
tests/Feature/PropertyBooking/RatePlanServiceTest.php
tests/Feature/PropertyBooking/BookingRateCalculatorTest.php
```

Coverage:

- property/category/amenity/unit-type/unit CRUD and archive restrictions;
- exact Livewire validation and authorization per action;
- image MIME/size/count, add/remove ownership, media order, and activity;
- occupancy and unit-type/property consistency;
- hourly/day-use/night calculations, local dates, tax inclusive/exclusive,
  occupancy extras, deposits, min/max duration, advance windows, closures,
  overrides, and no floating-point drift;
- DST boundary tests with an IANA timezone even though the default locale may
  not observe daylight saving.

### Availability and lifecycle

```text
tests/Feature/PropertyBooking/AvailabilitySearchTest.php
tests/Feature/PropertyBooking/UnitAllocationServiceTest.php
tests/Feature/PropertyBooking/AvailabilityBlockServiceTest.php
tests/Feature/PropertyBooking/BookingServiceTest.php
tests/Feature/PropertyBooking/BookingModificationTest.php
tests/Feature/PropertyBooking/BookingExpiryTest.php
tests/Feature/PropertyBooking/CheckInOutServiceTest.php
tests/Feature/PropertyBooking/UnitReadinessTest.php
```

Required edge cases:

- overlap on either side, full containment, equal interval, exact end/start
  boundary, block overlap, released assignment/block, inactive unit;
- two unit types and multiple units locked in deterministic order;
- stale read quote followed by conflicting placement;
- transaction rollback leaves no booking/stay/assignment/payment/activity;
- modify/extend with same unit, replacement unit, or no replacement;
- hold/pending expiry is idempotent and releases exactly once;
- cancellation/no-show releases exactly once;
- check-in requires confirmed/ready/assigned/timing/payment policy;
- checkout releases assignment, completes booking, and marks unit dirty;
- invalid transition leaves all timestamps and projections unchanged.

### Guest/payment/privacy

```text
tests/Feature/PropertyBooking/GuestServiceTest.php
tests/Feature/PropertyBooking/BookingChargeServiceTest.php
tests/Feature/PropertyBooking/BookingPaymentServiceTest.php
tests/Feature/PropertyBooking/PropertyBookingPrivacyTest.php
```

Coverage:

- normalized exact guest lookup, encrypted identity storage, deterministic
  fingerprint, duplicate prevention, and masked output;
- additional charge posting/voiding and aggregate recalculation;
- partial/full/split/cash-change/payment projection, duplicate reference,
  metadata sanitizer, mismatch rollback, and shift cash projection;
- logs, activity, mail, PDFs, public pages, JSON, exceptions, and receipts do
  not expose identity values, internal notes, payment metadata, signed URLs, or
  staff-only unit numbers.

### Storefront/documents/notifications

```text
tests/Feature/PropertyBooking/PropertyBookingStorefrontTest.php
tests/Feature/PropertyBooking/StorefrontBookingCheckoutTest.php
tests/Feature/PropertyBooking/BookingSignedAccessTest.php
tests/Feature/PropertyBooking/BookingDocumentAdapterTest.php
tests/Feature/PropertyBooking/PropertyBookingNotificationTest.php
```

Coverage:

- only published and truly available results;
- accessible filter/query behavior and session selection isolation;
- browser total/availability tampering rejected by server recalculation;
- guest creation/linking, booking placement, confirmation, and tracking;
- missing/expired/invalid signatures and booking ULID route binding;
- portrait/landscape adapter parity, institution/property branding, and
  privacy-safe content;
- after-commit dispatch, rollback silence, one event/one message, queue name,
  active/verified/property-scoped recipient isolation, and Mailpit delivery
  probe.

### Point of Booking

```text
tests/Feature/PropertyBooking/ReceptionRegisterTest.php
tests/Feature/PropertyBooking/ReceptionShiftServiceTest.php
tests/Feature/PropertyBooking/PointOfBookingInterfaceTest.php
tests/Feature/PropertyBooking/PobCheckoutServiceTest.php
tests/Feature/PropertyBooking/BookingReceiptAdapterTest.php
tests/Feature/PropertyBooking/BookingReceiptPrintingTest.php
```

Coverage:

- register CRUD and receipt-setting validation;
- one open shift per register/receptionist, ownership, expected cash, counted
  cash, signed variance, close idempotency, and threshold event;
- receptionist versus manager/system-admin access and cross-property denial;
- search/guest/selection/hold/resume/discard/payment UI actions;
- held booking repricing and ownership;
- exact split tender and all-or-nothing booking/payment/assignment transaction;
- browser/PDF/58/80 mm receipt parity, privacy, manual/auto-prompt instructions,
  and unauthorized receipt denial.

### Dashboard/reporting

```text
tests/Feature/PropertyBooking/PropertyBookingDashboardTest.php
tests/Feature/PropertyBooking/PropertyBookingReportExportTest.php
```

Coverage:

- real property-scoped values for arrivals, departures, in-house, occupancy,
  availability, readiness, blocks, revenue, balance, channels, and shifts;
- date-range/timezone boundaries and no N+1 regressions on bounded fixtures;
- action queues and links appear only with relevant permissions/routes;
- report filters, row adapters, portrait/landscape rendering, and privacy.

## 3. Temporary Runtime Probes

Each phase records one narrow probe, for example:

### Provider/resource probe

```bash
php artisan route:list --name=property-booking
php artisan about
```

Then boot a temporary autoloaded script twice with
`PROPERTY_BOOKING_ENABLED=true/false` and assert route/view registration.

### Availability probe

Inside one rolled-back transaction:

1. create property, unit type, two units, nightly rate, and one active assignment;
2. search overlapping and boundary-touching intervals;
3. assert overlap excludes one unit and exact boundary restores it;
4. attempt a conflicting allocation and assert domain exception;
5. rollback and assert no persistent records.

### POB probe

Inside a rolled-back transaction:

1. resolve seeded/temporary manager and receptionist capabilities;
2. open a real reception shift through `ReceptionShiftService`;
3. complete a booking with cash plus mobile-money tenders through
   `PobCheckoutService`;
4. assert booking, assignment, payment, expected cash, receipt adapter, and
   print instruction values;
5. close/reconcile and roll back.

### Notification/Mailpit probe

With sync queue and local Mailpit:

1. dispatch each implemented event through its real service boundary;
2. assert the expected listener/notification mapping and queue processing;
3. query Mailpit's local API/UI for recipient, subject, and count;
4. verify private values are absent;
5. report exact observed messages without committing the temporary script.

## 4. Command Gate Per Phase

Commands are adjusted to the phase, but the closeout baseline is:

```bash
php artisan optimize:clear
php artisan migrate:fresh --env=testing
php artisan test tests/Feature/PropertyBooking tests/Unit/PropertyBooking
php artisan test
vendor/bin/pint --test
php artisan route:list --name=property-booking
php artisan view:cache
php artisan config:cache
php artisan optimize:clear
npm run build
```

No production or current developer database is fresh-migrated/reseeded by
Codex during implementation unless the user explicitly requests it.

## 5. Browser And Visual QA Matrix

Dedicated Playwright-style scripts will be added as surfaces land:

```text
scripts/qa-property-booking-admin.mjs
scripts/qa-property-booking-storefront.mjs
scripts/qa-property-booking-pob.mjs
```

Minimum viewport/theme matrix:

| Surface | Desktop | Tablet | Mobile | Light | Dark | Print |
| --- | --- | --- | --- | --- | --- | --- |
| Admin dashboard/catalog/calendar/bookings | yes | yes | yes | yes | yes | reports |
| Storefront search/detail/checkout/signed pages | yes | yes | yes | yes | yes | booking summary |
| POB terminal/register/shift/receipt | yes | yes | yes | yes | yes | 58/80 mm and A4/PDF |

Checks include:

- no horizontal overflow, clipping, duplicate text, layout shift, or overlapping
  controls;
- usable date/time/occupancy controls and modal content at each viewport;
- visible focus, keyboard order, labels, landmarks, contrast, reduced motion,
  and Livewire status announcements;
- real property/unit images render with useful alt text and stable aspect ratios;
- light/dark badges, tables, menus, form controls, and print output remain
  legible;
- print output forces white paper, dark text, visible line items, and hides app
  theme/navigation/settings controls.

Screenshots are stored under a module-specific QA evidence directory and later
indexed by an adoption screenshot manifest.

## 6. Migration Verification Matrix

Before release:

- fresh migration and complete rollback on SQLite;
- upgrade migration from the base engine database without module tables;
- module-enabled and module-disabled config-cache boots;
- MySQL/MariaDB fresh migration on the intended Hostinger-compatible version;
- foreign-key/index/comment inspection on adopter database;
- lock-contention probe with two concurrent connections;
- rollback of the most recent module migration in isolation;
- no migration modifies or depends on Commerce tables.

## 7. Phase Documentation And Commit Gate

Every phase adds or updates a normal implementation record under
`.docs/PropertyBooking/` containing:

- base commit and branch;
- reviewed source files/contracts;
- planned versus delivered scope;
- exact files added/changed;
- migrations/models/services/components/routes/assets implemented;
- authorization, privacy, logging, and error-handling decisions;
- focused/full test and probe commands with observed results;
- browser screenshots and residual risks;
- deferred work and next-phase prerequisites.

Only then is the phase committed with a conventional commit and its branch
eligible for merge.

## 8. Final Release Gate

The module is adoption-ready only when:

- all six master phases pass;
- fresh install, upgrade, disabled boot, and rollback are documented;
- all route/action policies and property scopes are proven;
- overlap and lock-contention behavior is proven on the deployment database;
- public checkout, POB, arrival, departure, documents, notifications, and shift
  reconciliation pass end-to-end;
- full regression, static syntax, Pint, Vite, route/view/config caches, browser,
  accessibility, theme, print, and PDF checks pass;
- adoption, operations, glossary/scenarios, extension, and screenshot documents
  are complete;
- no code under `App\Modules\PropertyBooking` imports
  `App\Modules\Commerce` and no module implementation leaked into global
  domain folders.
