# K3 Handoff: Departures, Availability, And Pricing

Status: scope and UX decision for the next phase; no K3 implementation is
claimed here. Branch from the merged K2 state on `main`.

## Existing Contract

The master plan assigns departure scheduling, capacity, staff, rate plans,
participant rates, pricing rules, promotions, availability, and quotes to K3.
The domain workflow defines a tour as an editorial product and a departure as
one dated, capacity-governed instance of that tour. A published tour may remain
visible without a bookable departure and should offer an inquiry path instead
of invented availability.

The scaffold already has `TourDeparture`, related pricing/hold models,
permission names, and a read-only
`/admin/travel/departures` list. The K2 tour editor has Basics, Route,
Itinerary, Experience, Pricing, Media, and Publication tabs but no departure
write interface. K3 upgrades the existing read-only surface rather than
introducing a second competing schedule.

## Recommended Operator UX

Use **both entry points with one owner**:

1. Add a **Departures** tab to an existing tour's editor. Show that tour's
   upcoming and past departures, seats used/available, state, next departure,
   and a nearby Add departure command. Create/edit opens a contextual modal
   or inline form, returning to the same list after save. A new unsaved tour
   cannot schedule departures. Link to this tab from the tour catalog row.
2. Make the existing **Departures** menu page the cross-tour operations
   workspace. It lists all schedules with tour, local dates/timezone, booking
   window, capacity, available seats, assigned rate plan, and state. Provide
   search, tour/date/state filters, create, and a direct link back to the tour.
   A compact list is the first deliverable; a calendar or bulk scheduler is
   optional only after real operational need is established.
3. Use one module-owned Livewire departure manager/form and the same typed
   scheduling services in both contexts. The tour tab supplies a locked tour
   context; the central page exposes a tour selector. Neither view owns
   availability or capacity mutations. This prevents two CRUD behaviors from
   drifting apart.

This keeps the natural authoring flow ("I have finished this tour; when does
it run?") while giving an operator one place to coordinate every upcoming
departure. A tab alone would hide cross-tour planning; a central page alone
would force editors to leave the tour they are configuring.

## Delivery Order

| Increment | Operator result | Boundary |
| --- | --- | --- |
| K3A | Create/edit/cancel dated departures, booking windows, capacity, status, and staff from both entry points | Local inputs resolve through an IANA timezone to persisted UTC instants; existing commitments cannot be silently displaced |
| K3B | View trustworthy open/closed/sold-out availability and select a departure on public tour detail | Available seats derive from capacity, active holds, and confirmed commitments; the public page never treats a draft/closed departure as bookable |
| K3C | Manage non-default/departure-linked rate plans and adult/child/infant fares | Keep K2's simple default fare as the fallback; present advanced configuration separately from the basic tour price |
| K3D | Add seasonal, group, early-bird, and promotion rules; integrate deterministic quote output | Document priority/stacking, currency, tax, deposits, and exact minor-unit examples before exposing controls |
| K3E | Regression and browser acceptance | Role-aware forms, filters, responsive/theme QA, quote examples, and one realistic capacity contention proof |

K5 still owns the customer checkout, hold lifecycle UX, payment capture, and
booking confirmation. K3 can expose departure selection and quote information
without making its administration page a checkout screen.

## Public Listing Guidance

Keep the catalog's primary unit as the **tour**, not a separate card for each
departure. A tour card can show the next eligible departure and starting
price; the tour detail should list the eligible dates and seat state. This
avoids duplicate tour cards, duplicate SEO URLs, and confusing itinerary
variants. Date search filters tours by matching eligible departures while
leaving tours without dates discoverable through ordinary browsing and inquiry.
