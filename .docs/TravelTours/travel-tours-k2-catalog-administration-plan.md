# TravelTours K2 Catalog Administration Plan

Status: active. K2A-K2C are committed; K2C is commit `38f0710`. K2D is
implemented and locally verified on `feature/travel-tours-k2-itinerary-content`
but remains uncommitted pending user review. K2E must not start before approval.

## 1. Objective

Deliver the first complete staff workflow: authorized users can create,
organize, enrich, preview, publish, unpublish, and archive the tours displayed
by the existing public storefront. K2 turns the implemented catalog schema into
a usable module without absorbing departure pricing, checkout, or operations.

The branch is `feature/travel-tours-k2-catalog-administration`. Each increment
requires implementation, focused and full verification, browser evidence for
interfaces, user review, then an approved commit before the next increment.

## 2. Included Domain Surface

K2 owns these existing models:

- `TourCategory` and parent hierarchy;
- `Destination` and parent hierarchy;
- `Tour`, `TourCategoryAssignment`, and `TourDestinationAssignment`;
- `ItineraryDay` and `ItineraryActivity`;
- `TourContentItem` for highlights, inclusions, exclusions, requirements, and
  packing notes;
- `TourFaq` and `TourExtra`;
- tour/destination media collections and SEO/publication fields.

K2 may read departure and rate-plan summaries to explain storefront readiness,
but it may not create or edit them. Those writes belong to K3.

## 3. Explicit Exclusions

- departure scheduling, capacity, staffing, availability, and holds;
- rate plans, participant rates, pricing rules, promotions, and tax policy;
- checkout, travelers, lifecycle transitions, payments, and refunds;
- booking-desk registers, shifts, receipt printing, and reconciliation;
- production notification delivery, PDF adapter completion, reporting, and
  cross-module activity implementation;
- review, wishlist, customer portal, agent portal, or payment gateway work.

These exclusions are feature boundaries, not abandoned requirements. Their
phase owners are listed in `travel-tours-k1-reconciliation.md`.

## 4. Module Structure

```text
app/Modules/TravelTours/Catalog/
  Data/
    TourCategoryData.php
    DestinationData.php
    TourData.php
    TourAssignmentData.php
    ItineraryDayData.php
    ItineraryActivityData.php
    TourContentItemData.php
    TourFaqData.php
    TourExtraData.php
    CatalogMediaData.php
  Exceptions/
    CatalogException.php
    HierarchyCycle.php
    PublicationBlocked.php
  Livewire/Admin/
    TourCategoryManager.php
    DestinationManager.php
    TourCatalog.php
    TourEditor.php
  Livewire/Forms/
    TourCategoryForm.php
    DestinationForm.php
    TourForm.php
    ItineraryDayForm.php
    ItineraryActivityForm.php
    TourContentItemForm.php
    TourFaqForm.php
    TourExtraForm.php
  Policies/
    TourCategoryPolicy.php
    DestinationPolicy.php
    TourPolicy.php
  Services/
    TourCategoryService.php
    DestinationService.php
    TourService.php
    TourAssignmentService.php
    TourItineraryService.php
    TourContentService.php
    CatalogMediaService.php
    TourPublicationService.php
```

Blade views remain module-owned under
`Resources/views/livewire/admin/catalog/`. Routes remain in the module's
`Routes/admin.php`; no host route file should know these components.

## 5. Administration Experience

### Catalog overview

The existing `/admin/travel/catalog` route becomes the `TourCatalog` Livewire
page. K2C provides bounded search, status/type/category/destination filters,
publication-state counts, cover thumbnails, and permission-aware create/edit
controls. K2E adds storefront readiness, preview, and publication controls.
Pagination uses the configured Bootstrap theme and query-string filters.

### Categories

A dedicated manager presents the hierarchy in deterministic `sort_order`, then
name order. Staff can create and edit nodes, choose a parent, order them, set
SEO fields and active state, and assign an icon key. Moving a node below itself
or any descendant is rejected. Destructive deletion is unavailable when the
category has children or tour assignments; deactivation/archive is preferred.

### Destinations

The destination manager handles country/region/city/place hierarchy,
coordinates, timezone, summaries, feature/publication state, SEO, cover and
gallery media. Parent cycles, invalid latitude/longitude, invalid country code,
invalid timezone, and publishing below an inactive/unpublished parent are
rejected.

### Tour editor

The editor is a full-page work surface with stable tabs:

1. Basics: code, slug, type, name, summaries, descriptions, duration, age,
   difficulty, participant bounds, languages, and booking mode.
2. Route: category and destination assignments, primary category, destination
   role, sequence, and overnight state.
3. Itinerary: ordered days and ordered activities with optional local times,
   locations, coordinates, meals, and accommodation.
4. Experience: highlights, inclusions, exclusions, requirements, packing notes,
   FAQs, and optional extras.
5. Media and SEO: cover, gallery, downloadable public documents, alt text,
   metadata, canonical data, featured state, and display order.
6. Publication: readiness checklist, preview, publish/schedule, unpublish, and
   archive actions.

Dialogs remain accessible, keyboard-operable, and sized predictably. Compact
controls use existing dashboard patterns, Lucide icons, and theme tokens.

## 6. Write And Validation Contract

Livewire Forms validate presentation input and return typed DTOs. Services own
transactions, hierarchy checks, assignment replacement, ordered child writes,
media mutation, and publication rules. Components never call `forceFill()`,
`sync()`, `addMedia()`, or delete domain rows directly.

