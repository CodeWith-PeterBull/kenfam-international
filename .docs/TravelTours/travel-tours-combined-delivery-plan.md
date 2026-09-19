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

### M5 — Pricing administration (K3C, K3D) · 2026-09-19

**Delivered.** The calculator already applied rate plans, date-bound fares, rules, and promotions; this milestone gives operators the surfaces to define them. `/admin/travel/pricing` lists tours with their pricing coverage, `/pricing/tours/{tour}` hosts the `RatePlanManager` and `PricingRuleManager`, and `/pricing/promotions` hosts the `PromotionManager`, all under `view-tour-pricing` / `manage-tour-pricing` through a shared `PricingPolicy`.

| Area | Result |
|---|---|
| Rate plans | `RatePlanService::save` writes a plan and its fares in one transaction; one default per tour; codes unique per tour; currency frozen once departures or bookings use the plan; a default or still-selling plan cannot be deactivated. |
| Fares | Replaced atomically; active fares of one participant type may not overlap in time (open ends count), at least one active adult fare, ordered age bands and windows. Percentages entered by operators become basis points through `ScaledDecimal::toMinor`. |
| Rules | Seasonal, group, and early-bird adjustments (percentage in basis points, fixed, or override), ordered travel and sales windows, departure must belong to the tour; rules are switched off, never deleted (booking price lines reference them). |
| Promotions | Case-insensitive unique codes, fixed discounts carry a currency, all-tours-with-exclusions or explicit tour list, validity and usage limits; toggled, never deleted. |
| Storefront | The promotion entry point shipped in M1 now has codes to accept; the selector quotes what the manager saved (covered by `test_saved_plan_and_promotion_price_the_storefront_selection`). |
| Fixes found | A refused fare set used to leave an empty plan behind (plan and fares were saved in two steps); now one transaction. A prevented click on a `role="switch"` pinned the old checked state after re-render; switches now carry a state-bearing `wire:key` (also applied to the category manager). |

Files: `Pricing/Services/RatePlanService.php`, `Pricing/Services/PricingRuleService.php`, `Pricing/Services/PromotionService.php`, `Pricing/Data/{RatePlanData,ParticipantRateData,PricingRuleData,PromotionData}.php`, `Pricing/Livewire/Admin/{RatePlanManager,PricingRuleManager,PromotionManager}.php`, `Pricing/Livewire/Forms/{RatePlanForm,PricingRuleForm,PromotionForm}.php`, `Pricing/Http/Controllers/PricingAdminController.php`, `Policies/PricingPolicy.php`, `Resources/views/admin/pricing/{index,tour,promotions}.blade.php`, `Resources/views/livewire/admin/pricing/{rate-plan-manager,pricing-rule-manager,promotion-manager}.blade.php`, `Resources/views/livewire/admin/catalog/tour-category-manager.blade.php`, `Resources/views/admin/catalog/index.blade.php`, `Pricing/Services/TourBasePriceService.php`, `Routes/admin.php`, `TravelToursServiceProvider.php`, `tests/Feature/TravelTours/TravelToursPricingAdministrationTest.php` (7 tests).

Verification: `php artisan test --filter=TravelTours` 113 passed (2327 assertions), Pint clean; browser harness `qa-m5-pricing.mjs` (scratchpad): overlapping fares refused inline then saved, group rule created and toggled with the switch state verified after re-render, promotion validated and saved, editor sees no write controls; desktop/mobile in both themes: 14 captures, zero runtime/network errors, dialogs within the viewport, every input labelled. Evidence in `qa/admin-m5-pricing/`.

### M6 — Booking desk (K6 lean) · 2026-09-19

**Delivered.** The terminal boundary at `/travel-booking-desk` is now the `Terminal` Livewire workspace: the operator opens a shift on a register with a counted float, sells a booking through the same quote → hold → placement → payment services the storefront uses (attributed to the register, shift, and operator), prints a receipt through the browser, declares cash in/out, and closes the shift against the counted drawer. Managers create registers and reconcile closed shifts at `/admin/travel/shifts`.

