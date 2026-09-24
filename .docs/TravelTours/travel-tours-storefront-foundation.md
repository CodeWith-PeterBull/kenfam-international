# TravelTours Storefront Foundation

Status: locally accepted public-experience vertical slice. This record closes
the initial storefront foundation requested alongside K0/K1; it does not mark
the complete K4 discovery phase or the K5 booking flow as delivered.

## 1. Accepted Scope

The current public surface provides:

- a Kenfam-branded homepage backed by published TravelTours records;
- a published-only tour catalogue with keyword, destination, and category
  filters plus Bootstrap pagination;
- a tour-detail page with cover media, overview, destinations, departure
  options, four-day sample itineraries, inclusions, exclusions, FAQs, and a
  protected inquiry form;
- signed confirmation, tracking, and document entry points for the existing
  booking foundation;
- desktop, tablet, and mobile headers with a centered brand, public navigation,
  theme settings, sign-in, account creation, and authenticated Workspace action;
- a shared responsive footer, page loader, light/dark themes, reduced-motion
  behavior, canonical URLs, Open Graph metadata, and structured data;
- exact money presentation using persisted ISO currency and exponent values.

Advanced search facets, maps, interactive galleries, wishlists, reviews,
checkout, live availability holds, and customer dashboards remain governed by
K4/K5 and are not implied by this slice.

## 2. Public Route Contract

| Surface | Route name | Publication/privacy rule |
| --- | --- | --- |
| Homepage | `home` | Reads published featured tours and destinations only |
| Catalogue | `travel-tours.storefront.catalog.index` | Public scopes and URL query filters only |
| Tour detail | `travel-tours.storefront.tours.show` | Draft, archived, or future-scheduled records return 404 |
| Inquiry submit | `travel-tours.storefront.inquiries.store` | Honeypot, rate limit, idempotency, and tour/departure ownership validation |
| Confirmation | `travel-tours.storefront.bookings.confirmation` | Temporary signed URL and noindex metadata |
| Tracking | `travel-tours.storefront.bookings.track` | Temporary signed URL and noindex metadata |
| Booking document | `travel-tours.storefront.bookings.document` | Temporary signed URL; full DTO/no-store hardening remains C-04 work |

The root homepage coordinates host identity and published module content through
`HomeController`. Catalogue retrieval stays in `TourSearchService`; the views do
not construct publication queries or calculate prices.

## 3. Layout And Responsive Contract

`Resources/views/layouts/storefront.blade.php` owns the document shell, critical
loader integration, Vite entries, theme controller, metadata, and shared header
and footer. Page views provide only their page body and optional SEO data.

| Width class | Header behavior | Account behavior |
| --- | --- | --- |
| Desktop, 992 px and above | Full navigation, centered logo, visible theme and account actions | Guests see Sign in/Create account; authenticated users see Workspace |
| Tablet, 768-991 px | Compact centered brand with essential actions and offcanvas navigation | Same state contract without clipped labels |
| Mobile, below 768 px | Stable icon/brand/action grid and full-height offcanvas menu | Sign in/Create account remain reachable; close control is pinned 16 px from the right |
| Narrow, 320 px | Single-column content and constrained controls | No horizontal overflow or off-screen action |

The theme controller persists the established Aureon appearance keys. Both
light and dark variants use the Kenfam wine primary, neutral surfaces, visible
focus treatment, readable status text, and theme-appropriate logo variants.
Reduced-motion preference disables decorative transition timing without
removing content or navigation.

## 4. Storefront Assets

The module owns:

- `Resources/assets/css/storefront.css` for public layout and components;
- `Resources/assets/js/storefront.js` for public interaction and safe
  enhancement;
- `Resources/demo/travel/*.webp` for thirteen lightweight fictional demo covers;
- the `travel-tours-storefront` Vite CSS/JS entries.

The image files are demonstration assets, not representations of currently
sold Kenfam products. Spatie Media Library copies them to the configured public
disk during explicit demo seeding. Local and hosted environments therefore need
a valid `public/storage` link before media URLs can render.

## 5. Optional Demonstration Data

`TravelToursDemoSeeder` is deliberately absent from `DatabaseSeeder`. Run it
only in a disposable local, review, or demonstration environment:

```bash
php artisan db:seed --class="App\\Modules\\TravelTours\\Database\\Seeders\\TravelToursDemoSeeder" --no-interaction
```

The application must have `TRAVEL_TOURS_ENABLED=true`, its migrations must be
current, and `php artisan storage:link` must expose the public media disk. The
demo catalogue seeder invokes `TravelToursDemoOperatorSeeder`, which in turn
reconciles the people-free `TravelToursAccessSeeder`; no additional role or
operator command is required for the complete fixture.

For a disposable local or QA database only, rebuild the complete host and demo
fixture with:

```bash
php artisan migrate:fresh --seed --no-interaction
php artisan db:seed --class="App\\Modules\\TravelTours\\Database\\Seeders\\TravelToursDemoSeeder" --no-interaction
php artisan storage:link
```

`migrate:fresh` is destructive and must never be used on adopter, staging, or
production data. Rerunning only `TravelToursDemoSeeder` is non-destructive and
reconciles stable fixture identities.

The idempotent, non-destructive fixture creates or reconciles:

| Record | Count |
| --- | ---: |
| Published tours / categories / destinations | 13 / 10 / 13 |
| Featured tours | 6 |
| Future departures / rate plans / participant rates | 26 / 13 / 39 |
| Itinerary days / activities | 52 / 52 |
| Content items / FAQs / extras | 130 / 26 / 13 |
| Tour cover / destination media records | 13 / 13 |
| Demonstration operators | 3 |

Local-only operators are `travel.manager@example.test`,
`booking.agent@example.test`, and `tour.editor@example.test`, each assigned its
matching TravelTours role plus the compatible host role. Their deterministic
password is `password`, for local evaluation only, and must never be deployed
unchanged.
The access seeder remains independent and people-free.

## 6. Verification Evidence

Evidence captured on 2026-09-14:

| Gate | Result |
| --- | --- |
| Composer definition / advisory audit | Valid lock; no known security advisories |
| Full host regression | 142 tests passed, 2,357 assertions |
| Focused public/disabled tests | 9 tests passed, 98 assertions |
| Complete TravelTours feature suite | 19 tests passed, 1,699 assertions |
| Migration probe | 34 tables, 645 documented columns, 0 missing comments |
| PHPDoc audit | All module PHP files passed |
| Pint | Module, TravelTours tests, and homepage test passed |
| Blade compile | `view:clear` followed by `view:cache` passed |
| Route inspection | 12 namespaced TravelTours routes |
| Vite build | Production build passed |
| Browser UAT | 11 inspections and six viewport captures passed |

The lock closeout upgrades the vulnerable packages identified during review to
`dompdf/dompdf` 3.1.6, `guzzlehttp/guzzle` 7.15.5,
`league/commonmark` 2.10.1, and `livewire/livewire` 4.4.4. Laravel remains on
the tested 12.64.0 baseline. The complete regression and browser harness were
rerun after this dependency change.

The executable browser harness is
`scripts/qa-travel-tours-storefront.mjs`; run it through
`npm run qa:travel-tours-storefront` against a server with the optional demo
catalogue. Its diagnostics and captures live in
`.docs/TravelTours/qa/storefront/`.

Browser evidence covers 1440 px desktop, 820 px tablet, 390 px mobile, and
320 px narrow rendering in light/dark and reduced-motion states. It asserts
the correct header variant, centered logo, account actions, theme surface,
loader completion, canonical and schema output, Vite assets, public data,
filter behavior, detail content, labelled controls, duplicate IDs, broken
images, runtime/network errors, and horizontal overflow.

## 7. Known Environment And Phase Boundaries

The local PHP runtime reports that Imagick was compiled against ImageMagick
1808 while 1810 is loaded. Media conversion completed in this fixture run, but
the version mismatch remains an environment defect and should be reconciled
before production media acceptance.

The Claude review at `claude-review/concern_file.md` remains the source review.
Its current dispositions are recorded separately in
`claude-review/remediation-status.md`. Open C-04 through C-10 items are not
silently closed by storefront QA and remain sequenced before their affected
K5-K7 capabilities are accepted.
