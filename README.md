# Kenfam International

Independent client application built from the Aureon CMS scaffold. The reusable
travel capability belongs to `App\Modules\TravelTours`; company identity belongs
to the host institution and branding configuration.

## Current Adoption Status

- Clean independent source baseline: `f4139b7`, imported from Aureon subtree
  commit `c7aaddbb40e85dd1796576ce350c8a4a97af0189`.
- Working phase branch: `feature/travel-tours-k2-itinerary-content`.
- Commerce and Property Booking are retained as implementation references and
  disabled by default. Their inherited descriptions below do not mean those
  modules are enabled for Kenfam.
- TravelTours K1 is reconciled and closed at foundation scope: 34 models and
  tables, 34 factories, typed foundational services, private signed responses,
  module isolation, fixtures, and the accepted storefront foundation. Later
  concurrency and operational controls are assigned to their owning phases.
- The locally accepted public foundation now includes the responsive Kenfam
  homepage, tour catalogue/filtering, tour details, inquiries, signed booking
  presentation, exact money formatting, opt-in demo catalogue/operators, and
  executable storefront browser QA. This is not acceptance of K2-K7 operations.
- The independent private origin and foundation branch are published.
- K2 catalog administration is active. K2A establishes distinct publication
  authority, context-owned policies, typed catalog inputs, and scoped staff
  queries. K2B adds operational category/destination workspaces, hierarchy and
  geography rules, and owned accessible destination media. K2C tour catalog,
  base editor, route assignments, and browser QA are committed as `38f0710`.
  K2D itinerary, activity, experience, FAQ and exact-money extra editing is
  implemented and awaits review before commit. Publication remains K2E.

Start with [the TravelTours master plan](.docs/TravelTours/travel-tours-module-plan.md),
[foundation audit](.docs/TravelTours/travel-tours-foundation-audit.md), and
[foundation implementation](.docs/TravelTours/travel-tours-foundation-implementation.md),
then read the [storefront foundation](.docs/TravelTours/travel-tours-storefront-foundation.md)
and [review remediation status](.docs/TravelTours/claude-review/remediation-status.md),
then use the [implementation ledger](.docs/TravelTours/travel-tours-implementation-ledger.md)
for current acceptance evidence. Read the [K1 reconciliation](.docs/TravelTours/travel-tours-k1-reconciliation.md)
for delivery boundaries; the immediate next-phase contract is the
[K2 catalog administration plan](.docs/TravelTours/travel-tours-k2-catalog-administration-plan.md).
Repository provenance is recorded in the
[scaffold initialization record](.docs/Kenfam/kenfam-scaffold-initialization.md).
Client-specific documentation lives under [.docs/Kenfam](.docs/Kenfam).

## Inherited Aureon Scaffold

Laravel Aureon is the reusable Laravel 12 engine for the Aureon corporate template and future branded adopting sites. It combines the Bootstrap 5 Aureon public-template assets with a DreamPOS-derived administration dashboard architecture benchmarked against the CSK backend.

## Current foundation