| Area | Result |
|---|---|
| Shifts | `BookingShiftService`: one open shift per operator and per register (guard columns + checks, lock order user → register); opening writes the float movement; `close` derives expected cash from the signed movement ledger and records the variance; `reconcile` is the manager sign-off; variances above `pob.variance_threshold_minor` are flagged. |
| Expected cash | Float + cash payments + cash-in − cash refunds − cash-out, always the sum of `travel_booking_shift_movements.amount_minor`; every financial source writes exactly one movement (payment service for cash payments and refunds, shift service for float and manual movements). |
| Sale | `DeskCheckoutService::sell` in one transaction: quote, hold keyed to the shift and sale key, `TourBookingService::place` with channel `booking_desk`, money received recorded and confirmed at once, booking confirmed on the operator's authority once confirmed money covers the deposit; a double submit records once. |
| Receipts | `PrintsBookingReceipts` is bound to `BrowserReceiptPrinterDriver`; `ReceiptPrinterManager` resolves the register's driver and hands the terminal a `ReceiptPrintInstructionData` (signed-in receipt page, 58/80 mm, automatic print once per session via `sessionStorage`). Printing never rolls back a sale. |
| Authorization | Desk operators (`access-travel-booking-desk`) open and close their own shift (`ShiftPolicy::open`); registers and reconciliation stay with `manage-travel-booking-shifts`. The K1 closeout test that refused a log-only printer stub now asserts the browser driver binding. |
| Fix found | `record()` (money in hand) announced a `BookingPaymentRecorded` event before confirming in the same step, mailing confirmers about evidence that needed no confirmation; pending is now announced only by `recordPending`. |

Files: `PointOfBooking/Services/{BookingShiftService,DeskCheckoutService,ReceiptPrinterManager}.php`, `PointOfBooking/Printing/BrowserReceiptPrinterDriver.php`, `PointOfBooking/Data/{DeskSaleData,ShiftMovementData,ReceiptPrintInstructionData}.php`, `PointOfBooking/Exceptions/PointOfBookingException.php`, `PointOfBooking/Livewire/Terminal.php`, `PointOfBooking/Livewire/Admin/ShiftManager.php`, `PointOfBooking/Livewire/Forms/DeskSaleForm.php`, `PointOfBooking/Http/Controllers/{ReceiptController,ShiftAdminController}.php`, `Policies/ShiftPolicy.php`, `Support/TravelToursPermission.php`, `Bookings/Services/BookingPaymentService.php`, `Resources/views/livewire/pob/terminal.blade.php`, `Resources/views/livewire/admin/pob/shift-manager.blade.php`, `Resources/views/pob/receipt.blade.php`, `Resources/views/pob/terminal/index.blade.php`, `Resources/views/admin/shifts/index.blade.php`, `Resources/assets/css/admin.css`, `Routes/pob.php`, `Routes/admin.php`, `TravelToursServiceProvider.php`, `tests/Feature/TravelTours/TravelToursBookingDeskTest.php` (6 tests), `tests/Feature/TravelTours/TravelToursNotificationsTest.php`, `tests/Feature/TravelTours/TravelToursFoundationCloseoutTest.php`.

Verification: `php artisan test --filter=TravelTours` 120 passed (2409 assertions), Pint clean; browser harness `qa-m6-desk.mjs` (scratchpad): manager creates a register, agent opens a shift, sells with cash (booking confirmed, drawer = float + cash), opens the 58/80 mm receipt, banks cash out, closes with a KES 500 shortfall reported, manager reconciles; desktop/mobile in both themes: 15 captures, zero runtime/network errors, dialogs within the viewport, every input labelled. Evidence in `qa/pob-m6-desk/`.

Process note: the isolated QA database was wiped once during this milestone by running the test suite in a shell that still had the QA environment exported (`phpunit.xml` does not force `DB_DATABASE`). It was rebuilt from the seeders; the developer database was never involved. Tests are now run only from a clean shell.

### M6 realignment — desk mirrored on the PropertyBooking POB · 2026-09-19

**Delivered on the M7 branch.** Review of the M6 surface found it misaligned with the sibling modules: the desk rendered inside the dashboard with a form-first layout, operators opened their own shifts from the terminal, and registers and shifts shared one admin page. The desk now follows the PropertyBooking Point of Booking in structure, interaction, and operations, while keeping the M6 domain layer (`BookingShiftService` ledger, `DeskCheckoutService` reuse of the storefront services).

