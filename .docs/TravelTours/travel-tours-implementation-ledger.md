# Travel & Tours Implementation Ledger

This is an evidence ledger, not a roadmap. A phase remains partial until every
exit gate in the master plan is supported by named verification.

| Date | Phase | Status | Change | Evidence / limitation |
| --- | --- | --- | --- | --- |
| 2026-09-13 | K0 | Baseline committed locally | Imported an independent root tree from Aureon subtree commit `c7aaddbb40e85dd1796576ce350c8a4a97af0189` | Local `main` commit `f4139b7`; origin is configured but authenticated remote access was unavailable during the 2026-09-14 verification |
| 2026-09-13 | K0 | Complete locally | Recontextualized application/package identity, Kenfam configuration/assets, institutional defaults, and public homepage | Homepage feature and browser tests pass; client approval still governs institutional/legal copy |
| 2026-09-14 | Planning | Complete for foundation | Expanded module plan, architecture, data dictionary, workflows, diagrams, service contract, verification plan, and factual foundation audit | Ten generic module documents plus the Kenfam adoption/provenance documents |
| 2026-09-14 | K1 schema | Verified on SQLite | Seven ordered migrations define 34 module tables and 645 documented columns | Foundation probe reports zero missing comments; production-engine constraints/concurrency remain pending |
| 2026-09-14 | K1 models/factories | Verified | 34 models define ULIDs, enums, casts, relationships, scopes, privacy hiding, media contracts, and one factory per model | Complete TravelTours suite passes |
| 2026-09-14 | K1 services | Partial | Deterministic quote, customer resolution, owner-bound holds, atomic booking placement, booking numbering, payments, instalments, and inquiries | Lifecycle, refund, register/shift, audit, and production contention slices remain open |
| 2026-09-14 | Review A/B | Partially resolved | Corrected homepage regression, signed-link configuration paths, environment contract, and exponent-aware money rendering | C-01, C-02, and C-03 are covered by focused tests; C-04 through C-10 retain explicit phase gates |
| 2026-09-14 | Public storefront foundation | Accepted locally | Added responsive/theme-aware homepage, catalogue/filtering, tour details, inquiries, signed status presentation, shared navigation/footer, and client/module assets | `qa:travel-tours-storefront` passes 11 inspections and six viewport captures; advanced K4/K5 work remains |
| 2026-09-14 | Demonstration fixtures | Verified opt-in | Added six fictional travel products with complete public relational data/media and three sample operator roles | Seeder is idempotent, non-destructive, and absent from `DatabaseSeeder`; fixture test runs it twice |
| 2026-09-14 | Disabled-module isolation | Verified | Added a fresh-application test with TravelTours disabled | No routes, views, policies, event listeners, or contract bindings are registered |
| 2026-09-14 | Code/document standards | Verified | Added storefront implementation record, review remediation ledger, PHPDoc audit, and formatting checks | PHPDoc audit and Pint both pass |
| 2026-09-14 | Host regression | Verified locally | Re-ran the complete application test surface after populated-storefront corrections | 142 tests passed with 2,357 assertions; clean Blade cache and 12-route inspection passed |
| 2026-09-14 | Dependency hardening | Verified | Refreshed stale lock metadata and patched Dompdf, Guzzle, CommonMark, and Livewire advisories without changing the Laravel 12.64 baseline | `composer validate` passes; `composer audit --locked` reports no known advisories; full tests/browser QA were rerun afterward |
| 2026-09-16 | Repository publication | Complete | Independent `main` and `feature/travel-tours-foundation` history published to the Kenfam private origin | Foundation branch tracks `origin/feature/travel-tours-foundation` at `ecdc00c`; Aureon remains fetch-only with push disabled |
| 2026-09-16 | K1 reconciliation | Complete at foundation scope | Discarded the oversized uncommitted closeout, assigned advanced controls to their owning phases, added private signed-response headers, and removed the misleading log-only receipt-printer binding | `TravelToursFoundationCloseoutTest`, signed HTML/PDF header assertions, 20 module tests / 1,722 assertions, 143 host tests / 2,380 assertions |
| 2026-09-16 | K2A catalog access boundary | Verified locally | Added separate publication authority, context-owned policies, policy-scoped catalog queries, immutable catalog DTOs, named failures, and explicit absence of unfinished routes | `TravelToursCatalogAccessTest`: 5 tests / 33 assertions; module 25 / 1,755; host 148 / 2,414; Pint, PHPDoc, Blade and route inspection pass |
| 2026-09-16 | K2B category and destination administration | Verified locally | Added DTO-driven transactional category/destination services, Livewire Forms/managers, hierarchy and geography rules, owned accessible destination media, completed routes, role-aware navigation, and Aureon token-based admin styling | Focused 9 tests / 62 assertions; module 29 / 1,784; host 152 / 2,443; PHPDoc, Pint, Blade, route inspection, and Vite build pass; authenticated browser matrix remains K2F |
| 2026-09-17 | K2C tour catalog and base editor | Complete, committed and pushed as `38f0710` | Added bounded Livewire catalog search/filters/counts, draft tour basics, category/destination route assignment, ULID edit pages, queued-conversion image fallback, and authenticated admin browser harness | Focused 8 / 37; module 37 / 1,823; host 160 / 2,482; Pint, PHPDoc, schema probe, Blade, build, and seven desktop/tablet/mobile theme captures pass |
| 2026-09-17 | K2D itinerary and experience editing | Complete; committed and pushed as `a17e937` | Added typed day/activity, content, FAQ and exact-money extra services, Forms, responsive editor tabs, and contextual CRUD dialogs; enforced tour ownership, order, duration and archive rules | Focused 11 / 52; module 48 / 1,875; host 171 / 2,534; 12 authenticated captures and five dialog focus/viewport probes pass |
| 2026-09-17 | K2E catalog completion | Complete; reviewed, committed as `527d752`, and merged into `main` | Added default base participant pricing, tour cover/gallery/documents, filename alt suggestions and upload previews for tours/destinations, readiness and publication, private preview, catalog cues, public filters and expandable captioned gallery | Focused 9 / 70; module 57 / 1,945; host 180 / 2,604; Pint, PHPDoc, schema probe, Blade and build pass; 12 admin and nine public screenshots; browser gallery navigation/focus, responsive/theme checks pass |
| 2026-09-18 | K2 category manager UX | Implemented and locally verified; uncommitted | Replaced the numeric order column with service-owned sibling reorder arrows, made visibility a switch that states its blocker, added a policy-authorized details dialog, rendered the hierarchy depth-first with indentation, laid row actions inline, added `novalidate`, focus-on-open, `aria-invalid`/`aria-describedby`, and the dark-mode close-button rule | `TravelToursCategoryOrderingTest` 7 / 28; module 64 / 1,973; host 187 / 2,632; Pint, PHPDoc, Blade, build pass; nine authenticated captures under `qa/catalog-taxonomy/category-manager-enhancement/` with zero assertion, runtime, or network failures |
| 2026-09-18 | K3A departure scheduling | Implemented and verified; committed on `feature/travel-tours-k3-departures` | One Livewire departure manager shared by the central departures workspace and a tour editor tab; typed `DepartureData`; service-owned create/update/transition with locked transactions, DST-gap and fold rejection, ordered booking windows, tour-owned rate plans, a capacity floor against holds and bookings, committed-date protection, explicit status edges, and departure team assignment; public tour dates now resolve seats through the availability service | Focused 10 / 44; module 74 / 2,018; host 197 / 2,677; Pint, PHPDoc, schema probe, Blade; 13 captures under `qa/admin-k3a/` with zero failures. Record: `travel-tours-k3-departures-implementation-plan.md` |
| 2026-09-18 | M1 public availability and selection (K3B) | Complete; committed as `03cbcd2`, merged into `main` | Added the `DepartureSelector` Livewire component and `SelectionForm`: bookable departures with live seat availability, a participant mix bounded by configuration, a server-side quote through the unchanged `TourQuoteCalculator`, and promotion codes reported inline | Focused module suite 81 / 2,066; Pint clean; harness `qa-m1-selection.mjs`, 12 captures, zero runtime/network errors, no horizontal overflow. Evidence: `qa/storefront-m1-selection/` |
| 2026-09-18 | M2 seat holds and public checkout (K5 holds, checkout, travellers) | Complete; committed as `eddd9b0`, merged into `main` | Owner-bound `AvailabilityHold` on Continue, `/tours/checkout/{hold}` with customer and participant snapshots, terms version and preferred payment method, idempotent placement keyed on the hold ULID, signed confirmation, and the release/expiry console commands | Focused module suite 90 / 2,161; Pint clean; harness `qa-m2-checkout.mjs` walked select → Continue → validation → placement → confirmation → PDF, consumed-hold redirect, foreign-session 403, expired page; 9 captures, zero runtime/network errors. Evidence: `qa/storefront-m2-checkout/` |
| 2026-09-19 | M3 manual payment confirmation and booking workspace (K5 payments, admin) | Complete; committed as `3ae08ba`, merged into `main` | `BookingPaymentService` split into `recordPending` / `confirm` / `reject` with refunds; `CONFIRM_PAYMENTS` and `REFUND_PAYMENTS` permissions; the `BookingManager` workspace with filters, a detail dialog, and lifecycle actions | Focused module suite 101 / 2,261; Pint clean; harness `qa-m3-bookings.mjs`: agent records evidence without a confirm control, manager confirms payment and booking, refund ceiling refused inline then recorded, cancel requires a reason; 13 captures, zero runtime/network errors. Evidence: `qa/admin-m3-bookings/` |
| 2026-09-19 | M4 queued booking and payment communications (K7 lean) | Complete; committed as `14d7d1a`, merged into `main` | Placed, payment-recorded (staff), payment-confirmed and booking-confirmed notifications; `StaffRecipientResolver` for `CONFIRM_PAYMENTS` holders; queue name, after-commit dispatch, retries and backoff owned by configuration and gated on `notifications.enabled` | Focused module suite 106 / 2,275; Pint clean; every notification rendered against the isolated QA database with subjects, queue, after-commit flag, retries and links checked. Evidence: `qa/communications-m4/` (`*.html`, `diagnostics.json`) |
| 2026-09-19 | M5 rate plan, pricing rule and promotion administration (K3C, K3D) | Complete; committed as `f953700`, merged into `main` | Transactional `RatePlanService`, `PricingRuleService` and `PromotionService` with atomic participant-rate replacement, overlapping-window refusal, code uniqueness and tour assignments; three Livewire managers under `VIEW_PRICING` | Focused module suite 113 / 2,327; Pint clean; harness `qa-m5-pricing.mjs`: overlapping fares refused inline then saved, rule toggled with switch state verified after re-render, promotion validated, editor sees no write controls; 14 captures, zero runtime/network errors. Evidence: `qa/admin-m5-pricing/` |
| 2026-09-19 | M6 booking desk with shifts, assisted sales and receipts (K6 lean) | Complete; committed as `e5554c8`, merged into `main` | `BookingShiftService` (one open shift per user, expected-cash formula, variance threshold, movements), `DeskCheckoutService` reusing the public hold and placement services, and a browser receipt driver bound to `PrintsBookingReceipts` — closing review concern C-06 | Focused module suite 120 / 2,409; Pint clean; harness `qa-m6-desk.mjs`: register created, shift opened, cash sale confirmed with the drawer reconciled, 58/80 mm receipt, cash banked out, close with a KES 500 shortfall reported and reconciled; 15 captures, zero runtime/network errors. Evidence: `qa/pob-m6-desk/` |
| 2026-09-19 | M6 realignment on the PropertyBooking POB | Complete; folded into `c2d82cf` on `main` | The desk was rebuilt to mirror the PropertyBooking point-of-booking: tour search, customer create/select, deposit and split tenders, parked holds with resume and discard, receipt with both tenders, manager reconciliation and sign-off | Harness `qa-desk-realigned.mjs`: float validation, deposit and mobile-money split, one `window.print()` after checkout and none on reload, PDF served, park/resume/discard, close KES 500 short with variance and sign-off, no-shift gate; 20 captures, zero runtime/network errors. Evidence: `qa/pob-m6-desk-realigned/` |
| 2026-09-19 | M7 release hardening (K8, D-1..D-5) | Complete; committed as `c2d82cf`, merged into `main` | Full-journey and contention tests; D-1 timezone from configuration, D-2 multi-line Blade, D-3 `Support/LikePattern` wildcard escaping, D-4 one exception base, D-5 bounded tour selector; dead configuration keys removed | Focused module suite 126 / 2,511; Pint clean; release sweep `qa-m7-sweep.mjs` over every storefront, admin and desk surface at desktop, mobile and one 320 px pass in both themes: 63 captures, zero horizontal overflow, zero runtime/network errors, every input labelled. Evidence: `qa/release-m7-sweep/` |
| 2026-09-19 | Post-release cosmetics and olive brand palette | Complete; committed as `7ee5592` and `be44113` on `main` | Sidebar group order, desk tour thumbnails, `#6a753d` added as an independent palette and made the default in both theme controllers, palette-aware dashboard components, and PDF/mail colours driven by `config('kenfam.colors')` | Host suite 252 passed; Pint clean; harness `qa-cosmetics.mjs` with a fresh profile: both surfaces resolve olive by default, wine still switchable, buttons follow the active palette, sidebar hover and active states bound to it. Evidence: `qa/cosmetics-olive-palette/` |
| 2026-09-19 | Live catalogue search | Complete; committed as `8b483a2`, merged into `main` | `/tours` became the `TourSearch` Livewire component: every filter live and URL-bound under the previous query keys, server-rendered first paint, inline validation, loading and offline states, in-component pagination | Focused module suite 132 / 2,573; Pint clean; harness `qa-live-search.mjs`: a window token proves no document reload across five filters and clear, `data-loading` and `aria-busy` observed, offline notice under CDP emulation, ten Livewire requests and zero page loads. Evidence: `qa/storefront-live-search/` |
| 2026-09-20 | Admin alignment — destinations, departures, inquiries | Complete; committed as `d075787`, merged into `main` | The three managers adopted the category pattern: enumerated tables of essential columns, switches that state their blocker, an eye that opens a read-only details dialog, one fluid filter bar as URL state, and row actions pinned in view. Closes C-1, C-2, C-3, C-4, C-6, C-7 and C-9 for these surfaces | Focused module suite 136 / 2,673; Pint clean; harness `qa-admin-alignment.mjs`: tables enumerated from 1, filter controls on one baseline, destination details with cover and gallery, sales switch closed and reopened, inquiry note recorded on the timeline; dark, tablet and phone with row actions measured inside the viewport; 13 captures. Evidence: `qa/admin-alignment/` |
| 2026-09-20 | Admin alignment — bookings manager; favicon preloader | Complete; committed as `f41b8d4`, merged into `main` | The bookings list adopted the same pattern with channel and needs-attention filters, a status count strip, `travel-status` pills, sticky `#` and Actions columns, and a scoped loading state; `x-loader` renders the configured favicon | Host suite 261 / 3,345; Pint clean; harness `qa-bookings-alignment.mjs`: filters 20 px under the header on one baseline, list marked busy with the refresh pill during a filter request, URL state, details dialog, dark and phone widths; loader `src` equals the document favicon on both surfaces. Evidence: `qa/admin-alignment/` |
| 2026-09-20 | Storefront social identity and WhatsApp booking | Complete; committed as `1828810` and `0bc5d63`, merged into `main` | Module-local `InstitutionContact` resolving the WhatsApp number, X handle, `sameAs` and footer profile links from Institution Details; footer social bar in the lead row with inline brand marks; tour-page share group, `Book via WhatsApp`, price and availability meta, `TouristTrip` offers; configurable profile defaults; the olive favicon replacing the wine mark | Host suite 272 / 3,418; Pint clean; harnesses `qa-social-share.mjs` and `qa-footer-lead.mjs`: share marks measured, copy toast after a real click, popup share without navigation, footer bar between the heading and the call to action at 1440 and stacked at 390, both surfaces resolving the new icon. Evidence: `qa/storefront-social-share/` |
| 2026-09-20 | Brand colour environment hardening | Complete; committed as `e355a55`, merged into `main` | An unquoted `#rrggbb` in `.env` parses as a comment, so `env()` returned an empty string and the storefront default palette rendered with blank custom properties. `config/kenfam.php` now accepts only a valid hex and falls back to the brand default; the storefront and error-page `theme-color` metas follow the configured primary | Host suite 273 / 3,424; Pint clean; `config:cache` verified to store plain strings; `BrandColorsTest` covers empty, un-hashed, valid uppercase and non-hex environment values |
| 2026-09-20 | Homepage hero copy | Complete; committed as `ac8b1d6`, merged into `main` | The hero adopted the brand tagline as its heading ("Travel farther. Return richer."), a broader lead naming escorted, business, educational, group, corporate and custom travel, and an eyebrow reading "Tours and Travel experts since {founded year}" from the institution profile | `HomePageTest` asserted the superseded heading and eyebrow and failed from this commit until the assertions were corrected on 2026-09-24; host suite 273 / 3,424 after the correction |

