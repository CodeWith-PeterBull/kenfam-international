# Property Booking Operations Implementation

**Module:** `App\Modules\PropertyBooking`

**Branch:** `feature/property-booking-operations`

**Phase:** 5 - Booking and property operations

**Status:** Complete and verified.

**Prepared:** 2026-09-01

## 1. Objective

Complete the operational layer that follows public and Point of Booking intake:

- property-scoped booking and guest administration;
- authoritative confirmation, modification, cancellation, and no-show actions;
- validated check-in, check-out, and concrete-unit moves;
- unit readiness workflow for housekeeping;
- a cross-property operations dashboard with arrival, departure, occupancy,
  settlement, readiness, and reception-shift queues;
- dedicated after-commit operational events and notifications.

The work remains fully owned by `App\Modules\PropertyBooking` and does not
import Commerce implementation classes.

## 2. Factual Schema Decision

Phase 1 already supplied every field required by this phase:

- booking/stay status, lifecycle reasons, actor projection, and timestamps;
- guest occupant check-in/check-out timestamps;
- immutable stay/rate/guest/property snapshots;
- exact-unit assignment history and release reasons;
- unit operational status and `last_ready_at`;
- notification idempotency timestamps;
- nullable administration payments and reception-shift payments.

No migration was introduced. Any newly discovered persistence requirement must be
documented and tested before a migration is introduced; operational state must
not be hidden in free-form notes or activity metadata.

## 3. Domain Service Boundaries

### Booking lifecycle

`BookingLifecycleService` owns locked transitions:

| Operation | Required persisted state | Result |
| --- | --- | --- |
| Confirm | pending, expected, active allocation | confirmed and confirmation timestamp |
| Cancel | held/pending/confirmed, expected | cancelled, reason/timestamp, allocation release |
| No-show | confirmed, expected, arrival threshold elapsed | booking/stay no-show and allocation release |
| Check in | confirmed, expected, ready active units, payment/timing policy | checked-in booking, stay, and occupants |
| Check out | confirmed, checked-in, settlement/timing policy | completed/checked-out, released units become dirty |

Every operation:

1. authorizes in the Livewire/controller boundary and rechecks property scope in
   the service;
2. locks the booking aggregate and dependent rows;
3. rejects invalid or terminal transitions with a domain exception;
4. writes service-owned timestamps, reasons, actor, assignments, and readiness;
5. records a privacy-safe system activity;
6. dispatches a scalar-only event after commit.

Timing or settlement exceptions require an explicit bounded override reason.
An override never bypasses property scope, active allocation, unit readiness,
or state-machine requirements.

### Modification and unit move

`BookingModificationService` owns interval changes and exact-unit moves.

- Expected bookings may change arrival/departure; checked-in bookings may only
  extend departure.
- The current rate is recalculated from locked rate configuration.
- A modification cannot reduce the total below already collected payments.
- The current assignment is released and reallocated inside one transaction;
  failure rolls back both changes.
- A manual unit move requires a ready, same-property, same-unit-type target and
  a reason. In-house moves mark the vacated unit dirty.
- Browser totals and availability previews are never authoritative.

### Administration payments

Administration payment recording is separate from POB cash handling. It may
record a completed non-cash/manual tender against an eligible booking without a
reception shift. Cash remains a POB shift concern. Duplicate references,
currency, overpayment, status projection, and private metadata rules remain
service controlled.

### Unit readiness

The existing `AccommodationUnitService::transitionReadiness()` state machine
remains the sole write path. A dedicated housekeeping manager supplies scoped
search/filter/actions and never edits catalog identity fields.

## 4. Administration Surfaces

All pages use the Aureon dashboard shell and module-owned `admin.css`/`admin.js`.

| Route | Livewire component | Capability |
| --- | --- | --- |
| `/admin/accommodation` | Read-only operations dashboard | `VIEW_DASHBOARD` |
| `/admin/accommodation/bookings` | Booking register and lifecycle dialogs | `VIEW_BOOKINGS` plus action policies |
| `/admin/accommodation/guests` | Guest CRUD and archive/restore | `MANAGE_GUESTS` |
| `/admin/accommodation/readiness` | Housekeeping readiness board | `MANAGE_READINESS` |

The booking register provides URL-backed search, property/channel/status/stay/
payment filters, Bootstrap pagination, immutable detail projections, and
explicit dialogs for destructive or consequential actions. Component IDs are
locked; every action reloads its model and invokes `Gate::authorize()`.

When the module and named storefront route are available, administrator and
role sidebars expose **Stay storefront**, while the overview welcome band
exposes **Public stays**. Both links resolve through
`property-booking.storefront.catalog.index` and open `/stays` in a separate tab
so the active operations workspace is retained.

