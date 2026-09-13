# Property Booking Domain Workflows And Service Plan

**Status:** Catalog, rate, quote, allocation, and availability-block workflows are implemented through Phase 2; later-phase workflows remain approved contracts.

## 1. Service Ownership Map

### Catalog

| Service | Owns |
| --- | --- |
| `PropertyCategoryService` | Category create/update/activation and hierarchy validation. |
| `PropertyService` | Property create/update/publication/archive, assignment scope, property galleries, and policy windows. |
| `AmenityService` | Amenity catalogue and property/unit-type associations. |
| `UnitTypeService` | Unit-type metadata, occupancy rules, publication, and galleries. |
| `AccommodationUnitService` | Concrete unit CRUD, activation, and readiness transitions. |
| `PropertyAccessService` | Assigned-property query scopes and policy checks for non-admin staff. |

### Pricing

| Service | Owns |
| --- | --- |
| `RatePlanService` | Rate-plan CRUD, publication, deposit/tax/restriction validation. |
| `RateOverrideService` | Non-overlapping local-date override CRUD. |
| `RateOverrideResolver` | Resolve the one eligible override for each stay date. |
| `BookingRateCalculator` | Server-owned billable units, occupancy extras, discounts, tax, deposits, and immutable line totals. |
| `BookingQuoteService` | Read-only availability/rate quote with expiry metadata; never reserves a unit. |

### Availability

| Service | Owns |
| --- | --- |
| `AvailabilitySearchService` | Read models for property/unit/rate search; results are advisory. |
| `UnitAllocationService` | Deterministic locks, overlap checks, active assignment create/release/move. |
| `AvailabilityBlockService` | Create/release maintenance, owner-use, cleaning, and administrative blocks. |
| `BookingExpiryService` | Idempotently expire eligible holds/pending bookings and release assignments. |

### Guests And Bookings

| Service | Owns |
| --- | --- |
| `GuestService` | Guest CRUD, normalized lookup, identity encryption/fingerprint, and privacy-safe activity. |
| `BookingNumberService` | Assign channel-specific number once at placement/confirmation boundary. |
| `BookingService` | Create hold/place/confirm/cancel/no-show/complete aggregates and snapshots. |
| `BookingModificationService` | Date, occupancy, rate, unit, and guest changes with lock/reprice/reallocation. |
| `CheckInService` | Arrival validation and stay/check-in mutation. |
| `CheckOutService` | Departure, release, completion, and unit-dirty transition. |
| `BookingChargeService` | Post/void additional charges and recalculate totals. |
| `BookingPaymentService` | Sanitize, record, confirm, refund-state, and payment projection updates. |
| `BookingDocumentDataFactory` | Immutable privacy-safe A4 report view data. |
| `BookingDocumentService` | Portrait/landscape PDF and browser document rendering. |

### Point Of Booking

| Service | Owns |
| --- | --- |
| `ReceptionRegisterService` | Register CRUD and validated receipt settings. |
| `ReceptionShiftService` | Open/close/reconcile, ownership, expected cash, and variance event. |
| `PobSelectionService` | Server-backed terminal selection/hold state, never trusted totals. |
| `PobCheckoutService` | One outer transaction for allocation, booking, split tenders, and completion. |
| `BookingReceiptDataFactory` | Immutable thermal/browser/PDF receipt data. |
| `BookingReceiptService` | Receipt render and PDF adapter. |
| `ReceiptPrinterManager` | Configured module print-driver resolution and print instruction DTO. |

### Storefront, Reporting, And Notifications

| Service | Owns |
| --- | --- |
| `StorefrontNavigation` | Published property/category navigation. |
| `BookingSelectionSession` | Session-backed interval, occupancy, property, unit type, and rate selection. |
| `StorefrontBookingCheckoutService` | Guest normalization plus authoritative web placement. |
| `BookingAccessUrlService` | Temporary signed confirmation, tracking, and document URLs. |
| `PropertyBookingDashboardService` | Cross-module operational snapshot and authorized links. |
| `OperationalNotificationDispatcher` | Active, verified, permission/property-scoped recipients. |

Services accept typed DTOs rather than Livewire arrays. Mutating services use
the existing `RecordsSystemActivity` contract and return refreshed aggregates.

## 2. Booking Lifecycle

Allowed `BookingStatus` transitions:

```text
held -----> pending -----> confirmed -----> completed
  |             |              |                 |
  +-> expired   +-> expired    +-> no_show      terminal
  +-> cancelled +-> cancelled  +-> cancelled
```

Rules:

- `held` has no final business number and must have `hold_expires_at`;
- `pending` is placed and consumes availability while awaiting configured
  confirmation/payment action; it must have `pending_expires_at` when expiry is
  enabled;
- `confirmed` is expected and remains availability-consuming;
- `cancelled`, `expired`, `no_show`, and `completed` are terminal for ordinary
  transitions;
- cancellation is allowed only before check-in; an in-house booking departs
  early through checkout/modification instead;
- no-show is an explicit authorized action after the arrival threshold, never
  an automatic scheduler decision;
- completion is performed only by checkout, not by payment completion.