| Area | Result |
|---|---|
| Shell | `layouts/pob.blade.php` is a dedicated full-width shell (`body.travel-tours-pob-shell`): brand, register/shift links for shift managers, dashboard, theme toggle, operator chip, sign-out; `assets/css/pob.css` and `assets/js/pob.js` are ports of the parent sheet and print script under the travel class names. |
| Terminal | Three panes as in the parent: **01 Departures** (date window with a "Today" quick action, traveller mix, tour/departure search, departure cards with cover, dates, seats left, and the priced total for the mix), **02 Booking** (selection summary, customer search and select, compact new-customer form via `TravelCustomerService::resolve`, traveller rows that follow the mix with "customer travels" as lead, special requests, active holds with resume/discard), **03 Payment** (promotion code, fares/discount/tax/total/deposit due, split tenders with method, amount, cash tendered or reference, "Deposit" and "Total" quick-apply, Hold booking / Complete booking). Context strip shows register, operator, shift opened, active holds, and a shift selector for managers who may operate any open shift (`#[Url] shift`). |
| Holds | A hold is a pending desk booking parked on the shift (`DeskCheckoutService::hold`); `checkoutHeld` settles it, `discardHeld` cancels it through the lifecycle service; a shift with holds cannot close. |
| Settlement | `DeskCheckoutService::checkout` records every tender as confirmed money (`DeskTenderData`; cash carries tendered/change in `safe_metadata`), requires the sum to cover the outstanding deposit and never exceed the balance, then confirms the booking; `completeBooking` redirects to the receipt with `print=checkout`. |
| Shifts | Opened and closed by shift managers only (`ShiftPolicy::open/close` = `manage-travel-booking-shifts`; `operate` = own shift or manager): `ShiftManager` at `/admin/travel/pob/shifts` opens a free register for a free desk operator with a counted float and note (`ShiftOpenForm`), reconciles with the ledger's expected cash prefilled (`ShiftCloseForm`), and signs closed shifts off; `ShiftVarianceDetected` → `ShiftVarianceNotification` to managers past `pob.variance_threshold_minor` (queued, re-reads the shift). Manual cash in/out has no terminal dialog any more (P-7); the ledger and `recordMovement` remain. |
| Registers | `RegisterManager` at `/admin/travel/pob/registers` through `BookingRegisterService` (create, update, activate/retire, never delete): search, state filter, statistics, receipt driver/mode/paper width/printer label (`ReceiptPrintMode`, `ReceiptPaperWidth`). |
| Receipts | Per booking at `/travel-booking-desk/receipts/{booking}` (+`/pdf`): `BookingReceiptDataFactory` projects the immutable snapshots into `BookingReceiptData` (masked contact), `ReceiptPrinterManager` resolves the register's driver from `pob.receipt_printing.drivers` and hands the page a browser-safe `ReceiptPrintInstructionData`; the page script prints once after checkout when the register is on auto prompt. `BookingReceiptService` streams the A4 PDF through the shared report engine. |
| Navigation | Admin and role sidebars carry Booking desk, Booking registers, and Booking shifts (the role sidebar also gained the departures, pricing, bookings, and inquiries entries that earlier milestones left out). |
| Fix found | Switching a tender from cash to another method let the reused `<input>` keep its old `wire:model` binding, so a typed reference also landed in `tendered` and failed validation silently; the conditional inputs now carry distinct `wire:key`s and `tenderData()` clears the field the method does not use. |

Config: `pob.maximum_tenders`, `pob.search_results`, `pob.payment_methods`, `pob.receipt_printing.drivers` (env keys in `.env.example`). Routes: `travel-tours.pob.terminal`, `travel-tours.pob.receipts.show|pdf`, `travel-tours.pob.admin.registers.index|shifts.index`; `travel-tours.admin.shifts.index` and the per-payment receipt route are gone.

