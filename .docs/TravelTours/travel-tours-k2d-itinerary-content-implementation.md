# TravelTours K2D Itinerary And Content Implementation

Status: implemented and locally verified on
`feature/travel-tours-k2-itinerary-content`; awaiting user review. Nothing in
this increment has been committed or pushed.

## Delivered

K2D completes the child-content portion of catalog administration without
entering K2E publication/media or K3 departure/pricing work.

- The Tour editor now exposes Itinerary and Experience tabs beside Basics and
  Route. New-tour pages keep child tabs unavailable until the draft exists.
- `TourItineraryService` owns transactional day/activity creation, updates,
  adjacent reordering, removal and gap closure. It verifies tour/day/activity
  ownership, tour duration, active destinations, local times and coordinates.
- `TourContentService` owns typed highlights, inclusions, exclusions,
  requirements and packing notes; FAQs; and optional extras. It verifies child
  ownership and deterministic ordering. Extra amounts remain integer minor
  units, currencies are ISO codes, archived codes remain reserved, and extra
  removal is soft deletion.
- Five immutable DTOs and five Livewire Forms separate browser input from
  service writes. Exact decimal extra input is converted with string/integer
  arithmetic rather than a float.
- Two nested module-owned Livewire components keep itinerary and experience
  state independent from tour basics. Every request reauthorizes the parent;
  every mutation resolves children through their parent tour/day.
- Add/Edit Day, Activity, Item, FAQ and Extra now open in contextual,
  theme-aware Bootstrap dialogs instead of rendering below the complete list.
  Dialogs identify the selected day where relevant, autofocus their first
  control, close on Escape, remain scrollable on small screens, and keep save
  actions visible. This removes the long-list scroll jump that made FAQ and
  related create actions appear unresponsive.
- The existing demo catalogue supplies realistic itinerary, content, FAQ and
  extra rows for browser acceptance. No default seeder or migration changed.
- Admin CSS now supports ordered child rows, inline forms, light/dark themes,
  responsive controls, and a four-tab mobile strip that remains visible at
  390 px while retaining horizontal scrolling below that width.

## Principal Files

```text
Catalog/Data/
  ItineraryDayData.php
  ItineraryActivityData.php
  TourContentItemData.php
  TourFaqData.php
  TourExtraData.php
Catalog/Services/
  TourItineraryService.php
  TourContentService.php
Catalog/Livewire/Forms/
  ItineraryDayForm.php
  ItineraryActivityForm.php
  TourContentItemForm.php
  TourFaqForm.php
  TourExtraForm.php
Catalog/Livewire/Admin/
  TourItineraryEditor.php
  TourExperienceEditor.php
Resources/views/livewire/admin/catalog/
  tour-itinerary-editor.blade.php
  tour-experience-editor.blade.php
tests/Feature/TravelTours/TravelToursTourContentTest.php
```

The parent `TourEditor`, provider aliases, shared admin stylesheet, admin QA
harness and phase documentation were extended. K1's five existing child tables
remain authoritative; no new persistence structure was introduced.

## Verification

| Gate | Result |
| --- | --- |
| `php artisan test tests/Feature/TravelTours/TravelToursTourContentTest.php --compact` | 11 tests, 52 assertions; ownership, ordering, duration, published minimum, exact money, archive, all five contextual dialogs, Forms and authorization covered |
| `php artisan test tests/Feature/TravelTours --compact` | 48 tests, 1,875 assertions |
| `php artisan test --compact` | 171 tests, 2,534 assertions |
| `php vendor/bin/pint --test app/Modules/TravelTours tests/Feature/TravelTours` | Passed at final closeout |
| `php scripts/audit-travel-tours-docblocks.php` | Passed for every module PHP file |
| `php scripts/probe-travel-tours-foundation.php` | 34 tables, 645 documented columns, zero missing comments |
| `php artisan view:cache` | Passed at final closeout after the contextual dialog views were added |
| `npm.cmd run build` | Passed; inherited runtime-resolved absolute asset warnings only |
| K2D authenticated browser QA | 12 captures, five contextual-dialog probes, zero runtime/network/assertion/focus failures |

Browser evidence is under `.docs/TravelTours/qa/admin-k2d/`. It covers the
existing catalog/taxonomy matrix plus desktop Day and Item dialogs, tablet FAQ,
mobile Activity and Extra dialogs. Each contextual form is asserted visible,
inside the viewport, and keyboard-focused. The final isolated SQLite database
was seeded explicitly, used media IDs starting at 930001 to avoid shared-media
collisions, and did not touch developer data.

## Remaining Boundary

K2E still owns tour cover/gallery/documents, SEO completion, readiness rules,
authenticated draft preview, publish/schedule/unpublish/archive transitions,
and public-scope tests. K2D does not imply that a draft is publication-ready.
K3 remains the owner of departures, capacities, rate plans, participant rates,
seasonal/group rules and promotions.
