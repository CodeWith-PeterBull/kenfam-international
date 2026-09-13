# Property Booking Module Architecture Contract

**Status:** Implemented through the Phase 2 administration boundary; later route surfaces remain constrained by this architecture.

## 1. Ownership Boundary

All domain implementation lives below `app/Modules/PropertyBooking`. The host
application registers one provider and contributes only the unavoidable shared
integration edits: provider registration, permission catalogue composition,
dashboard navigation, Vite entries, environment examples, and root docs.

The module must not place its models, controllers, Livewire components, views,
migrations, routes, notifications, or assets in global application folders.

## 2. Canonical Folder Structure

```text
app/Modules/PropertyBooking/
|-- PropertyBookingServiceProvider.php
|-- Config/
|   `-- property-booking.php
|-- Contracts/
|   |-- AllocatesUnits.php
|   |-- CalculatesBookingRates.php
|   `-- PrintsBookingReceipts.php
|-- Support/
|   |-- Concerns/HasUlid.php
|   |-- Data/
|   |-- MoneyFormatter.php
|   |-- ScaledDecimal.php
|   |-- PropertyBookingPermission.php
|   `-- PropertyBookingRole.php
|-- Database/
|   |-- Factories/
|   |-- Migrations/
|   `-- Seeders/
|-- Routes/
|   |-- admin.php
|   |-- pob.php
|   `-- storefront.php
|-- Catalog/
|   |-- Data/
|   |-- Enums/
|   |-- Exceptions/
|   |-- Http/Controllers/
|   |-- Livewire/Admin/
|   |-- Livewire/Forms/
|   |-- Models/
|   |-- Policies/
|   `-- Services/
|-- Pricing/
|   |-- Data/
|   |-- Enums/
|   |-- Exceptions/
|   |-- Models/
|   `-- Services/
|-- Availability/
|   |-- Data/
|   |-- Enums/
|   |-- Exceptions/
|   |-- Models/
|   |-- Policies/
|   `-- Services/
|-- Guests/
|   |-- Data/
|   |-- Exceptions/
|   |-- Livewire/Admin/
|   |-- Models/
|   |-- Policies/
|   `-- Services/
|-- Bookings/
|   |-- Data/
|   |-- Enums/
|   |-- Events/
|   |-- Exceptions/
|   |-- Http/Controllers/Admin/
|   |-- Livewire/Admin/
|   |-- Livewire/Forms/
|   |-- Models/
|   |-- Policies/
|   `-- Services/
|-- PointOfBooking/
|   |-- Data/
|   |-- Enums/
|   |-- Events/
|   |-- Exceptions/
|   |-- Http/Controllers/
|   |-- Livewire/Admin/
|   |-- Livewire/Forms/
|   |-- Models/
|   |-- Policies/
|   |-- Printing/
|   `-- Services/
|-- Notifications/
|   |-- Listeners/
|   `-- *.php
|-- Reporting/
|   |-- Data/
|   |-- Filters/
|   |-- Reports/
|   `-- Services/
|-- Storefront/
|   |-- Data/
|   |-- Http/Controllers/
|   |-- Livewire/
|   |-- Livewire/Forms/
|   `-- Services/
`-- Resources/
    |-- demo/
    |-- views/
    |   |-- admin/
    |   |-- layouts/
    |   |-- livewire/
    |   |-- pob/
    |   `-- storefront/
    |-- css/
    |   |-- admin.css
    |   |-- pob.css
    |   `-- storefront.css
    `-- js/
        |-- admin.js
        |-- pob.js
        `-- storefront.js

tests/
|-- Feature/PropertyBooking/
`-- Unit/PropertyBooking/

.docs/PropertyBooking/
```

Generic directories named `Helpers`, `Managers`, or `Utils` are prohibited.
Classes belong to the bounded context that owns their invariant.

## 3. Provider And Module Toggle

`PropertyBookingServiceProvider` is always listed in `bootstrap/providers.php`
and is the sole module entry point.

### Register phase

- call `mergeConfigFrom()` for `Config/property-booking.php`;
- return after the merge when the module is disabled;
- bind module interfaces to module implementations only when enabled;
- do not query the database or mutate domain state.