Files: `Resources/views/layouts/pob.blade.php`, `Resources/assets/css/pob.css`, `Resources/assets/js/pob.js`, `PointOfBooking/Livewire/Terminal.php`, `PointOfBooking/Livewire/Admin/{RegisterManager,ShiftManager}.php`, `PointOfBooking/Livewire/Forms/{RegisterForm,ShiftOpenForm,ShiftCloseForm}.php`, `PointOfBooking/Services/{BookingRegisterService,BookingShiftService,DeskCheckoutService,ReceiptPrinterManager,BookingReceiptDataFactory,BookingReceiptService}.php`, `PointOfBooking/Data/{DeskSaleData,DeskTenderData,ReceiptPrinterSettingsData,ReceiptPrintInstructionData}.php`, `PointOfBooking/Data/Documents/{BookingReceiptData,BookingReceiptLineData,BookingReceiptPaymentData}.php`, `PointOfBooking/Enums/{ReceiptPaperWidth,ReceiptPrintMode}.php`, `PointOfBooking/Events/ShiftVarianceDetected.php`, `PointOfBooking/Listeners/SendShiftVarianceNotification.php`, `PointOfBooking/Notifications/ShiftVarianceNotification.php`, `PointOfBooking/Printing/BrowserReceiptPrinterDriver.php`, `PointOfBooking/Http/Controllers/{TerminalController,ReceiptController,RegisterAdminController,ShiftAdminController}.php`, `Contracts/PrintsBookingReceipts.php`, `Policies/ShiftPolicy.php`, `Support/TravelToursPermission.php`, `Config/travel-tours.php`, `Routes/pob.php`, `Routes/admin.php`, `TravelToursServiceProvider.php`, `Resources/views/pob/terminal/index.blade.php`, `Resources/views/pob/receipts/show.blade.php`, `Resources/views/pob/admin/{registers,shifts}/index.blade.php`, `Resources/views/livewire/pob/terminal.blade.php`, `Resources/views/livewire/pob/admin/{register-manager,shift-manager}.blade.php`, `Resources/views/reports/booking-receipt.blade.php`, `Resources/assets/css/admin.css`, `resources/views/layouts/partials/{sidebar-admin,sidebar-role}.blade.php`, `vite.config.js`, `.env.example`, `tests/Feature/TravelTours/TravelToursBookingDeskTest.php` (7 tests), `tests/Feature/TravelTours/TravelToursBookingJourneyTest.php`. Removed: `PointOfBooking/Livewire/Forms/DeskSaleForm.php`, `Resources/views/pob/receipt.blade.php`, `Resources/views/admin/shifts/index.blade.php`, `Resources/views/livewire/admin/pob/shift-manager.blade.php`.

Verification: browser harness `qa-desk-realigned.mjs` (scratchpad): manager creates an auto-prompt register and opens a shift for the agent (float validation shown, expected cash = float); agent searches tours, prices two adults, creates and selects a customer, applies the deposit, adds a mobile-money split, completes → receipt page with both tenders, balance, one `window.print()` after checkout and none on reload, PDF served; parks a hold from customer search, resumes it, discards it; manager reconciles with the expected cash prefilled, closes KES 500 short, sees the variance, signs off; agent without a shift sees the gate. Desktop/mobile in both themes: 20 captures, zero runtime/network errors, dialogs within the viewport, every input labelled. Evidence in `qa/pob-m6-desk-realigned/`.

### M7 — Release hardening (K8, D-1..D-5) · 2026-09-19

**Delivered.** The two journeys are proven end to end by tests, capacity is proven safe under contention at the transaction level, the K3A refinements are closed, dead configuration is gone, and every surface was swept in the browser.

| Area | Result |
|---|---|
| Journey tests | `TravelToursBookingJourneyTest`: web — select → hold → checkout → pending → agent records evidence → manager confirms payment and booking → confirmed, tracked, documented, four notifications; desk — manager opens a shift → agent completes a walk-in with cash → confirmed at once → receipt → balanced close. |
| Contention | `TravelToursContentionTest`: two owners racing for the last seat (second refused until the first releases), two storefront visitors (second sees the refusal on Continue), the expiry sweep frees only stale holds. Serialisation is on the locked departure row, so the transaction-level proof is the one that matters; a multi-process MariaDB probe is deferred (P-8) because no MariaDB server exists on this workstation. |
| D-1 | `DepartureForm` reads `travel-tours.defaults.timezone`. |
| D-2 | `departure-manager.blade.php`, `admin/dashboard/index.blade.php`, and `admin/inquiries/index.blade.php` rewritten multi-line. |
| D-3 | `Support/LikePattern` (`contains()` + `CLAUSE` with `ESCAPE '!'`) applied to every list search: catalog, destinations, departures, bookings, promotions, storefront search, and the new desk searches. |
| D-4 | `CatalogException` and `DepartureException` extend `TravelToursException`. |
| D-5 | The central departure selector is bounded (`DepartureManager::TOUR_OPTION_LIMIT`). |
| Configuration | `currency_symbol`, `catalog_limit`, and `numbering.padding` removed from `Config/travel-tours.php` and `.env.example` (never read). |

