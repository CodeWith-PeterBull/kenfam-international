# TravelTours K2D: Itinerary and Experience Editing

Status: in progress on `feature/travel-tours-k2-itinerary-content`. Base commit:
`38f0710` (K2C). This increment is not to be committed before review.

## Scope

K2D makes the existing tour child tables editable from two new tabs in the
tour editor. The Itinerary tab owns ordered days and ordered activities. The
Experience tab owns typed highlights/inclusions/exclusions/requirements/packing
notes, FAQs, and optional paid extras. Create/edit actions use contextual,
scrollable dialogs so operators never have to find a form below a long list.
All records remain owned by one tour; the public tour page already reads days,
content and FAQs from these tables.

## Contract

- New child writes use immutable DTOs and module services, not direct Livewire
  model persistence. Livewire Forms validate UI input; services revalidate
  domain invariants for non-UI callers.
- New days and activities append to consecutive one-based order. Reordering
  swaps adjacent positions transactionally; removal closes gaps. IDs supplied
  for updates/removal must resolve under the authorized tour (and day).
- Days must fit within the tour duration. Optional destinations must be active;
  times are local clock times, not fabricated UTC instants. Coordinates, when
  supplied, form a valid WGS84 pair.
- Content has a controlled type and deterministic order per type. FAQs and
  extras have deterministic order. Extra codes remain unique per tour, including
  archived extras. Paid amounts are integer minor units, formatted and entered
  as two-decimal amounts without floating-point conversion.
- Extra removal uses soft deletion to retain references from future booking
  snapshots. Day and activity removal is explicit; a published tour cannot be
  left without an itinerary day. Publication readiness as a whole belongs K2E.
- The editor keeps Basics and Route unchanged. The new tabs appear only after
  a tour draft exists. Each Livewire child reauthorizes on every request and
  each write resolves its parent tour independently.

## Files and verification

Add typed data, Forms, `TourItineraryService`, `TourContentService`, two
module-owned Livewire editors and Blade surfaces. Extend the TourEditor tab
shell, provider aliases, CSS, focused `TravelToursTourContentTest`, browser QA
captures, implementation ledger and architecture/workflow docs. No migration
is required: K1 created and documented the five child tables.

Exit gates: focused and full PHPUnit, Pint, PHPDoc audit, schema probe, Blade
cache, Vite build, authenticated desktop/tablet/mobile light/dark screenshots,
contextual-dialog viewport/focus probes, no browser runtime/network errors, and
review before commit. K2E media, SEO, preview and publication remain pending.
