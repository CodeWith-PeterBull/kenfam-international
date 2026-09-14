# Travel & Tours Architecture Contract

Status: revised target architecture. Actual implementation is recorded in the
ledger. Versions are those locked locally: Laravel 12.64.0, Livewire 4.3.3,
Spatie Media Library 11.23.2 and Permission 6.25.0.

## 1. Ownership And Canonical Structure

Kenfam is the client application; TravelTours is independently reusable.
Module code must not import Commerce/PropertyBooking classes, query their tables,
or read config('kenfam.*'). The host supplies institutional identity and branding.

```text
app/Modules/TravelTours/
  TravelToursServiceProvider.php
  Config/travel-tours.php
  Contracts/
  Support/{Concerns,Data}
  Catalog/{Data,Enums,Models,Policies,Services,Http/Controllers,Livewire/Forms}
  Scheduling/{Data,Enums,Models,Policies,Services,Livewire/Forms}
  Pricing/{Data,Enums,Models,Policies,Services,Livewire/Forms}
  Customers/{Data,Enums,Models,Policies,Services,Livewire/Forms}
  Bookings/{Data,Enums,Models,Policies,Services,Events,Exceptions,Livewire/Forms}
  Inquiries/{Data,Enums,Models,Policies,Services,Livewire/Forms}
  PointOfBooking/{Data,Enums,Models,Policies,Services,Printing,Livewire/Forms}
  Storefront/{Data,Services,Http/Controllers,Livewire/Forms}
  Operations/{Data,Services,Http/Controllers,Livewire}
  Reporting/{Data,Filters,Reports,Services}
  Notifications/{Listeners,RecipientResolution}
  Console/Commands/
  Database/{Migrations,Factories,Seeders}
  Routes/{storefront,admin,pob}.php
  Resources/views/{layouts,storefront,admin,pob,livewire,reports}
  Resources/assets/{css,js}
tests/{Feature,Unit}/TravelTours/
.docs/TravelTours/
```

PricingRule belongs to Pricing even when it references a departure. Factories
are module-owned. Prefer Aureon patterns; do not invent a repository framework,
dynamic form engine or competing mail/report infrastructure.

## 2. Provider And Toggle Contract

The provider first merges config in register(), then returns when disabled.
Only accepted implementations receive contract bindings. No boot/register
database reads or writes. Boot applies the same guard before registering
migration paths, views (travel-tours::), routes, policies, Livewire aliases,
commands, schedules and event listeners.

Disabled means no runtime registration or new migration/seed activity, not data
deletion. Already existing tables/media/history stay intact. Clear config/route
caches and restart workers after toggling. Queued work checks enablement again
before sending or mutating. Menu removal alone is not module isolation.

Commerce and PropertyBooking default to false in this client. Audit global
command discovery, scheduler hooks, permission composition and DatabaseSeeder
calls as well as provider guards. Isolation tests require a fresh application
boot; changing config after boot does not unregister prior resources.

## 3. Permitted Host Changes

| Integration | Responsibility |
| --- | --- |
| bootstrap/providers.php | Register one module provider |
| CmsPermission catalogue | Compose enabled module-owned permissions |
| Dashboard registry and redirect | Travel role bundles without bypassing auth/2FA |
| Sidebars/topbars | Enabled flag, route availability and Gate checks |
| Institution details/branding | Client identity and theme defaults |
| .env.example/README/deployment | Document settings and commands without secrets |
| Vite/static copy | Build real entries and copy public resources only |
| Root homepage | Client shell must render even with TravelTours disabled |
| Root seeder | Access initialization only; demonstration people/data opt-in |

Host contracts: RecordsSystemActivity, ResolvesInstitutionProfile,
RendersPdfReports, App\Models\User, Laravel validation/authorization/database/
events/queue/mail/cache/scheduler/filesystem/signatures and Spatie media.
Confirm actual interface namespaces and signatures before binding.
Never reuse a concrete Commerce or PropertyBooking service.

Global system-admin Gate bypass grants authorization, not permission to violate
capacity, payment, retention or reconciliation rules. Services enforce those
invariants regardless of actor role.

## 4. Route And Livewire Surfaces

| Surface | Prefix | Name prefix | Boundary |
| --- | --- | --- | --- |
| Public | /tours | travel-tours.storefront.* | Public catalog; signed private booking reads |
| Admin | /admin/travel | travel-tours.admin.* | web/auth/verified, existing 2FA, permission and policy |
| POB | /travel-booking-desk | travel-tours.pob.* | auth/verified, access POB, operator/shift ownership |

Public includes destination discovery, catalog/detail, selection, checkout,
inquiry and confirmation/tracking/document routes. Fixed paths precede
/{tour:slug}. Admin covers catalog, destinations, departures, rates, promotions,
bookings, customers/travelers, inquiries, registers, shifts and reporting.
POB includes terminal, active/history booking lists and receipts.

ULID is an identifier, not authorization. Public catalog binds published slugs.
Nested routes validate parent ownership. Private booking links use ULID plus
temporary signature, minimal recipient-safe data, noindex/nofollow and private
no-store headers. They do not authorize payment confirmation or profile edits.
Orientation/receipt format is whitelisted, never a user-selected view path.

Aliases are explicit: travel-tours.admin.tour-manager,
travel-tours.storefront.checkout, travel-tours.pob.terminal.
Typed Livewire Forms own fields, rules, labels and DTO normalization. Components
own filters, Bootstrap pagination, modal and loading state, not transactions.
Authorize each action including child/media actions. Locked IDs are supplementary,
not an authorization replacement. Validate at the service boundary as well.
Error summaries, focus, field errors and retry states form part of each workflow.

## 5. Access Matrix

