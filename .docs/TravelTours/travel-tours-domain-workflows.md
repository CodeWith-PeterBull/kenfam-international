# Travel & Tours Domain Workflows

Status: target behavior for service implementation. None of these transaction
guarantees is accepted without the corresponding verification evidence.

## 1. Catalog And Publication

An authorized editor creates draft categories/destinations and tour content.
Hierarchy changes lock the affected aggregate and reject direct/indirect cycles.
Tour children are always validated against their owning tour. Reordering is
atomic; assignment of one primary category clears the previous primary.

Publication requires valid name/slug, description, duration, destination,
accessible cover and approved policy content. A published tour can be visible
without a bookable departure, showing an inquiry action instead of invented
availability. Public queries filter publication status/time and archive state
on every route, not just catalog cards. Archive never removes booking snapshots.

## 2. Departure Scheduling

Input contains local start/end, IANA timezone, booking window, capacity, minimum
participants, confirmation override and optional same-tour rate plan.
Validate timezone and DST ambiguity, convert instants to UTC, then persist.
End must follow start; opening precedes closing; closing cannot exceed departure.
Changing capacity or dates on a sold departure needs a locked commitment check,
an explicit operator reason and dedicated affected-customer notification.

Status path: draft -> open -> guaranteed -> departed -> completed.
Open/guaranteed may close or cancel. Sold-out is availability presentation, not
permission to skip capacity checks. Reopening closed inventory validates window,
tour publication, pricing and operational state. Cancellation uses booking
lifecycle/refund services, not a bulk status update.

## 3. Deterministic Quote Pipeline

Input: departure, rate plan, per-traveler age/type data, requested extras,
promotion code, actor/channel and explicit quote instant. Never accept totals.
Validate public/staff eligibility, ownership, currency and participant bounds.
Age is calculated on the departure's local start date; unknown required DOB
must be collected or explicitly handled by operator policy.

Evaluation order:
1. Select exactly one eligible base ParticipantRate per participant; overlapping
   bands/windows are a configuration error, not first-row-wins.
2. Apply the matching seasonal stage, then group stage, then early-bird stage.
   Within a stage use priority then immutable ID. A non-stackable match ends
   that stage; stackable matches apply in the documented order.
3. Add validated tour extras. Fixed adjustments state whether per participant
   or booking-wide; do not infer units from the sign of a number.
4. Validate promotion scope/exclusions, currency, sales window, thresholds and
   limits. Exclusion wins. Invalid requested codes produce an explicit error.
5. Allocate discounts, calculate tax, sum final lines, then calculate deposit
   and instalment due amounts.
6. Return immutable line/snapshot data, currency exponent, calculation version,
   generated/expiry timestamps and fingerprint.

Money stays integer. Round half-up at the declared line boundary, distribute
residual minor units in stable participant order, and preserve reconciliation.
Inclusive tax extracts the tax portion; exclusive tax adds it. Never tax a
discount twice. Percentage bounds and signed overrides are validated; total and
deposit cannot become negative or deposit exceed payable.

Example, tax-exclusive KES minor units: two adults at 100000 each, child at
50000 => subtotal 250000. A 10% eligible group discount => 25000 reduction.
At an illustrative 16% configured tax, 225000 + 36000 = 261000.
A 30% deposit => 78300, remaining instalment => 182700. These are calculation
fixtures, not a statement of applicable Kenyan travel tax.

A quote does not reserve seats. Fingerprints detect changed inputs/results but
do not authorize a booking. Recalculate under transaction before commitment.

## 4. Hold Creation, Renewal And Expiry

Resolve the requester from authenticated ownership or a purpose-hashed anonymous
session token. Do not accept another customer's ID from a hidden input.
Lock departure first; then relevant hold rows in ID order. Expired holds are
excluded by timestamp whether cleanup ran or not. Count consuming bookings plus
active unexpired holds, excluding only a validated hold being replaced.

Reject overcapacity, invalid date/window, stale quote or party limits.
Idempotent same-key/same-input retry returns the existing result; changed input
under the same key is a conflict. Bound renewal count/lifetime to prevent
inventory denial through endless anonymous renewals. Rate-limit creation.

Hold lifecycle: active -> consumed | released | expired; terminal states cannot
become active again. Expiry cleanup locks/rechecks and is safe to repeat.
Waitlist inquiries consume zero seats.

## 5. Booking Placement And Lock Order

One transaction:
1. Validate input shape, terms acceptance, actor/channel and operation identity.
2. Lock the departure; lock its referenced hold and relevant inventory rows in
   deterministic ID order. Never lock hold first in one path and departure first
   in another.
3. Recheck hold owner, active/expiry state, one-booking uniqueness, publication,
   booking window and capacity. Reprice on the server with an explicit clock.
4. Lock promotion when applicable; recheck remaining uses under that lock.
5. Resolve customer identity safely. Anonymous contact does not authorize
   modifying a verified customer or attaching their saved travelers.
6. Validate exact participant counts/ages/seat policy, extras and same-parent
   ownership. Create booking plus immutable participant/price/policy snapshots,
   status history, redemption and payment schedule.
7. Mark hold consumed, with a unique consumed-booking relationship.
8. Commit; only then dispatch placement/approval-request notifications and
   privacy-safe activity. Send no mail within the transaction.

