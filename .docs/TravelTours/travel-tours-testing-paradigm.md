# TravelTours Testing Paradigm: Parent-Module Adoption

Status: adoption standard, 2026-09-16. Derived from the Aureon reference
modules as they exist in `laravel-aureon`: Commerce (25 feature classes),
Property Booking (23 feature classes), the `scripts/qa-*.mjs` browser
harnesses, and the committed evidence under `.docs/PropertyBooking/qa/`. This
document states *how* TravelTours tests are shaped and evidenced. *What* each
phase must test is owned by `travel-tours-verification-plan.md` and the phase
plans; this document does not duplicate those matrices.

## 1. Principles Carried Over From The Parent

These are the parent's verification principles, adopted without weakening:

1. Every slice adds focused tests before it is committed. A slice is not
   complete because its files exist.
2. Every slice runs its focused class, the full host regression, Pint, the
   PHPDoc audit, the schema probe, and Blade compilation. Counts are quoted
   in the implementation record exactly as measured.
3. Temporary autoload probes live outside committed module code and are
   removed once their useful assertions become regression tests.
4. SQLite proves portable behaviour: schema execution, factories, casts,
   service invariants, authorization, rendering. It cannot prove row-level
   locking. Contention evidence belongs to the phase that owns the complete
   workflow being raced (K5 capacity, K6 money and drawer), on a real engine,
   against a database whose name announces it is disposable.
5. Browser QA runs against an isolated database seeded only with opt-in
   demonstration data. Verification never migrates, truncates, or reseeds a
   developer or adopter database.
6. The happy path rendering is not a gate. Authorization, rollback, privacy,
   disabled-module isolation, dark mode, responsive width, reduced motion, and
   accessible naming are part of every surface's acceptance.
7. Assert persisted state and rendered output, not merely that an exception
   type appeared.

## 2. Test Tiers

The parent organises feature tests into seven tiers. Each tier is one or more
classes with a single-sentence class docblock beginning "Verifies …".

| Tier | Parent classes | What the tier proves |
| --- | --- | --- |
| A. Foundation | `*SchemaTest`, `*ModelTest`, `*UlidTest`, `*FactoryTest`, `*DisabledModuleTest` | Provider registration; every documented column, comment, FK, index; casts, relations, hidden attributes, mass-assignment exclusions; ULID generation/immutability/binding; every factory persists a coherent graph; a disabled module registers no routes, views, policies, listeners, bindings |
| B. Authorization | `*AuthorizationTest` | Permissions join the host catalogue; system administrator passes through `Gate::before`; inactive user is denied; assigned scope cannot be crossed by substituting an identifier |
| C. Service | one class per service family: `*CatalogServiceTest`, `*RateCalculatorTest`, `*AllocationServiceTest`, `*OrderServiceTest`, `*PosTransactionServiceTest`, `GuestServiceTest` | Invariants, named exceptions, rollback leaves nothing, idempotent replay returns the original, exact integer money, deterministic ordering |
| D. Interface | `*AdminInterfaceTest`, `*PobInterfaceTest`, `*OperationsInterfaceTest`, `*MediaTest`, `*CustomerOrderAdminTest` | Each admin route enforces its read capability and renders the module-owned Livewire component; a view-only operator is forbidden at every mutation entry; CRUD succeeds through Forms and is asserted in the database; media honours MIME, size, count, order, and ownership |
| E. Public | `*StorefrontTest`, `*StorefrontAccessTest`, `*StorefrontOrderingTest`, `*StorefrontShareTest` | Public queries expose only published records; signed links require exact signatures and expire; document projections exclude internal fields; SEO and structured data render |
| F. Operational | `*OperationalNotificationTest`, `*DocumentAdapterTest`, `*DashboardTest`, `*DemoSeederTest`, `*ReportExportTest` | After-commit dispatch and rollback silence; recipients by permission; PDF parity portrait/landscape; dashboard aggregates and empty-safe UI; opt-in seeder idempotency and cross-table invariants |
| G. Browser UAT | `scripts/qa-<module>-<surface>.mjs` | Real Chromium renders each surface across widths, themes, and motion preference with zero runtime or network errors; evidence is committed |

Tiers A–F are PHPUnit feature tests under `tests/Feature/<Module>/`. Tier G is
a Node script with committed screenshots and diagnostics. A phase that adds a
surface adds the classes for the tiers that surface touches; it does not
extend one class indefinitely.

## 3. PHPUnit Shape Conventions

Observed in every parent class and adopted verbatim:

**File and class.** `tests/Feature/TravelTours/TravelTours<Surface>Test.php`,
`final class`, `use RefreshDatabase`, extends `Tests\TestCase`. A file-level
docblock states purpose; the class docblock is one sentence beginning
"Verifies" or "Prove". Every test method carries a one-line docblock stating
the invariant in plain language.

**Method names.** `test_<subject>_<invariant>` or
`test_<actor>_<can|cannot>_<outcome>`, for example
`test_view_only_operator_cannot_mutate_catalog_rates_or_availability` and
`test_signed_access_is_restricted_to_placed_web_bookings`.

**`setUp`.** Pins a deterministic `app.key`, enables the module, pins any
config the class depends on, and seeds the role catalogue through the access
seeder. Operators are created through private helpers such as
`operator(string $role)` and `userWith(string ...$permissions)` so every case
states its actor's capability explicitly.

**Fixtures.** Module factories only. A factory graph shares one tour, one
departure, one rate plan, one customer; tests never create unrelated parents
for a single aggregate.

**Livewire.** The canonical mutation test is:

```php
Livewire::actingAs($administrator)
    ->test(TourCategoryManager::class)
    ->call('openCreate')
    ->set('form.name', 'Escorted journeys')
    ->call('save')
    ->assertHasNoErrors();

$this->assertDatabaseHas('travel_tour_categories', ['slug' => 'escorted-journeys']);
```

and the canonical denial test is one line per manager:

```php
Livewire::actingAs($viewer)->test(TourCategoryManager::class)->call('openCreate')->assertForbidden();
```

Route rendering is proven with `actingAs()->get(route(...))->assertOk()->assertSeeLivewire(...)`
for the permitted actor and `->assertForbidden()` for an outsider.

**Media.** `Storage::fake('public')` plus `UploadedFile::fake()->image()`;
assert collection, count, order, custom properties, and that a foreign or
incomplete order is rejected.

**Side effects.** `Notification::fake()` proves dispatch and recipient scope;
integration cases resolve the real listener. After-commit behaviour is proven
by wrapping a failing transaction and asserting nothing was sent.

**Time and money.** `CarbonImmutable` and explicit instants for boundaries;
integer minor units in every assertion; never a float.

**Isolation.** The disabled-module class changes the process environment,
calls `refreshApplication()`, and asserts routes, view hints, bindings,
listeners, and migration paths are absent.

## 4. Browser UAT Harness Contract

The parent harness (`scripts/qa-property-booking-admin.mjs`) is the template.
Its contract:

**Runtime.** Chrome DevTools Protocol over a headless Chromium found on the
machine (Brave, then Edge). No npm browser dependency. A temporary profile
directory is created and removed. Environment: `AUREON_QA_URL`,
`AUREON_QA_EMAIL`, `AUREON_QA_PASSWORD`, `AUREON_QA_PORT`.

**Session.** Navigate to `/login`, submit the form, await load. Theme is set
by writing the dashboard settings key in `localStorage` before navigation;
reduced motion by `Emulation.setEmulatedMedia`. Viewport by
`Emulation.setDeviceMetricsOverride` with touch emulation on mobile.

**Per capture.** Navigate, await `document.fonts.ready` and the
`aureon:page-loader-dismissed` event, then evaluate one snapshot object and
assert on it before writing the PNG. The standard snapshot fields are:

```text
path, title, viewportWidth, documentWidth, rootWidth, rootScrollWidth,
shellOverlap, sidebarRight, contentLeft, rootLeft, headerProbeStack,
theme, loaderHidden, panels, cssLoaded, cardColor, cardBackground,
unlabeledButtons, duplicateIds, overflowText, reducedMotion
```

The standard assertions: correct route; title present; document width equals
viewport width (no horizontal overflow); module root does not overflow its
workspace; sidebar does not overlap the shell and leaves a desktop gutter;
sidebar does not occlude the heading; expected theme applied; loader settled;
module stylesheet loaded; card text distinguishable from its surface; zero
unlabeled visible buttons; zero duplicate IDs; zero overflowing text; reduced
motion honoured when requested.

**Capture matrix.** Desktop 1440×1000 and 1280×900, tablet 820×1080, mobile
390×844, plus 320 px narrow on public surfaces. Light and dark are both
exercised; at least one capture per surface runs with reduced motion.

**Interaction probes.** Open a manager's edit dialog and assert
`.modal.show[aria-modal="true"]` lies within the viewport; open a media
section and assert it does not overflow and retains the active theme.

**Error collection.** `Runtime.exceptionThrown`, `Log.entryAdded` at error
level, and `Network.responseReceived` with status ≥ 400 are accumulated for
the whole run and must be empty.