## 5. Dashboard Read Model

`PropertyBookingDashboardService` builds a property-scoped immutable snapshot:

- arrivals today, departures today, in-house stays, and active bookings;
- collected payments and booked value for a bounded range;
- pending confirmations, arrival/no-show review, balance, and departure queues;
- ready, dirty, cleaning, maintenance, and out-of-service unit counts;
- current occupancy denominator based on active concrete units;
- active reception shifts and recent bookings;
- links rendered only when both route and permission are available.

Read models carry only fields required by the page. Guest identity documents,
internal notes, raw payment metadata, and signed URLs are excluded.

The booked-value chart follows the working Commerce dashboard asset contract:
the page loads the local `build/plugins/chartjs/chart.min.js` vendor runtime
before the module Vite entry, then renders a three-series Web, Point of Booking,
and Administration trend. A semantic daily-data table remains available when
canvas output is unavailable or unsuitable for the user.

## 6. Events And Notifications

Dedicated scalar events and focused deliveries are mapped as follows:

| Event | Customer delivery | Staff delivery |
| --- | --- | --- |
| `WebBookingPlaced` | Booking receipt/confirmation | New-web-booking review alert |
| `BookingConfirmed` | Confirmation notice | None |
| `BookingModified` | Revised stay notice | None |
| `BookingCancelled` | Cancellation notice | Cancellation operations alert |
| `BookingMarkedNoShow` | No-show notice | None |
| `BookingCheckedIn` | Check-in notice | None |
| `BookingCheckedOut` | Check-out notice | Unit-turnover alert |
| `BookingPaymentConfirmed` | Focused payment receipt | None |
| `ReceptionShiftVarianceDetected` | None | Material shift-variance alert |

Notifications implement `ShouldQueue`, use after-commit dispatch, target the
configured `notifications` queue, retry three times, and back off at 30, 120,
and 300 seconds. Events and queued notifications carry scalar identifiers and
immutable customer email snapshots rather than serialized model graphs.

Staff recipients are resolved at delivery time and must be active, email
verified, hold the exact capability (or be an active system administrator), and
have global or explicit access to the event property. Customer delivery also
rehydrates the booking and rejects stale or incompatible state. Delivery
failures are logged without rolling back committed operations. The global
`PROPERTY_BOOKING_NOTIFICATIONS_ENABLED` switch, queue name, and twelve
per-delivery environment switches are documented in `.env.example`.

## 7. Verification

| Gate | Result |
| --- | --- |
| `php artisan test tests/Feature/PropertyBooking` | 83 passed, 1,477 assertions |
| `php artisan test` | 320 passed, 3,329 assertions |
| `vendor/bin/pint --test` | Passed |
| `composer validate --no-check-publish` | Valid |
| `npm.cmd run build` | Production Vite build passed |
| `php artisan route:list --path=admin/accommodation` | 11 protected administration routes |
| `php artisan view:cache` / `config:cache` | Both compiled successfully; development caches cleared afterward |
| Runtime container probe | Dashboard service resolved; confirmation and payment events each resolved one listener |
| `npm.cmd run qa:property-booking` | 12 captures passed with no runtime, network, overflow, duplicate-ID, labeling, or theme failures |

The browser matrix covers desktop, tablet, and mobile layouts; light and dark
themes; reduced motion; existing catalog/media CRUD; and all new operations
surfaces. The dashboard assertion requires the Chart.js runtime, a ready
`694 x 310` canvas, and sampled nontransparent pixels, preventing a blank chart
from passing merely because the page loaded. The retained evidence is under
`.docs/PropertyBooking/qa/catalog-availability/`.

The local QA database was restored with the existing idempotent root, Commerce,
and Property Booking seeders after a stale framework config cache caused an
earlier test invocation to retain the local SQLite target. Acceptance ended
with `php artisan optimize:clear`; PHPUnit's configured database remains
SQLite `:memory:`.

The only environment warning is the existing local Imagick build/runtime
version mismatch (compiled against ImageMagick 1808 while 1810 is loaded). It
did not fail media or document tests but should be aligned on deployment hosts.

## 8. Delivery Ledger

| Slice | Deliverable | Status |
| --- | --- | --- |
| 5.1 | Lifecycle, modification, unit-move, and administration-payment services | Complete |
| 5.2 | Booking, guest, and readiness administration | Complete |
| 5.3 | Operations dashboard and scoped read models | Complete |
| 5.4 | Dedicated events, notifications, QA, and final documentation | Complete |
