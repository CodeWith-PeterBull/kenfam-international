# TravelTours Homepage, Demo Catalogue, Dashboard, And Identity Refinement

Status: implementation complete and awaiting review on `feature/travel-home-demo-dashboard-refinement`. No commit has been created.

## 1. Objective

Deliver one coherent adoption refinement without adding a new domain boundary:

1. Turn the existing homepage hero into an accessible informational-first tour carousel.
2. Expand the opt-in demonstration catalogue with complete, locally illustrated destination journeys.
3. Improve the travel operations dashboard as a permission-aware navigation surface.
4. Resolve authentication branding through the same institution profile contract used by mail, reports, and storefronts.
5. Make the mobile dashboard navigation trigger inherit the active theme palette.

The existing TravelTours models, media collections, services, route names, feature flag, and seeder entry point remain authoritative.

## 2. Delivery Boundaries

### Included

- The current informational hero remains slide one and retains its institutional image and calls to action.
- Published tours are appended in deterministic featured-first order, so non-featured journeys fill remaining slots and naturally become the complete fallback when no featured tour exists.
- The carousel is bounded to six tour slides in addition to the informational slide.
- Tour slides use the `tour_cover` hero conversion and the institutional hero when media is absent.
- Previous/next arrows, centered pagination, autoplay, keyboard navigation, focus/hover pause, and reduced-motion handling.
- Seven additional demonstration journeys: Maasai Mara, Serengeti, Zanzibar, Thailand, Turkey, China, and Israel.
- One optimized local cover per new journey, reused by its primary destination.
- Permission-aware dashboard links from statistics, departures, inquiries, and bookings.
- Institution-resolved branding on login, registration, password, verification, and two-factor screens.
- Theme-token styling for dashboard cards and the mobile navigation trigger.

### Excluded

- A carousel content-management table or a new hero-slide model.
- Remote image URLs or runtime network dependencies.
- Destructive replacement of adopter records during demo seeding.
- New destination, tour, pricing, or departure schema.
- Payment gateways, reports, traveller profiles, or other post-M7 backlog items.

## 3. Homepage Projection

`HomeController` owns the read-only homepage query. It will return:

- `featuredDestinations`: the existing destination collection.
- `featuredTours`: the existing lower-page selected journeys collection.
- `heroTours`: up to six published tours from one deterministic query ordered by featured state and editorial sort order.

The hero query eager-loads only presentation dependencies: destinations, public rates with participant rates, and the next bookable departure. No quote, hold, or booking write runs on the homepage.

## 4. Carousel Interaction Contract

- The server renders useful stacked content when JavaScript is unavailable.
- JavaScript progressively initializes one Swiper instance on `[data-travel-hero-slider]`.
- Autoplay uses a restrained interval and does not disable manual controls.
- The active slide exposes its state through Swiper's classes and pagination semantics.
- Buttons have explicit accessible names and remain inside the stable hero geometry.
- `prefers-reduced-motion: reduce` disables autoplay and uses near-zero transition speed.
- Images after the first slide are lazy-loaded; the first informational image remains high priority.
- Content widths and heading sizes remain bounded at desktop, tablet, 390 px, and 320 px.

## 5. Demonstration Fixture Contract

The existing `Database/Data/demo-catalog.php` remains the sole reviewed manifest. Every row provides the destination, journey identity, exact integer prices, capacity, structured highlights, itinerary, and local image filename. `TravelToursDemoSeeder` remains:

- opt-in and absent from `DatabaseSeeder`;
- idempotent through stable codes/slugs;
- non-destructive to unrelated adopter records;
- transactional for the relational graph;
- strict about missing local media.

New images are landscape, text-free, watermark-free, realistic destination photography. They are converted to lightweight WebP assets before inclusion.

## 6. Dashboard Contract

The dashboard remains controller-composed and read-only. Statistics link only when the current user holds the destination permission. The controller supplies named route metadata rather than embedding business queries in Blade. Queue headers and each meaningful record row link to their owning manager with a stable filter/search query where supported.

Visual changes use Aureon variables (`--aureon-primary`, `--aureon-primary-rgb`, surfaces, borders, ink, and muted text), preserving light, dark, and selectable palettes.

## 7. Authentication Identity Contract

A shared Blade partial resolves `ResolvesInstitutionProfile::current()` once per authentication page and renders:

- the institution main logo for the normal variant;
- a configured light-logo fallback for dark surfaces;
- the institution name as alternative text;
- the homepage as the brand link.

Database-managed institution media therefore overrides configuration. With no database record or media table, `config/institution.php` remains the fallback. Authentication views will no longer reference Aureon brand paths directly.

## 8. Verification Matrix

| Surface | Automated evidence |
| --- | --- |
| Hero data | Featured-first, published-only, fallback, order, limit, media fallback |
| Hero rendering | Informational slide first, tour slide metadata/CTA, named controls, pagination |
| Seeder | Thirteen journeys, required destination slugs, complete graph, media, repeat-run idempotency |
| Dashboard | Authorized deep links, empty-safe rendering, responsive/theme card selectors |
| Auth identity | Config fallback and custom institution media on login and registration |
| Mobile dashboard | Trigger uses active primary variables and remains visible in light/dark modes |
| Browser UAT | Homepage slider interaction; desktop/tablet/390/320; light/dark/reduced motion; dashboard and auth captures; zero runtime/network/overflow/accessibility failures |

