# TravelTours Module Review: Concerns And Standards Alignment

Review date: 2026-09-14. Reviewer scope: read-only. No source file, migration,
test, configuration, or document was modified by this review other than this
file.

## 1. Method And Evidence

Read in this order: the Aureon base engine at
`kingster-education-html-template-2023-11-27-05-11-02-utc/Custom Templates
Builds/laravel-aureon` (README feature ledger, `.docs/PropertyBooking/`,
`.docs/PointOfSale/`, `app/Modules/{Commerce,PropertyBooking}`, `tests/`,
`scripts/`), then the Kenfam foundation set (`.docs/Kenfam/`,
`.docs/TravelTours/` module plan, architecture, data model, workflows, service
contracts, verification plan, foundation audit, foundation implementation,
implementation ledger), then all 199 files of `app/Modules/TravelTours` and its
host integration points.

Commands actually executed against this working tree:

| Command | Result |
| --- | --- |
| `php vendor/bin/phpunit tests/Feature/TravelTours --testdox` | OK, 13 tests, 1,638 assertions |
| `php scripts/probe-travel-tours-foundation.php` | 34 tables, 645 documented columns, 0 missing comments, exit 0 |
| `php scripts/audit-travel-tours-docblocks.php` | Passed for all module PHP files, exit 0 |
| `php vendor/bin/pint --test app/Modules/TravelTours tests/Feature/TravelTours` | `{"tool":"pint","result":"passed"}` |
| `php artisan route:list --name=travel-tours` | 12 routes |
| `php artisan test` (Kenfam default suite) | **1 failed, 135 passed, 2,283 assertions** |

The local PHP runtime still emits the documented Imagick/ImageMagick 1808 vs
1810 warning on every invocation. That environment defect is unresolved and
still blocks media-conversion QA, exactly as the foundation implementation
record states.

## 2. What Is Genuinely At Standard

These are verified, not assumed, and should not be reworked:

- **Module isolation is real.** No `App\Modules\Commerce` or
  `App\Modules\PropertyBooking` import exists anywhere in TravelTours, and no
  `config('kenfam.*')` read exists in module code. TT-03's dependency-scan
  condition passes.
- **Provider toggle discipline matches the base contract.**
  [TravelToursServiceProvider.php](app/Modules/TravelTours/TravelToursServiceProvider.php)
  merges config first, returns before binding when disabled, and repeats the
  guard as the first boot instruction. This mirrors
  `PropertyBookingServiceProvider` exactly.
- **Persistence foundation is the strongest part of the module.** 34 tables,
  645 commented Blueprint columns, explicit timestamp comments, comment-before-
  `constrained()` ordering, and one factory per model — all independently
  re-verified by the probe and the foundation test, not by source regex.