- Laravel 12.64 on PHP 8.2.
- Breeze Blade authentication and profile flows.
- Livewire 4, Spatie Permission, Spatie Media Library, Honeypot, DOMPDF, Intervention Image, and Log Viewer.
- Vite 7 with static-copy targets for the dashboard and Aureon public assets.
- Complete benchmark dashboard resources under `resources/css`, `scss`, `fonts`, `img`, `js`, and `plugins`.
- Complete Aureon frontend asset kit under `resources/aureon/assets`.
- Responsive Aureon-branded dashboard shell with persisted mode, layout, width, palette, sidebar surface, and background controls.
- Frontend-aligned progressive loader with a three-second minimum hold, theme-aware surface, reduced-motion bypass, and fail-safe.
- Protected dashboard index and reusable blank page.
- Seeded `system-admin`, `content-manager`, `editor`, and `viewer` roles.
- Role-routed administration, content-manager, editor, and viewer dashboards with protected sample workspaces and role-owned navigation shells.
- Illustrated Aureon error pages for 401, 403, 404, 419, 500, and 503 responses, including guarded 419 session recovery.
- Structured, permission-protected system activity trail with a reusable recorder contract.
- Permission-protected application log viewer with separately controlled read and destructive actions.
- Database-driven Institution Details with typed fallback resolution, validated brand media, and an authorized Livewire editor.
- Institution-aware HTML/text notification shell with protected preview and throttled test delivery.
- Reusable two-pass A4 PDF renderer with explicit portrait/landscape layouts and page totals.
- Aureon-branded authentication screens on a lean full-page shell (login, registration, password flows, email verification, password confirmation).
- Email-OTP two-factor authentication with enabled-by-default managed accounts, administrator and self-service controls, hashed-at-rest codes, audited rate limiting, and scheduled pruning.
- Per-user last-login timestamp stamped at completed sign-in (after the OTP challenge for two-factor accounts).
- One-to-one corporate user profiles with enum-backed identification, profile-photo media, and self-service profile editing.
- Permission-protected Livewire user directory with transactional account/profile/access CRUD, status controls, filters, and audit events.
- Permission-protected role composer with code-owned capability catalogue, custom-role CRUD, and protected base-role invariants.
- Self-contained Commerce module foundation with configuration, separate route surfaces, typed catalog/inventory/order/POS models, selective ULIDs, and a fully documented shared schema.
- Policy-protected Commerce transaction services with deterministic pricing, locked inventory, web ordering, split payments, POS checkout, held-sale repricing, and till reconciliation.
- Permission-protected Commerce catalog and inventory workspaces with Livewire product/category management, exact price input, gallery media, lifecycle actions, controlled stock adjustments, and immutable movement history.
- Auto-generated internal Code 128 barcodes with an optional manufacturer barcode matched on POS scan, plus an independent A4 label-print workspace rendering scannable labels through the shared PDF engine.
- Public Aureon Commerce shell with responsive headers, theme controls, a URL-synchronized Livewire catalog, published-only slug product pages, exact money presentation, and an optional ten-product demonstration dataset.
- Session-backed server-repriced cart and Livewire checkout with conservative reusable-customer matching, immutable order snapshots, pickup/delivery, manual payment preferences, queued confirmation mail, temporary signed confirmation/tracking/PDF access, and responsive dark-mode presentation.
- Permission-protected Commerce customer and order workspaces with reversible customer archival, fulfillment transitions, payment confirmation, cancellation/restock, and audited portrait/landscape order documents.
- Full-width Commerce POS terminal with register/till administration, cashier-owned sessions, barcode/SKU/name lookup, server-repriced cart, customer selection, hold/resume/discard, exact split tender, cash change, protected browser/PDF receipts, and configurable actor-scoped sales history.
- Permission-scoped Commerce administration dashboard with real cross-channel sales trends, fulfillment queues, stock exceptions, recent orders, active tills, and authorized operational links.
- Privacy-safe Commerce order/receipt document adapters with institutional branding, orientation enforcement, stream/download parity, and register-owned 58/80 mm browser print profiles.
- Independent Property Booking module with a runtime switch, 20 fully documented tables, typed accommodation/rate/guest/booking/POB models, property-scoped authorization, and opt-in demonstration fixtures across every model. It now covers media-rich catalog and availability administration, the public `/stays` booking flow, configurable protected guest ID/passport capture, immediate POB walk-ins, full-width receptionist shift/tender/receipt controls, booking and guest operations, check-in/out and readiness workflows, a real Chart.js operations overview, and dedicated queued customer/staff notifications.

## CMS baseline feature ledger

Use this table as the implementation source of truth for the reusable CMS engine:

- `[x] Complete`: usable, integrated, and covered by the current verification baseline.
- `[-] Foundation`: dependency, schema hook, asset, scaffold, or sample exists, but the capability is not complete.
- `[ ] Planned`: no reusable implementation should be assumed yet.

Update a row in the same change that advances its implementation. Add new rows when a reusable capability enters scope, and only mark `[x]` after its user-facing workflow and authorization boundary are verified.