Files: `Support/LikePattern.php`, `Catalog/Livewire/Admin/{TourCatalog,DestinationManager}.php`, `Catalog/Services/TourSearchService.php`, `Scheduling/Livewire/Admin/DepartureManager.php`, `Scheduling/Livewire/Forms/DepartureForm.php`, `Bookings/Livewire/Admin/BookingManager.php`, `Pricing/Livewire/Admin/PromotionManager.php`, `Catalog/Exceptions/CatalogException.php`, `Scheduling/Exceptions/DepartureException.php`, `Config/travel-tours.php`, `.env.example`, `Resources/views/livewire/admin/scheduling/departure-manager.blade.php`, `Resources/views/admin/dashboard/index.blade.php`, `Resources/views/admin/inquiries/index.blade.php`, `tests/Feature/TravelTours/TravelToursBookingJourneyTest.php` (2 tests), `tests/Feature/TravelTours/TravelToursContentionTest.php` (3 tests).

Verification: `php artisan test --filter=TravelTours` 126 passed (2511 assertions), Pint clean; release sweep `qa-m7-sweep.mjs` (scratchpad) over every storefront and admin surface plus the desk, registers, and shifts pages at desktop and mobile in both themes and one 320 px pass: 63 captures, zero horizontal overflow, zero runtime/network errors, every input labelled. Evidence in `qa/release-m7-sweep/`.

### Post-release cosmetics — sidebar order, desk thumbnails, olive brand palette · 2026-09-19

**Delivered** on `feature/travel-tours-cosmetic-alignment`.

| Area | Result |
|---|---|
| Sidebar | The "Travel and tours" group now follows "Workspace" in the admin sidebar; the role sidebar lists the travel entries first under "Assigned tools". |
| Desk thumbnails | Departure cards used `getUrl('thumb')` unconditionally, which points at a file that does not exist while media conversions are still queued (`QUEUE_CONNECTION=database` without a worker leaves `generated_conversions` empty). The card now follows the module convention: the thumbnail when generated, the original otherwise. Covered by `test_terminal_departure_cards_show_the_cover_before_conversions_exist`. |
| Olive palette | `#6a753d` added as an independent palette (`olive`) in both controllers and made the default: dashboard (`aureon-dashboard.js` palettes, `theme-settings` pre-paint defaults, `dashboard-settings` swatch "Kenfam olive", `aureon-dashboard.css` `:root` fallbacks, `meta theme-color`, loader and error-page fallbacks) and storefront (`theme-init.js` options and defaults, `theme.css` `:root` primitives with wine moved to its own `[data-palette="wine"]` block, the swatch arrays and custom pickers in the commerce, accommodation, travel, and home controller partials). Auxiliaries are those of the corporate green palette: secondary Kenfam red `#70233a`, accent heritage gold `#b28a4b`; dark surfaces from the forest palette. Wine and every other palette stay selectable. |
| Brand colour setting | The theme controllers live in the browser, so server-rendered surfaces read one server-side setting instead: `config('kenfam.colors')` (env `KENFAM_BRAND_PRIMARY`, `_PRIMARY_DARK`, `_SECONDARY`, `_ACCENT`, `_ON_PRIMARY`; defaults = olive). PDF report styles (`reports/partials/styles.blade.php`), the mail layout (`.button.button-primary` override in `vendor/mail/html/layout.blade.php`; the inliner parses `<style>` before the theme CSS, so the override carries two classes), the dashboard's olive palette (handed over as `window.AureonDashboardTheme.brand`) and the storefront `:root` tokens (`layouts/partials/brand-theme-tokens.blade.php`, included after `theme.css` in the three module layouts) all follow it. Covered by `tests/Feature/BrandColorsTest.php`. The mail theme CSS keeps olive as a fallback only. |
| Dashboard buttons | The template stylesheet pins `.btn.btn-primary`, `.btn-outline-primary`, `.btn-added`, checks, pagination, tabs, links, and badges to a literal wine with `!important`, so palette switches never reached them. `aureon-dashboard.css` now carries a palette-aware component block (same weight, loaded later) that binds them to `--aureon-primary`; the core dashboard and admin stat cards use `var(--aureon-primary)` / `var(--aureon-secondary)` instead of hex. Verified live: the admin "Open workspace" button renders olive by default and teal after a palette switch. |
| Sidebar states | The template pins the sidebar's active and hover items (text, span, marker, arrows, collapse toggle) to a literal wine at higher specificity than the overlay's generic rule, so palette switches left the menu red. The overlay now repeats those selectors bound to `--aureon-primary`: active rows on a 12% tint of the primary, hovered rows on an 8% tint, text and marker in the primary; the light/dark/brand sidebar surfaces keep their own treatments. Verified with real mouse hover on olive and after switching to teal (`sidebar-*` captures, `sidebar-diagnostics.json`). |
| Left as-is | `resources/css/style.css` (compiled template) still hard-codes wine in places the overlay does not cover (calendar, chat, POS template widgets); they behave as before for any non-wine palette. |