Core rules:

- codes and slugs are normalized and unique among retained rows;
- parent resources must be active and of a valid hierarchy type;
- parent changes cannot create a cycle;
- exactly one primary category is permitted when categories are assigned;
- destination sequences and itinerary day numbers are unique per tour;
- activity, content, FAQ, extra, and media ordering is deterministic;
- assignment IDs must belong to the current tour and target type;
- maximum participants cannot be lower than minimum participants;
- duration, age, coordinates, language codes, and local times are bounded;
- extras use integer minor units, ISO currency, and the persisted pricing unit;
- media MIME type, size, collection, count, and alt text use live configuration;
- publication requires minimum public content, cover media, one category, one
  destination, a valid itinerary, SEO essentials, and no invalid child state;
- archiving a tour hides it publicly without breaking booking history.

Errors become named catalog exceptions with user-safe messages. Unexpected
exceptions continue through Laravel reporting and are not converted into false
success notices.

## 7. Authorization And Scope

K2 introduces `publish-travel-catalog` in addition to the existing view/manage
capabilities. Tour editors receive view/manage, travel managers receive
view/manage/publish, booking agents receive view-only where required, and the
host system administrator retains the existing `Gate::before` bypass.

Every Livewire request reauthorizes in `boot()`. Every mutation authorizes its
specific resource/action. IDs are `#[Locked]`, but locking is not treated as
authorization. Record lookup uses policy-scoped queries; substituting another
ULID or numeric ID must not reveal or mutate an unauthorized record. Buttons
and menus use the same policy checks for presentation only.

The access seeder remains idempotent and creates no users. Demo operator roles
remain opt-in fixtures.

## 8. Media And Public Preview

The existing Spatie Media Library collections remain authoritative. Uploads
use Livewire temporary files, service-owned final attachment, configured limits,
and meaningful alt text. Reordering stores deterministic custom order. Replacing
a single cover removes only the prior cover; gallery replacement is explicit.

Draft preview must be authenticated and authorized; it must not change public
query scopes or create a guessable public URL. Public storefront routes continue
to resolve only published records. K2 does not introduce private customer media.

## 9. Routes And Navigation

Planned named routes:

```text
travel-tours.admin.catalog.index
travel-tours.admin.catalog.categories
travel-tours.admin.catalog.destinations
travel-tours.admin.catalog.tours.create
travel-tours.admin.catalog.tours.edit
travel-tours.admin.catalog.tours.preview
```

The Travel dashboard and sidebar expose Catalog, Categories, and Destinations
according to policy. The public preview and public page open in a new tab where
appropriate. A draft never links to the public route as if it were published.

## 10. Delivery Increments

| Increment | Status | Deliverable | Exit evidence |
| --- | --- | --- | --- |
| K2A | Complete | Permissions, policies, scoped queries, DTOs, exceptions, authorized overview route | Role matrix, policy registration/action, query authorization, route and typed-data tests |
| K2B | Complete | Category and destination services, Forms, managers, media | CRUD, cycles, parent state, coordinate/timezone, media ownership/order/limit and Livewire tests |
| K2C | Complete, commit `38f0710` | Tour index, base editor, category/destination assignments, and authenticated browser QA | 8 focused tests / 37 assertions; full 160 / 2,482; seven cross-viewport captures |
| K2D | Implemented, awaiting review | Itinerary, activity, content, FAQ, and exact-money extra management | Focused service/Livewire tests and 11 authenticated browser captures; no commit yet |
| K2E | Pending | Media, SEO, readiness, preview, publish/unpublish/archive | Publication blocker, draft privacy, archive/history tests |
| K2F | Pending | Responsive/theme/accessibility QA and documentation reconciliation | Focused/full suites, build, screenshots, diagnostics, ledger update |

K2A deliberately omitted unfinished routes. K2B registered category and
destination routes after their service/Form/Livewire surfaces were tested.
K2C now registers tour create/edit routes, while preview remains absent until
K2E implements the publication and privacy workflow.

## 11. Verification Matrix

Required automated evidence:

- service tests for each create/update/reorder/state transition;
- hierarchy-cycle and invalid-parent tests for categories and destinations;
- policy tests for viewer, editor, manager, booking agent, and administrator;
- Livewire action tests, validation tests, locked-ID tampering, and pagination;
- media upload/replace/remove/reorder limits with a fake public disk;
- publication readiness and scheduled publication boundary tests;
- public queries proving draft/inactive/archived records stay hidden;
- retained booking relationships after catalog archive;
- disabled-module isolation with no TravelTours Livewire route surface;
- module suite, full host regression, Pint, PHPDoc audit, schema probe, Blade
  cache, route inspection, Vite build, and storefront regression.

Browser acceptance covers catalog overview, category manager, destination
manager, and every tour-editor tab at desktop, tablet, and mobile widths in
light and dark mode. It checks keyboard dialogs, focus return, field labels,
validation announcements, file controls, no horizontal overflow, stable tables,
and public preview isolation.

## 12. Documentation And Handoff

K2 implementation must update the module plan, architecture, workflows,
service contracts, verification plan, implementation ledger, and remediation
status. Add `travel-tours-k2-catalog-administration-implementation.md` with
changed files, decisions, commands, measured results, screenshots, limitations,
and exact K3 handoff.

No engineer working K2 may silently implement K3 rate/departure mutations or
mark C-04/C-05/C-08/C-10 complete. Cross-phase needs are documented as a typed
interface or explicit handoff, then implemented on their owning branch.