| Domain | Base feature | Status | Current boundary |
| --- | --- | --- | --- |
| Platform | Laravel 12 runtime and benchmark package baseline | `[x]` | Installed and validated on PHP 8.2 |
| Platform | Database, migrations, local seed, storage link, and media schema | `[x]` | SQLite local baseline is operational |
| Platform | Vite dashboard build and static-copy public asset pipelines | `[x]` | Dashboard and Aureon assets publish separately |
| Dashboard | Shared header, sidebar, footer, content, and script partials | `[x]` | Reused by dashboard and blank workspace |
| Dashboard | Administration overview and module-ready blank page | `[x]` | Uses controller-owned sample view models |
| Dashboard | Role-specific landing workspaces and navigation shells | `[x]` | Enum-backed redirect registry, protected routes, controllers, topbars, sidebars, and working sample pages cover all four base roles |
| Dashboard | Responsive desktop, compact, detached, boxed, and mobile layouts | `[x]` | Persisted through the theme controller |
| Dashboard | Light, dark, system, palette, custom color, and sidebar settings | `[x]` | Header and offcanvas controls stay synchronized |
| Dashboard | Progressive, theme-aware Aureon page loader | `[x]` | Matches the active frontend loader lifecycle |
| Frontend | Centralized Aureon public assets | `[-]` | Assets are available under `resources/aureon` |
| Frontend | Public Blade master layout and shared components | `[ ]` | Convert from `custom-corporate-template` |
| Frontend | Corporate base pages and page-family renderers | `[ ]` | Preserve modular Blade ownership |
| Authentication | Breeze login, registration, password, and email verification | `[x]` | Rebranded onto the Aureon full-page shell; registration assigns the `viewer` role; completed sign-in stamps `users.last_login_at` |
| Authentication | Authenticated profile management | `[x]` | Username, email, personal/contact profile, Spatie profile photo, password, 2FA, and guarded deletion |
| Authentication | Two-factor authentication | `[x]` | Email OTP: env-gated, self-service opt-in, hashed codes, audited rate limits; remember-device is `[-]` foundation |
| Authentication | Branded HTTP error and expired-session recovery pages | `[x]` | Six illustrated states use stable replaceable assets; 419 responses prevent cache reuse and recover a fresh login form |
| Users and access | `HasRoles`, middleware aliases, and four base roles | `[x]` | Seeder is idempotent |
| Users and access | User management interface | `[x]` | Authorized Livewire CRUD, filters, pagination, profile media, account status, managed 2FA preference, role sync, safeguards, and audit |
| Users and access | Role and permission management interface | `[x]` | Role CRUD and code-owned permission composition; base roles and assigned-role deletion are protected |
| Users and access | Route, policy, and action-level permission enforcement | `[-]` | User/access, activity, logs, Institution Details, communications, Commerce, and Property Booking foundation boundaries are enforced; extend per module |
| Content | Posts and editorial workflow | `[ ]` | Dashboard metric is sample data only |
| Content | Managed pages and reusable blocks | `[ ]` | Dashboard metric is sample data only |
| Content | Menus and navigation management | `[ ]` | Planned after public Blade shell |
| Content | Events and calendar management | `[ ]` | Dashboard list is sample data only |
| Content | Team and profile management | `[ ]` | Dashboard metric is sample data only |
| Content | Inquiries and form submissions | `[ ]` | Dashboard metric is sample data only |
| Content | Expenditure and approvals | `[ ]` | Dashboard panels are sample data only |
| Media | Spatie Media Library integration | `[-]` | Institution logos, user profile photos, product/category images, and accessible property/unit galleries are complete; general media management UI is pending |
| Discovery | SEO, metadata, redirects, and sitemap management | `[ ]` | Planned with public page modules |
| Commerce | Modular package, configuration, and shared persistence | `[x]` | Provider, three route surfaces, 11 migrations with comments, models/factories, selective ULIDs, typed failures, policies, transactional services, ten optional contextual demonstration catalogs, web/admin workflows, and integration tests are operational |
| Commerce | Catalog and inventory administration | `[x]` | Authorized responsive Livewire product/category management, exact pricing, gallery/category media, publication lifecycle, stock projections, adjustments, immutable history, permissions, activity, and QA |
| Commerce | Storefront catalog and self-ordering | `[x]` | Responsive shell, catalog/detail, ID-and-quantity session cart, server-repriced Livewire checkout, reusable customer resolution, immutable snapshots, pickup/delivery, manual payment preference, queued mail, signed confirmation/tracking/PDF, and multi-viewport dark-mode QA |
| Commerce | Customer and order administration | `[x]` | Authorized customer CRUD/archive/restore, order filters/details, fulfillment transitions, payment confirmation, unpaid cancellation/restock, activity records, and portrait/landscape downloads |
| Commerce | Point-of-sale terminal and till operations | `[x]` | Register/till administration, cashier-owned full-width terminal, lookup/cart/customer/hold workflows, configurable add-to-cart confirmation beep, exact split tender, atomic stock, reconciliation, protected browser/PDF receipts, active/all-session cashier history, tests, and responsive dark-mode QA |
| Commerce | Administration overview and operational indicators | `[x]` | Dedicated permission, real integer-minor-unit aggregates, 7/30/90-day channel trends, order queue, stock exceptions, active tills, authorized deep links, module-disable boundary, and responsive dark-mode QA |
| Commerce | Document adapters and receipt printing | `[x]` | Immutable privacy-filtered order/receipt projections, institutional PDF layouts, portrait/landscape rules, safe filenames, register print settings, configurable browser driver, 58/80 mm roll layouts, manual printing, and post-sale auto prompt |
| Commerce | Filtered PDF register reports | `[x]` | Product catalogue, order register, and stock level/movement PDF exports reusing the shared report engine, driven by the admin list filters, Livewire streamed downloads with loading states, selling-price-only projections, 2,000-row synchronous cap, permission gating, activity records, and tests |
| Commerce | Operational events and notifications | `[x]` | Seven after-commit ULID/scalar events, dedicated queued messages, explicit permission recipients, immutable customer snapshot routing, stock-transition deduplication, configurable till materiality, and stale-state delivery guards |
| Commerce | Contextual demonstration-data launcher | `[x]` | Dedicated permission, ten visual merchant-context cards, keep/archive modes, explicit archive confirmation, serialized Livewire execution of the canonical Artisan command, current-data totals, output feedback, and activity records |
| Property booking | Foundation, catalog, availability, and public booking | `[x]` | Runtime-gated provider, 20 commented tables, typed models/factories, ULIDs, guest privacy, scoped policies, opt-in seeders, responsive Livewire administration, ordered media, integer pricing, exact-unit allocation, independent `/stays` shell, atomic guest checkout, signed pages, and booking PDFs |
| Property booking | Point of Booking and reception shifts | `[x]` | Register/shift administration, receptionist-owned full-width terminal, quote and guest lookup, hold/resume, exact split tender, reconciliation, institutional browser/PDF receipts, and configurable 58/80 mm printing |
| Property booking | Booking operations, readiness, overview, and alerts | `[x]` | Property-scoped booking/guest workspaces, lifecycle and unit-move services, administration payments, housekeeping transitions, real dashboard aggregates and Chart.js trends, plus after-commit customer/staff notifications |
| Property booking | Reports and adoption readiness | `[ ]` | Phase 6 report set, adopter/operations/extension guides, deployment matrix, and final screenshot catalogue |
| Operations | Institution Details and base organization configuration | `[x]` | Singleton profile, typed resolver, logo media, Livewire editor, permissions, audit, and tests |
| Operations | Broader application and tenant/site settings | `[ ]` | Extend separately from presentation-safe Institution Details |
| Operations | Structured system activity and audit trail | `[x]` | Injectable recorder, sanitized context, explorer, permissions, and tests |
| Operations | Protected application log viewer | `[x]` | Read/download and destructive actions use separate permissions |
| Operations | Shared mail notification presentation | `[x]` | Institution-aware HTML/text Markdown shell, preview, throttled test send, and audit |
| Operations | Notification delivery preferences | `[ ]` | User/channel preferences remain a later module |
| Operations | Portrait/landscape PDF report foundation | `[x]` | Typed context, two-pass page totals, safe local branding, previews, and downloads |
| Operations | Queue, scheduler, and recurring task visibility | `[ ]` | Infrastructure workflow pending |
| Operations | Backup, deployment, and health administration | `[ ]` | Deployment baseline pending |
| Quality | Laravel feature and unit test baseline | `[x]` | `338` tests and `5,590` assertions passed in the latest full regression |
| Quality | Authenticated and public responsive Chromium QA | `[x]` | Dashboard, activity explorer, configuration pages, auth screens, 2FA challenge, contextual Commerce catalogs and demo-data launcher, product sharing and SEO, product gallery and lightbox, Commerce overview, POS terminal, receipt, print media, Property Booking administration, and Property Booking storefront harnesses |
| Quality | Engineering plans, handoffs, and implementation records | `[x]` | Maintained under `.docs/dev/` and dedicated `.docs/<ModuleName>/` folders |

