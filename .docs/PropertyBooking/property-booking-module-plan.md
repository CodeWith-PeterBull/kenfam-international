# Property Booking Module Master Plan

**Status:** Phases 1 through 5 complete; Phase 6 adoption readiness is next.

**Planning branch:** `feature/property-booking-module`

**Current implementation branch:** `feature/property-booking-operations`

**Base branch:** `feature/laravel-aureon-base-engine` at `414c492`

**Prepared:** 2026-08-30

Supporting planning documents:

- `property-booking-module-architecture.md`: plugin boundary, provider/config,
  routes, permissions, shared contracts, assets, events, and code standards;
- `property-booking-data-model.md`: enums, exact tables/fields, identities,
  migration order, constraints, allocation locking, retention, and factories;
- `property-booking-domain-workflows.md`: service ownership, state machines,
  rate/availability rules, admin/storefront/POB behavior, and notifications;
- `property-booking-module-diagrams.md`: context, dependency, ER, transaction,
  lifecycle, POB, toggle, and future-tenancy diagrams;
- `property-booking-verification-plan.md`: focused tests, probes, browser QA,
  migration matrix, and phase/release gates.
- `property-booking-pob-implementation.md`: Phase 4 register, shift, terminal,
  transaction, receipt, printing, authorization, and adoption contracts.

## 1. Objective

Build a self-contained accommodation booking and operations module for Aureon.
It must support hotels, apartments, bed-and-breakfast properties, rooms,
stand-alone houses, villas, and similar short-stay inventory through three
coherent surfaces:

1. an administration workspace for properties, units, rates, availability,
   guests, bookings, payments, shifts, documents, and reporting;
2. a public storefront for availability search, accommodation detail,
   guest checkout, booking confirmation, tracking, and documents; and
3. a full-width **Point of Booking (POB)** workspace for receptionists to make
   walk-in or telephone bookings, take split payments, check guests in and out,
   control shifts, and issue receipts.

The module will be behaviorally patterned after Commerce, but it will not be a
search-and-replace copy. Commerce consumes quantity stock once. Accommodation
consumes a concrete unit over a time interval, with occupancy, rate,
housekeeping, arrival, departure, modification, and cancellation constraints.

## 2. Factual Aureon Baseline

The plan is aligned to the current application rather than an assumed stack:

- Laravel `^12.0`, Livewire `^4.2`, PHP `^8.2`;
- Spatie Media Library `^11.21` and Spatie Permission `^6.24`;
- Dompdf through the shared `RendersPdfReports` contract;
- shared institution profile, system activity, mail/PDF templates, theme,
  dashboard, authentication, two-factor, and system-administrator override;
- SQLite in local/default and in-memory test environments, with portable
  Eloquent transactions required for adopter databases;
- Commerce registered through one provider with module-owned config,
  migrations, seeders, routes, views, Livewire aliases, policies, listeners,
  CSS, JavaScript, services, and tests.

Property Booking will preserve those host contracts without importing any
class from `App\Modules\Commerce`. The two modules must remain independently
enableable and removable.

## 3. Canonical Vocabulary

| Term | Meaning |
| --- | --- |
| Property Booking | Canonical PHP module and documentation name. |
| Accommodation | Operator/public label for the catalog of places to stay. |
| Property | The establishment or address that owns bookable inventory, such as a hotel, apartment building, or house. |
| Property category | Property classification such as hotel, apartment, B&B, villa, or holiday home. |
| Unit type | Sellable accommodation description such as Standard Double Room or Two-bedroom Apartment. |
| Unit | Concrete assignable inventory such as Room 101, Apartment B2, or House A. |
| Rate plan | Pricing, occupancy, tax, payment, cancellation, and stay restrictions for one unit type. |
| Booking | One guest reservation at exactly one property, potentially containing multiple units. |
| Booking stay | One requested unit-type/rate-plan line within a booking. |
| Unit assignment | The concrete unit allocated to a booking stay for an exact interval. |
| Guest | Reusable person record; a booking stores immutable contact snapshots as well. |
| Reception register | Named front-desk endpoint used to receive money and print receipts. |
| Reception shift | Cash-control session opened for one receptionist at one register; this is the booking equivalent of a till session. |
| Point of Booking | Authenticated reception workspace, abbreviated POB. |