Same operation replay returns the original booking only for the same requester
and normalized payload. Partial failure rolls back all rows, capacity and
redemption. Unique constraints are final guards, not substitutes for ownership.
An admin booking without a hold follows the same departure locking and pricing.

Global lock families: departure -> holds -> booking -> promotion for placement;
booking -> payment/schedule -> shift for payment; user -> register for shift
open. No operation acquires these in reverse. When a workflow spans families,
document and test the combined order before adding it.

## 6. Confirmation And Lifecycle

Hybrid resolution: explicit departure mode -> tour mode -> safe approval default.
Persist resolved instant/approval mode in the booking. Payment state is separate.

| From | Action | To | Capacity/effect |
| --- | --- | --- | --- |
| None | Instant placement | confirmed | Seats committed; unpaid may remain unpaid |
| None | Approval placement | pending | Seats committed until approval deadline |
| pending | Approve | confirmed | No second capacity allocation |
| pending | Reject/cancel | cancelled | Release capacity; record reason |
| pending | Deadline elapsed | expired | Release once; no automatic refund |
| confirmed | Approved cancellation | cancelled | Release; refund handled independently |
| confirmed | Operational completion | completed | Retain historical occupancy and finances |

A paid pending booking expiring requires operator review and a refund workflow;
it must not silently discard money. Rebooking/rescheduling must reprice and
reserve the new departure before releasing old capacity, with a separately
specified multi-departure lock order (ascending departure IDs). Do not expose
that feature until its transaction is tested.

## 7. Payments, Instalments And Refunds

Manual payment request contains booking, method, amount, currency, reference,
operation key and optional schedule/shift. Server supplies receiver and status.
A public preference selection is not confirmed payment. Lock booking and target
records; validate amount, ownership, currency and allowed state. Confirmation
updates payment, paid total and instalment allocation atomically.

Provider identity is provider + transaction ID, not an assumed globally unique
bank reference. Replays are idempotent only with equivalent payload. Refuse
unsupported overpayment or place it in an explicitly implemented credit workflow;
never silently clamp. Failed/pending transactions do not increase paid totals.

Refund request identifies original payment, amount and reason. Lock originals,
subtract prior successful/pending reserved refunds, and reject over-refund.
Completion posts refund and cash movement if applicable, updates net payment
state and emits a dedicated event. A refund does not automatically cancel a
booking; cancellation does not mean money was returned. No deletion of attempts.

Schedules sum to payable. Allocate confirmed payments in deterministic due order,
record partial amounts and derive overdue from date. Waiver needs an authorized
financial adjustment, not merely a status checkbox.

## 8. Booking Desk And Shift Operations

Open: authorize operator, lock user then register, verify active register/no
existing open shift, write opening float and unique active guards atomically.
System administrator may operate but still needs a valid shift for desk cash.

Terminal: select departure/party/customer, get server quote, commit booking,
record tender(s) and receipt using the same services as web/admin. Desk customer
selection is permission scoped. A held quote is not a completed sale.

Expected cash = opening float + confirmed cash receipts + cash-ins - cash
refunds - cash-outs. Mobile-money/card/transfer receipts remain visible in
reporting but never enter drawer cash. Use one movement per financial source.
Closing locks shift, stops new postings, recomputes expected cash, stores actual
and variance, releases active guards. Supervisor reconciliation records reason,
actor/time and emits variance alert without editing prior movements.

Receipt auto mode may open the browser print dialog after successful commit.
A failed/cancelled print does not roll back the sale; reprint the same receipt.
58/80 mm and PDF share privacy-limited immutable data and institution branding.

## 9. Inquiry And Customer Workflow

Public inquiry validates tour/departure publication and relationship, contact
channel, dates, party and consent. Rate-limit and use an actual registered
anti-spam middleware; do not insert a nonexistent alias. WhatsApp uses a
normalized telephone and URL-encoded minimal inquiry text, never passport data.

New -> assigned -> in_progress -> awaiting_customer -> converted/closed.
Spam is operator classified. Every assignment/contact/transition appends activity.
Conversion invokes booking service and links the resulting booking once;
availability/prices are rechecked at conversion.

Anonymous guests get transaction contact snapshots. They cannot overwrite a
known verified profile through email matching. Saved traveler reuse requires
ownership or staff permission. Health/document capture is purpose-limited and
never required for an ordinary initial inquiry.

## 10. Notifications, Documents And Failure Recovery

One event corresponds to one focused notification. After-commit dispatch,
recipient resolution, delivery identity and queue retry handling must prevent
duplicate business messages. Confirmation, payment receipt, refund and departure
change are separate. Module-disable and preference checks occur at delivery.

Document adapters expose only necessary fields. Public signed summaries exclude
internal notes, all identity/medical data and provider payloads. Operational
manifests require stronger permission and audited access. All documents preserve
institution context, orientation, currency and commercial snapshots.

Recoverable errors return explicit field/action feedback: unavailable seats,
expired hold, changed quote, invalid promotion, ownership conflict, closed shift.
Unexpected failures retain correlation IDs, not sensitive payloads. Reconciliation
commands report discrepancies; they do not silently rewrite historical money.