## Source benchmarks

- Backend/dashboard: `C:\Users\Peter Maina\Desktop\projects\CSK\backend`
- Public frontend: `../custom-corporate-template/`

The benchmark projects remain references. New Laravel behavior belongs in this folder.

## Key paths

```text
app/Http/Controllers/DashboardController.php Neutral role-dashboard redirect fallback
app/Http/Controllers/{Admin,ContentManager,Editor,Viewer}/DashboardController.php Role workspace controllers
app/Http/Middleware/RedirectToRoleDashboard.php Canonical authenticated landing redirect
app/Support/DashboardRegistry.php             User type to route/shell registry
app/Contracts/RecordsSystemActivity.php    Cross-module activity write contract
app/Contracts/ResolvesInstitutionProfile.php Typed organization presentation contract
app/Contracts/RendersPdfReports.php         Shared PDF render/stream/download contract
app/Services/InstitutionDetailsService.php  Transactional profile/media mutation service
app/Services/PdfReportService.php           Two-pass portrait/landscape PDF engine
app/Services/SystemActivityService.php     Sanitized structured activity recorder
app/Services/TwoFactorService.php          Email-OTP two-factor engine
app/Services/UserManagementService.php     Transactional account/profile/media/access mutations
app/Services/RoleManagementService.php     Protected role and permission composition
app/Modules/Commerce/                      Self-contained ecommerce and POS module
app/Modules/Commerce/Routes/               Separate admin, POS, and storefront route surfaces
app/Modules/Commerce/Database/             Module-owned migrations, factories, and optional demo seeders
app/Modules/Commerce/{Catalog,Inventory,Customers,Orders}/Livewire/ Module-owned forms and admin components
app/Modules/Commerce/Storefront/           Public catalog, cart, checkout, and signed-order components/services
app/Modules/Commerce/PointOfSale/           Register, till, terminal, receipt, and transaction orchestration
app/Modules/Commerce/PointOfSale/Printing/  Config-resolved receipt printer driver contract and browser implementation
app/Modules/Commerce/Reporting/             Immutable Commerce dashboard projections and aggregate queries
app/Modules/Commerce/Notifications/         Dedicated queued mail, listeners, recipient resolution, and safe delivery
app/Modules/Commerce/DemoData/              Guarded Livewire launcher for contextual demonstration fixtures
app/Modules/Commerce/Resources/            Namespaced admin/storefront/POS views, assets, reports, and demo media
app/Modules/PropertyBooking/                Independent accommodation booking and operations module
app/Modules/PropertyBooking/Database/       Module migrations, factories, and opt-in demonstration seeders
app/Modules/PropertyBooking/{Catalog,Pricing,Availability,Guests,Bookings}/ Typed foundation aggregates and services
app/Modules/PropertyBooking/PointOfBooking/ Reception register and shift foundation
app/Livewire/Admin/UserManagement.php      Authorized user directory and CRUD workflow
app/Livewire/Admin/RolesAndPermissionsManager.php Authorized role composition workflow
app/Livewire/Profile/TwoFactorSettings.php Self-service 2FA security panel
config/institution.php                     Serializable Institution Details fallbacks
config/two-factor.php                      Two-factor configuration contract
resources/aureon/assets/                  Aureon public assets
resources/css|scss|fonts|img|js|plugins/ DreamPOS dashboard resources
resources/views/layouts/                 Shared dashboard Blade shell
resources/views/dashboards/              Dashboard index and blank page
resources/views/errors/                  Laravel exception entry views
resources/views/components/error-page.blade.php Shared resilient error presentation
resources/img/server-status/             Replaceable exception artwork contract
config/aureon.php                        Reusable Aureon runtime settings
database/seeders/RoleSeeder.php           Base role contract
scripts/qa-dashboard.mjs                  Authenticated responsive browser QA
scripts/qa-auth.mjs                       Auth-screen and 2FA challenge browser QA
scripts/qa-commerce-dashboard.mjs         Commerce overview responsive, chart, theme, and accessibility QA
scripts/qa-commerce-demo-data.mjs         Context selector, archive warning, media, responsive, and theme QA
scripts/qa-commerce-pos.mjs               Authenticated terminal, receipt, responsive, dark, and print QA
.docs/<ModuleName>/                       Module-specific plans and implementation records
.docs/dev/                                Engineering context and handoffs
```