The word **unit** is intentional. Booking.com's current Rooms API uses it for
rooms, suites, apartments, villas, and other single- or multi-room sellable
accommodations, avoiding a hotel-only model. See the official
[Rooms API terminology](https://developers.booking.com/connectivity/docs/rooms-api/introduction-to-rooms-api).

Concrete catalog examples:

- hotel: one Property, several Unit Types, and many numbered Units;
- apartment building: one Property, one or more apartment Unit Types, and one
  Unit per apartment number;
- stand-alone house/villa/B&B cottage: one Property, one Unit Type, and one
  concrete Unit representing the whole bookable place.

## 4. Approved Architecture Decisions

### 4.1 Multi-property from the first migration

Every unit, rate, booking, register, shift, and operational query belongs to
one property. A booking cannot span properties. This reflects the reservation
contract documented by Booking.com and prevents later multi-property retrofits.

### 4.2 Concrete-unit allocation prevents overselling

Version one assigns a real unit inside the authoritative booking transaction,
including public bookings. The public UI may show only the unit type; the
actual room or house number remains staff-only until policy permits disclosure.

This is deliberately simpler and safer than selling an unbounded unit-type
quantity and assigning rooms later. A future inventory projection may permit
deferred assignment at scale without changing booking identity.

### 4.3 Half-open stay intervals

All overlap checks use `[starts_at, ends_at)`. Two stays do not overlap when the
first ends exactly when the second begins. The canonical overlap predicate is:

```text
existing.starts_at < requested.ends_at
AND existing.ends_at > requested.starts_at
```

Property-local inputs are validated in the property's IANA timezone and stored
as UTC timestamps. Booking.com likewise documents date-range ends as exclusive
in its official
[availability contract](https://developers.booking.com/connectivity/docs/b_xml-availability).
A property's turnover buffer is then applied as a separate operational rule;
it may make a boundary-touching unit unavailable even though the stays
themselves do not overlap.

### 4.4 One-property, one-interval booking aggregate

A booking may contain multiple units, but all version-one booking stays belong
to one property and share one arrival, departure, and pricing-unit basis. Split
stays with different dates or mixed hourly/nightly lines require separate
bookings. This keeps aggregate check-in, checkout, payment, documents, and
signed guest access precise.

### 4.5 Separate state machines

Booking lifecycle, stay/occupancy state, payment state, unit readiness, unit
assignment, and reception-shift state are independent. For example, a booking
may be confirmed and partially paid while its stay is still expected; a unit
may be dirty now but available for a future date.

### 4.6 No direct Commerce dependency

Property Booking will reproduce or adapt small module-owned support contracts
where necessary. It will consume only host-level contracts such as institution
profile resolution, PDF rendering, system activity, Laravel notifications,
authorization, users, and media storage. It must not reference Commerce models,
tables, enums, services, CSS, views, routes, configuration, or seeders.

### 4.7 Short stays first; leasing through a later bounded context

Version one supports hourly, day-use, nightly, and multi-night bookings. The
property/unit model is compatible with later tenancy work, but leases require
recurring billing, deposits, notices, inspections, tenant obligations, and
legal lifecycle rules. Those will be added as a separate `Tenancies` context,
not hidden inside booking statuses or rate plans.

### 4.8 Internal integers and external ULIDs coexist

Auto-incrementing integer IDs remain primary/foreign keys and media relation
keys. Externally addressable aggregates receive a separate immutable unique
ULID. Public catalog routes use slugs where readable; signed booking URLs use
ULIDs and signatures. A ULID identifies a record but never authorizes access.

### 4.9 Manual payment baseline, gateway-ready boundary

Cash, mobile money, card, and bank transfer may be recorded manually through a
sanitized payment service. No raw card number, CVC, provider secret, or full
gateway payload may be stored or logged. Gateway capture remains an adapter
extension behind the payment service.

## 5. Version-one Scope

### Included

- property categories, properties, amenities, unit types, and concrete units;
- property and unit-type image galleries through Livewire and Spatie Media;
- occupancy, room/bed/bathroom counts, size, features, rules, contacts, location,
  check-in/check-out windows, publication, and SEO fields;
- hourly, day-use, nightly, and multi-night rate plans;
- date-based rate overrides and basic restrictions;
- interval availability, maintenance/owner/admin blocks, and deterministic
  concrete-unit allocation;
- reusable guest records and booking guest/contact snapshots;
- web, POB, and administrator booking channels;
- booking hold, pending, confirmation, modification, cancellation, no-show,
  check-in, check-out, completion, and expiry flows;
- deposits, partial/full payments, split POB tenders, change, and balance;
- register configuration, receptionist shift opening/closing/reconciliation,
  expected cash, counted cash, variance, and receipt-print preferences;
- public availability search, listing, detail, checkout, signed confirmation,
  tracking, and document pages;
- administration dashboard, catalog, rates, availability calendar, booking,
  guest, payment, register, shift, and operational queues;
- booking summary PDFs, POB browser/thermal receipts, PDF reprints, and print
  driver contracts using institutional and property details;
- event-specific queued notifications and privacy-safe activity records;
- responsive light/dark interfaces and browser/accessibility verification;
- optional module-owned demonstration factories/seeders, never automatic.

### Explicitly deferred

- leases, tenants, rent schedules, landlord accounting, and legal notices;
- channel-manager synchronization with Booking.com, Airbnb, or Google;
- payment gateway capture, stored cards, PCI card-data handling, and payouts;
- dynamic yield management, promotions, coupons, loyalty, and packages;
- meal plans, restaurant/minibar inventory, housekeeping task assignment,
  maintenance work orders, smart locks, and key-card integrations;
- deferred room assignment/intentional overbooking;
- recurring invoices, deposits held in trust, and damage-claim workflows;
- multi-currency conversion and translated content;
- reviews, wishlists, comparison, messaging, and recommendation engines.

Deferred capabilities receive extension boundaries, not speculative tables.

## 6. Commerce-to-Booking Context Map

| Commerce capability | Property Booking adaptation |
| --- | --- |
| Product category | Property category and property/unit-type catalog |
| Product | Unit type metadata plus concrete units |
| Stock projection | Interval availability derived from active unit assignments and blocks |
| Stock movement | Booking/unit assignment and availability-block activity history |
| Customer | Guest, booking guests, and immutable booking contact snapshots |
| Order/item | Booking and booking stays/additional charges |
| Order calculator | Availability-aware quote and rate calculator |
| Order status | Booking lifecycle, separate stay and payment states |
| POS register/till | Reception register/reception shift |
| POS terminal | Full-width Point of Booking terminal |
| Payment | Booking payment with the same integer-money and sanitization rules |
| Receipt/document | Booking summary plus thermal/PDF receipt adapters |
| Storefront catalog | Availability-led property/unit search and detail |
| Cart/checkout | Session-backed booking selection and server-repriced checkout |
| Low-stock alerts | Arrival/departure, expiring hold, unpaid balance, occupancy, block, and shift-variance alerts |

## 7. Primary User Journeys

### Public booking

1. Guest selects property/date interval and occupancy.
2. Search returns only published unit types with an available concrete unit and
   an eligible rate plan.
3. Guest selects a rate and enters identity/contact and occupant information.
4. Server discards browser totals, locks the relevant unit type and candidate
   units, recalculates, allocates, writes booking snapshots, and commits.
5. After commit, the module sends signed confirmation/tracking links and the
   staff operational notification.

### Reception booking

1. Receptionist opens or resumes an authorized reception shift.
2. POB searches property-local availability by interval, occupancy, unit code,
   unit type, guest name, phone, or email.
3. Receptionist creates/selects a guest, chooses a rate/unit, adds any allowed
   charge, and may hold the booking.
4. Completion reprices and reallocates under locks, records one or more exact
   tenders, and commits all-or-nothing.
5. Receipt instructions follow register settings: manual, prompt-after-sale, or
   browser auto-prompt. Browser security still controls the print dialog.

### Arrival and departure

1. Arrival queue validates confirmation, timing, balance policy, guest details,
   unit assignment, and unit readiness.
2. Check-in records actor/time and changes only the stay state.
3. Extensions and unit moves run the same overlap-safe allocation service.
4. Check-out settles/acknowledges the balance, records departure, releases the
   assignment, completes the booking, and marks the unit dirty.
5. Authorized readiness actions move dirty to cleaning to ready.

## 8. Delivery Phases

| Phase | Branch increment | Deliverable | Exit gate | Status |
| --- | --- | --- | --- | --- |
| 0 | `feature/property-booking-module` | Approved architecture, data model, workflows, diagrams, and verification plan | No unresolved naming, ownership, availability, or lifecycle ambiguity | Complete |
| 1 | `feature/property-booking-foundation` | Provider/config, host integration, enums, migrations, models, factories, ULIDs, permissions/policies, module toggle | Fresh migration, schema/model/ULID/disabled-module tests and autoload probe pass | Complete: 25 tests, 711 assertions |
| 2 | `feature/property-booking-catalog-availability` | Admin property/category/amenity/unit/rate/block CRUD, media galleries, quote and allocation services | Livewire CRUD/media tests, overlap/concurrency tests, admin responsive/theme QA pass | Complete: 39 focused tests, 895 assertions; 276-test full regression; eight browser captures |
| 3 | `feature/property-booking-storefront` | Public search, listing/detail, session selection, checkout, signed pages, booking summary, guest mail | Guest booking, repricing, privacy, signed URL, PDF, accessibility, responsive/theme QA pass | Complete: 52 module tests, 1,057 assertions; 289-test full regression; seven storefront browser captures |
| 4 | `feature/property-booking-pob` | Registers, shifts, full-width POB, holds, guest lookup, split tender, receipt, print settings | Ownership, cash reconciliation, rollback, receipt parity, print, responsive/theme QA pass | Complete: 63 module tests, 1,202 assertions; 300-test full regression; 12 browser/print captures |
| 5 | `feature/property-booking-operations` | Booking/guest admin, modification, cancellation, no-show, check-in/out, unit moves/readiness, dashboard, alerts | State-transition, authorization, allocation release, after-commit notification, dashboard tests pass | Complete: 83 module tests, 1,477 assertions; 320-test full regression; 12 browser captures with rendered-chart verification |
| 6 | `feature/property-booking-adoption-readiness` | Reports, screenshots, operations/adoption/extension docs, full regression and deployment matrix | Full suite, Pint, build, route/view/config cache, browser, accessibility, dark mode, PDF and migration matrix pass | Planned |

Each phase is documented, reviewed, committed, and merged independently. The
parallel module branch remains separate from the base engine until its own
acceptance gates pass.

Post-Phase 5 guest-identity and immediate-walk-in hardening is documented in
`property-booking-guest-identity-and-pob-walk-in-update.md`. The current module
baseline passes 90 tests and 1,515 assertions, the full application passes 327
tests and 3,372 assertions, and the updated storefront/POB browser matrices
retain seven and 12 captures respectively.

## 9. Planning Sources And Rationale

The domain structure is informed by primary platform documentation, adapted to
Aureon's deliberately smaller scope:

- Booking.com distinguishes total inventory from search-specific availability
  and ties pricing to room type, rate plan, occupancy, and restrictions:
  [Rates and Availability overview](https://developers.booking.com/connectivity/docs/ari).
- A reservation may include multiple rooms/apartments/villas but belongs to one
  property: [Reservations API overview](https://developers.booking.com/connectivity/docs/reservations-api/reservations-overview).
- Unit metadata, occupancy/capacity, and single- versus multi-room structures
  are separate from rates and availability:
  [Managing units](https://developers.booking.com/connectivity/docs/rooms-api/managing-units).
- Google similarly separates relatively stable room/package metadata from
  frequently changing itinerary pricing and availability:
  [Transaction messages](https://developers.google.com/hotels/hotel-prices/dev-guide/transaction-overview).
- Minimum/maximum stay, advance booking, closed-on-arrival, and
  closed-on-departure are established rate restrictions, but overlapping rules
  require care: [pricing types](https://developers.booking.com/connectivity/docs/understanding-pricing-types).

These sources define useful semantics; Aureon is not implementing their APIs in
version one.

## 10. Phase-zero Approval Gate

Implementation may begin only after review confirms:

- `PropertyBooking`, Property, Unit Type, Unit, Guest, Booking, Reception Shift,
  and POB are accepted canonical terms;
- exact-unit allocation at placement is acceptable for version one;
- short-stay scope and separate future tenancy context are accepted;
- the module-owned table prefix and route prefixes are accepted;
- the service/state/permission boundaries in the supporting documents are
  complete enough to prevent architectural invention during migration work.
