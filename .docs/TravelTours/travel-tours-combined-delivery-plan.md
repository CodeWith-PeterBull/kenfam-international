# TravelTours combined delivery plan (K3B → booking completion)

**Status:** in progress · **Started:** 2026-09-18 · **Baseline:** `3f63276` (K3A shipped)

This record supersedes the *delivery order* in `travel-tours-k3-scope-and-departure-ux.md` for everything after K3A. Those documents remain intact for audit; this one is the single place where the remaining features are mapped to the milestone that resolves them and where each milestone's results are appended when it lands.

## Outcome

By the end of this plan a customer can complete a booking on the storefront (select a departure, hold seats, check out, receive a confirmation link), staff can record and manually confirm the payment from the admin bookings surface, and a desk operator can do the same for a walk-in traveller. Payments stay manual by default; the gateway seam is the `provider` + `transaction_identifier` columns on `BookingPayment` and the pending → confirmed transition. No `PaymentGateway` interface is introduced (the parent modules do not have one either).

## Fixed decisions

| Decision | Choice | Why |
|---|---|---|
| Booking confirmation | Hybrid: departure `booking_mode` → tour mode → approval default. Payment state is separate. | Already designed in K1; no change. |
| Payment confirmation | Manual by default. Customer chooses a *preferred* method at checkout (not a payment). Staff `recordPending` → `confirm` / `reject`. | Client operates without a gateway today; keeps the seam for one. |
| Travellers | Checkout captures participant snapshots (`BookingParticipant`). Reusable `Traveler` profiles and document uploads are deferred. | Booking completion does not need them. |
| Booking desk | Included, lean: shift open/close, assisted booking through the same services, cash recorded as confirmed with a shift movement, browser receipt print. | Desk already has registers/shifts schema and a terminal boundary. |
| Out of this plan | Reports, per-record agent scoping, calendar/bulk scheduler, instalment-plan tables, gateway interface. | Recorded in `todo/refinements_todo.md` (P-1..P-5). |

## Milestones

| # | Branch | Milestone | Resolves | Outcome gate |
|---|---|---|---|---|
| M1 | `feature/travel-tours-m1-public-availability` | Public availability & selection | K3B | Tour page lists bookable departures with live seats; participant mix → server quote; Continue enabled only for a valid quote. |
| M2 | `feature/travel-tours-m2-hold-checkout` | Hold & checkout | K5 holds, checkout, travellers | Hold on Continue; checkout captures customer + participants + terms + preferred method; booking placed; signed confirmation; expiry commands. |
| M3 | `feature/travel-tours-m3-payments-booking-admin` | Manual payments & booking admin | K5 payments, admin surface | Staff record pending payments, confirm/reject, confirm/cancel bookings, refund. Track page shows state. |
| M4 | `feature/travel-tours-m4-communications` | Communications | K7 (lean) | Placed / payment-pending (staff) / payment-confirmed / booking-confirmed mail, queued once, config-gated. |
| M5 | `feature/travel-tours-m5-pricing-admin` | Pricing administration | K3C, K3D | Rate plans with date windows and participant rates; pricing rules; promotions; promo code in selector. |
| M6 | `feature/travel-tours-m6-booking-desk` | Booking desk | K6 (lean) | Open shift, assisted booking, cash confirmed + movement, receipt, close with expected-vs-counted. |
| M7 | `feature/travel-tours-m7-release-hardening` | Release hardening | K8, D-1..D-5 | Journey tests, contention probe, dead config removed, browser QA, docs closed. |

## Feature → milestone map

| Feature (as previously planned) | Resolved in |
|---|---|
| K3B public availability and selection | M1 |
| K3C advanced rate plans and date-bound fares | M5 (calculator already supports them; M5 adds administration) |
| K3D rules and promotions | M5 (promo entry in selector lands in M1; administration in M5) |
| K5 holds | M2 |
| K5 checkout | M2 |
| K5 travellers | M2 (snapshots) |
| K5 payments | M3 |
| Admin surface (bookings, payments) | M3 |
| K6 booking desk | M6 |
| K7 communications | M4 |
| K8 regression and contention proof | M7 |

## Journeys

```
/tours/{slug} ─► DepartureSelector (M1) ─► hold (M2) ─► /tours/checkout/{hold} (M2)
      ─► TourBookingService::place ─► /tours/bookings/{b}/confirmation (signed)
Staff: /admin/travel/bookings ─► BookingManager (M3): recordPending ─► confirm/reject ─► confirm booking
Desk:  /travel-booking-desk ─► Terminal (M6): shift ─► selection ─► customer ─► cash ─► receipt
```