Vite publishes content-hashed compiled dashboard and module entries plus benchmark resources under `public/build`. It publishes the Aureon asset contract under `public/aureon/assets`. Both public outputs are generated and ignored by Git; source assets are committed under `resources`. Always deploy `public/build/manifest.json` with the exact generated files it references so CDN-cached releases cannot mix asset versions.

## Setup

```powershell
composer install
npm.cmd install
Copy-Item .env.example .env
php artisan key:generate
New-Item -ItemType File -Force database\database.sqlite | Out-Null
php artisan migrate --seed
php artisan storage:link
npm.cmd run build
```

The local development seed creates one account per base dashboard:

| Workspace | Email |
| --- | --- |
| Administration | `admin@aureon.test` |
| Content management | `content@aureon.test` |
| Editorial | `editor@aureon.test` |
| Viewer | `viewer@aureon.test` |

All four local accounts use the password `password`.

These credentials are for local development only and must not be deployed.

The Commerce demonstration graph is opt-in and intentionally excluded from
`DatabaseSeeder`. The no-argument command preserves the original mixed catalog:

```powershell
php artisan commerce:demo-seed
```

Pass one of the allowlisted merchant contexts to build a focused catalog:

```powershell
php artisan commerce:demo-seed computers-it
php artisan commerce:demo-seed hardware-construction
php artisan commerce:demo-seed boutique-fashion
php artisan commerce:demo-seed pharmacy-health
php artisan commerce:demo-seed supermarket-fmcg
php artisan commerce:demo-seed beauty-personal-care
php artisan commerce:demo-seed automotive-parts
php artisan commerce:demo-seed agrovet-farm
php artisan commerce:demo-seed office-bookshop
```

