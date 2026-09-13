# Commerce Glossary and Scenarios

**Status:** Canonical vocabulary and scenario reference for the initial Commerce release.

**Updated:** 2026-08-18

## 1. Purpose

Commerce uses one domain model for the public store and POS. This document gives
product owners, operators, developers, testers, and adopting teams the same
meaning for each term, then traces the implemented workflows from precondition
to persisted result.

Unless a scenario explicitly says otherwise, amounts are stored as integer
minor units, all mutations occur through services, and authorization is checked
at both page and action boundaries.

## 2. Core Catalog Terms

### Product

The saleable catalog aggregate. It owns identity, category, descriptions, SKU,
barcodes, quantity limits, regular/sale/cost prices, tax behavior, stock policy,
dimensions, publication state, SEO fields, and gallery media. A historical
order does not read its display values back from the current product.

### Product category

An ordered, optionally nested catalog grouping with active/hidden state, media,
description, and SEO fields. Category hierarchy is cycle-protected.

### SKU

A unique stock-keeping identifier chosen by the business. POS exact lookup can
match SKU, and order-item snapshots preserve it for historical traceability.

### Internal barcode

A unique store-controlled Code 128 value. When omitted during creation, the
module can generate a prefix plus zero-padded product identifier, such as
`MM0000000145`. It is printable in the barcode label workspace.

### Manufacturer barcode

An optional unique identifier printed by a manufacturer. POS exact scan matches
it alongside internal barcode and SKU. The module does not generate it.

### Draft, published, and archived

The product lifecycle. Draft is the creation state. Published products are
eligible for public/POS discovery when other saleability rules pass. Archived
products are removed from active selling while historical snapshots remain.

### Regular price

The base selling price stored in integer minor units.

### Sale price

An optional selling price with an active time window. The server chooses the
effective current price; a browser-submitted value is never trusted.

### Cost price

An internal amount retained for future financial extensions. It is excluded
from customer documents, receipts, storefront payloads, and current PDF
register rows.

### Minor unit

The smallest configured currency unit. With two decimal places, KSh 1.00 is
stored as `100`. All totals, fees, payments, change, floats, and variances stay
integer-valued until formatted at the presentation boundary.

### Basis point

One hundredth of a percentage point. A tax rate of 16.00 percent is stored as
`1600` basis points.

## 3. Inventory Terms

### Tracked stock

A product policy requiring saleability and deductions to use its stock
projection. With overselling disabled, a tracked balance cannot be committed
below zero.

### Untracked stock

A product policy that does not restrict sale quantity through the on-hand
projection. This is appropriate only where physical-unit tracking is not
required. It is a supported runtime state but is not present in the current
optional demo product graph.

### On hand

The current integer stock projection in `stocks.on_hand`. It is updated in the
same transaction as its corresponding movement.

### Low-stock threshold

The product-specific integer boundary at or below which positive tracked stock
is considered low. New stock projections use the configured default, but each
projection owns its effective threshold.

### Healthy stock

Tracked stock whose on-hand value is above its low-stock threshold.

### Low stock

Tracked stock where `on_hand > 0` and `on_hand <= low_stock_threshold`.

### Out of stock

Tracked stock where `on_hand <= 0`.

### Stock projection

The current fast-read row for one product. It is not a replacement for movement
history and must equal the sum of its opening and subsequent signed movements.

### Movement ledger

The append-only `stock_movements` history. Each entry records its type, signed
quantity, balance before, balance after, actor, source reference, and note.
Implemented types are opening, adjustment increase, adjustment decrease, order
commit, and order cancellation.

### Stock commitment

The transactional deduction performed when a web order is placed or a POS sale
is completed. A POS hold does not commit stock.

### Stock release

The exact inverse movement created once when an eligible unpaid web order is
cancelled. It does not delete the original commitment.

## 4. Customer and Order Terms

### Reusable customer

A current customer profile used for future selection and administrative
maintenance. It may be linked to an application account and can be archived
without deleting order history.

### Guest customer resolution

Checkout's conservative matching rule. An account-owned customer is preferred;
otherwise an exact normalized first-name, last-name, email, and phone tuple is
reused. A different tuple creates a new reusable customer rather than merging
potentially shared contact data.

### Immutable customer snapshot

The name, contact, company, tax identifier, and address copied onto an order at
placement. Later edits to the reusable customer do not rewrite it.

### Immutable order-item snapshot