Lock order is unchanged: departure → holds → booking → promotion; booking → payment/schedule → shift; user → register.

## Standards checklist (every milestone)

- Services `final readonly`, DTO inputs, `DatabaseManager::transaction`, `lockForUpdate`, `forceFill` with server-built arrays, `operation_key` idempotency, integer minor units.
- Livewire: `Gate::authorize` in `boot()` for admin, `#[Locked]` identifiers, `Form` objects, domain exceptions → `addError()`, multi-line Blade with `novalidate` and `aria-*`.
- PHPDoc on every class and method; Pint clean; PHPUnit per milestone; browser QA evidence in `qa/<milestone>/`.
- Conventional commit per milestone, body lists edited files and references; branch → push → fast-forward `main` → push.

## Results

_Appended per milestone._

### M1 — Public availability & selection (K3B) · 2026-09-18

**Delivered.** The tour page's static departure list is replaced by the `DepartureSelector` Livewire component: bookable departures soonest first with live seat evidence from `DepartureAvailabilityService`, the participant mix validated by `SelectionForm`, and the price recomputed by `TourQuoteCalculator` on every change. Nothing priced is trusted from the browser; the quote is computed in `render()` before Livewire shares the error bag so validation and promotion messages are the ones shown.

| Area | Result |
|---|---|
| Listing | `TourDeparture::bookable()`, limit 12, sold-out departures stay visible but are not selectable; ≤ 3 seats shows "Only N places left". |
| Pricing | Departure's own public plan, else the tour's default public plan; a departure with neither is listed as "Price on request". From-price is the single adult rate active today. |
| Quote panel | Fare lines with quantity × unit, rule and promotion lines as credits, tax when non-zero, total, deposit and balance-due copy. |
| Promotion code | Accepted in the selector (K3D entry point); `PromotionNotApplicable` is reported on the field. |
| Guards | Unpublished tours 404 for visitors on mount and on every later request (`hydrate()`); catalog viewers keep the admin preview. |
| Fix found | `@section('meta_description', null)` on tours without descriptions left a Blade output buffer open (pre-existing on `main`; the K3A test worked around it). The section now falls back to the profile description and the K3A test asserts the buffer level instead. |

Files: `Storefront/Livewire/DepartureSelector.php`, `Storefront/Livewire/Forms/SelectionForm.php`, `Resources/views/livewire/storefront/departure-selector.blade.php`, `Storefront/Http/Controllers/TourController.php`, `Catalog/Http/Controllers/TourEditorController.php`, `Resources/views/storefront/catalog/show.blade.php`, `Resources/assets/css/storefront.css`, `TravelToursServiceProvider.php`, `tests/Feature/TravelTours/TravelToursDepartureSelectionTest.php` (7 tests), `tests/Feature/TravelTours/TravelToursDepartureManagementTest.php`.

Verification: `php artisan test --filter=TravelTours` 81 passed (2066 assertions), Pint clean; browser harness `qa-m1-selection.mjs` (scratchpad) against the isolated environment: 12 captures, zero runtime/network errors, no horizontal overflow, evidence in `qa/storefront-m1-selection/`.

Carried forward: none new. M2 adds Continue → hold → checkout on top of this selection.

### M2 — Hold & checkout (K5 holds, checkout, travellers) · 2026-09-18

**Delivered.** Continue on the selector reserves the quoted seats through `AvailabilityHoldService` and hands over to `/tours/checkout/{hold}`, where `BookingCheckout` collects the customer, one identity per held seat, a payment preference, and terms acceptance, then places the booking through the existing `TourBookingService::place`. The confirmation and tracking pages now explain the next step for the chosen payment method and link the booking document. Two scheduled sweeps free expired holds and expire unpaid pending bookings.