Files: `resources/views/layouts/partials/{sidebar-admin,sidebar-role,theme-settings,dashboard-settings,title-meta,loader-bootstrap,brand-theme-tokens}.blade.php`, `resources/js/aureon-dashboard.js`, `resources/css/{aureon-dashboard,aureon-errors}.css`, `resources/aureon/assets/js/theme-init.js`, `resources/aureon/assets/css/theme.css`, `resources/views/partials/aureon-home-theme-controller.blade.php`, `resources/views/reports/partials/styles.blade.php`, `resources/views/vendor/mail/html/layout.blade.php`, `resources/views/vendor/mail/html/themes/aureon.css`, `config/kenfam.php`, `.env.example`, `app/Http/Controllers/{Admin,Editor,ContentManager,Viewer}/DashboardController.php`, `resources/views/livewire/admin/{user-management,system-activity-index,roles-and-permissions-manager}.blade.php`, `app/Modules/{Commerce,PropertyBooking,TravelTours}/Resources/views/layouts/storefront.blade.php`, `app/Modules/{Commerce,PropertyBooking,TravelTours}/Resources/views/storefront/partials/theme-controller.blade.php`, `app/Modules/TravelTours/Resources/assets/css/storefront.css`, `app/Modules/TravelTours/Resources/views/livewire/pob/terminal.blade.php`, `tests/Feature/BrandColorsTest.php` (2 tests), `tests/Feature/TravelTours/TravelToursBookingDeskTest.php` (8 tests).

Verification: `php artisan test` 252 passed, Pint clean; browser harness `qa-cosmetics.mjs` (scratchpad) with a fresh profile: storefront and dashboard resolve olive by default, both panels list the olive option first and selected with the custom pickers on olive, wine still switchable, admin sidebar order Workspace → Travel and tours, primary buttons olive by default and teal after switching; zero runtime/network errors. Evidence in `qa/cosmetics-olive-palette/`.

### Live catalogue search — /tours filters without a page load · 2026-09-19

**Delivered** on `feature/travel-tours-live-catalog-search`. The public catalogue is now the `TourSearch` Livewire component: the filter form and the results share one component, every filter is live, and the page never reloads.

| Area | Result |
|---|---|
| State | Each filter is a public property with `#[Url(as: …, except: '')]` under the same query keys the classic request accepted (`keyword`, `destination`, `category`, `type`, `maximum_duration_days`, `departure_date`), so shared and refreshed URLs reproduce the results and the first paint is server-rendered. `WithPagination` keeps `page` in the URL and pages inside the component. |
| Live fields | Keyword and maximum days use `wire:model.live.debounce.400ms`; the selects and the date use `wire:model.live`. `updated()` validates only the changed field (`validateOnly`) and calls `resetPage()`. The form stays a plain GET form with `name` attributes and `wire:submit="search"`, so it still works without JavaScript. |
| Results | `#[Computed] tours()` runs the search once per request through the unchanged `SearchesTours` boundary; only values that pass validation reach it (`Validator::valid()`), so an invalid field is reported inline (`aria-invalid`, `aria-describedby`, `role="alert"`) while the results stay as they were. Cards carry `wire:key`; the summary is an `aria-live` region; `clearFilters` resets state, page, and URL. |
| Loading | Livewire 4 marks the originating field with `data-loading` (styled as a focused ring) and the results block uses `wire:loading.class` / `wire:loading.attr="aria-busy"` scoped with `wire:target` to the filters, actions, and paging; a sticky progress pill shows while a request runs; the submit button swaps its label. |
| Offline | `wire:offline` shows the notice and `wire:offline.class` dims the form only while the browser reports no network. |
| Pagination | Livewire's Bootstrap pagination view (`wire:click`), scrolled to `#travel-results`, styled for the storefront palette. |

Files: `Storefront/Livewire/TourSearch.php`, `Storefront/Http/Controllers/StorefrontController.php`, `Resources/views/storefront/catalog/index.blade.php`, `Resources/views/livewire/storefront/tour-search.blade.php`, `Resources/views/storefront/catalog/partials/tour-card.blade.php`, `Resources/assets/css/storefront.css`, `TravelToursServiceProvider.php`, `tests/Feature/TravelTours/TravelToursTourSearchTest.php` (5 tests).