Required closeout commands:

```text
php artisan test --filter=TravelTours
php artisan test
vendor/bin/pint --test
php scripts/audit-travel-tours-docblocks.php
php scripts/probe-travel-tours-foundation.php
php artisan view:cache
npm run build
npm run qa:travel-tours-storefront
npm run qa:travel-tours-admin
npm run qa:auth
```

## 9. Implemented Result

### 9.1 Homepage hero

- `HomeController` projects at most six published journeys from one featured-first query and eager-loads only the destinations, categories, public fares, and next bookable departure needed by the view.
- The institutional message remains the first server-rendered slide. Tour slides follow it and use the tour cover conversion, with the institutional hero image as their fallback.
- One progressively initialized Swiper instance owns fade transitions, seven-second autoplay, keyboard support, clickable pagination, looping, focus/hover pausing, and reduced-motion behavior.
- Arrow buttons invoke the initialized Swiper instance directly. Browser QA performs two consecutive next actions, previous, a nonadjacent pagination jump, a return pagination jump, and another next action to prevent the former one-interaction regression.
- Pagination is absolutely centered over the hero and raised from its lower edge: `36px` on desktop and `34px` on mobile. Measured center deviation is at most two pixels.
- The mobile layout keeps arrows at the sides of the pagination rail, reserves content space above the controls, and does not overflow at 390px or 320px.

### 9.2 Demonstration catalogue

- The reviewed manifest now contains thirteen tours and their destination graphs.
- Seven new journeys cover Maasai Mara, Serengeti, Zanzibar, Thailand, Turkey, China, and Israel.
- Seven local, optimized, text-free WebP covers live under `app/Modules/TravelTours/Resources/demo/travel/` and are validated through the existing strict media import path.
- Featured state is read from the manifest instead of being forced globally. Six tours are intentionally featured; published non-featured tours remain the hero fallback.
- Existing stable codes, slugs, exact minor-unit prices, idempotent upserts, and opt-in seeding behavior are preserved.

### 9.3 Operations dashboard and identity

- Dashboard statistics, recent bookings, departures, and inquiry queues link to authorized operational workspaces and preserve supported filters.
- Cards use Aureon surface, border, primary, shadow, and dark-mode tokens; the module grid no longer introduces workspace overflow.
- Authentication pages share `resources/views/auth/partials/brand.blade.php` and resolve both normal and light logo variants through `InstitutionProfileResolver`.
- Database-managed institution media overrides configured assets; configuration remains the no-database fallback.
- The inherited auth browser harness now defaults to the Kenfam administrator fixture instead of the removed Aureon fixture.
- The mobile dashboard trigger inherits the active primary palette. Optional telephone-input initialization is guarded when its vendor script is absent.

## 10. Verification Evidence

Measured on 24 September 2026 against the isolated QA database at `storage/framework/testing/travel-home-dashboard-qa.sqlite`.

| Gate | Result |
| --- | --- |
| `php artisan test --filter=TravelTours` | **149 passed, 2,783 assertions** |
| `php artisan test` | **276 passed, 3,478 assertions** |
| Changed-file `php vendor/bin/pint --test` | **Passed** |
| JavaScript syntax checks | **Passed** for the hero, storefront, storefront QA, admin QA, and auth QA files |
| `php scripts/probe-travel-tours-foundation.php` | **Passed:** 34 tables, 645 documented columns, zero missing comments |
| `php artisan view:cache` | **Passed** |
| `npm run build` | **Passed:** 125 modules transformed |
| `npm run qa:travel-tours-storefront` | **Passed:** 14 inspections, 9 viewport captures, no runtime/network failures |
| `npm run qa:travel-tours-admin` | **Passed:** 9 captures |
| `npm run qa:auth` | **Passed:** guest auth, successful login, confirm-password, profile security, light/dark/mobile branding |
| `git diff --check` | **Passed** |

Primary evidence:

- `.docs/TravelTours/qa/storefront/desktop-light-home.png`
- `.docs/TravelTours/qa/storefront/desktop-light-home-tour-slide.png`
- `.docs/TravelTours/qa/storefront/mobile-light-home.png`
- `.docs/TravelTours/qa/storefront/diagnostics.json`
- `.docs/TravelTours/qa/admin/desktop-light-travel-dashboard.png`
- `.docs/TravelTours/qa/admin/mobile-dark-travel-dashboard.png`
- `.docs/TravelTours/qa/admin/diagnostics.json`
- `.docs/dev/auth-qa/login-dark.png`
- `.docs/dev/auth-qa/login-mobile.png`
- `.docs/dev/auth-qa/diagnostics.json`

## 11. Baseline Findings

Two repository-wide quality gates remain red for files outside this refinement:

- Full `php vendor/bin/pint --test` flags only `scripts/probe-travel-tours-foundation.php`. The changed-file Pint gate passes.
- `php scripts/audit-travel-tours-docblocks.php` reports the existing 43 undocumented Livewire computed methods. This refinement adds no reported PHPDoc debt.

PHP commands also emit the local Imagick binary warning: extension build `1808` is loading ImageMagick `1810`. All image, seeder, test, and browser gates complete successfully, but the local extension should be rebuilt against the loaded library independently of this feature.

The feature is implemented and verified but remains uncommitted pending review approval.