### Boot phase

The first boot instruction is:

```php
if (! config('property-booking.enabled', true)) {
    return;
}
```

Only when enabled may the provider register:

- policies and gates;
- explicit Livewire aliases;
- event/listener mappings;
- module migrations;
- namespaced views as `property-booking::`;
- admin, POB, and storefront routes;
- optional publish tags for config/assets/document templates.

`PROPERTY_BOOKING_ENABLED=false` means routes, view namespace, Livewire aliases,
policies, listeners, migrations, and menu links are not registered for that
application boot. It never deletes already migrated tables, uploaded media, or
source files. After changing the flag, adopters must clear cached configuration
and restart long-running workers.

The disabled-module test must refresh the application after changing the
process environment and assert that representative routes are absent.

## 4. Host Integration Files

Expected host-level edits are tightly limited to:

| Host file | Required integration |
| --- | --- |
| `bootstrap/providers.php` | Register `PropertyBookingServiceProvider`. |
| `app/Support/CmsPermission.php` | Compose the module-owned permission catalogue. |
| dashboard sidebars | Show authorized links only when config is enabled and routes exist. |
| `vite.config.js` | Add existing module CSS/JS entry candidates. |
| `package.json` | Add module browser-QA commands when scripts exist. |
| `.env.example` | Document all `PROPERTY_BOOKING_*` settings. |
| `README.md` | Add enablement, routes, commands, and documentation pointers. |

No host controller or root route owns module behavior.

## 5. Route Surfaces

| Surface | Prefix | Name prefix | Middleware | Binding |
| --- | --- | --- | --- | --- |
| Administration | `/admin/accommodation` | `property-booking.admin.*` | `web`, `auth`, `verified`, permission/policy | ULID |
| Point of Booking | `/pob` | `property-booking.pob.*` | `web`, `auth`, `verified`, POB permission | ULID |
| Public storefront | `/stays` | `property-booking.storefront.*` | `web`; signed where private | slug for catalog, ULID plus signature for booking |

Planned route groups:

```text
/admin/accommodation
/admin/accommodation/properties
/admin/accommodation/amenities
/admin/accommodation/units
/admin/accommodation/rates
/admin/accommodation/availability
/admin/accommodation/guests
/admin/accommodation/bookings
/admin/accommodation/registers
/admin/accommodation/shifts

/pob
/pob/receipts/{booking:ulid}
/pob/receipts/{booking:ulid}/pdf

/stays
/stays/{property:slug}
/stays/{property:slug}/{unitType:slug}
/stays/selection
/stays/checkout
/stays/bookings/{booking:ulid}/confirmation
/stays/bookings/{booking:ulid}/track
/stays/bookings/{booking:ulid}/document/{orientation}
```

Public booking URLs are temporary signed URLs. Receipt routes also authorize
the receptionist/shift owner or staff with booking/till review permission.
Nested property/unit-type routes use scoped bindings, and policies still verify
that every child belongs to the parent property.

## 6. Configuration Contract

The module config will expose typed, bounded defaults rather than business
logic scattered through components:

```text
enabled
currency.code, symbol, decimal_places
country_code
timezone (fallback only; each property owns its timezone)
tax.default_rate_bps, prices_include_tax
media.property_gallery_limit, unit_type_gallery_limit, upload_max_kilobytes
booking.hold_minutes, pending_minutes, minimum_notice_minutes
booking.maximum_stay_days, guest_identity_required,
        check_in_payment_requirement
pricing.allowed_units [hour, day_use, night], default_turnover_minutes
storefront.selection_session_key, confirmation_link_minutes,
           tracking_link_days, document_link_days
pob.payment_methods, maximum_tenders, search_results
pob.receipt_printing.default_driver, default_mode, default_paper_width_mm,
                     drivers
numbering.web_prefix, pob_prefix, admin_prefix
notifications.enabled, queue, events.*
privacy.identity_hash_key, activity_safe_fields
```

Environment input is normalized and bounded. Configuration must never allow
overselling by bypassing the allocation service. Exact-unit allocation is an
invariant in version one, not an operator toggle.