Verification: `php artisan test --filter=TravelTours` 132 passed (2573 assertions), Pint clean; browser harness `qa-live-search.mjs` (scratchpad): a window token proves no document reload across keyword, destination, type, duration, and clear; the URL follows every change and is clean after clearing; `data-loading` and `aria-busy` observed during a search; invalid duration reported inline with results untouched; the offline notice shows under CDP network emulation and hides on reconnect; `?keyword=Dubai&type=private` seeds fields and results on a fresh load; mobile light and dark; ten Livewire requests, zero page loads, zero runtime/network errors. Evidence in `qa/storefront-live-search/`.

### Admin alignment — destinations, departures, and inquiries on the category pattern · 2026-09-20

**Delivered** on `feature/travel-tours-admin-alignment`. The three managers now read like the category manager: an enumerated table of essential columns, switches for state changes with the blocker stated before the service refuses, an eye that opens a details dialog, an edit pencil, one fluid filter bar, and row actions that stay in view on every width.

| Area | Result |
|---|---|
| Shared | `#` enumeration column (paginated lists count from `firstItem()`), `.travel-admin-filters` is one `auto-fit` grid (search spans two tracks) used by the catalog, destinations, departures, and inquiries; below 1200 px the table scrolls sideways while the `#` and Actions columns stay pinned (C-3 for every admin table at once); row actions never wrap. |
| Destinations | Columns: destination (thumb, name, code, featured), hierarchy (type, parent, country), catalog use (pluralised, C-7), **Active** switch, **Published** switch, actions (eye, edit). The Published switch is the new `DestinationService::publish/unpublish` behind the existing `publish`/`unpublish` policy: publishing needs an active record and a published parent; unpublishing is refused while published tours visit it or published children hang off it. The eye opens a details dialog (identity, publication, SEO, copy, **cover and gallery** with the upload, replace, reorder, caption, and remove controls for editors, child destinations, tours visiting) — the separate images dialog is gone. Filters are URL state (C-6); the form has `novalidate` and inline `aria-invalid` (C-2); regions no longer require a country code (C-1); the row actions wrap in `.travel-admin-row-actions` (C-4); the read-only details dialog answers C-9. |
| Departures | Columns: departure, tour, local window, rate/confirmation, seats, **Sales** switch (on = open, off = closed; draft and closed switch on to open, open and guaranteed switch off to closed; sold-out, departed, completed, cancelled, and past-dated rows explain why they cannot), actions (eye, edit, team, and a menu for guarantee, departed, completed, cancel). The eye opens a details dialog with the travel window, capacity and booking counts, rate and confirmation mode, meeting instructions, internal notes, and the assigned team. Filters are URL state. |
| Inquiries | The page is now the `InquiryManager` component. Filters: search (reference, name, email, phone), status, type, owner (anyone, mine, unassigned), follow-up (overdue, today, this week, unscheduled); a count strip for new, open, overdue, and awaiting-customer. Columns: inquiry (reference, type, age), contact, request (tour or destinations, dates, party), owner, follow-up (overdue in red), status, actions (eye, assign-to-me). The details dialog shows contact, request, message, consent, and, for `manage-tour-inquiries`, the ownership and follow-up form (owner, status, next follow-up) and an activity form (note, call, email, WhatsApp) over the timeline. `TourInquiryService` gained `assign`, `transition`, `scheduleFollowUp`, and `addActivity`, each appending a `TourInquiryActivity`; the first outbound activity stamps `responded_at`, terminal states stamp `closed_at` and clear the follow-up, "converted" is reserved for the booking that converts it, and closed inquiries reopen only as in progress. |

Files: `Catalog/Services/DestinationService.php`, `Catalog/Livewire/Admin/DestinationManager.php`, `Catalog/Livewire/Forms/DestinationForm.php`, `Scheduling/Livewire/Admin/DepartureManager.php`, `Inquiries/Services/TourInquiryService.php`, `Inquiries/Enums/InquiryStatus.php`, `Inquiries/Exceptions/InquiryException.php`, `Inquiries/Livewire/Admin/InquiryManager.php`, `Inquiries/Http/Controllers/InquiryAdminController.php`, `TravelToursServiceProvider.php`, `Resources/views/livewire/admin/catalog/{destination-manager,tour-category-manager}.blade.php`, `Resources/views/livewire/admin/scheduling/departure-manager.blade.php`, `Resources/views/livewire/admin/inquiries/inquiry-manager.blade.php`, `Resources/views/admin/inquiries/index.blade.php`, `Resources/assets/css/admin.css`, `tests/Feature/TravelTours/TravelToursAdminAlignmentTest.php` (4 tests), `tests/Feature/TravelTours/TravelToursCatalogCompletionTest.php`.