Every transition locks the booking, validates current state, updates reason and
timestamp fields, updates/release assignments when required, records one
activity, and dispatches the event after commit.

## 3. Stay State

Allowed `StayStatus` transitions:

```text
expected -> checked_in -> checked_out
    |
    +-----> no_show
```

- check-in requires confirmed booking, expected stay, active assignment,
  property/register access, current timing policy, unit readiness, and any
  configured deposit/balance precondition;
- checkout requires checked-in state and records actual time before releasing
  assignments and marking units dirty;
- early/late check-in and checkout require an explicit policy or authorized
  override reason recorded in activity;
- unit readiness remains independent from future date availability;
- one aggregate stay status is used in version one. Partial check-in/out for
  individual rooms is deferred; all booking stays move together.

## 4. Payment Projection

`BookingPaymentStatus` is recalculated under a booking lock after every
completed/refunded payment or total-changing charge:

```text
paid_minor == 0                      => unpaid
0 < paid_minor < total_minor         => partial
paid_minor >= total_minor            => paid
paid_minor == 0 after full refund     => refunded (when refund history exists)
```

Rules:

- totals and `paid_minor` are never accepted from the browser;
- split POB tenders must equal the amount being settled; cash may have
  `tendered_minor >= amount_minor` and computed change;
- a reception-shift cash projection counts only completed cash payments and
  subtracts completed full cash refunds;
- duplicate provider references are rejected within a method/provider scope;
- metadata is allow-listed and recursively rejects card/PIN/CVC/token/secret
  keys;
- web payment preference is not a completed payment;
- events fire only when the persisted payment state actually crosses the
  relevant boundary.

## 5. Unit Readiness

Allowed operational transitions:

```text
ready -> dirty -> cleaning -> ready
  |        |          |
  +------> maintenance <------+
              |
              +-> out_of_service
out_of_service -> maintenance -> ready
```

`AccommodationUnitService` enforces transitions and permission. A maintenance
or out-of-service interval also creates/maintains an availability block when it
must prevent future bookings. Merely marking a checked-out unit dirty does not
erase future availability, but immediate check-in cannot use it until ready.

## 6. Reception Shift Lifecycle

```text
closed/nonexistent -> open -> closed/reconciled
```

Open:

- authorize register/shift management;
- require active register and assigned active receptionist;
- lock register and open-guard rows;
- reject another open shift for the register or receptionist;
- record opening float, actor, note, and timestamps.

Operate:

- POB requires the authenticated receptionist's open shift, except an
  authorized manager/system administrator acting under an explicit selected
  shift;
- all POB bookings/payments retain register, shift, and receptionist ownership;
- a receptionist cannot resume another shift's hold.

Close:

- lock shift and payment projection;
- compute expected cash from opening float plus net completed cash tenders;
- require counted cash and compute signed variance;
- clear open guards and close once;
- dispatch a dedicated variance event only when the configured absolute
  threshold is crossed.

## 7. Rate Calculation

### Common rules

1. Resolve property timezone and validate `end > start`.
2. Validate capacity and rate-plan eligibility.
3. Require every stay in a multi-unit booking to share the booking's property,
   interval, and pricing unit.
4. Validate advance window, minimum/maximum billable units, closures, and
   arrival/departure restrictions.
5. Resolve non-overlapping rate override(s).
6. Calculate occupancy extras, subtotal, discount, and tax with integers.
7. Calculate required deposit from the final eligible amount.
8. Return an immutable calculation DTO; never write from the calculator.

### Night

- billable nights are the difference between local checkout and check-in dates;
- minimum is one night;
- each local stay date from start inclusive to end exclusive resolves its rate;
- property check-in/out windows determine valid times, not the number of nights.

### Hour

- duration is measured from normalized UTC instants;
- billable hours use ceiling after any explicitly configured grace rule;
- no floating-point hours are persisted;
- hourly rate overrides are deferred; the base plan applies in version one.

### Day use

- day-use stays are constrained by property-local day-use/check-in/out windows;
- billable units are whole eligible local calendar service days;
- a day-use interval does not silently become a nightly stay when it crosses a
  configured boundary.

### Quote staleness

Storefront/POB quotes carry generated/expiry timestamps for user feedback, but
placement always locks records and recalculates. A changed or unavailable quote
returns a clear stale-quote/availability error without partial persistence.

## 8. Availability Search And Allocation

Search filters by:

- published property/unit type/rate plan;
- property/category/location;
- exact interval and pricing-unit compatibility;
- adult, child, infant, and total capacity;
- active concrete-unit count;
- active blocks and active assignment overlap;
- rate closures and stay/advance restrictions;
- optional amenities and price range.

Allocation is deterministic:

- sort requested unit types and lock each unit-type row;
- evaluate candidate units in ascending integer ID;
- exclude overlap using `[start, end)`;
- apply the property's turnover buffer after either departure before a
  subsequent stay may consume the same unit;
- assign the first eligible concrete unit(s);
- do not reveal staff-only unit codes in public availability responses;
- reject, never oversell, when capacity is gone.

