# TravelTours Refinements Carried Forward

Open refinements identified during review and UAT, each assigned to the phase
that owns the surface. An item leaves this list only when its owning phase
records named evidence in the implementation ledger. Sources:
`qa/catalog-taxonomy/diagnostics.json` (K2B UAT, 2026-09-16), the K2 commit
review of 2026-09-18, and `claude-review/concern_file.md`.

## Scope and contract

| # | Item | Owner | Status |
| --- | --- | --- | --- |
| S-1 | `TourBasePriceService` writes `travel_tour_rate_plans` and `travel_participant_rates` from K2E although the K2 plan at `23f6855` reserved those writes for K3; the plan text was changed in the same commit. Decide: move to a K3 branch, or record a dated exception in `travel-tours-k1-reconciliation.md` together with the `TourRatePlan.currency_exponent` decision. | K2E review | Open |
| S-2 | Slug and code columns carry global `unique()` indexes with soft deletes; the K2 plan says "unique among retained rows". Settle the wording or ship an additive composite index. | K2 review | Open |
| S-3 | Ledger K2E row says "awaiting review, not committed" while `527d752` is on `main`; K4 row claims public-lightbox work outside K2. Reconcile. | K2 review | Open |

## Catalog administration (K2B/K2C surfaces)

| # | Item | Owner | Status |
| --- | --- | --- | --- |
| C-1 | Region destinations seeded without a country code cannot be re-saved: `countryCode` rule is `required_unless:type,continent`. Exempt `region`; add a domain message. | K2F | Open |
| C-2 | `novalidate` on the destination form so Livewire's inline error path answers a blank submit (done for categories). | K2F | Open |
| C-3 | Row actions sit off-screen on phones inside the scrolling table; adopt a card layout or pinned action column below 576 px for every admin table at once. | K2F | Open |
| C-4 | Vertically stacked `.btn-icon` actions inflate destination rows to ~105 px; wrap in `.travel-admin-row-actions` as the category manager now does. | K2F | Open |
| C-5 | Dialogs: focus trap and body scroll lock; a live region for validation errors. Focus-on-open and `aria-invalid`/`aria-describedby` are done for the category manager only. | K2F | Open |
| C-6 | Destination search and filters are not URL state (`#[Url]`); the tour catalog already is. | K2F | Open |
| C-7 | "1 tours" / "0 child destinations" pluralisation. | K2F | Open |
| C-8 | Mobile filter block fills the first screen; collapse type/status below 576 px. | K2F | Open |
| C-9 | Category details dialog: offer the same read-only detail view for destinations and tours. | K2C/K2F | Open |

## Verification and tooling

| # | Item | Owner | Status |
| --- | --- | --- | --- |
| V-1 | Promote the scratchpad admin harness to `scripts/qa-travel-tours-admin.mjs` with a `qa:travel-tours-admin` entry; it must include the 320 px capture, the modal-bounds probe, and the dark close-button check. | K2F | Open |
| V-2 | `TravelToursDisabledModuleTest` should also assert listener and migration-path absence, matching the parent. | K2F | Open |
| V-3 | The `php artisan --version` banner (12.69.2) disagrees with the documented lock baseline (12.64.0); note it wherever framework version is captured as evidence. | Docs | Open |

## Foundation concerns deferred by phase

`claude-review/remediation-status.md` holds C-04 through C-10 with their owning
phases (K5, K6, K7). They are not repeated here.

## Scheduling administration (K3A surface)

| # | Item | Owner | Status |
| --- | --- | --- | --- |
| D-1 | `DepartureForm` hard-codes `Africa/Nairobi`; read `travel-tours.defaults.timezone` so the module stays client-neutral. | M7 | Done |
| D-2 | `departure-manager.blade.php` uses compressed single-line Blade (longest line 1,130 chars); reformat to the parent's multi-line convention alongside the other K2 views. | M7 | Done |
| D-3 | List searches (`DepartureManager`, `DestinationManager`, `TourCatalog`, `TourSearchService`) pass `%`/`_` through to `LIKE` unescaped; the verification plan requires wildcard treatment tests. | M7 | Done (`Support/LikePattern`) |
| D-4 | `DepartureException` and `CatalogException` extend `RuntimeException` directly while K1 exceptions extend `TravelToursException`; settle one base. | M7 | Done |
| D-5 | The central departures workspace loads every tour for its selector on each render; bound or search it before the catalogue grows. | M7 | Done (bounded to 200) |

## Deferred by the combined delivery plan

Scope decisions recorded in `travel-tours-combined-delivery-plan.md`; each item
is intentionally outside M1–M7 so the booking journey ships without speculative
structure.

| # | Item | Owner | Status |
| --- | --- | --- | --- |
| P-1 | Reports surface (`VIEW_REPORTS`): sales, settlements, shift reconciliation exports. | Post-M7 | Open |
| P-2 | Reusable `Traveler` profiles and private traveller document uploads; checkout captures `BookingParticipant` snapshots only. | Post-M7 | Open |
| P-3 | Per-record agent scoping of bookings (agents currently see every booking their permission allows). | Post-M7 | Open |
| P-4 | Departure calendar / bulk scheduler and instalment-plan tables beyond `PaymentSchedule`. | Post-M7 | Open |
| P-5 | Payment gateway integration: implement against the `recordPending` → `confirm` seam with `provider` + `transaction_identifier`; no interface is introduced ahead of a real provider. | Post-M7 | Open |
| P-6 | Relocate root `Events/`, `Listeners/`, `Notifications/` under their bounded contexts. | Post-M7 | Open |
| P-7 | Manual drawer cash in/out at the desk: the M6 terminal dialog was removed when the desk was realigned to the PropertyBooking POB (which has none); `BookingShiftService::recordMovement` and the ledger remain, so a small manager-side dialog on the shifts page is all that is needed if the business wants it. | Post-M7 | Open |
| P-8 | Multi-process MariaDB contention probe for the last-seat race (two PHP processes against a real MariaDB); the transaction-level proof lives in `TravelToursContentionTest`, and no MariaDB server is available on the delivery workstation. | Post-M7 | Open |
