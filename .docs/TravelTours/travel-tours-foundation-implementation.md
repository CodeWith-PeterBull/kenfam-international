# TravelTours Foundation Implementation

## Status And Scope

This document records the implemented K1 persistence foundation and the first
transaction-service vertical slice. The separately accepted public storefront
foundation is recorded in `travel-tours-storefront-foundation.md`. Neither
record marks the complete K2-K8 administration, booking, or operations phases
complete.

Accepted in this slice:

- one module provider and runtime toggle;
- 34 domain models and 34 owned tables;
- 645 Blueprint columns with non-empty migration comments;
- explicit foreign keys, indexes, retention behavior and public ULIDs;
- enums, relationships, casts, privacy hiding and media collections;
- one module-owned factory per domain model, including explicit pivot models;
- deterministic quote calculation for configured participant rates;
- owner-bound, expiring and idempotent availability holds;
- atomic booking placement with complete immutable snapshots;
- idempotent payment recording and deterministic instalment allocation;
- schema/model/factory and focused service verification.

Not accepted by this slice:

- catalog, destination, itinerary or schedule administration UI;
- complete catalog/pricing CRUD services and Livewire forms;
- lifecycle, cancellation and refund services;
- booking-desk register/shift operational interfaces;
- public Livewire checkout and traveler document workflows;
- MySQL/PostgreSQL concurrent-connection oversell proof;
- production payment gateways, thermal drivers or client-approved content;
- administration/POB browser QA, Mailpit, document, and deployment acceptance.

## Implemented Module Layout

The module remains under `App\Modules\TravelTours` and owns its Config,
Contracts, Catalog, Pricing, Scheduling, Customers, Bookings, Inquiries,
PointOfBooking, Operations, Storefront, Events, Listeners, Notifications,
Policies, Database, Resources, Routes and Support namespaces.

The provider merges configuration before checking the toggle. If disabled, it
does not expose migrations, routes, views, policies, listeners or bindings. If
enabled, contract bindings remain visible only for concrete services registered
in `TravelToursServiceProvider`.

## Persistence Foundation

Seven ordered migration files create these dependency groups:

| Order | Migration responsibility |
| --- | --- |
| 1 | Categories, destinations, tours, assignments, itineraries, content, FAQs and extras |
| 2 | Rate plans, participant rates, promotions and promotion assignments |
| 3 | Customers and reusable travelers |
| 4 | Departures, staff assignments, availability holds and pricing rules |
| 5 | Booking registers and shifts |
| 6 | Bookings, participants, extras, price lines, history, instalments, payments, refunds and redemptions |
| 7 | Shift movements, inquiries and inquiry activity |

All domain columns carry `->comment()`. Timestamps are explicit commented
columns rather than undocumented `timestamps()` expansion. Generated index
names are constrained to the portable 64-character ceiling. Foreign-key delete
behavior differentiates editable children, historical commercial records and
nullable source links.

Important hardened fields include:

- `operation_key` on holds, bookings, payments, refunds, movements and inquiries;
- `owner_token_hash` for anonymous hold ownership;
- booking `currency_exponent`, `refunded_minor`, policy, rate-plan, meeting,
  pricing, customer, tour and departure snapshots;
- participant sequence, local-departure age, seat consumption and guardian link;
- provider-scoped payment/refund transaction uniqueness;
- register/operator open-shift guard columns;
- consent purpose/version and contact hash version;
- release evidence for holds and promotion redemptions.

Availability is intentionally derived from capacity-consuming bookings and
unexpired active holds. There is no mutable `reserved_seats` authority.

## Model And Privacy Boundary

`TravelToursModel` supplies ULIDs, module factory resolution and protection for
identifiers, operation keys and actor-owned fields. Transaction services use
`forceFill()` only with explicit server-built arrays after validation. HTTP or
Livewire request payloads must never be passed to `forceFill()`.

Sensitive identity, medical, dietary, accessibility, emergency-contact,
idempotency, payment-correlation and inquiry-contact attributes are hidden from
routine serialization. Travel-document and care fields use encrypted casts.
Lookup hashes are purpose-prefixed HMACs rather than unsalted contact hashes.

Spatie Media Library owns tour covers/galleries/documents, destination imagery,
itinerary media, customer profiles and private travel documents. A media path
or URL is not persisted as a competing domain field.

## Factory Foundation

`Database/Factories` contains exactly one concrete factory for each domain
model. Custom pivot models use `HasTravelToursFactory`, giving them the same
module-local factory resolution as aggregate models. Factories provide coherent
required foreign keys, valid enums, ISO currency/country values, integer money,
unique operational references and safe non-secret fixture metadata.

Factories are test construction tools, not the Kenfam demonstration catalogue.
No client tour records or production-like identity documents are seeded here.

## Typed Service Inputs

The first write paths no longer accept unbounded request arrays:

- `TravelCustomerData` validates customer identity/contact shape.
- `BookingParticipantData` excludes client-controlled prices and produces a
  server-allocated immutable participant snapshot.