## Current Phase State

From 2026-09-18 the K3-K8 roadmap was superseded by the seven milestones of
`travel-tours-combined-delivery-plan.md`, which reordered the same scope around
the customer journey. The K-phase column below records which milestone resolved
each phase; the plan holds the per-milestone feature map.

| Phase | State | Remaining gate |
| --- | --- | --- |
| K0 | Complete and published | Client approval remains required for institutional/legal copy |
| K1 | Complete at foundation scope | See the K1 reconciliation; the operational controls it deferred were delivered by M2-M6 |
| K2 | Complete at catalog scope; `527d752` merged into `main` | Admin UX refinements C-5 and C-8 remain open in `todo/refinements_todo.md` |
| K3 | Complete: K3A (`3f63276`), K3B by M1, K3C and K3D by M5, K3E by M7 | Bulk departure scheduling is deferred as P-4 |
| K4 | Complete at shipped scope: storefront foundation, live catalogue search, social sharing | Maps and approved editorial content are not in scope; copy remains subject to client approval |
| K5 | Complete: holds, checkout and participant snapshots by M2, payments and booking administration by M3 | Reusable traveller profiles (P-2), per-record agent scoping (P-3) and gateway integration (P-5) are post-release |
| K6 | Complete: desk, registers, shifts and browser receipts by M6, realigned on the PropertyBooking POB | Manual drawer movements need a manager dialog (P-7) |
| K7 | Complete at lean scope: events, listeners, recipients, queue ownership and mail by M4 | Purpose-limited document projections (C-04), system activity writes (C-10) and the reporting surface (P-1) remain open |
| K8 | Complete at release scope: journey and contention tests, full-surface QA sweep, documentation closed by M7 | Multi-process MariaDB contention proof is deferred as P-8; deployment and adoption closeout stay with the client |

