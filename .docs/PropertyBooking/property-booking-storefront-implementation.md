# Property Booking Storefront Implementation

## 1. Delivery Identity

- **Module:** `App\Modules\PropertyBooking`
- **Phase:** 3, public discovery and self-service booking
- **Branch:** `feature/property-booking-storefront`
- **Starting commit:** `73ec054 feat(property-booking): deliver catalog and availability administration`
- **Status:** Complete; implementation and every Phase 3 acceptance gate passed
- **Data safety:** existing demonstration seeders remain unchanged; the additive migration and aggregate demo seeder were run against the configured database at the user's explicit request, without a destructive reset

## 2. Approved Scope

Phase 3 delivers the public `/stays` surface without importing any Commerce
class, view, route, asset, configuration, table, or service. It consumes the
Phase 2 publication, rate, quote, availability, guest, allocation, institution,
PDF, activity, notification, and media contracts.

### Delivery A: discovery

- module-owned storefront layout, header, footer, theme controller, CSS, and JS;
- availability search using property-local dates/times and bounded occupancy;
- published property/category results backed by current concrete availability;
- scoped property and unit-type detail routes;
- large property/unit photography canvases, ordered galleries, amenities,
  policies, capacity, bed/layout facts, and public rate cards;
- no concrete room number or other staff-only inventory value in public output.

### Delivery B: selection and placement

- session storage containing only rate-plan identity, interval, and occupancy;
- server-side rehydration and re-quote on review, checkout, and placement;
- Livewire guest/contact, payment-preference, special-request, and consent form;
- reusable guest resolution with configurable optional/required ID or passport
  capture through the protected guest service;
- one outer transaction that locks the selected public catalog roots, re-quotes,
  persists booking/stay/guest snapshots, allocates one exact unit, assigns the
  business number, and records privacy-safe system activity;
- no browser-owned total, availability count, unit ID, or booking status.

### Delivery C: private follow-through

- independent temporary signed confirmation, tracking, and document URLs;
- `private, no-store` and `noindex` response headers;
- immutable public booking/document projections excluding internal notes,
  concrete units, shift/register data, protected identity, and payment metadata;
- shared institutional portrait/landscape PDF rendering;
- one after-commit web-booking event and one dedicated queued customer
  confirmation notification using the immutable booking email snapshot.

### Delivery D: hardening

- migration comments and exhaustive schema expectations for storefront fields;
- route binding, nested ownership, signature expiry, stale quote, rollback,
  notification, document, privacy, and disabled-module tests;
- static syntax, Pint, Vite, Laravel cache, focused/full regression gates;
- desktop/tablet/mobile, light/dark, keyboard, reduced-motion, overflow, and
  runtime/network browser diagnostics with committed screenshot evidence.

## 3. Explicit Boundaries

- Version one places one property, one unit type, one rate plan, and one exact
  unit per storefront booking. The schema remains compatible with multi-stay
  bookings, but multi-room selection is deferred until its occupancy semantics
  are designed explicitly.
- Storefront payment choice is a preference, not a completed payment. It is
  snapshotted on the booking and creates no payment ledger record.
- Placement creates a `pending` booking with the configured expiry because no
  payment gateway or automatic confirmation policy exists in this phase.
- Public quote feedback never reserves inventory. Allocation remains the only
  authoritative availability-consuming write.
- Point of Booking, operational booking CRUD, check-in/out, receipts, register
  shifts, and staff alerts remain later phases.

## 4. Runtime Implementation Map

### Runtime route map

| Route | Name | Contract |
| --- | --- | --- |
| `GET /stays` | `property-booking.storefront.catalog.index` | Published, active-category discovery and availability search |
| `GET /stays/{property}` | `property-booking.storefront.properties.show` | Slug property detail with private concrete-unit identity |
| `GET /stays/{property}/{unitType}` | `property-booking.storefront.units.show` | Parent-scoped slug unit-type detail and current rates |
| `GET /stays/selection` | `property-booking.storefront.selection.index` | Session selection re-quote and review |
| `GET /stays/checkout` | `property-booking.storefront.checkout.index` | Livewire guest checkout for a still-valid selection |
| `GET /stays/bookings/{booking}/confirmation` | `property-booking.storefront.bookings.confirmation` | Short-lived signed post-placement confirmation |
| `GET /stays/bookings/{booking}/track` | `property-booking.storefront.bookings.track` | Signed current guest-visible booking state |
| `GET /stays/bookings/{booking}/document/{orientation}` | `property-booking.storefront.bookings.document` | Signed portrait/landscape institutional PDF stream |