The product name, SKU, quantity, unit selling price, tax, discount allocation,
and line total captured at placement. Historical orders do not change when the
catalog changes.

### Order

The shared transaction aggregate for both web and POS. It owns channel,
fulfillment, customer snapshot, items, totals, payment projection, stock
timestamps, lifecycle timestamps, actor/register/till context, and a public
ULID.

### Sale

A completed POS order. The implementation does not maintain a parallel sales
table; `orders.channel=pos` identifies the source.

### Channel

The transaction surface that originated the order: `web` or `pos`. Channel is
not fulfillment type and does not imply payment state.

### Fulfillment type

How an order reaches the customer: store pickup, delivery, or POS counter sale.
Counter fulfillment is reserved for POS.

### Fulfillment state

The order lifecycle value. Web orders progress through pending, confirmed,
processing, ready, and completed. Held is a POS-only unresolved state;
cancelled is terminal.

### Payment state

The aggregate settlement projection on an order: unpaid, partial, paid, or
refunded. The initial release derives unpaid/partial/paid from completed payment
rows; a refund engine is not implemented even though the enum reserves the
future state.

### Payment record

One manual preference or completed tender. It stores method, status, applied
amount, cash tendered/change where relevant, sanitized reference/metadata,
actor, till, and paid timestamp.

### Pending payment preference

The idempotent pending row created for an unpaid web checkout's selected manual
method. It records intent, not collected funds. A later completed payment may
reuse that row.

### Order number

The business identifier assigned to placed web orders and completed POS sales,
using the configured `WEB` or `POS` prefix. POS holds remain unnumbered.

### ULID

A 26-character external identifier stored separately from the integer primary
key. It supports non-sequential routes but is not authorization and is not a
secret.

### Signed order link

A Laravel temporary signed URL containing the order ULID for confirmation,
tracking, or customer document access. The signature and expiry are required;
possessing only the ULID is insufficient.

## 5. POS and Cash Terms

### Register

A named physical or logical POS endpoint. It owns code, location, active state,
and effective receipt driver/mode/width/printer label. A register can have only
one open till.

### Till session

A cashier-owned operating period on one register. It records opening float,
expected cash, counted cash, variance, notes, opener/closer, and timestamps.

### Opening float

The non-negative physical cash declared when a till opens. It initializes
expected cash.

### Expected cash

At close, the locked recalculation of opening float plus completed cash payment
amounts assigned to that till. Non-cash tenders and cash change do not become
additional applied cash amounts.

### Counted cash

The non-negative physical cash value entered by the till manager during close.

### Variance

`counted cash - expected cash`. Negative is a shortage; positive is an overage.
Any non-zero variance is audited, while mail requires the configured absolute
materiality threshold.

### Held sale

An unnumbered POS order in `held` state. It belongs to one cashier and till,
does not commit stock, and does not contain completed payments. It is repriced
and revalidated when resumed.

### Split tender

Two or more completed payment methods applied to one POS total. The configured
maximum defaults to four rows, and the applied amounts must exactly settle the
server-calculated order total.

### Cash received

The physical amount tendered for a cash payment. It must be greater than or
equal to the applied cash amount.

### Cash change

`cash received - applied cash amount`. It is calculated on the server and does
not reduce the payment amount recorded against the order.

### Receipt print driver

A configured implementation of `ReceiptPrinterDriver`. The bundled browser
driver supplies a 58/80 mm layout and print instruction, but does not enumerate
printers or silently control hardware.

## 6. Governance Terms

### Permission

A code-owned Spatie capability controlling a page or action. Route middleware,
policies, and service preconditions serve different layers and are all retained.

### System activity

The structured audit record for a successful business mutation or protected
document export. It contains stable action names and safe summaries, not raw
request bodies or credentials.

### Operational event

A scalar/ULID event emitted by a transaction-owning service and deferred until
the outer transaction commits.

### Operational notification

A dedicated queued mail for exactly one approved event. It has its own
configuration key, recipient rule, retry policy, and stale-state guard.

## 7. Lifecycle Diagrams

### Web order

```text
cart
  -> checkout validation and server repricing
  -> pending + stock committed + payment preference
  -> confirmed
  -> processing
  -> ready
  -> completed

pending|confirmed|processing|ready
  -> cancelled only while fully unpaid
  -> stock released exactly once
```

### POS order

```text
current terminal cart
  -> hold -> held -> resume and reprice -> completed sale
  -> hold -> held -> discard -> cancelled audit record
  -> exact tender checkout -> completed sale
```

### Till