Existing products are kept by default. For a controlled demonstration database,
`--archive-existing` archives every current product before the selected context
is upserted and published. It never deletes product, stock, movement, media, or
transaction history:

```powershell
php artisan commerce:demo-seed computers-it --archive-existing
```

The contextual seeder is idempotent and provides 10-12 products, stock and
opening movements, two gallery sources per new contextual product, customers,
registers, a reconciled till, and representative web/POS orders. Each new
contextual product has a lightweight generated featured image and a branded
secondary gallery fallback. Seeder reruns compare source and stored media
checksums, so approved image replacements propagate without duplicating media.
Fixture prices, tax behavior, medicine/farm labels, and stock are demonstration
values that adopters must review.

Authorized administrators may run the same fixed command contract from
`/admin/commerce/demo-data`. The visual launcher requires the dedicated
`manage-commerce-demo-data` permission, accepts only the ten allowlisted
contexts, serializes runs, requires a second confirmation for archive mode, and
records completed or failed attempts in System activity.

The seeder also reconciles the current permission catalogue and creates this
least-privileged terminal fixture:

| Workspace | Email | Password | Access |
| --- | --- | --- | --- |
| Point of sale | `cashier@aureon.test` | `password` | `pos-cashier` (`access-pos` only) |

Register and till administration requires `manage-tills`. Assign both
`access-pos` and `manage-tills` to a custom supervisor role when one operator
must sell and administer tills. Active system administrators are platform
super-users and can access both surfaces even while older role-permission rows
are being reconciled.

The Property Booking demonstration graph is also opt-in and excluded from
`DatabaseSeeder`:

```powershell
php artisan db:seed --class="App\Modules\PropertyBooking\Database\Seeders\PropertyBookingDemoSeeder" --no-interaction
```

It is rerunnable and provides three categories, four amenities, one property,
three unit types, seven concrete units, three rate plans and overrides, one
availability block, two guests, one reception register, one reconciled shift,
representative web/POB bookings with stays, guests, assignments, charges and
payments, eleven attached media records, and two property-assigned operators:

| Workspace | Email | Password | Access |
| --- | --- | --- | --- |
| Property booking management | `booking.manager@aureon.test` | `password` | `property-booking-manager` |
| Point of Booking | `receptionist@aureon.test` | `password` | `booking-receptionist` |

Phase 2 publishes the protected Property Booking catalog workspaces at
`/admin/accommodation/properties`, `/amenities`, `/units`, `/rates`, and
`/availability`. Phase 3 publishes the independent public storefront at
`/stays`, including catalog/property/unit discovery, session selection,
Livewire checkout, and signed guest follow-through. Phase 4 adds `/pob`,
`/admin/accommodation/pob/registers`, and `/admin/accommodation/pob/shifts`.
Phase 5 adds the `/admin/accommodation` overview plus `/bookings`, `/guests`,
and `/readiness`. All implementation records are under `.docs/PropertyBooking/`.

Receipt printing is configured per register from the register administration
workspace. The built-in `browser` driver supports 58 mm and 80 mm layouts plus
manual or post-sale print-dialog prompting. These optional defaults apply to new
registers and may be overridden per record:

```dotenv
COMMERCE_POS_RECEIPT_PRINT_DRIVER=browser
COMMERCE_POS_RECEIPT_PRINT_MODE=manual
COMMERCE_POS_RECEIPT_PAPER_WIDTH_MM=80
COMMERCE_POS_SALES_HISTORY_TERMINAL=true
COMMERCE_POS_SALES_HISTORY_DASHBOARD=true
COMMERCE_POS_SALES_HISTORY_PER_PAGE=8
```

Normal browser security leaves physical-printer selection and silent dispatch
to the operating system, a managed kiosk, or a separately installed trusted
bridge. Driver extension and event semantics are documented in
`.docs/PointOfSale/commerce-document-adapters-implementation.md`. Cashier-owned
active and historical sales panels can be independently mounted in the terminal
and dashboards with the switches above; disabling either surface does not alter
order history.

Operational Commerce mail is globally and individually configurable. Staff
alerts resolve active users through explicit `manage-orders`,
`manage-inventory`, or `manage-tills` permissions; customer lifecycle mail uses
the immutable order snapshot address. Production environments must run a queue
worker for the configured queue:

```dotenv
COMMERCE_OPERATIONAL_NOTIFICATIONS_ENABLED=true
COMMERCE_TILL_VARIANCE_ALERT_THRESHOLD_MINOR=10000
COMMERCE_NOTIFICATION_QUEUE=default
```

The complete event catalogue and per-event switches are documented in
`.docs/PointOfSale/commerce-operational-notifications-implementation.md`.

## Commerce adoption documentation

The Phase 4.4 adopter set is maintained beside the Commerce implementation
records:

| Document | Use |
| --- | --- |
| [Adoption guide](.docs/PointOfSale/AdoptionReadiness/commerce-adoption-guide.md) | Prerequisites, configuration, deployment, permissions, optional data, disablement, and removal |
| [Operations handbook](.docs/PointOfSale/AdoptionReadiness/commerce-operations-handbook.md) | Daily catalog, inventory, web-order, POS, till, receipt, and incident procedures |
| [Glossary and scenarios](.docs/PointOfSale/AdoptionReadiness/commerce-glossary-and-scenarios.md) | Canonical vocabulary, lifecycle truth, and end-to-end walkthroughs |
| [Extension guide](.docs/PointOfSale/AdoptionReadiness/commerce-extension-guide.md) | Safe gateway, variant, warehouse, return, tax, hardware, webhook, and reporting boundaries |
| [Screenshot manifest](.docs/PointOfSale/AdoptionReadiness/commerce-screenshot-manifest.md) | Existing visual evidence and the final Phase 4.5 capture matrix |

The optional seed graph was not executed or changed during this documentation
increment. Its current contents and limitations are stated explicitly in the
adoption guide and scenario truth table.

Serving the Markdown tree through Laradocs is a separate auxiliary feature. The
package currently requires PHP 8.3 or newer, while this project retains a PHP
`^8.2` contract and a verified PHP 8.2 runtime. Runtime upgrade, document
curation, a dedicated permission, middleware protection, SEO/API exposure, and
production caching must be reviewed before that package is installed.