## Current Verification Snapshot

Measured on 2026-09-24 at `ac8b1d6` with no cached configuration present
(`php artisan optimize:clear` first: a cached config makes the suite read the
development database instead of the in-memory one declared by `phpunit.xml`).

```text
php artisan test --compact
PASS: 273 tests, 3424 assertions

php artisan test tests/Feature/TravelTours --compact
PASS: 147 tests, 2746 assertions

php vendor/bin/pint --test app/Modules/TravelTours tests/Feature/TravelTours tests/Feature/HomePageTest.php
PASS

php scripts/probe-travel-tours-foundation.php
PASS: 34 tables, 645 documented columns, 0 missing comments

php scripts/audit-travel-tours-docblocks.php
FAIL: 43 reported issues, all false positives (see below)

npm run build
PASS: production assets built, eight static-copy targets copied

npm run qa:travel-tours-storefront
npm run qa:travel-tours-admin
Not rerun in this documentation pass; the promoted harnesses still cover the
K2E surface only. Every milestone from M1 onward was verified with a
scratchpad harness whose captures and diagnostics are committed under
`qa/<slice>/` and named in the row for that slice.
```

The docblock audit failure is a defect in the audit script, not in the module.
`hasDeclarationDocblock()` walks back past visibility, `static`, `final`,
`abstract` and `readonly` modifiers but not past a PHP 8 attribute, so every
method carrying `#[Computed]`, `#[Url]`, `#[Locked]` or `#[On]` is reported as
undocumented even when its PHPDoc sits directly above the attribute. All 43
reported declarations were spot-checked and documented. The fix is carried as
V-4 in `todo/refinements_todo.md`; until it lands the audit cannot be used as
acceptance evidence for Livewire components.

The foundation probe reports `full_foundation_accepted: false`. That flag is
reserved for production-engine acceptance (constraint and concurrency
behaviour on MariaDB); the SQLite structural assertions it gates all pass, and
the outstanding multi-process proof is carried as P-8.

`composer validate --no-check-publish` last passed on 2026-09-16; no Composer
dependency or lock file has changed since. The 2026-09-14 locked advisory audit
remains the latest network-permitted evidence.

The local PHP process continues to report an Imagick/ImageMagick 1808 versus
1810 version mismatch. It does not make the current test or fixture run red,
but production media acceptance remains conditional on environment alignment.

Update this ledger in the same commit as each implementation slice. Evidence
must name focused tests, probes, builds, and browser captures. Never replace a
missing gate with "implemented" based only on file existence.