```text
active register + authorized cashier
  -> open with float
  -> POS cash payments update expected projection
  -> resolve all holds
  -> count cash
  -> close with expected, counted, and signed variance
```

## 8. Scenario: Guest Pickup Order

**Preconditions:** Commerce is enabled; at least one product is published and
saleable; pickup and a configured manual payment method are available.

1. The guest adds a product from `/shop` to the session cart.
2. Cart reads re-fetch current product, price, quantity limits, and stock.
3. The guest enters identity/contact details, chooses Store pickup, selects a
   payment preference, and submits checkout.
4. `StorefrontCheckoutService` resolves or creates the reusable customer.
5. `OrderService` locks and reprices products, creates snapshots, assigns the
   web number, commits stock, and records placement.
6. `PaymentService` creates one pending preference.
7. After commit, the cart clears, signed URLs are created, customer
   acknowledgement is queued, and staff with explicit `manage-orders` receive
   the new-order alert.

**Result:** A pending, unpaid web pickup order exists with committed stock and
an immutable customer/item snapshot. No money has been collected merely because
a payment preference exists.

## 9. Scenario: Guest Delivery Order

**Preconditions:** The pickup scenario preconditions plus configured delivery
address fields and flat fee.

1. The guest chooses Delivery.
2. Checkout validates the delivery address.
3. The server adds `COMMERCE_DELIVERY_FEE_MINOR` during calculation.
4. Placement follows the same locked customer, order, item, stock, preference,
   signed-link, audit, and mail sequence as pickup.

**Result:** A pending web delivery order contains the address snapshot and flat
fee. No carrier, zone, distance, or weight quote has been performed.

## 10. Scenario: Confirm Manual Payment

**Actor:** An operator with `manage-orders`.

1. Open the order register and inspect the outstanding balance.
2. Record the actual method, a positive amount no greater than the balance, and
   an optional sanitized reference.
3. `PaymentService` locks the order, rejects held/cancelled or overpaid input,
   and reuses a pending preference where possible.
4. It writes a completed payment and derives partial or paid aggregate state.
5. After commit, a dedicated payment confirmation is queued to the immutable
   order snapshot email.

**Result:** Collected payment is represented separately from fulfillment. A
partial amount leaves a positive balance and partial state; later completed
payments may settle the remainder.

## 11. Scenario: Advance Fulfillment

**Actor:** An operator with `manage-orders`.

1. Start with a pending web order.
2. Advance one step at a time: confirmed, processing, ready, completed.
3. Each action reloads and locks the order, validates the exact next state, and
   records `commerce.order.status_changed`.
4. Reaching ready dispatches customer-ready mail after commit.

**Result:** No step is skipped or reversed. Payment state remains independent;
the current service does not silently collect funds during advancement.

## 12. Scenario: Cancel and Restock

**Actor:** An operator with `manage-orders`.

1. Select a web order in pending, confirmed, processing, or ready state.
2. Confirm it has no completed payment and stock was committed but not released.
3. Enter a concise cancellation reason.
4. The service locks the order and creates order-cancel stock movements.
5. It sets cancelled state/timestamp and prevents a second release.
6. Activity is recorded and customer cancellation mail is queued after commit.

**Result:** Current stock is restored exactly once while original order and
movement history remain available. Paid cancellation is rejected because the
initial release has no refund engine.

## 13. Scenario: Walk-In POS Sale

**Preconditions:** An active register, an open till assigned to the signed-in
cashier, `access-pos`, and a published saleable product.

1. The cashier opens `/pos` and leaves customer as Walk-in customer.
2. The cashier adds products and quantities.
3. The terminal previews current server-repriced totals.
4. The cashier applies exact tender and completes the sale.
5. Checkout locks the till/products/stock, writes a numbered completed POS
   order, commits stock, writes payment rows, updates expected cash when needed,
   and records activity.
6. The cashier is redirected to the protected receipt.

**Result:** A completed POS order exists without a reusable customer link. Its
customer display snapshot is the walk-in representation.

## 14. Scenario: Known-Customer POS Sale

1. Create or locate the customer in `/admin/commerce/customers`.
2. At `/pos`, search by name, email, or phone and select that record.
3. Complete the sale normally.

**Result:** The order links to the reusable customer and preserves an immutable
snapshot. Editing or archiving that customer later does not rewrite the sale.

## 15. Scenario: Barcode Lookup