- `AvailabilityHoldRequest` binds a quote, operation key and owner evidence.
- `BookingPlacementData` binds operation key, hold, customer, participants,
  channel, terms and internal actor/register context.
- `BookingPaymentData` binds payment identity, integer amount, currency,
  provider evidence, optional schedule/shift and actor.

Forms and controllers remain responsible for authorization and presentation
validation. Services repeat domain ownership, currency, state and consistency
checks inside transactions.

## Quote Service

`TourQuoteCalculator` verifies departure state and booking window, rate-plan
ownership/activity and participant limits. It requires exactly one eligible
rate for every requested participant type and rejects ambiguous overlap rather
than selecting the first row.

All arithmetic uses integer minor units. Percentage, tax and deposit division
uses deterministic half-up rounding. Pricing rules are ordered by priority;
non-stacking rules stop subsequent rule application. Promotions enforce date,
tour inclusion/exclusion, minimum value/party, fixed-discount currency and
known usage ceilings. Placement repeats promotion limits under a row lock.

The quote fingerprint covers departure, rate plan, participant mix, promotion,
line output, total and deposit. Booking placement recalculates from persisted
hold inputs and rejects changed pricing.

## Hold Service

`AvailabilityHoldService` runs inside a retrying transaction and locks the
departure before approving capacity. Anonymous ownership stores only a
purpose-specific HMAC; authenticated ownership stores the customer reference.
The operation key returns the original hold only when departure, quote and
owner evidence match. Reuse with different data raises
`DuplicateOperationConflict`.

Release requires ownership and records status, UTC release instant and reason.
Expiry is a non-destructive state transition with the same release evidence.

## Booking Placement Service

Placement follows this lock order:

1. check an existing booking operation key;
2. read the hold only to discover its departure;
3. lock the departure;
4. lock and revalidate the hold;
5. resolve/lock customer contact identity;
6. recalculate quote and lock the selected rate plan;
7. lock promotion usage before redemption persistence.

The service proves hold ownership, participant-count parity, one-lead semantics,
quote fingerprint parity and accepted policy version. It persists the aggregate,
ordered participant snapshots, deterministic participant fare allocation,
price lines, initial status history and optional promotion redemption in one
transaction. The hold becomes consumed only after all records succeed.

`TourBookingPlaced` implements `ShouldDispatchAfterCommit`; rolled-back writes
therefore do not notify recipients. Booking-number generation uses a
channel-specific prefix, UTC date and ULID suffix rather than `max(id) + 1`.

## Payment Service

`BookingPaymentService` locks the booking, checks operation-key compatibility,
rejects terminal bookings, enforces booking currency, prevents overpayment and
validates schedule/shift ownership. Confirmed payment totals and processed
refund totals derive the aggregate net balance.

`PaymentScheduleService` locks all instalments before allocating exact minor
units in preferred-then-due order. Cash payments require an authenticated actor
and an open shift, and produce one idempotent shift movement. The dedicated
payment event dispatches after commit.

## Verification Evidence

Passing focused commands at the time of this record:

```text
php artisan test tests/Feature/TravelTours
PASS (19 tests, 1699 assertions; foundation, services, storefront, and disabled-module isolation)

php artisan test
PASS (142 tests, 2357 assertions)

php scripts/probe-travel-tours-foundation.php
34 tables; 645 documented columns; 0 missing comments

php scripts/audit-travel-tours-docblocks.php
all module PHP files have file, type, and named-function PHPDoc

php vendor/bin/pint --test app/Modules/TravelTours tests/Feature/TravelTours tests/Feature/HomePageTest.php
PASS

php artisan view:clear
php artisan view:cache
PASS

npm run qa:travel-tours-storefront
PASS (11 inspections and six viewport captures)
```

The public harness confirms that all currently implemented storefront pages
load their compiled assets and media with no runtime error, network error,
broken image, duplicate ID, unlabeled control, or horizontal overflow down to
320 px. The local PHP runtime emits an environment warning because Imagick was
compiled against ImageMagick 1808 while 1810 is loaded. This warning is not
suppressed and must be reconciled before production media acceptance.

SQLite validates schema execution, factories and service behavior. It does not
prove production row-lock contention. Oversell tests require separate database
connections on the selected production-equivalent engine.

The access catalogue is independent of optional local operator fixtures.
`TravelToursDemoOperatorSeeder` is never called by the application seeder.
Public inquiry writes use `TourInquiryData` and `TourInquiryService`, require an
idempotency key, verify public tour/departure ownership, and invoke Spatie's
concrete anti-spam middleware rather than an unregistered alias.

## Next Foundation Closeout

Before K2 is marked active:

1. add lifecycle, refund, and register/shift service slices with focused tests;
2. add production-engine concurrent-connection verification for hold/placement
   collision;
3. close the applicable concerns recorded in
   `claude-review/remediation-status.md` before accepting affected later phases;
4. commit the K1/storefront-foundation slice independently and publish only
   after authenticated remote access verifies the destination repository.