| Area | Result |
|---|---|
| Hold identity | `CheckoutSession` keeps a secret owner token in the session; operation keys are `sha256(owner \| nonce \| quote fingerprint)`, so repeating a selection reuses the active hold and rotating the nonce (after placement or expiry) allows a fresh one. |
| Checkout guards | Ownership proven on the page request, on mount, and on every Livewire request (`hydrate`); foreign sessions get 403; consumed holds redirect to the confirmation; expired or released holds answer 410 with the expired page and rotate the nonce. |
| Travellers | Rows fixed by the held mix (adults, children, infants); traveller 1 can reuse the customer's details; child and infant dates of birth are required; fields validate on blur. The service still re-verifies the mix, price fingerprint, and terms version before writing. |
| Payment preference | From `storefront.payment_methods` ∩ `PaymentMethod`; recorded as `preferred_payment_method` only. No money is taken online. |
| Status page | "What happens next" per method with deposit due and pay-by time; travellers; confirmed payments; PDF and tracking links. |
| Sweeps | `travel-tours:release-expired-holds` (every minute) and `travel-tours:expire-pending-bookings` (every 15 minutes) via `BookingLifecycleService::expirePending()`, which skips bookings with any recorded money. |
| Fixes found | `MoneyFormatter` threw on negative amounts, so the booking PDF failed for any booking with a promotion; it now renders a leading minus. Livewire never caches a computed property that returns `null`, so the selector's pricing is wrapped in `QuoteAttempt` to stop validation re-running (and clearing errors) on every access. Unused `booking.quote_minutes` removed. |

Files: `Storefront/Services/CheckoutSession.php`, `Storefront/Data/QuoteAttempt.php`, `Storefront/Http/Controllers/CheckoutController.php`, `Storefront/Livewire/BookingCheckout.php`, `Storefront/Livewire/Forms/CheckoutForm.php`, `Storefront/Livewire/DepartureSelector.php`, `Storefront/Http/Controllers/BookingAccessController.php`, `Bookings/Services/BookingLifecycleService.php`, `Console/Commands/ReleaseExpiredHoldsCommand.php`, `Console/Commands/ExpirePendingBookingsCommand.php`, `Resources/views/storefront/checkout/{show,expired}.blade.php`, `Resources/views/livewire/storefront/{booking-checkout,departure-selector}.blade.php`, `Resources/views/storefront/bookings/show.blade.php`, `Resources/assets/css/storefront.css`, `Routes/storefront.php`, `Config/travel-tours.php`, `Support/MoneyFormatter.php`, `TravelToursServiceProvider.php`, `routes/console.php`, `tests/Feature/TravelTours/TravelToursCheckoutTest.php` (9 tests), `tests/Feature/TravelTours/TravelToursStorefrontTest.php`.

Verification: `php artisan test --filter=TravelTours` 90 passed (2161 assertions), Pint clean; browser harness `qa-m2-checkout.mjs` (scratchpad) walked select → Continue → checkout validation → placement → confirmation → PDF, the consumed-hold redirect, the foreign-session 403, and the expired page across desktop and mobile in both themes: 9 captures, zero runtime/network errors, evidence in `qa/storefront-m2-checkout/`.

Carried forward: none new. A customer can now place a booking; M3 lets staff record and confirm the payment.

### M3 — Manual payments & booking admin (K5 payments, admin surface) · 2026-09-19

**Delivered.** Payments now move in two audited steps: an agent records evidence (`recordPending`) and someone holding `confirm-tour-payments` confirms or rejects it; only confirmation settles aggregates, instalments, and the desk cash movement. Refunds (`refund-tour-payments`) are recorded against confirmed money and drive `PartiallyRefunded`/`Refunded`. The `BookingManager` workspace replaces the read-only bookings table with filters, a full detail dialog, and every lifecycle and money action, each authorized per request.

| Area | Result |
|---|---|
| Payment seam | `ProcessesBookingPayments` gains `recordPending`, `confirm`, `reject`, `refund`; `record()` is now pending + confirm in one transaction (desk cash, existing tests unchanged). A future gateway calls `recordPending` with provider + transaction identifier and then `confirm`; nothing else changes. |
| Guards | Amount ≤ outstanding at recording and again at confirmation; refunds ≤ net paid and ≤ the named payment's remainder; rejected rows keep their reason in `safe_metadata`; terminal bookings refuse money. |
| Lifecycle | `BookingLifecycleService::confirm` / `cancel` (reason required, releases the promotion redemption) / `complete` (only after the departure ends), each historied. |
| Permissions | `CONFIRM_PAYMENTS`, `REFUND_PAYMENTS` added to the catalogue; manager holds both, agent keeps `MANAGE_PAYMENTS` (record only). `BookingPolicy` gains `recordPayment`, `confirmPayment`, `refund`. |
| Workspace | `#[Url]` search/status/payment filters (LIKE wildcards escaped), pagination, detail dialog (journey, customer, travellers, price, payments, refunds, history), record/confirm/reject/refund/confirm/cancel/complete, operation keys minted when a money dialog opens so a double submit records once. |
| Money input | `ScaledDecimal::toMinor()` parses operator decimals by the booking's exponent without floats. |