Modification and extension first verify that the current unit can cover the new
interval; otherwise they seek a replacement unit and record a unit move. All
changes occur in one transaction, so a failed reallocation preserves the
original booking.

## 9. Public Storefront Vertical Slice

### Pages/components

- storefront layout/header/footer/theme controller;
- availability search with dates/times, adults, children, infants, category,
  location, and accessible validation;
- property results and property detail with real galleries, amenities,
  policies, location/contact summary, and available unit/rate cards;
- unit-type detail with capacity, layout, beds, size, gallery, amenities,
  pricing, cancellation/deposit terms, and selection action;
- session-backed booking selection/review;
- Livewire guest checkout with occupant details, special request, payment
  preference, policy consent, and server-priced review;
- signed confirmation, tracking, and portrait/landscape summary links.

### Security/privacy

- no raw unit number, guest identity number, internal note, shift, staff actor,
  or payment metadata on public pages;
- temporary signed routes plus booking ULID;
- URL signature and expiry verified before model detail is rendered;
- search/checkout throttles are keyed without logging private request bodies;
- form values are escaped, bounded, validated, and never trusted for totals or
  availability.

## 10. Administration Interfaces

### Dashboard

Real property-scoped statistics and queues:

- today's arrivals, departures, in-house and no-show review;
- occupancy and available/blocked/dirty/maintenance units;
- booking value, receipts, outstanding balances, and channel split;
- held/pending expiries;
- upcoming reservations and recent activity;
- open shifts and variance alerts;
- authorized links to each operational surface.

### Livewire CRUD/workspaces

- categories/properties/amenities;
- unit types, concrete units, galleries, and readiness;
- rate plans and date overrides;
- availability search/calendar and block management;
- guests with exact identity lookup and masked display;
- booking list/detail, transition, modification, unit move, charges, payments,
  document/receipt actions;
- registers and reception shifts.

Every component has loading/empty/error states, Bootstrap pagination, stable
modal dimensions, responsive tables/cards, light/dark badges, and explicit
authorization for page and action.

## 11. Point Of Booking Interface

The POB is an operational workspace, not a dashboard card. It uses a full-width
module layout with stable responsive regions:

- top context: property, register, open shift, receptionist, clock, theme, and
  shift/register shortcuts;
- availability/search: date/time, occupancy, unit type/code, and rapid result
  cards;
- selection: chosen stay lines, rate/deposit terms, totals, and remove/edit;
- guest: find by name/phone/email/exact identity fingerprint or create through
  validated form;
- booking: notes, hold/resume/discard, confirm/check-in when eligible;
- payment: outstanding amount, split tenders, cash change, references, and
  exact settlement validation;
- completion: booking number, print instruction, receipt/document actions, and
  clean terminal reset.

POB holding does not accept stale totals. Resume locks the hold, verifies shift
ownership, reruns availability and pricing, and either refreshes it or reports
the exact conflict.

## 12. Documents And Notifications

### Documents

- `BookingDocumentDataFactory` produces the only input accepted by A4 templates;
- `BookingReceiptDataFactory` produces the only input accepted by receipt
  templates/printers;
- both adapters use immutable booking/stay/payment snapshots plus resolved
  institution/property branding;
- portrait and landscape totals must match; browser receipt, 58/80 mm receipt,
  and PDF receipt must share one data adapter;
- identity numbers, emergency contacts, internal notes, metadata, and signed
  URLs are excluded.

### Dedicated notifications

| Event | Recipient intent |
| --- | --- |
| `WebBookingPlaced` | Property booking managers/reception queue. |
| `BookingReceived` | Primary guest acknowledgement while a web booking is pending. |
| `BookingConfirmed` | Primary guest confirmation. |
| `BookingModified` | Primary guest with changed safe summary. |
| `BookingCancelled` | Primary guest and property operators, separate messages if copy differs. |
| `BookingPaymentConfirmed` | Primary guest receipt acknowledgement. |
| `BookingCheckedIn` | Optional property operations notice. |
| `BookingCheckedOut` | Guest departure/receipt and operations update. |
| `BookingHoldExpiring` | Relevant staff or guest when contact/consent exists. |
| `ArrivalDue` / `DepartureDue` | Assigned-property operational recipients. |
| `ReceptionShiftVarianceDetected` | Staff with shift-management permission. |

Listeners resolve the aggregate by ULID after commit, recheck module/event
configuration, enforce active/verified/property-scoped recipients, and queue on
the configured queue. Notification tests assert one event to one dedicated
message, recipient isolation, rollback silence, and Mailpit-compatible output.

## 13. Future Tenancy Extension

The reusable foundation is Property, Unit Type, Unit, Amenity, Guest, media,
property staff scope, and interval availability. A future `Tenancies` context
may reference those IDs while owning:

- applicants/tenants and lease agreements;
- recurring rent schedules, invoices, deposits, penalties, and ledgers;
- meter readings, inspections, maintenance responsibility, notices, renewals,
  terminations, and vacancy turnover.

It must not add lease-only values to `BookingStatus`, pretend monthly rent is a
very long nightly rate, or route tenancy money through reception shifts without
an explicit accounting decision.