All routes share `web`, `throttle:120,1`, and scoped binding. Booking routes
also require Laravel's exact `signed` middleware and emit private/no-store and
noindex headers.

### Code ownership map

| Concern | Module-owned implementation |
| --- | --- |
| Discovery projections | `Storefront/Data`, `StayDiscoveryService`, `CatalogController` |
| Minimal session state | `BookingSelectionData`, `BookingSelectionSession` |
| Public forms | `StaySearchForm`, `BookingCheckoutForm`, three Livewire components |
| Placement | `StorefrontBookingCheckoutService`, `BookingService`, existing quote/allocation services |
| Guest reuse | `GuestProfileData`, `GuestService::resolveForCheckout()` |
| Follow-through access | `BookingAccessUrlService`, `BookingAccessController` |
| Documents | immutable document DTOs, `BookingDocumentDataFactory`, `BookingDocumentService` |
| Customer mail | `WebBookingPlaced`, dedicated listener and queued notification |
| Presentation | module layout, responsive header/footer/theme partials, views, CSS, Swiper gallery JS |
| Browser verification | `scripts/qa-property-booking-storefront.mjs` and `.docs/PropertyBooking/qa/storefront/` |

### Authoritative placement sequence

1. Read only the rate ULID, UTC interval, and occupancy from the session.
2. Lock and revalidate the published property/category, unit type, and rate.
3. Recalculate current availability and integer-minor-unit pricing.
4. Resolve or create the reusable public-safe guest inside the outer database
   transaction.
5. Persist pending booking, immutable stay/contact/financial snapshots,
   consent, and payment preference without creating a payment record.
6. Lock and allocate one available concrete unit, then assign the booking
   number and record privacy-safe system activity.
7. Commit, dispatch the scalar after-commit event, clear the selection, and
   redirect to an independently signed confirmation URL.

The event listener routes exactly one queued customer acknowledgement to the
immutable booking email snapshot. The queue payload carries only the booking
ULID; the notification reloads current data at execution time.

### Host integration and configuration

The service provider owns routes, migrations, views, Livewire aliases,
navigation sharing, and event registration behind `PROPERTY_BOOKING_ENABLED`.
Host edits are limited to the provider's existing integration points,
`vite.config.js`, `package.json`, `.env.example`, tests, and README status.
Storefront settings cover session key, catalog bound, signed-link lifetimes,
allowed manual payment preferences, guest identity requirement, notification
enablement, and queue name.

## 5. Verification Ledger

- `php artisan migrate --no-interaction`: applied
  `2026_08_31_100000_add_storefront_fields_to_property_bookings_table` to the
  configured development database without rebuilding existing tables.
- `php artisan db:seed --class="App\Modules\PropertyBooking\Database\Seeders\PropertyBookingDemoSeeder" --no-interaction`:
  completed the existing access, catalog/media, and operations seeders on the
  configured development database. No seeder source was changed in Phase 3.
- The canonical Phase 2 demo commands are also preserved in
  `property-booking-catalog-availability-implementation.md` for adopters.
- A temporary read-only Laravel bootstrap probe verified the resulting
  connected dataset and was removed immediately after execution:

| Aggregate | Count | Aggregate | Count |
| --- | ---: | --- | ---: |
| Property categories | 3 | Amenities | 4 |
| Properties | 1 | Unit types | 3 |
| Concrete units | 7 | Rate plans | 3 |
| Rate overrides | 3 | Availability blocks | 1 |
| Guests | 2 | Bookings | 2 |
| Booking stays | 2 | Booking guests | 2 |
| Booking charges | 1 | Booking payments | 2 |
| Unit assignments | 2 | Reception registers | 1 |
| Reception shifts | 1 | Catalog media | 11 |

- `npm.cmd run build`: passed with both module storefront Vite entries present;
  existing host font/background paths remain runtime-resolved build notices.
- Focused storefront suite: 13 tests and 109 assertions passed.
- Complete Property Booking regression: 52 tests and 1,057 assertions passed.
- Full application regression: 289 tests and 2,914 assertions passed.
- Pint passed in repository-wide `--test` mode.
- Configuration, route, and Blade caches compiled successfully and were then
  cleared back to development state. Eight named storefront routes were
  verified.
- Headless Chromium QA passed the catalog, property gallery, full-screen
  lightbox, unit detail, selection, checkout, mobile navigation, reduced
  motion, light/dark theme, labeling, duplicate-ID, image, text overflow, and
  runtime/network checks. Seven reviewed screenshots and `diagnostics.json`
  are stored under `.docs/PropertyBooking/qa/storefront/`.
