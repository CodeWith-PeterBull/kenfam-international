# TravelTours Verification And Release Gates

Status: planned evidence catalogue. Check the implementation ledger for commands
actually executed. Existing scaffold test results are not module test evidence.

## 1. Environments And Safety

Unit tests: pure calculation/value objects, explicit clocks and no database.
Feature tests: disposable SQLite with foreign keys, APP_ENV=testing, array cache/
session, fake mail/events as appropriate. Never inherit an adopter's .env database.
MySQL integration: dedicated empty test database and independent connections for
row-lock/unique-index/race behavior. SQLite cannot establish those guarantees.
Browser QA: isolated local seeded database and test identities, not production.

K2C evidence is recorded in `travel-tours-k2-catalog-administration-
implementation.md`: eight focused tests, 37 TravelTours tests, 160 full-suite
tests, and seven authenticated admin browser captures. K2D evidence is recorded
in `travel-tours-k2d-itinerary-content-implementation.md`: typed child-service
and Livewire tests plus 11 authenticated captures including both new tabs.
These are increment gates, not release acceptance of publication or booking.

Lock versions to composer.lock/package-lock. Capture PHP/framework/Livewire and
database versions. Local PHP currently emits an Imagick/ImageMagick version
mismatch warning; image conversion QA must address that environment separately.

## 2. Foundation Test Matrix

| Suite | Assertions |
| --- | --- |
| Source/registry | 34 expected models/tables, namespaces and no forbidden module dependency |
| Provider enabled | Config, migration paths, accepted bindings, view namespace and intended routes |
| Provider disabled | Fresh application has no module routes/listeners/migrations/commands/aliases; no seed side effects |
| Fresh schema | Every documented field/type/default/nullability; complete FK graph and index names |
| Migration comments | Inspect Blueprint column metadata; every domain/identity/actor/timestamp has a real comment |
| MySQL DDL | Comment persistence, FK/index name limits and unsigned/unique behavior |
| Rollback/remigrate | Reverse dependency order drops only owned tables and recreates correctly |
| ULIDs | Generated, valid, unique, immutable; external binding uses identifier without granting access |
| Casts/privacy | Enums, integers, immutable dates, JSON; encrypted DB values and hidden serialization |
| Relationships | Persist coherent related rows and exercise every relation including inverse/custom pivot |
| Factories | Every model creates valid records; graph uses same tour/rate/departure/customer |
| Retention | Ordinary delete prohibited for history/finance, including administrator code paths |
| Archival | Soft-deleted catalog excluded publicly but historic bookings still render |
| PHP standards | Strict types, meaningful file/class/method PHPDoc, Pint and syntax |
| Role catalogue | Enabled permissions only; access seed idempotent; no default demo users |

Blueprint metadata testing must fail when comment() is attached after
constrained(). A source search for the word comment is insufficient.

## 3. Catalog/Scheduling/Pricing

CRUD authorization on route and Livewire actions; locked ID tampering;
same-parent child/media validation; hierarchy cycles; primary/default uniqueness;
publication time; deleted/inactive parents; search SQL wildcard treatment and
bounded sorts/pages; coordinate/timezone validation; same-tour rate ownership.

Quote datasets: adult/child/infant alone and mixed; zero/negative/excess counts;
age boundary on departure date; overlapping windows; no matching rate; private/
inactive plan; currency mismatch; seasonal/group/early stages; stack/non-stack
priority ties; fixed versus percentage versus override; promo exclusions,
start/end boundaries, minimums, usage limits and stale rates.

Exact-money fixtures include inclusive/exclusive tax, discounts, extras,
rounding residuals, deposit limits and instalment sums. Test overflow and zero
payable explicitly. Quote uses injected instant, not incidental now() calls.

## 4. Booking And Concurrency

At least two independent connections compete for final seats; exactly one
successful commitment when only one fits. Test hold versus hold, hold versus
direct booking, booking versus capacity edit, duplicate hold consume, expiry
versus placement and promotion final-use contention. Use a synchronization
barrier and assert persisted totals after both transactions finish.

