# Commerce Operational Events and Notifications

**Status:** Implemented; all automated verification gates passed.

**Branch:** `feature/commerce-operational-notifications`

**Baseline:** `140ca0a`

**Implemented:** 2026-07-23

## 1. Purpose

Phase 4.3 adds event-driven operational mail without changing Commerce
transaction rules or introducing a generic activity message. Each approved
business activity owns one after-commit event, one listener, and one focused
queued notification.

The existing `OrderConfirmationNotification` remains the independent customer
acknowledgement sent after storefront placement. Phase 4.3 adds a separate
staff alert for that order and independent customer lifecycle messages.

## 2. Delivered Catalogue

| Committed activity | Event | Notification | Recipient |
| --- | --- | --- | --- |
| New storefront order | `WebOrderPlaced` | `NewWebOrderReceivedNotification` | Active users explicitly holding `manage-orders` |
| Completed web payment | `OrderPaymentConfirmed` | `CustomerPaymentConfirmedNotification` | Immutable order snapshot email |
| Web order reaches ready | `OrderReady` | `CustomerOrderReadyNotification` | Immutable order snapshot email |
| Unpaid web order cancelled | `OrderCancelled` | `CustomerOrderCancelledNotification` | Immutable order snapshot email when valid |
| Healthy stock enters positive low band | `StockBecameLow` | `LowStockNotification` | Active users explicitly holding `manage-inventory` |
| Positive stock reaches zero or below | `StockDepleted` | `OutOfStockNotification` | Active users explicitly holding `manage-inventory` |
| Closed till exceeds material variance | `TillVarianceDetected` | `TillVarianceNotification` | Active users explicitly holding `manage-tills` |

## 3. Architecture

```text
Service-owned transaction
  -> scalar/ULID event implementing ShouldDispatchAfterCommit
  -> explicit listener registered by CommerceServiceProvider
  -> OperationalNotificationDispatcher
       -> event configuration gate
       -> CommerceNotificationRecipientResolver
       -> dedicated ShouldQueue notification
            -> afterCommit queue flag
            -> 3 bounded attempts: 30s, 120s, 300s
            -> current-state shouldSend() guard
            -> institution-aware Laravel mail presentation
```

Events contain only ULIDs and integer snapshots. They never serialize an
Eloquent aggregate, internal note, payment metadata, credential, customer tax
identifier, or database integer identifier.

`CommerceNotificationRecipientResolver` selects active users through direct or
role-composed Spatie permissions. It intentionally does not use the global
system-administrator `Gate::before` bypass, so operational distribution remains
an explicit permission decision.

## 4. Transaction And Delivery Rules

- `StorefrontCheckoutService` emits `WebOrderPlaced` after its outer order and
  pending-payment transaction returns successfully.
- `OrderService`, `PaymentService`, `InventoryService`, and `TillService` emit
  events from locked transaction boundaries. Laravel defers listeners until
  the outermost transaction commits.
- A rollback discards the event callback. No mail is queued for a failed order,
  payment, inventory mutation, or till close.
- Queue failures are safely logged with event key, subject ULID, and exception
  class. They do not make an already committed HTTP operation appear failed.
- Notification delivery does not write a second system-activity record. The
  underlying service mutation remains the single audit event.
- Customer mail uses only the order snapshot email. Missing or invalid snapshot
  addresses are ignored without exposing their value in logs.
- Every notification re-queries its subject in `shouldSend()`. Deleted,
  replenished, disabled, unauthorized, or no-longer-applicable subjects fail
  closed when a queued job eventually runs.

## 5. Stock And Till Noise Control

```text
before > threshold AND after > 0 AND after <= threshold -> StockBecameLow
before > 0 AND after <= 0                               -> StockDepleted
```

A healthy-to-zero movement emits only `StockDepleted`, avoiding two messages
for one mutation. Further deductions while already low or depleted do not
repeat alerts. Replenishment above the threshold rearms the next low transition.

Till variance compares the absolute signed variance against a positive
minor-unit threshold. The default `10000` is KSh 100.00 under the default
two-decimal KES configuration. Zero variance never alerts.