1. Focus the terminal search field and scan/enter a value.
2. Submit exact lookup.
3. The server searches internal barcode, manufacturer barcode, then SKU.
4. A unique saleable result is added; ordinary text input continues to provide
   bounded name/SKU discovery.

**Result:** The product is still repriced and stock-checked. A barcode is an
identifier, not a trusted price payload.

## 16. Scenario: Hold and Resume

1. Build a POS cart and choose Hold sale.
2. The module persists an unnumbered held order owned by the current cashier and
   till, with no stock commitment or payment.
3. Later, that same cashier and till select Resume.
4. Checkout reloads current products, revalidates stock, and recalculates
   prices/totals before accepting payment.
5. Completion preserves the held order ULID, assigns the number, replaces stale
   item snapshots, commits stock, and settles payment.

**Result:** A hold is not a reservation. If current price or stock changed, the
resumed transaction follows current facts or fails safely.

## 17. Scenario: Discard a Hold

1. The owning cashier chooses Discard on an unresolved hold.
2. The service verifies channel, state, ownership, till, missing number, and
   absence of stock commitment.
3. It changes the hold to cancelled with an audit reason.

**Result:** No number, stock movement, or payment is created. The audit record
remains, and the till may close once no other holds remain.

## 18. Scenario: Split Tender

1. Build a sale and add a second tender row.
2. Apply part of the total to mobile money/card/bank transfer with an external
   reference where required.
3. Apply the remaining amount to cash or another configured method.
4. Ensure applied amounts exactly equal the recalculated order total.
5. Complete the sale.

**Result:** One completed order owns multiple completed payment rows. Only the
cash-applied amount contributes to expected till cash.

## 19. Scenario: Cash Change

1. Select Cash.
2. Apply the amount due to the order.
3. Enter physical cash received greater than the applied amount.
4. Review server-calculated change.
5. Complete the sale and return the displayed change.

**Result:** Payment amount equals the amount applied to the order. Tendered and
change remain separate receipt values; expected cash rises by the applied cash
amount, not by cash received.

## 20. Scenario: Till Open, Close, and Variance

1. A till manager opens an active free register for an active `access-pos`
   cashier with a non-negative float.
2. The cashier completes counter sales. Completed cash payments update the
   expected projection.
3. Before close, the manager ensures every hold is completed or discarded.
4. The manager counts physical cash and enters the result.
5. `TillService` locks the session, recalculates expected cash, computes
   `counted - expected`, closes the session, and records activity.
6. If absolute non-zero variance meets the configured threshold, an alert is
   queued to active users explicitly holding `manage-tills`.

**Result:** The close values are an immutable reconciliation snapshot. The
current optional demo graph contains a balanced closed till; open and variance
cases must be created through normal operations when needed for QA.

## 21. Scenario: Low-Stock Response

1. Begin with tracked on-hand stock above threshold.
2. A web order, POS sale, or manual decrease moves it into the positive low
   band.
3. The locked inventory mutation emits `StockBecameLow` once after commit.
4. Active `manage-inventory` recipients receive the dedicated alert.
5. An inventory controller reviews movement history and posts a reasoned
   increase when physical replenishment arrives.
6. Moving above threshold rearms a future transition alert.

If a single movement goes from healthy to zero or below, only `StockDepleted`
is emitted. Repeated activity while already low/depleted does not generate
noise. The current optional demo product graph begins with healthy in-stock
items; it does not seed the alert states.

## 22. Scenario Truth Table

| Scenario | Current implementation | Present in optional demo graph |
| --- | --- | --- |
| Guest pickup | Supported | One pending pickup order |
| Guest delivery | Supported | No |
| Partial/completed manual payment | Supported | POS completed payments; web preference only |
| Fulfillment transitions | Supported | Web order begins pending |
| Unpaid cancellation/restock | Supported | No cancelled demo order |
| Walk-in POS | Supported | Demo POS uses a known customer |
| Known-customer POS | Supported | Yes |
| Internal/manufacturer/SKU lookup | Supported | Internal/manufacturer/SKU data exists |
| Hold/resume/discard | Supported | No held demo order |
| Split tender | Supported | Yes, mobile money plus cash |
| Cash change | Supported | Yes |
| Open/close till | Supported | Balanced closed session only |
| Material variance | Supported | No variance fixture |
| Low/out stock response | Supported | Demo starts healthy and tracked |
| Untracked product | Supported | No untracked demo product |

This distinction is intentional: documentation may describe a verified service
workflow without claiming that the local fixture leaves the database in every
possible transient state.