Hold ownership/session spoofing, lifetime/renewal bounds, expired cleanup lag,
stale quote repricing, invalid participant snapshot, client total injection,
foreign tour extra, same key/same payload replay and changed-payload conflict.

Instant/approval paths; pending approval expiry; paid pending review; cancellation
capacity release once; history timestamps and transitions; no after-commit event
on rollback. Signed pages: expired/tampered/missing signatures, changed resource,
minimal fields, noindex/no-store and no sensitive exports through customer URLs.

## 5. Identity, Payments And Desk

Anonymous contact matching cannot overwrite existing verified profiles or select
saved travelers. Missing identity cannot select the first customer row.
Encrypted database values and model serialization checks cover identity, medical/
accessibility, private notes and hashes. Private media returns 403/404 outside
authorized scope and is absent from public storage copy.

Payment: pending/confirmed/failure, same/changed replay, foreign schedule/shift,
currency mismatch, overpayment, partial allocation, provider reference collision,
successful/failed refund, over-refund and cash source movement uniqueness.

Shift: cashier/admin ownership, inactive register, one-open-register/operator
race, split tender, non-cash exclusion from drawer, cash-in/out reason, closed
shift refusal, recomputed expected/actual/variance and supervisor reconciliation.
Print failure must not duplicate or roll back an already committed booking.

## 6. Events, Mail And Documents

Each event/listener/notification pair has focused tests. Fake events only when
testing dispatch; run real listener resolution for integration tests.
Assert after-commit and rollback behavior, recipient scope, retries, duplicate
delivery handling, disabled-module jobs, preferences and failure logs.

Mailpit exercise uses isolated fixtures and both sync and actual queue worker.
Record correlation ID, event, recipient and inbox result for each notification.
No fake delivery claim from Notification::fake alone.

PDF: shared institution details, two-way layout/template data, portrait/landscape
parity, text extraction, safe filename, currencies, snapshots and private-field
absence. Receipt: browser/PDF/58/80 mm, dark-mode printing, manual/auto-prompt,
reprint identity and driver failure without sale duplication.

## 7. Browser, Accessibility And SEO

Desktop 1440x900, tablet 820x1180 and mobile 390x844, plus narrow 320 px overflow
check. Light/dark/system/reduced-motion. Public discovery, tour detail/gallery,
itinerary accordion, inquiry, party form, checkout and signed confirmation;
admin CRUD, dashboard, POB, shift close and receipts.

Keyboard-only navigation, focus visibility/return, labels, errors/live regions,
modal trapping/Escape, no overlapping text, no color-only status, bounded loading
controls and image alt text. Capture screenshots plus console/network failures.
Automated accessibility scan supplements, not replaces, manual task completion.

SEO: unique title/description/canonical, OG/Twitter image, favicon, crawlable public
pagination, breadcrumb and accurate structured data, sitemap published resources
only, robots noindex for private/auth pages. No fabricated offers/reviews/ratings.
CSP and static MIME types checked on staging after build upload.

## 8. Execution Commands And Evidence

From the client app root, after isolated testing configuration:
```powershell
php artisan --version
php artisan test --filter=TravelTours
php vendor/bin/pint --test app/Modules/TravelTours tests/Feature/TravelTours
php artisan route:list --name=travel-tours
npm ci
npm run build
```

Only run existing QA scripts; never add a README command pointing to a missing
script. Each temporary autoload probe must bootstrap the actual provider with
an in-memory connection, return pass/fail evidence and avoid real data mutation.
Remove probes only after their useful assertions become regression tests.

Release gate: all focused tests, full host regression, MySQL migration/contention,
browser/accessibility, theme/print, queue delivery and deployment smoke checks.
Record failures and unavailable environments explicitly. No phase marked
complete because its budget is exhausted or its source files exist.