## 6. Configuration

```dotenv
COMMERCE_OPERATIONAL_NOTIFICATIONS_ENABLED=true
COMMERCE_NOTIFY_WEB_ORDER_PLACED=true
COMMERCE_NOTIFY_PAYMENT_CONFIRMED=true
COMMERCE_NOTIFY_ORDER_READY=true
COMMERCE_NOTIFY_ORDER_CANCELLED=true
COMMERCE_NOTIFY_LOW_STOCK=true
COMMERCE_NOTIFY_OUT_OF_STOCK=true
COMMERCE_NOTIFY_TILL_VARIANCE=true
COMMERCE_TILL_VARIANCE_ALERT_THRESHOLD_MINOR=10000
COMMERCE_NOTIFICATION_QUEUE=default
```

The global switch or any event switch may disable delivery. A production
adopter must run a worker for the selected queue; the test environment's `sync`
driver remains deterministic.

## 7. Main File Boundaries

```text
app/Modules/Commerce/{Orders,Inventory,PointOfSale}/Events/
app/Modules/Commerce/Notifications/
app/Modules/Commerce/Notifications/Listeners/
app/Modules/Commerce/Notifications/CommerceNotificationRecipientResolver.php
app/Modules/Commerce/Notifications/OperationalNotificationDispatcher.php
app/Modules/Commerce/CommerceServiceProvider.php
app/Modules/Commerce/Config/commerce.php
tests/Feature/Commerce/CommerceOperationalNotificationTest.php
```

No migration was added. This increment intentionally excludes a database
notification center, read/unread state, delivery preferences, SMS, WhatsApp
delivery, digests, and scheduler-owned reminders.

## 8. Verification

- PHP syntax checks passed for every changed PHP file.
- `php artisan event:list --event=Commerce` exposed all seven listener mappings.
- Focused Phase 4.3 suite: **8 tests, 77 assertions**.
- Complete Commerce coverage: **113 tests, 1,214 assertions**.
- Complete repository suite: **233 tests, 1,828 assertions**.
- Coverage includes explicit permissions, inactive-user exclusion, customer
  snapshot routing, stock deduplication and rearming, till materiality,
  disabled configuration, missing email, stale-state suppression, queue
  contracts, and rollback.
- Pint, Composer validation, configuration cache, route cache, view cache, and
  `git diff --check` passed.

The local PHP runtime continues to report the pre-existing Imagick binary
version warning. It does not affect this mail-only implementation.

## 9. Live Queue And Mailpit Verification

An initial real SMTP check exposed a queue-only defect that notification fakes
could not reveal. `QueuedCommerceNotification` stored its event key in a private
parent property. Laravel's queued notification serializer reflects the concrete
child class and did not restore that private parent property, causing
`shouldSend()` to encounter an uninitialized value after sync-queue
serialization. The delivery coordinator correctly isolated the exception, which
is why the committed order succeeded and only the independent legacy
confirmation reached Mailpit.

The event key is now a protected class constant on each dedicated notification,
so it has no serialized runtime state. The contract test serializes and restores
all seven notifications and invokes their configuration guard, preventing this
regression.

The corrected implementation was exercised against the local application
database and Mailpit on 2026-07-23:

- effective queue: `sync`;
- effective transport: SMTP at `localhost:1025`;
- Mailpit API/UI: `http://127.0.0.1:8025/`;
- all seven event switches: enabled;
- staff recipients: `admin@aureon.test` and
  `commerce-notifications@aureon.test`;
- customer recipients: immutable smoke-order snapshot addresses;
- latest verification batch: **11 operational messages** plus **1 existing
  order acknowledgement**;
- every returned Mailpit record had a nonempty body snippet;
- `jobs` and `failed_jobs` both remained empty, confirming synchronous
  processing.

Staff subjects appeared twice because two active users explicitly held the
required permission. Customer payment, ready, and cancellation subjects
appeared once at their respective order snapshot addresses. The service-driven
batch covered web checkout, completed payment, ready transition, cancellation,
healthy-to-low stock, stock depletion, and material till variance.
