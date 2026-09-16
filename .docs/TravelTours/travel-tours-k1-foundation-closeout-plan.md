# TravelTours K1 Foundation Closeout Plan

Status: reconciled and closed, 2026-09-16. The original work packages remain
below as decision history. Their current ownership is governed by section 2.1
and `travel-tours-k1-reconciliation.md`; they are not all prerequisites to K2.

## 1. Purpose

Close the remaining TravelTours foundation risks before K2 administration and
K5-K7 operational surfaces add more dependencies to them. The phase should
turn the current sound persistence core into a trustworthy extension platform:
authorization must be resource-aware, configuration must have one authority,
quotes must expire, documents must expose projections rather than aggregates,
events must have one queue boundary, writes must be auditable, and registered
contracts must represent real behavior.

This closeout is intentionally narrower than a feature phase. It does not build
catalog Livewire CRUD, advanced storefront discovery, checkout UI, payment
gateways, a booking desk, reports, or thermal-printer integrations.

## 2. Verified Starting Point

The incoming branch provides:

- 34 TravelTours tables and models with 645 documented columns;
- 34 factories and separated access/operator/demo seeders;
- deterministic integer-money quote calculation;
- owner-bound availability holds and atomic booking placement;
- customer, payment, instalment, inquiry, signed-access, and draft document
  service slices;
- a responsive public homepage, catalogue, detail, inquiry, and signed-status
  presentation foundation;
- 19 passing TravelTours tests with 1,699 assertions;
- 142 passing host tests with 2,357 assertions;
- passing storefront browser QA, build, Blade compilation, Pint, PHPDoc audit,
  Composer validation, and a zero-advisory Composer lock.

The independent source review is
`claude-review/concern_file.md`. Its live disposition is
`claude-review/remediation-status.md`. C-01 through C-03 are resolved. This
phase owns C-04 through C-10 where described below.

### 2.1 Reconciled package disposition

| Package | K1 disposition | Continuing owner |
| --- | --- | --- |
| K1C-01 bounded-context structure | Do not perform a broad namespace move. Relocate each class when its owning feature is implemented. | K2-K7 by context |
| K1C-02 permission catalogue | Baseline route permissions and policies remain. Add scoped capabilities with real actions. | K2, K3, K5, K6 |
| K1C-03 configuration/quote validity | Configuration is structurally present. Each phase must activate and test only its keys; quote expiry is checkout behavior. | K2-K7; quote expiry K5 |
| K1C-04 private projections | Private response headers closed in K1. Purpose-limited document DTOs remain mandatory. | K7 |
| K1C-05 events/notifications | Foundation events exist; delivery acceptance waits for complete recipients, content, and operations. | K7 |
| K1C-06 receipt printing | Closed for K1 by removing the false log-only binding. | Real printer contract K6 |
| K1C-07 activity trail | Not required to begin catalog CRUD; remains mandatory before operational release. | K7 |
| K1C-08 remaining services | Lifecycle/refund/traveler services belong to checkout; register/shift services belong to booking desk. | K5 and K6 |
| K1C-09 contention proof | The abandoned K1 harness is not retained. True two-service races use completed workflows and an isolated production-equivalent engine. | Capacity K5; money/drawer K6 |

K1 therefore closes the module/schema/model/factory/service-interface and
storefront foundation without claiming that K2-K7 behavior is complete.

## 3. Non-Negotiable Invariants

1. Commerce and Property Booking stay disabled and absent at runtime.
2. TravelTours remains client-neutral and must not read `config('kenfam.*')`.
3. All money remains integer minor units with an explicit ISO currency and
   exponent.
4. All writes use typed DTOs, services, transactions, and idempotency keys where
   an external retry is possible.
5. Public booking access remains temporary-signed and privacy-minimal.
6. Events leave the transaction only after commit and have exactly one queue
   owner.
7. Logs and activity payloads never contain identity numbers, medical details,
   tokens, signed URLs, or raw provider payloads.
8. A contract is registered only when its concrete implementation returns a
   truthful, testable result.
