# K3 departures implementation record

Status: K3A implemented, reviewed, and verified on `feature/travel-tours-k3-departures`, 2026-09-18. K3B-K3E remain open.

## Baseline verified

- K2 is on `main`. The departures schema, factory, ULID model, policy, read-only administration route, availability calculator, and default adult/child/infant rate editor already exist.
- `travel_tour_departures` stores UTC instants and an IANA timezone. Its `rate_plan_id` is optional, and a departure can override the tour's booking mode.
- The current administration page cannot create or change a departure. The tour editor has no scheduling tab. K5, not K3, owns customer holds and checkout.

## K3A operator workflow

The central `/admin/travel/departures` page and a new Departures tab on an existing tour use one Livewire manager. The central page offers tour/status search, date and seat visibility, and create/edit actions. The tour tab is scoped to that tour and cannot be used to mutate another tour by changing component input. The entry form is presented beside the listing, with a direct close action.

Input is local wall time plus IANA timezone. The service rejects nonexistent or ambiguous local DST instants, converts to UTC, validates ordering and booking windows, checks the selected rate plan belongs to the tour, and writes creator/updater IDs. Capacity reductions cannot undercut confirmed bookings or active, unexpired holds. Status transitions are explicit: draft -> open; open -> guaranteed/closed/cancelled; guaranteed -> closed/departed/cancelled; closed -> open/cancelled; departed -> completed. Closed -> open must pass the same open prerequisites. Cancellation with existing bookings belongs to a later booking-lifecycle workflow and is not offered here. Sold-out remains a calculated presentation state, not an operator-set status.

The K3A form does not expose waitlisting or destructive deletion; those require coherent operations workflows. Staff assignment is delivered, as the K3 scope record lists it for K3A: a departure team panel assigns active users holding a module role to guide, coordinator, driver, or host, with one lead per departure. It does expose the persisted meeting and internal instructions, windows, mode override, and optional rate plan, so the foundational record is genuinely operable.

## K3B-K3D contract and boundaries

- K3B: public tour detail shows upcoming bookable dates and remaining seats, with capacity and window conditions resolved server-side; quote calculations use the existing calculator and no client money input.
- K3C: advanced plans and date-bounded participant rates remain a distinct pricing editor; the K2 base-rate editor must continue to preserve advanced policy.
- K3D: seasonal/group/early-bird rules and promotions get their own validated management and precedence tests. Their schema and calculation engine exist, but no operator interface is claimed complete yet.

## Verification and evidence

Focused service and Livewire tests cover UTC conversion, invalid dates, cross-tour plan selection, capacity floor, transitions, role separation, and tour scoping. Then run TravelTours and full host tests, Pint, Blade cache, PHPDoc/schema probes, and an isolated browser session at desktop/tablet/mobile widths in light/dark modes. Screenshots and diagnostics are filed under `.docs/TravelTours/qa/`; no demo seeding or migration runs against the developer database.

## Edit ledger

Planned edits: scheduling DTO/service/form/manager; module component registration; central departures view/controller; tour editor tab; module-owned admin CSS; focused tests and browser QA evidence. No schema change is planned. Complete results and any deferral must be recorded here before review.

## Results, 2026-09-18

| Gate | Result |
| --- | --- |
| `TravelToursDepartureManagementTest` | 10 passed, 44 assertions |
| `tests/Feature/TravelTours` | 74 passed, 2,018 assertions |
| `php artisan test` | 197 passed, 2,677 assertions |
| Pint, PHPDoc audit, schema probe, Blade cache, `git diff --check` | Pass (Pint required a formatting pass on five new files before commit) |
| Browser QA (`AUREON_QA_PHASE=k3a`) | 13 captures at 1440/1280/820/390/320 in light and dark, one reduced-motion; zero assertion, runtime, or network failures; evidence under `qa/admin-k3a/` |

Delivered files: `Scheduling/Data/DepartureData.php`,
`Scheduling/Exceptions/DepartureException.php`,
`Scheduling/Services/DepartureManagementService.php`,
`Scheduling/Livewire/Forms/DepartureForm.php`,
`Scheduling/Livewire/Admin/DepartureManager.php`,
`Resources/views/livewire/admin/scheduling/departure-manager.blade.php`, the
`departures` editor tab in `TourEditor` and its view, the rewritten
`DepartureAdminController` and departures page, a `user()` relation on
`DepartureStaffAssignment`, a future-only `bookable()` scope on
`TourDeparture`, provider registration, module admin CSS, and the phase-routed
browser harness.

### K3B work delivered early

The public tour detail and the authenticated preview now resolve remaining
seats through `DepartureAvailabilityService` and show the fare of each
departure's own rate plan when one is assigned, falling back to the tour's
default. `TourSearchService` uses the shared `bookable()` scope. This is the
K3B "remaining seats resolved server-side" commitment, landed here because the
`bookable()` scope change made the previous public rendering incorrect. K3B
still owns date selection on the public page and the storefront QA for it.

### Carried forward

Recorded in `todo/refinements_todo.md`: the departure form's hard-coded
`Africa/Nairobi` default should read the module configuration; the departure
manager view uses the compressed single-line Blade style the parent modules
avoid; list searches across the module do not escape SQL wildcards; the
scheduling exception extends `RuntimeException` directly while the K1
exceptions extend `TravelToursException`.