## Development

```powershell
composer run dev
```

Or run the services separately:

```powershell
php artisan serve
npm.cmd run dev
```

Base routes:

- `/login`
- `/register`
- `/two-factor/challenge` (only mid-login while 2FA is active)
- `/dashboard`
- `/admin/dashboard`
- `/admin/blank`
- `/content/dashboard`
- `/content/queue`
- `/content/calendar`
- `/editor/dashboard`
- `/editor/drafts`
- `/editor/reviews`
- `/viewer/dashboard`
- `/viewer/library`
- `/viewer/saved`
- `/admin/system-activity`
- `/admin/institution-details`
- `/admin/communication-templates`
- `/admin/users`
- `/admin/roles-and-permissions`
- `/admin/commerce/catalog`
- `/admin/commerce/inventory`
- `/admin/commerce/orders`
- `/admin/commerce/customers`
- `/admin/commerce/pos/registers`
- `/admin/commerce/pos/tills`
- `/pos`
- `/pos/receipts/{order-ulid}`
- `/shop`
- `/shop/products/{slug}`
- `/logs`
- `/profile`

Two-factor authentication is off by default (`TWO_FACTOR_ENABLED=false`).
When enabled, users opt in from the profile Security panel; OTP mail is
queued, so run a queue worker (`composer run dev` includes one) and read
codes from `storage/logs/laravel.log` while `MAIL_MAILER=log`.

## Verification

```powershell
composer validate --no-check-publish
composer audit --locked --format=summary
php artisan test
php artisan view:cache
php artisan route:list
npm.cmd run build
npm.cmd run qa:dashboard
npm.cmd run qa:auth
npm.cmd run qa:travel-tours-storefront
npm.cmd run qa:travel-tours-admin
```

The TravelTours storefront harness defaults to `http://127.0.0.1:8011`; the
authenticated admin harness defaults to `http://127.0.0.1:8013` and expects
`admin@kenfam.test` / `password` in an isolated, opt-in demonstration database.
Override either with `AUREON_QA_URL` and keep `APP_URL` aligned so Media Library
URLs use the same origin. Run the
inherited Commerce or Property Booking QA scripts only in a deliberate
reference-module environment where the corresponding module flag is enabled;
both are disabled in the default Kenfam application. `qa:auth` additionally
drives the full 2FA challenge when the server runs with
`TWO_FACTOR_ENABLED=true` and the script is invoked with `AUREON_QA_2FA=1`.

## Development context

Start with `.docs/dev/laravel-aureon-foundation-implementation.md`, then read the base-engine plan and delegation context before adding modules. Cross-cutting capabilities must preserve the dashboard/public asset boundary and keep their records in a dedicated `.docs/<ModuleName>/` folder. Current adoption contracts live in `.docs/DashboardArchitecture/`, `.docs/ErrorPages/`, `.docs/LogsAndSystemActivity/`, `.docs/InstitutionDetails/`, `.docs/MailNotifications/`, `.docs/PdfReports/`, `.docs/UserManagement/`, `.docs/RolesAndPermissions/`, `.docs/PointOfSale/`, `.docs/PropertyBooking/`, and `.docs/auth-2fa/`. Commerce implementation order is recorded in `.docs/PointOfSale/commerce-schema-model-implementation.md`, `commerce-services-authorization-implementation.md`, `commerce-catalog-inventory-admin-implementation.md`, `commerce-storefront-catalog-implementation.md`, `commerce-storefront-ordering-implementation.md`, `commerce-pos-terminal-implementation.md`, `commerce-phase-4-hardening-adoption-plan.md`, `commerce-admin-dashboard-implementation.md`, `commerce-document-adapters-implementation.md`, and `commerce-operational-notifications-implementation.md`. Property Booking starts with `.docs/PropertyBooking/property-booking-module-plan.md`, followed by `property-booking-foundation-implementation.md`, `property-booking-catalog-availability-implementation.md`, `property-booking-storefront-implementation.md`, `property-booking-pob-implementation.md`, and `property-booking-operations-implementation.md`.
