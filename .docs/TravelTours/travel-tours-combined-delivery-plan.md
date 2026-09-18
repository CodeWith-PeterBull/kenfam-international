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