9. No demo identity or catalogue is called by `DatabaseSeeder`.
10. Every migration column, file, class, and non-trivial method retains the
    project documentation standard.

## 4. Work Packages

### K1C-01: Bounded-Context Structure

Move root-level policies, events, and listeners into the bounded contexts that
own them before K2-K7 multiply references. The target follows the published
architecture:

```text
Catalog/Policies/
Scheduling/Policies/
Bookings/Policies/
Bookings/Events/
Bookings/Listeners/
Inquiries/Policies/
PointOfBooking/Policies/
Notifications/Listeners/
Notifications/RecipientResolution/
```

Update namespaces, provider mappings, tests, and documentation in one atomic
change. Do not create empty `Reporting`, `Console`, Livewire, or asset folders
to simulate parity. Those folders arrive with real phase behavior.

Exit evidence:

- dependency scan finds no obsolete root policy/event/listener namespace;
- every policy and event/listener registration resolves through the provider;
- existing booking/payment/inquiry tests pass unchanged in behavior;
- provider and disabled-module tests pass.

### K1C-02: Permission Catalogue And Resource Scope

Replace coarse permission equivalence with the minimum distinctions required
by the access matrix:

| Capability | Required separation |
| --- | --- |
| Catalog | view, manage, publish |
| Departures/pricing | view, manage |
| Bookings | view, manage, lifecycle transition |
| Travelers | standard details, sensitive details |
| Payments | record, confirm, refund |
| Inquiries | view, manage/assign |
| Booking desk | access terminal, manage registers |
| Shifts | open/close own, reconcile another operator |
| Reports | view/export |

Policies must scope list queries and individual actions. A booking agent must
not gain access to unrelated bookings, customers, travelers, shifts, or
inquiries by substituting a ULID. System-administrator bypass must use the
existing host `Gate::before` contract, not repeated role checks in components.

Reconcile `TravelToursAccessSeeder` grants with the documented role matrix.
Specifically, a booking agent must not inherit remote confirmation or refund
authority merely because the old `MANAGE_PAYMENTS` permission combined them.

Exit evidence:

- a permission-by-role matrix test covers system administrator, travel manager,
  booking agent, and tour editor;
- policy tests cover owned/assigned, unassigned, sensitive, refund, register,
  own-shift, and other-shift cases;
- route/controller actions use `authorize` or middleware and views use `@can`
  where actions become visible;
- access seeding remains idempotent and never creates people.

### K1C-03: Configuration Authority And Quote Validity

Inventory every `travel-tours.php` leaf key and classify it as:

- **live:** consumed by one named service and covered by a test;
- **phase-owned future:** documented under a clearly marked future block and
  omitted from adopter-facing environment examples until implemented; or
- **remove:** duplicate, misleading, or superseded by persisted configuration.

Per-rate-plan tax settings remain authoritative. Do not retain environment tax
switches that imply a second source of truth. Apply the same rule to payment
methods, media limits, notification controls, numbering, and POB defaults.

Extend `TourQuote` with an immutable version and `expiresAt`. The calculator
must consume the live quote-lifetime configuration, include version/expiry in
the fingerprint contract where appropriate, and reject an expired quote even
when a hold has not yet expired. Booking placement must continue recalculating
server-side under the established lock order.

Exit evidence:

- a config-consumer test accounts for every live key;
- `.env.example`, config documentation, and implementation agree;
- quote boundary tests cover exact expiry, expired quote, changed policy
  version, changed price, and a still-valid hold paired with an expired quote;
- integer-money and idempotent placement tests remain green.

### K1C-04: Privacy-Safe Booking Projections

Create purpose-specific immutable data objects under
`Bookings/Data/Documents/` for confirmation, tracking, PDF summary, and any
future receipt projection. A data factory may eager-load the aggregate, but the
view and renderer receive only approved scalar/DTO data. Never pass customer,
traveler, payment, or booking Eloquent models directly into signed views or
documents.

Signed HTML responses retain `noindex, nofollow, noarchive`. PDF/document
responses add at least:

```text
Cache-Control: private, no-store, max-age=0
Pragma: no-cache
X-Robots-Tag: noindex, nofollow, noarchive
X-Content-Type-Options: nosniff
```

Do not include encrypted identity, dietary, accessibility, medical, internal
notes, provider metadata, operation keys, or customer consent internals unless
a later approved document explicitly requires a narrowly projected field.

Exit evidence:

- tests fail if raw models are present in document/view data;
- tests assert the response headers and purpose-limited field set;
- portrait and landscape output still render through the institutional report
  adapter;
- zero-, two-, and three-exponent amounts remain exact.

### K1C-05: Event And Notification Boundary

Choose one queue owner. The preferred Aureon pattern is an after-commit domain
event, a synchronous listener that performs enablement and recipient checks,
and a dedicated queued notification on the configured notification queue.
Do not queue both listener and notification.

Every listener must re-check module and notification enablement at execution
time. Add `TravelRecipientResolver` before operational recipients are sent any
message. Customer and staff notifications remain separate classes because they
have different language, privacy, urgency, and retry behavior.

Exit evidence:

- one event creates one intended queue job per recipient/message;
- disabled module or notification switch produces no delivery;
- configured queue name is asserted;
- missing customer email and missing operational recipients are handled without
  masking the original transaction;
- synchronous and queued tests prove after-commit behavior and retry safety.

Mailpit delivery remains a K7 acceptance gate; this closeout verifies the
foundation semantics, not production mail content.

### K1C-06: Truthful Receipt-Printer Contract

Remove `PrintsBookingReceipts` from the provider until a real driver result is
implemented, or introduce the minimal truthful instruction/result abstraction
that reports browser handoff rather than physical print success. A log-only
method cannot satisfy the contract.

Thermal paper profiles, automatic printing, device drivers, print retries, and
register-owned printer configuration remain K6. The closeout must not create a
fake successful printer to make dependency resolution pass.

Exit evidence:

- resolving the contract cannot return silent success;
- unsupported printing produces a typed unavailable result or the contract is
  intentionally unbound;
- provider tests document the selected behavior when enabled and disabled.

### K1C-07: System Activity Audit Trail

Integrate the host `RecordsSystemActivity` contract into accepted write
services. At minimum cover:

- availability hold creation, expiry, release, and consumption;
- customer creation/matching decisions without logging contact values;
- booking placement and lifecycle changes;
- payment recording and instalment allocation;
- refund completion when the refund service lands;
- inquiry submission, assignment, follow-up, and conversion.

Activity entries should identify aggregate type/ULID, actor when available,
safe action name, before/after business state, source channel, and request or
operation correlation. They must be emitted only after the transaction outcome
is known and must preserve idempotency under retries.

Exit evidence:

- focused tests assert one safe activity record per successful operation;
- retries do not duplicate activity;
- rolled-back operations write no activity;
- a prohibited-field test rejects sensitive keys and values.

### K1C-08: Remaining Foundation Services

Implement only the service families needed to make the existing model and
transaction foundation coherent before feature UIs:

- `BookingLifecycleService` with an explicit transition map and history;
- `BookingRefundService` with payment/refund locking and exact aggregate
  reconciliation;
- `TravelerService` for reusable traveler matching and encrypted data updates;
- register/shift open, close, movement, and reconciliation services required by
  the later POB transaction boundary.

Catalog, itinerary, media, departure, rate-plan, pricing-rule, and promotion
write services belong to K2/K3 and should not be pulled into this closeout.

Exit evidence:

- typed DTOs and named domain exceptions for every operation;
- allowed/forbidden lifecycle transitions and immutable history tests;
- payment/refund idempotency and over-refund prevention;
- traveler privacy/ownership tests;
- one-open-shift constraints, expected-cash calculation, and reconciliation
  tests without a UI dependency.

### K1C-09: Production-Engine Contention Proof

SQLite proves schema execution and deterministic behavior but not row-level
locking. Add a dedicated MySQL or PostgreSQL test profile using two independent
connections. Exercise:

- last-seat hold versus hold;
- hold versus direct booking;
- two placements against one hold;
- duplicate operation keys;
- concurrent payment/refund allocation;
- one-register/one-operator open-shift constraints.

The test must assert final persisted capacity and money, not merely exception
types. Deadlock retry behavior needs a bounded deterministic assertion.

Exit evidence:

- documented engine/version and isolated database setup;
- repeatable green contention suite;
- no oversell, double consumption, duplicate charge, over-refund, or duplicate
  open shift after competing operations;
- SQLite default suite remains available for fast local feedback.

## 5. Original Recommended Delivery Sequence (Superseded)

| Order | Branch/commit concern | Depends on | Why now |
| ---: | --- | --- | --- |
| 1 | Structure and permission catalogue | Current branch | Prevents expensive namespace and authorization churn during K2 |
| 2 | Config authority and quote expiry | 1 | Freezes adopter-facing contracts before checkout work |
| 3 | Privacy document projections | 1 | Hardens already exposed signed routes |
| 4 | Queue ownership and recipient boundary | 1-2 | Corrects existing event behavior before more notifications arrive |
| 5 | Activity recording | 1 | Gives remaining service work an audit contract from day one |
| 6 | Lifecycle, refund, traveler, and shift services | 2, 5 | Completes core transaction semantics without UI coupling |
| 7 | Printer binding correction | 1 | Ensures the provider advertises only real capability |
| 8 | Production contention suite | 2, 6 | Verifies the final transaction shape on a locking database |
| 9 | Full closeout and documentation | All | Opens K2 with a measured foundation |

This sequence is retained only to explain the discarded uncommitted work. It
was too broad for one foundation increment. The active sequence is K2 catalog,
K3 scheduling/pricing, K4 public discovery, K5 checkout, K6 operations, K7
communications/documents/audit, and K8 adoption QA. Each phase uses a focused
branch and independently reviewable commits.

## 6. Required Test And QA Matrix

| Layer | Required command/evidence |
| --- | --- |
| Formatting | `php vendor/bin/pint --test app/Modules/TravelTours tests/Feature/TravelTours` |
| Documentation | `php scripts/audit-travel-tours-docblocks.php` |
| Schema | `php scripts/probe-travel-tours-foundation.php` |
| Module tests | `php artisan test tests/Feature/TravelTours` |
| Host regression | `php artisan test` |
| Disabled isolation | Dedicated provider/routes/views/listeners/bindings test |
| Blade/routes | Clean `view:cache` and namespaced route inspection |
| Dependency health | Composer validation and locked advisory audit |
| Public regression | `npm run build` and `npm run qa:travel-tours-storefront` |
| Concurrency | Deferred: two-service capacity races in K5 and money/drawer races in K6 on an isolated production-equivalent engine |

Use temporary Composer-autoload probes for container bindings, policy mappings,
event/listener registration, and configuration inventory where a full HTTP test
would obscure the contract under examination.

## 7. Documentation And Handoff Deliverables

During implementation:

1. update `travel-tours-implementation-ledger.md` with each accepted slice;
2. update `claude-review/remediation-status.md` only when evidence closes an ID;
3. add a focused K1 closeout implementation record containing changed files,
   decisions, commands, and limitations;
4. reconcile the architecture, data model, service-contract, workflow, and
   verification documents when the accepted contract changes;
5. record the production-engine setup without committing credentials;
6. leave K2/K3/K5-K7 status unchanged until their own exit gates pass.

## 8. Reconciled Phase Exit Gate

K1 is complete when the published foundation remains green and the two exposed
runtime corrections pass: private signed-response headers and absence of a
false receipt-printer binding. Later-phase concerns remain visible in the
remediation ledger and become hard gates when their complete workflows land.

K2 may now begin with catalog, destination, itinerary, content, FAQ, extra, and
media administration services and Livewire 4 forms. It must not absorb K3
departure/pricing management or K5 checkout merely to make screens appear
complete.