Files: `Bookings/Services/BookingPaymentService.php`, `Bookings/Services/BookingLifecycleService.php`, `Bookings/Data/BookingRefundData.php`, `Bookings/Exceptions/BookingLifecycleException.php`, `Bookings/Livewire/Admin/BookingManager.php`, `Bookings/Livewire/Forms/PaymentRecordForm.php`, `Bookings/Livewire/Forms/RefundForm.php`, `Bookings/Http/Controllers/BookingAdminController.php`, `Contracts/ProcessesBookingPayments.php`, `Policies/BookingPolicy.php`, `Support/TravelToursPermission.php`, `Support/ScaledDecimal.php`, `Resources/views/livewire/admin/bookings/booking-manager.blade.php`, `Resources/views/admin/bookings/index.blade.php`, `Resources/assets/css/admin.css`, `TravelToursServiceProvider.php`, `tests/Feature/TravelTours/TravelToursPaymentConfirmationTest.php` (5 tests), `tests/Feature/TravelTours/TravelToursBookingManagerTest.php` (6 tests).

Verification: `php artisan test --filter=TravelTours` 101 passed (2261 assertions), Pint clean; browser harness `qa-m3-bookings.mjs` (scratchpad): agent records evidence and sees no confirm control, manager confirms the payment and the booking, refund ceiling refused inline then a refund recorded, cancel requires a reason; desktop/mobile/narrow in both themes: 13 captures, zero runtime/network errors, dialogs within the viewport, every input labelled. Evidence in `qa/admin-m3-bookings/`.

Outcome gate met: a customer can complete a booking and the travel desk can confirm the payment manually. M4 adds the customer and staff mail for these steps.

### M4 — Communications (K7 lean) · 2026-09-19

**Delivered.** One domain event now produces exactly one queued mail job: listeners run synchronously inside the request and hand off to a notification that extends `QueuedTravelNotification` (`afterCommit`, module queue from `notifications.queue`, 3 tries, 30/120/300 s backoff). Delivery is gated by `notifications.enabled` at the listener, so the domain writes never depend on mail. Two events join the existing two.

| Event | Recipient | Notification |
|---|---|---|
| `TourBookingPlaced` | customer email snapshot | `TourBookingPlacedNotification` — total, deposit, pay-by time, private tracking link |
| `BookingPaymentRecorded` (new, dispatched by `recordPending`) | active users holding `confirm-tour-payments` via `StaffRecipientResolver` | `BookingPaymentRecordedNotification` — amount, method, reference, link into the bookings workspace |
| `BookingPaymentConfirmed` | customer | `BookingPaymentConfirmedNotification` — amount, reference, outstanding balance |
| `TourBookingConfirmed` (new, dispatched by `BookingLifecycleService::confirm`) | customer | `TourBookingConfirmedNotification` — travel dates, balance, tracking link |

Resolves the K1 review's double-queue concern (listener and notification both queued) and the unused `notifications.*` configuration. Root `Events/`, `Listeners/`, `Notifications/` stay where they are (P-6).

Files: `Notifications/QueuedTravelNotification.php`, `Notifications/TourBookingPlacedNotification.php`, `Notifications/BookingPaymentConfirmedNotification.php`, `Notifications/TourBookingConfirmedNotification.php`, `Notifications/BookingPaymentRecordedNotification.php`, `Events/TourBookingConfirmed.php`, `Events/BookingPaymentRecorded.php`, `Listeners/SendTourBookingPlacedNotification.php`, `Listeners/SendBookingPaymentConfirmedNotification.php`, `Listeners/SendTourBookingConfirmedNotification.php`, `Listeners/NotifyStaffOfPendingPayment.php`, `Support/StaffRecipientResolver.php`, `Bookings/Services/BookingPaymentService.php`, `Bookings/Services/BookingLifecycleService.php`, `TravelToursServiceProvider.php`, `tests/Feature/TravelTours/TravelToursNotificationsTest.php` (5 tests).

Verification: `php artisan test --filter=TravelTours` 106 passed (2275 assertions), Pint clean; each notification rendered against the isolated QA database (`qa/communications-m4/*.html`, `diagnostics.json`): subjects, queue, after-commit flag, retries, and links checked.