## 7. Authorization And Property Scope

The module contributes a code-owned permission catalogue to
`CmsPermission::catalogue()`:

| Constant | Permission string | Purpose |
| --- | --- | --- |
| `VIEW_DASHBOARD` | `view-property-booking-dashboard` | View operational overview. |
| `VIEW_PROPERTIES` | `view-booking-properties` | Browse catalog and unit metadata. |
| `MANAGE_PROPERTIES` | `manage-booking-properties` | Manage property/category/amenity/unit/media records. |
| `VIEW_RATES` | `view-booking-rates` | Inspect rate plans and overrides. |
| `MANAGE_RATES` | `manage-booking-rates` | Change rates and restrictions. |
| `VIEW_AVAILABILITY` | `view-booking-availability` | Search availability/calendar. |
| `MANAGE_AVAILABILITY` | `manage-booking-availability` | Create/release blocks and reassign units. |
| `VIEW_BOOKINGS` | `view-property-bookings` | Inspect bookings and safe payment summaries. |
| `MANAGE_BOOKINGS` | `manage-property-bookings` | Confirm, modify, cancel, expire, or mark no-show. |
| `MANAGE_GUESTS` | `manage-booking-guests` | Maintain guest records. |
| `MANAGE_PAYMENTS` | `manage-booking-payments` | Record/confirm eligible booking payments. |
| `ACCESS_POB` | `access-point-of-booking` | Use the reception terminal. |
| `MANAGE_SHIFTS` | `manage-reception-shifts` | Configure registers and open/reconcile shifts. |
| `CHECK_IN` | `check-in-booking-guests` | Perform validated guest check-in. |
| `CHECK_OUT` | `check-out-booking-guests` | Perform validated guest check-out. |
| `MANAGE_READINESS` | `manage-unit-readiness` | Move units through readiness states. |

Initial optional role bundles:

- `property-booking-manager`: all module permissions;
- `booking-receptionist`: assigned-property catalog/rates/availability view,
  guest/booking/payment management, POB access, check-in, and check-out;
- `booking-agent`: assigned-property catalog/rates/availability view and
  booking/guest management without shift cash control;
- `property-housekeeping`: assigned-property availability view and unit
  readiness management.

The global active system administrator continues to pass `Gate::before`.
Other operators require both a relevant permission and assignment to the
property through `property_booking_property_user`. `MANAGE_PROPERTIES` grants
cross-property administrative scope; lesser permissions remain property
scoped. Policies enforce this rule in addition to route middleware.

No new `UserType` enum case is required. Operational responsibility is a role,
not an authentication identity type.

## 8. Cross-module Contracts

Property Booking may depend on these host contracts:

- `RecordsSystemActivity` for privacy-safe audit records;
- `ResolvesInstitutionProfile` for institution identity and brand assets;
- `RendersPdfReports` for A4 portrait/landscape documents;
- `App\Models\User` for actors, roles, and receptionist ownership;
- Laravel events, queues, mail/notifications, authorization, cache, rate
  limiting, validation, signed URLs, database transactions, and scheduling;
- Spatie Media Library through module model media collections.

It may not depend on `App\Modules\Commerce\*`. If a support abstraction later
proves genuinely shared, extracting it into a host contract is a separate,
tested refactor; Property Booking will not quietly couple itself to Commerce.

## 9. Livewire Contract

- Livewire components own display state, filters, pagination, modal state, and
  validation feedback.
- typed Livewire `Form` objects own form fields, rules, labels, hydration, and
  normalized payloads.
- all transactional writes call services; components do not orchestrate
  multi-model persistence.
- `WithFileUploads` accepts only configured image MIME types and limits;
  services add/remove media and record activity after authorization.
- lists use Bootstrap pagination through the existing global Livewire config.
- query-string filters are bounded and do not expose identity numbers.
- component aliases are explicit, for example
  `property-booking.admin.property-manager` and
  `property-booking.pob.terminal`.

## 10. Assets And Presentation

Admin pages reuse the Aureon dashboard layout/tokens but load module-owned
`admin.css` and `admin.js`. The storefront and POB have independent layouts and
assets. No module CSS is appended to global `aureon-dashboard.css` unless a
truly shared token is being corrected.