Verification: `php artisan test --filter=TravelTours` 136 passed (2673 assertions), Pint clean; browser harness `qa-admin-alignment.mjs` (scratchpad) as the travel manager: each table enumerated from 1 with switches and no eye-off icons, filter controls on one baseline, destination details with the cover and gallery controls, departure details, sales switch closed and reopened, inquiry details with a note recorded on the timeline; dark theme; tablet and phone widths with the row actions measured inside the viewport; 13 captures, zero runtime/network errors, every input labelled. Evidence in `qa/admin-alignment/`.

### Admin alignment — bookings manager, and the favicon as the preloader mark · 2026-09-20

**Delivered** on `feature/travel-tours-bookings-alignment`. The bookings manager now follows the same list pattern as the other aligned managers, and every preloader on the platform shows the configured favicon.

| Area | Result |
|---|---|
| Filter bar | The horizontal filter row is gone. The card header holds the title, the count, and the refresh pill; the filters sit directly beneath it in the shared `.travel-admin-filters` grid (search spans two tracks; status, payment, **channel**, **needs attention**, clear) on one baseline. Channel and attention are new URL state (`?channel=`, `?attention=`); attention offers payments to confirm, bookings to confirm, confirmed with a balance, and departing within 30 days. One `updated()` hook resets the page for every filter; `clearFilters` resets state, page, and URL. |
| Count strip | `.travel-catalog-status-counts` under the filters: bookings by status plus the number of payments waiting on confirmation (`#[Computed] statusCounts`). |
| Table | `#` enumeration from `firstItem()`, booking (number, channel, placed at), customer (name, email or phone), departure (tour, local date, departure code), travellers, total with the amount received, payment and status as `travel-status` pills, and actions (eye → details, cash → record payment, check → confirm pending). Empty state is the shared `.travel-admin-empty`. The `#` and Actions columns stay pinned at every width, since the nine-column table scrolls even on a 1440 px desktop. |
| Loading status | The list is `wire:loading.attr="aria-busy"` scoped with `wire:target` to the filters, clear, paging, and row confirm; `.travel-admin-list[aria-busy]` dims it and blocks clicks while the header shows the `.travel-admin-refresh` pill. Dialog actions (confirm, complete, confirm payment) carry scoped `wire:target` with a loading label. |
| Details dialog | Status badges replaced by the same `travel-status` pills as the table; the unused Bootstrap badge helper is gone. |
| Preloader | `x-loader` takes an `icon` prop and otherwise renders `asset(config('kenfam.brand.favicon'))` instead of the hard-coded Aureon mark, so the dashboard and the site preloaders show the site favicon; the travel storefront passes `$travelProfile->iconUrl`, which is the same favicon unless the module overrides it. |

Files: `Bookings/Livewire/Admin/BookingManager.php`, `Resources/views/livewire/admin/bookings/booking-manager.blade.php`, `Resources/views/layouts/storefront.blade.php`, `Resources/assets/css/admin.css`, `resources/views/components/loader.blade.php`, `tests/Feature/TravelTours/TravelToursBookingManagerTest.php`.

Verification: `php artisan test` 261 passed (3345 assertions), Pint clean; browser harness `qa-bookings-alignment.mjs` (scratchpad) as the travel manager: filters begin 20 px under the header with every control on one baseline, enumeration from 1, the list marked busy with the refresh pill visible during a filter request, filtered state in the URL, details dialog, dark theme, and phone width with the row actions inside the viewport; the loader `src` equals the document favicon on both the storefront and the dashboard; five captures, zero runtime/network errors, every input labelled. Evidence in `qa/admin-alignment/` (`bookings-diagnostics.json`, `*-bookings*.png`, `desktop-light-booking-details.png`).

## Close-out

All seven milestones are on `main`. A customer completes a booking from `/tours/{slug}` through a staff-confirmed manual payment; an operator completes the same at the desk on a shift a manager opened, with a receipt and a reconciled drawer. Items outside this shipment are listed in `todo/refinements_todo.md` (P-1..P-8).