- **Booking placement is correctly engineered.**
  [TourBookingService.php:58-114](app/Modules/TravelTours/Bookings/Services/TourBookingService.php#L58-L114)
  implements the documented lock order (operation key, hold read, departure
  lock, hold lock, customer, quote recalculation, rate-plan lock, promotion
  lock), verifies quote fingerprint and policy version, consumes the hold only
  after all writes, retries three times at the transaction boundary, and
  dispatches `TourBookingPlaced` which correctly implements
  `ShouldDispatchAfterCommit`.
- **Integer-minor-unit arithmetic in the quote engine is sound.**
  [TourQuoteCalculator.php](app/Modules/TravelTours/Pricing/Services/TourQuoteCalculator.php)
  uses deterministic half-up division for percentage, inclusive/exclusive tax
  and deposit, and rejects ambiguous rate overlap rather than taking the first
  row.
- **ULID handling is correct.** `HasUlid` generates, validates, blocks mutation,
  and returns `ulid` from `getRouteKeyName()`, so signed public booking URLs
  address rows by ULID and not by internal ID.
- **Host integration is restrained and correctly gated.** `CmsPermission` now
  composes each module catalogue behind its own `enabled` flag; the admin
  sidebar checks `config('travel-tours.enabled')`, `Route::has(...)` and
  per-item `@can` before rendering any link; `DatabaseSeeder` calls the access
  seeder only when the module is enabled.
- **The access seeder is people-free and idempotent**, and operator identities
  are isolated in `TravelToursDemoOperatorSeeder`, which `DatabaseSeeder` never
  calls. The audit's disposition on this point is satisfied.
- **The storefront profile resolver is genuinely client-neutral.**
  [TravelStorefrontProfileResolver.php](app/Modules/TravelTours/Storefront/Services/TravelStorefrontProfileResolver.php)
  projects `ResolvesInstitutionProfile` rather than reading client config.
- **Documentation depth already exceeds the base modules.** Ten TravelTours
  documents plus six Kenfam documents is a stronger specification set than
  PropertyBooking had at the equivalent phase.

## 3. Structural Parity Against The Reference Modules

Measured against `app/Modules/PropertyBooking` and `app/Modules/Commerce`, which
the master plan names as the comparable engineering-depth benchmark.

| Element | Commerce | PropertyBooking | TravelTours | Assessment |
| --- | --- | --- | --- | --- |
| Livewire components | 24 | 17 | **0** | Absent |
| Livewire `Forms/` | Yes, per context | Yes, per context | **None** | Absent |
| Explicit Livewire aliases in provider | Yes | Yes | **None** | Absent |
| Policies location | Per bounded context | Per bounded context | Module root `Policies/` | Deviates from own §1 |
| Events location | `Orders/Events`, `Inventory/Events` | `Bookings/Events`, `PointOfBooking/Events` | Module root `Events/` | Deviates from own §1 |
| Listeners location | `Notifications/Listeners` | `Bookings/Listeners` | Module root `Listeners/` | Deviates from own §1 |
| `Reporting/` | Yes (Data, Filters, Reports, Services) | Yes | **Absent** | Absent |
| `Console/Commands/` | Yes | Yes | **Absent** | Absent |
| `PointOfBooking/Printing/` drivers | `Printing/{Contracts,Data,Drivers}` | `Printing/{Contracts,Data,Drivers}` | One stub service | Absent |
| Document DTOs | `Orders/Data/Documents/` | `Bookings/Data/Documents/`, `PointOfBooking/Data/Documents/` | **Absent** | Absent |
| `Support/MoneyFormatter`, `Support/ScaledDecimal` | Yes | Yes | **Absent** | Absent |
| Vite asset entries | 7 | 6 | **2** (storefront only) | No admin or POB assets |
| Module views | 51 | 48 | 15 | Shells |
| Feature test files | 25 | 23 | **2** | Foundation only |
| Disabled-module test | `CommerceDisabledModuleTest` | `PropertyBookingDisabledModuleTest` | **Absent** | TT-02 gap |
| Browser QA harness | 6 `.mjs` scripts | 3 `.mjs` scripts | **0** | Absent |
| `package.json` QA script | Yes | Yes | **Absent** | Absent |
| Committed UAT screenshot set | `.docs/dev/commerce-*-qa/` | `.docs/PropertyBooking/qa/{catalog-availability,pob,storefront}/` + `diagnostics.json` | **Absent** | Absent |
| Optional demonstration catalogue seeder | `commerce:demo-seed`, 10 contexts | `PropertyBookingDemoSeeder` | **Absent** (6 unused `.webp` files only) | Absent |
| `RecordsSystemActivity` usage | Extensive | Extensive | **Zero call sites** | Absent |

Service inventory: the service contracts document specifies 30 named services.
Twelve exist (`TourSearchService`, `TourQuoteCalculator`,
`DepartureAvailabilityService`, `AvailabilityHoldService`,
`TravelCustomerService`, `TourBookingService`, `BookingPaymentService`,
`PaymentScheduleService`, `BookingNumberGenerator`, `BookingAccessUrlService`,
`TourInquiryService`, `BookingDocumentService`). Eighteen do not, including every
catalog, itinerary, media, departure, rate-plan, pricing-rule and promotion write
service, plus `BookingLifecycleService`, `BookingRefundService`,
`TravelerService`, `BookingRegisterService`, `BookingShiftService`,
`BookingDocumentDataFactory`, `TravelOperationsService`, `TravelRecipientResolver`
and `NotificationDispatcher`.

This absence is consistent with the ledger's own K1/K2-K7 status. It is recorded
here as the parity baseline, not as a hidden regression.

## 4. Confirmed Defects

Each item below was reproduced against the current working tree.

### C-01 Kenfam default regression suite is red

`php artisan test` fails at
[HomePageTest.php:31](tests/Feature/HomePageTest.php#L31). The test asserts the
homepage renders `Operating since 1994`. That string does not exist anywhere in
`resources/` or `app/`. [welcome.blade.php:16-17](resources/views/welcome.blade.php#L16-L17)
renders `Escorted travel expertise since {{ $profile->foundedYear }}`. Both files
are modified in the working tree; the assertion was not updated when the view was
rewritten.

Impact: the implementation ledger's entry "Homepage/dashboard/TravelTours focused
set exits zero with 1,731 assertions" is no longer true. No phase may be signed
off against a red default suite, and the verification plan's release gate
explicitly requires the full host regression.

Secondary observation on the same assertion: `$profile->foundedYear` resolves
through `config('travel-tours.storefront.founded_year')` then
`config('institution.defaults.founded_year')`, both of which read environment
variables that are present in `.env.example` (lines 67 and 176) but absent from
the committed test environment in `phpunit.xml`. Even after the string is
corrected, the year is not deterministic under test. Whichever fix is chosen,
the founded-year source needs one authority.

### C-02 Signed-link lifetimes read configuration keys that do not exist

[BookingAccessUrlService.php:21,27,33](app/Modules/TravelTours/Storefront/Services/BookingAccessUrlService.php#L21)
reads `travel-tours.storefront.confirmation_link_minutes`,
`travel-tours.storefront.tracking_link_days` and
`travel-tours.storefront.document_link_days`.

[travel-tours.php:27-29](app/Modules/TravelTours/Config/travel-tours.php#L27-L29)
defines those three keys under `booking.`, not `storefront.`.

Every call therefore silently uses the inline literal fallback. The most visible
consequence: the configured tracking-link lifetime is 180 days, the inline
fallback is 90 days, so customer tracking URLs expire at half the documented
lifetime and no environment variable can change it. The three
`TRAVEL_TOURS_*_LINK_*` variables are additionally absent from `.env.example`, so
the defect is invisible from the adopter-facing surface.

This is the single highest-value correctable defect in the module: it is small,
silent, security-lifetime relevant, and a focused test would have caught it.

### C-03 Currency exponent is snapshotted but never honored in presentation

The data model and architecture §7 require an explicit currency exponent, the
migrations persist `currency_exponent` on bookings and shifts
([2026_09_13_100500...php:42](app/Modules/TravelTours/Database/Migrations/2026_09_13_100500_create_travel_booking_tables.php#L42)),
and `TourBookingService.php:195` writes it. No presentation path reads it.

Every money render hardcodes division by 100 and two decimal places:

| Location | Line |
| --- | --- |
| `Resources/views/reports/booking-summary.blade.php` | 9, 11, 12 |
| `Resources/views/admin/bookings/index.blade.php` | 4 |
| `Resources/views/admin/dashboard/index.blade.php` | 10 |
| `Resources/views/storefront/bookings/show.blade.php` | 37 |
| `Resources/views/storefront/catalog/show.blade.php` | 50, 77 |
| `Resources/views/storefront/catalog/partials/tour-card.blade.php` | 14 |
| `Notifications/BookingPaymentConfirmedNotification.php` | 43 |

A booking in a zero-exponent currency renders 100× its true value on the invoice
PDF and the customer email. Commerce and PropertyBooking both solve this with a
`Support/MoneyFormatter` plus `Support/ScaledDecimal`; TravelTours has neither.

### C-04 Document and signed-page adapters pass raw Eloquent models

The foundation audit required "raw models to documents" to be corrected, and
architecture §8 requires purpose-limited document DTOs.

[BookingDocumentService.php:31-34](app/Modules/TravelTours/Bookings/Services/BookingDocumentService.php#L31-L34)
eager-loads `participants`, `payments` and `customer` onto the aggregate and
passes `['booking' => $booking]` straight into the PDF view. The same pattern
appears on the public signed pages at
[BookingAccessController.php:23,29](app/Modules/TravelTours/Storefront/Http/Controllers/BookingAccessController.php#L23).

The current templates are careful, and `$hidden` protects the encrypted identity
and care fields. But privacy is enforced only by template discipline: any future
template edit, `@dump`, or exception page reaches the full customer and traveler
graph. PropertyBooking's `Bookings/Data/Documents/BookingDocumentData` and
`PointOfBooking/Data/Documents/BookingReceiptData` exist precisely so that the
projection, not the template, is the privacy boundary.

Related, smaller: the document response at
[BookingAccessController.php:37](app/Modules/TravelTours/Storefront/Http/Controllers/BookingAccessController.php#L37)
sets `X-Content-Type-Options` but no `Cache-Control: private, no-store` and no
`X-Robots-Tag: noindex, nofollow`. The HTML booking view does correctly set the
`robots` meta at `storefront/bookings/show.blade.php:12`, but a PDF cannot carry
a meta tag, so that route has no crawl or cache protection at all.

### C-05 Listener plus notification double queueing

Architecture §9 names this exact anti-pattern: "avoid accidental
listener-plus-notification double queueing." Both pairs implement it:

- `Listeners/SendTourBookingPlacedNotification.php:17` implements `ShouldQueue`
  and `Notifications/TourBookingPlacedNotification.php:19` implements
  `ShouldQueue`.
- `Listeners/SendBookingPaymentConfirmedNotification.php:17` and
  `Notifications/BookingPaymentConfirmedNotification.php:19` are identical.

One committed event therefore enqueues a listener job whose only work is to
enqueue a second notification job. The audit flagged "nested queued dispatch" and
its disposition did not close it.

Compounding this, neither listener checks `config('travel-tours.enabled')` or
`config('travel-tours.notifications.enabled')` before sending, so work queued
before a module disablement still delivers — contrary to architecture §2 and §9.
Neither path calls `onQueue(config('travel-tours.notifications.queue'))`, so both
land on the default queue while `.env.example` advertises a dedicated
`notifications` queue.

Recipients are customer-only. `TravelRecipientResolver` does not exist, so no
operational staff notification is possible.

### C-06 A stub is bound to a public contract

[BrowserReceiptPrinter.php:22](app/Modules/TravelTours/PointOfBooking/Services/BrowserReceiptPrinter.php#L22)
returns `void` after writing one `Log::info` line. It is bound in the provider as
the concrete `PrintsBookingReceipts`.

The service contract requires this contract to return
`PrintInstruction`/`PrintResult` with the invariant "No fake success or implicit
silent printing", architecture §8 states "A log entry does not mean a receipt
reached a printer", and the contracts document states "Do not register a contract
binding until its concrete implementation passes focused tests."

`PrintsBookingReceipts`, `RendersBookingDocuments` and `SearchesTours` are all
bound with zero test coverage. `PrintsBookingReceipts` is the one where the
binding actively misrepresents behavior.

### C-07 Twenty-one configuration keys have no consumer

Architecture §6: "Every live option has a consumer and test. Future options are
documented as future rather than shown as working controls."

`Config/travel-tours.php` defines 42 leaf keys. Twenty-one are read anywhere in
`app/`. The inert twenty-one include every operationally meaningful switch:

```text
defaults.currency_symbol      defaults.timezone
defaults.tax_rate_bps         defaults.prices_include_tax
defaults.confirmation_mode    booking.quote_minutes
booking.confirmation_link_minutes  booking.tracking_link_days
booking.document_link_days    storefront.catalog_limit
storefront.payment_methods    media.tour_gallery_limit
media.destination_gallery_limit    media.upload_max_kilobytes
notifications.enabled         notifications.queue
numbering.padding             pob.variance_threshold_minor
pob.receipt_printing.default_driver
pob.receipt_printing.default_mode
pob.receipt_printing.default_paper_width_mm
```

`.env.example` lines 162-191 publish 30 `TRAVEL_TOURS_*` variables to adopters.
Twelve of them change nothing. `TRAVEL_TOURS_TAX_RATE_BPS` and
`TRAVEL_TOURS_PRICES_INCLUDE_TAX` are the sharpest case: tax is genuinely
implemented, but the authority is the per-rate-plan `tax_rate_basis_points` and
`tax_inclusive` columns, so the two published environment variables are a second,
inert authority for the same decision.

### C-08 Quote has no expiry, and `quote_minutes` is dead

The service contract requires `CalculatesTourQuotes` to return "TourQuote with
lines/version/fingerprint/expiry".
[TourQuote.php](app/Modules/TravelTours/Pricing/Data/TourQuote.php) carries
fingerprint but neither version nor expiry, and
`config('travel-tours.booking.quote_minutes')` is never read.

Stale-quote protection currently rests entirely on the hold's `expires_at` plus
fingerprint comparison at placement. That is defensible for the K1 slice, but it
means a quote handed to a browser without a hold has no server-side lifetime, and
the verification plan's "stale read quote followed by conflicting placement" case
cannot be expressed against the DTO as typed.

### C-09 Authorization is coarser than the published access matrix

`TravelResourcePolicy::view()`
([TravelResourcePolicy.php:30](app/Modules/TravelTours/Policies/TravelResourcePolicy.php#L30))
accepts `Model $model` and ignores it. Architecture §5 requires: "Scope both list
queries and actions. A restricted agent must not browse every customer or
booking by substituting a ULID." No ownership, assignment, or scope predicate
exists in any policy except `ShiftPolicy::view()`.

The permission catalogue is missing the distinctions the access matrix depends
on. §5 enumerates "view/manage/publish catalog ... sensitive-traveler access;
record/confirm payment; refund; POB; register management; own shift open/close;
other-shift reconciliation". The implemented catalogue has no publish, no
sensitive-traveler, no separate refund, no separate register management, and no
separate other-shift reconciliation permission.

The consequence is visible in the access seeder: `travel-booking-agent` receives
`MANAGE_PAYMENTS`
([TravelToursAccessSeeder.php:34](app/Modules/TravelTours/Database/Seeders/TravelToursAccessSeeder.php#L34)),
whose own description is "Record instalments, payments, and refunds." The
published matrix says a booking agent's "Remote payment confirmation/refund" is
"No default". The seeded role currently contradicts the documented matrix.

### C-10 No system-activity audit trail

`RecordsSystemActivity` has zero call sites in the module. Architecture §3 lists
it as a host contract the module depends on, §9 specifies its content, TT-16
requires audit evidence, and both reference modules record activity on every
transactional write. Placement, payment, hold release, customer resolution and
inquiry submission currently leave no audit record.

## 5. Structural Misalignments Against The Module's Own Architecture Contract

These are not bugs; they are divergences between §1 of
[travel-tours-module-architecture.md](.docs/TravelTours/travel-tours-module-architecture.md)
and what exists. Deciding them now is cheaper than after K2-K7 code lands on top.

| Contract §1 says | Tree has | Note |
| --- | --- | --- |
| `Catalog/{...,Policies,...}`, `Bookings/{...,Policies,Events,...}` etc. | `Policies/`, `Events/`, `Listeners/` at module root | Both reference modules keep these per context. Moving eight policies and four event/listener files now is a mechanical change; after K2-K7 it is not. |
| `Notifications/{Listeners,RecipientResolution}` | `Notifications/` flat, `Listeners/` separate | |
| `Reporting/{Data,Filters,Reports,Services}` | Absent | K7 scope, but the folder contract should be honored when it lands. |
| `Console/Commands/` | Absent | §9 requires expiry and reminder commands; `AvailabilityHoldService` already has an expiry method with no scheduled caller, so holds are only expired opportunistically. |
| `PointOfBooking/{...,Printing,...}` | `PointOfBooking/Services/BrowserReceiptPrinter.php` | See C-06. |
| `Support/{Concerns,Data}` | `Support/Concerns` only | |
| `Resources/views/{layouts,storefront,admin,pob,livewire,reports}` | No `livewire/` | Consequence of zero Livewire components. |
| `Resources/assets/{css,js}` | storefront only | No `admin.css`/`admin.js`/`pob.css`/`pob.js`; admin views push no `@vite` at all, so the admin surface has no module-owned styling contract. |

Presentation standard: the five admin views and the report view are written as
single compressed lines of 549-1,401 characters. The reference modules keep
normal multi-line Blade with `@push('styles') @vite(...) @endpush` and
`@can(...)` guards around action buttons. There are **zero** `@can` directives in
any TravelTours view, so the "Booking desk" button on the bookings index renders
for anyone holding `VIEW_BOOKINGS` regardless of `ACCESS_POB`. The host sidebar
does gate correctly; the module views do not.

## 6. QA And End-To-End UAT Snapshot Gap

This is the area the review was specifically asked to assess, and it is the
largest single parity gap.

PropertyBooking ships `scripts/qa-property-booking-{admin,pob,storefront}.mjs`,
three `package.json` entries, and a committed evidence set at
`.docs/PropertyBooking/qa/` containing 30 screenshots across desktop, laptop,
tablet and mobile in light, dark and reduced-motion, plus a `diagnostics.json`
per surface. Commerce ships six harnesses and equivalent evidence under
`.docs/dev/commerce-*-qa/`.

TravelTours has none of it: no harness, no `package.json` script, no
`.docs/TravelTours/qa/` folder, no screenshots, no diagnostics. The verification
plan §7 fully specifies what the harness must cover (1440×900 / 820×1180 /
390×844 plus a 320 px overflow check, four theme states, keyboard-only
navigation, focus return, modal trapping, live regions, SEO and structured data).
None of it is executable today.

Two related documentation problems follow from this:

- `README.md` lines 430-443 still instruct an adopter to run `qa:commerce`,
  `qa:commerce-dashboard`, `qa:commerce-pos`, `qa:property-booking` and
  `qa:property-booking-storefront`. Those scripts exist, but both modules are
  disabled by default in Kenfam, so every one of those commands fails against a
  default Kenfam boot. No `qa:travel-tours` line exists. This is closeout item 5
  of the foundation implementation record and is still open.
- `.docs/Kenfam/kenfam-deployment-guide.md` contains no verification section at
  all beyond `npm run build`, so a deployer has no gate list.

Prerequisite the plan already implies: a browser harness needs seeded data, and
there is no optional demonstration catalogue seeder. Six `.webp` files sit in
`Resources/demo/travel/` with nothing that consumes them. Commerce has
`commerce:demo-seed` with ten contexts; PropertyBooking has
`PropertyBookingDemoSeeder`. Until an opt-in TravelTours equivalent exists, QA
would have to hand-build a catalog, and the plan forbids reseeding an adopter's
database.

Also missing at the test tier: there is no `TravelToursDisabledModuleTest`, which
both reference modules have and which the verification plan §2 lists as a
required row. `phpunit.xml` pins `TRAVEL_TOURS_ENABLED=true`, so the disabled
path has never executed. TT-02 has no automated proof.

## 7. Corrections Needed To The Documentation Record

The documents are unusually honest, which makes the drift worth naming precisely.

| Document | Statement | Actual |
| --- | --- | --- |
| `travel-tours-implementation-ledger.md` | `TravelToursFoundationTest`: 6 tests, 1,598 assertions; `TravelToursServiceTest`: 4 tests, 29 assertions | 7 and 6 tests, 1,638 assertions combined |
| `travel-tours-implementation-ledger.md` | "Homepage/dashboard/TravelTours focused set exits zero with 1,731 assertions" | Default suite now fails, see C-01 |
| `travel-tours-foundation-implementation.md` | Same two test counts quoted under Verification Evidence | Stale |
| `travel-tours-foundation-audit.md` §Resolution Checkpoint | Lists nested queued dispatch and raw-models-to-documents as resolved in the K1 rewrite | Both still present, see C-04 and C-05 |
| `README.md` Verification | Lists five QA commands for disabled modules, none for TravelTours | See §6 |
| `.env.example` | Publishes 30 `TRAVEL_TOURS_*` variables | 12 change nothing, see C-07 |

None of these invalidate the K1 claim. The schema, model, factory and
transaction-slice evidence is real and re-verified above.

## 8. Recommended Execution Order

Grouped so that each group is independently committable with its own evidence,
in line with the plan's "focused commit, evidence and master status update" rule.

**Group A — restore a green gate before any new phase work.** C-01, then refresh
the ledger and foundation-implementation test counts. Nothing else should be
signed off while the default suite is red.

**Group B — small, silent, high-consequence corrections.** C-02 (config key
path, plus publish the three link variables in `.env.example`), C-03 (introduce
`Support/MoneyFormatter` and `Support/ScaledDecimal` mirroring the reference
modules, then route every render through it), C-05 (drop `ShouldQueue` from the
two listeners, add the enablement guard and `onQueue`), C-06 (either implement
the printer against a real `Printing/` driver contract or remove the binding
until it exists). Each needs one focused test; each test would have caught its
defect.

**Group C — close the K1 foundation properly.** C-07 (delete inert keys or move
them into a clearly labelled future section), C-08, C-10 (activity recording on
the five existing transactional services), the missing
`TravelToursDisabledModuleTest`, and the production-engine contention proof the
ledger already lists as the open K1 gate.

**Group D — decide the structure before K2 lands on top.** The §5 folder
divergences, the permission-catalogue granularity and policy scoping in C-09, and
C-04's document DTO layer. All four get materially more expensive once catalog
and scheduling administration exists.

**Group E — the QA and UAT spine.** An opt-in demonstration catalogue seeder,
then `scripts/qa-travel-tours-{storefront,admin,pob}.mjs`, the `package.json`
entries, `.docs/TravelTours/qa/` evidence folders, and the README verification
block. This unblocks every K4-K8 acceptance gate and is currently the module's
binding constraint on phase progression.

Group D is the one with a real sequencing deadline. Groups A and B are the
cheapest defect-to-effort ratio in the module.