Vite builds six module entries. A disabled module never renders their tags.
Images used by optional demonstration data remain under module resources and
are attached through Media Library by the opt-in seeder.

Module media collections are `category_image`, `property_cover`,
`property_gallery`, `unit_type_cover`, and `unit_type_gallery`. Validated media
custom properties hold alt text and optional caption; Media Library ordering
owns gallery order. Module-defined non-upscaled thumbnail/card/detail
conversions provide stable responsive dimensions while originals remain
available. Upload, conversion failure, reorder, replacement, and deletion are
covered by services, activity records, and tests.

Required presentation states include:

- light/dark themes before first paint;
- loading, empty, unavailable, stale quote, validation, permission, and
  recoverable-domain-error states;
- responsive admin tables, property cards, galleries, date/occupancy controls,
  POB terminal, receipt, and modals;
- keyboard operation, visible focus, labels, live regions, reduced motion, and
  sufficient contrast.

## 11. Documents And Printing

- A4 booking summaries use shared institutional report layouts in portrait and
  landscape through an immutable `BookingDocumentData` adapter.
- POB receipts use immutable `BookingReceiptData`, shared institution details,
  property details, register/shift context, room/rate snapshots, charges,
  tenders, totals, and privacy-safe guest contact.
- thermal layouts support 58 mm and 80 mm widths plus browser/PDF views.
- each register stores driver, manual/prompt mode, paper width, and optional
  printer label.
- the default browser driver may invoke `window.print()` automatically after a
  successful sale, but cannot bypass the browser/system print dialog. Silent
  hardware printing requires a future trusted local/remote driver adapter.

## 12. Events, Queues, And Activity

Events are dispatched only after successful transaction commit. Each business
event maps to one dedicated listener and one focused notification; a generic
message containing unrelated conditions is prohibited.

Planned events include:

- `WebBookingPlaced`, `BookingReceived`, `BookingConfirmed`, `BookingModified`,
  `BookingCancelled`, `BookingMarkedNoShow`;
- `BookingPaymentConfirmed`, `BookingCheckedIn`, `BookingCheckedOut`;
- `BookingHoldExpiring`, `ArrivalDue`, `DepartureDue`;
- `UnitAvailabilityBlocked`, `ReceptionShiftVarianceDetected`.

Activity names use stable prefixes such as:

```text
property-booking.property.created
property-booking.unit.updated
property-booking.availability.blocked
property-booking.booking.placed
property-booking.booking.checked_in
property-booking.pob.booking_completed
property-booking.shift.closed
```

Never log guest identity values, full addresses, free-form private notes,
payment payloads, signatures, signed URLs, or uploaded identity documents.

## 13. Error Handling And Code Standard

- every PHP file uses `declare(strict_types=1);`;
- classes and non-obvious public/protected methods have useful docblocks;
- DTO array shapes and generic collections are documented;
- every migration column receives a specific `->comment()`;
- enums own stable stored values and labels;
- expected failures use context exceptions such as
  `AccommodationUnavailableException`, `InvalidBookingTransitionException`,
  `InvalidStayIntervalException`, `ReceptionShiftException`, and
  `BookingPaymentMismatchException`;
- services do not catch exceptions merely to rethrow them;
- unexpected failures flow through Laravel; add one structured log only when
  resource ULIDs and operation context materially improve diagnosis;
- money uses integer minor units and decimal parsing helpers, never float;
- services control totals, statuses, timestamps, actor IDs, assignments, and
  ULIDs; these are absent from ordinary mass-assignment payloads.

## 14. Scheduler And Worker Contract

Scheduled commands will:

- expire eligible held/pending bookings and release assignments idempotently;
- dispatch due arrival/departure/hold reminders once;
- flag confirmed past arrivals for no-show review, without automatically
  marking them no-show;
- prune only explicitly temporary booking-selection session/cache state.

Commands use chunking, locks, idempotency markers, and service methods. They do
not bypass lifecycle or availability services. Queue workers must be restarted
after deployment and module configuration changes.