Roles are bundles, not new identity types. Stable permission groups include
view dashboard, view/manage/publish catalog, view/manage departures, pricing,
promotions, bookings, customers and inquiries; sensitive-traveler access;
record/confirm payment; refund; POB; register management; own shift open/close;
other-shift reconciliation and report access.

| Capability | System admin | Travel manager | Booking agent | Tour editor |
| --- | --- | --- | --- | --- |
| Overview/catalog/schedule view | Yes | Yes | Yes | Catalog/schedule |
| Catalog/itinerary/media edit | Yes | Yes | No | Yes |
| Publish | Yes | Yes | No | Explicit permission |
| Pricing/promotions/capacity edit | Yes | Yes | No | No |
| Booking/customer/inquiry workflow | Yes | Yes | Own/assigned scope | No |
| Sensitive traveler details | Audited access | Explicit permission | Assigned + explicit permission | No |
| Desk payment recording | Yes | Yes | Own open shift | No |
| Remote payment confirmation/refund | Yes | Explicit permissions | No default | No |
| POB/own shift | Yes | Yes | Yes | No |
| Register/other shift reconciliation | Yes | Yes | No | No |
| Global financial reports | Yes | Yes | No default | No |

Scope both list queries and actions. A restricted agent must not browse every
customer or booking by substituting a ULID. Unassigned inquiries require a
specific triage capability. Sensitive exports/downloads require audited access.

## 6. Configuration Contract

| Group | Settings and bounds |
| --- | --- |
| Enablement | One module enabled flag, cached config, no runtime env reads |
| Localization | Supported ISO currencies/exponents, country and timezone fallbacks, no FX |
| Catalog | Bounded page size, media counts, sanitized rich-text allowlist |
| Booking | Hold/approval expiry, maximum party, signature lifetimes |
| Confirmation | Departure override -> tour mode -> approval; hybrid is resolution behavior |
| Participants | Non-overlapping adult/child/infant ages and explicit infant-seat policy |
| Pricing | Integer tax/deposit defaults and rounding, never browser totals |
| Privacy/media | Private document disk, upload/MIME limits, versioned purpose-specific hash key |
| POB | Manual methods, tender cap, driver, print mode and 58/80 mm width |
| Notifications | Enabled events, queue, recipients and reminder windows |
| Numbering | Channel prefixes and unique collision-safe strategy |

Every live option has a consumer and test. Future options are documented as
future rather than shown as working controls. Operator-approved legal, tax and
age rules must not be inferred from a country name.

## 7. Persistence And Service Boundaries

The data dictionary owns all 34 entities. Internal integer IDs support joins and
locks; immutable ULIDs support externally addressable rows. Money uses integer
minor units and explicit currency exponent. UTC timestamps preserve instants;
departure IANA timezone determines local-date rules.

Services receive immutable DTOs, actor context and operation identity. They
validate related-record ownership, reprice, lock in consistent order, write
snapshots and dispatch after commit. Inputs do not accept arbitrary model
attributes, status, totals, actor IDs or private metadata. Do not catch an
exception solely to rethrow it. Expected failures have typed domain exceptions;
unexpected failures flow through Laravel with privacy-safe operation context.

## 8. Media And Sensitive Data

Public collections: category image, destination cover/gallery, tour cover/gallery,
itinerary images and explicitly approved brochures. Store alt/caption metadata,
gallery order and non-upscaled thumbnail/card/detail conversions. Validate MIME,
size and dimensions; reject executable files, untrusted SVG and arbitrary URLs.

Travel identity documents are outside public storage on a private disk and served
only through authorized downloads. Profile media is private unless explicitly
published. Identity, health/accessibility and sensitive snapshot attributes are
encrypted AND hidden from serialization. Encrypted casts alone still expose
decrypted values through toArray(). Search identifiers use keyed,
purpose-separated hashes; do not expose these hashes publicly either.

Purpose-limited document DTOs feed shared institutional report layouts through
their existing two-way layout/template contract. Portrait/landscape use the same
commercial snapshot. Thermal receipt adapters add register/shift/tender data.
Browser auto-print can prompt, not guarantee silent hardware printing. A log
entry does not mean a receipt reached a printer. Print CSS uses readable paper
colors regardless of the dashboard's dark theme.

## 9. Events, Audit And Background Work

Booking placed, approved, rejected, cancelled, payment confirmed, refund
completed, departure changed/cancelled, inquiry assigned, payment due and shift
variance are separate events with focused messages. Recipients depend on
permissions, assignments, institution settings and preferences. Dispatch after
commit. Choose a deliberate queue boundary, delivery identity and retry policy;
avoid accidental listener-plus-notification double queueing.

Expiry/reminder commands process bounded chunks, avoid overlapping execution
and call domain services. Rechecks under locks make retries safe. Work queued
before module disablement checks the flag. Activity contains actor/resource ULID,
safe transition and correlation identity, never passport data, health notes,
raw provider payloads, signed URLs or session secrets.

## 10. UI, Assets And Deployment

Reuse Aureon responsive header/footer, theme/font tokens, first-paint initialization
and nonblocking loader. Use module-local assets and the installed icon library.
Public imagery must accurately represent approved content; generated illustration
provenance belongs in the asset register. Include empty, unavailable, sold-out,
stale quote, loading, validation, authorization and recovery states.

Deploy both Vite manifest/assets and public Aureon/client static resources.
Private media is never copied. Preserve uploads; distinguish missing static files
from HTML fallback MIME errors; rebuild caches and restart queues after release.

References: [Laravel 12 migrations](https://laravel.com/docs/12.x/migrations),
[Livewire 4 security](https://livewire.laravel.com/docs/4.x/security).
Local benchmarks: PropertyBooking architecture; InstitutionDetails module;
PdfReports templates; MailNotifications templates. These are contracts to read,
not names to cite without checking implementation.