**Output.** `.docs/<Module>/qa/<surface>/<name>.png` and
`.docs/<Module>/qa/<surface>/diagnostics.json` shaped as
`{ siteUrl, diagnostics, runtimeErrors, networkErrors, failures }`. Capture
names follow `<viewport>-<mode>-<page>[-reduced-motion]`. The script throws
when `failures` is non-empty, so a red harness cannot produce green evidence.

**Registration.** `package.json` gains `qa:<module>-<surface>`, and the README
verification block lists only scripts that exist.

## 5. Evidence And Documentation Contract

- The phase implementation record quotes each gate command and its measured
  result; counts are not rounded or carried forward.
- The implementation ledger gains one row per accepted slice naming the test
  classes and harness that prove it.
- The README quality rows and verification block reference only commands that
  run against the default Kenfam boot; reference-module harnesses are called
  out as requiring their module flag.
- Browser evidence is committed with the slice that produced it; a harness
  without committed diagnostics is not evidence.

## 6. TravelTours Adoption Map

State at commit `23f6855` on `feature/travel-tours-k2-catalog-administration`.

| Tier | Present | Gap and owning increment |
| --- | --- | --- |
| A. Foundation | `TravelToursFoundationTest` (7: schema, comments, relations, casts, privacy, factories, seeders), `TravelToursDisabledModuleTest` (1), `TravelToursFoundationCloseoutTest` (1) | ULID and factory coverage is folded into the foundation class rather than split; acceptable while the module is small. The disabled-module case should also assert listener and migration-path absence, matching the parent. |
| B. Authorization | `TravelToursCatalogAccessTest` (5) | Covers catalog roles, policy registration, publish/unpublish, query authorization. Each later phase adds its own role-matrix cases for the capabilities it introduces. |
| C. Service | `TravelToursServiceTest` (6: quote, hold, placement, payment, inquiry), category/destination/media service cases inside `TravelToursCatalogManagementTest` | As K2C–K2E add tour, assignment, itinerary, content, and publication services, split by family as the parent does; do not grow one management class past a single surface. |
| D. Interface | `TravelToursCatalogManagementTest` (4: two managers render, save, and forbid viewers) | K2C must add the route-renders-component case (`assertSeeLivewire`), locked-ID tampering, and bounded search/filter/pagination. Every new manager gets the one-line viewer denial. |
| E. Public | `TravelToursStorefrontTest` (5: demo catalogue, money formatting, signed-link lifetimes, disabled shell) | Document-projection exclusion case arrives with the K7 DTO work; K2E adds the draft/archived-hidden and draft-preview-authorized cases. |
| F. Operational | none | K7 by design. |
| G. Browser UAT | `scripts/qa-travel-tours-storefront.mjs`, `.docs/TravelTours/qa/storefront/` (11 inspections, 7 captures) | `scripts/qa-travel-tours-admin.mjs` with `.docs/TravelTours/qa/admin/` and `package.json` `qa:travel-tours-admin` are K2F must-haves. The admin harness adds the authenticated login step and the sidebar-gutter, modal, and media probes the storefront harness does not need. |

Two conventions the storefront harness already honours and the admin harness
must keep: the 320 px narrow capture, and the `documentWidth === viewportWidth`
overflow assertion on every capture.

## 7. Minimum K2 Test Set

The classes K2C–K2F must add to reach parent parity for the catalog surface:

| Increment | Class or script | Must cover |
| --- | --- | --- |
| K2C | `TravelToursTourEditorTest` | Route renders `TourCatalog`; each editor tab action authorizes; assignment IDs from another tour are rejected; `#[Locked]` ID substitution is forbidden; search wildcard treatment; sort and page bounds |
| K2D | `TravelToursTourContentTest` | Day and activity ordering unique per tour; content, FAQ, extra ordering deterministic; cross-tour child ownership rejected; extras persist exact minor units and ISO currency |
| K2E | `TravelToursPublicationTest` | Every readiness blocker produces `PublicationBlocked` with its reason; scheduled `published_at` boundary; draft preview requires authorization and never widens public scope; public queries exclude draft, review, archived; archiving retains booking relations |
| K2F | `scripts/qa-travel-tours-admin.mjs` | Catalog overview, category manager, destination manager, and each editor tab at desktop, tablet, and mobile in light and dark; one reduced-motion capture; one modal probe; one media probe; zero runtime and network errors; committed diagnostics |

Each class follows section 3; the harness follows section 4; the K2
implementation record quotes the results as section 5 requires.
