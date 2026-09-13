# Property Booking Guest Identity And POB Walk-In Update

**Branch:** `fix/property-booking-guest-identity-and-pob-dates`

**Status:** Implemented; verification ledger is recorded below.

**Schema impact:** None. The existing protected guest identity columns are reused.

## Purpose

This update adds ID/passport capture to public stay checkout and the Point of
Booking (POB), makes the requirement independently configurable for each
surface, and supports immediate onsite arrivals without weakening public
advance-booking rules.

## Configuration Contract

| Environment key | Config key | Default | Effect |
| --- | --- | ---: | --- |
| `PROPERTY_BOOKING_STOREFRONT_GUEST_IDENTITY_REQUIRED` | `storefront.guest_identity_required` | `false` | Keeps ID/passport optional on `/stays` checkout unless the adopter requires it. |
| `PROPERTY_BOOKING_POB_GUEST_IDENTITY_REQUIRED` | `pob.guest_identity_required` | `true` | Requires protected identity before a POB hold or completed booking. |
| `PROPERTY_BOOKING_POB_ENFORCE_ADVANCE_NOTICE` | `pob.enforce_advance_notice` | `false` | When `false`, an authenticated onsite booking may waive property/rate lead time. |
| `PROPERTY_BOOKING_POB_WALK_IN_PAST_GRACE_MINUTES` | `pob.walk_in_past_grace_minutes` | `15` | Bounds how long a receptionist may finish a booking after choosing the current minute. |

Supported guest-facing document choices are centrally defined under
`property-booking.identity.types`; the initial options are `national_id` and
`passport`. Run `php artisan config:clear` after changing local environment
values, or rebuild the production configuration cache during deployment.

## Protected Identity Flow

1. Livewire validates document type and number as a pair. The surface-specific
   requirement determines whether an empty pair is allowed.
2. `GuestProfileData` may carry the plaintext number only as transient command
   input. It is never assigned to a model field or booking snapshot.
3. `GuestService` stores only encrypted ciphertext and a keyed HMAC fingerprint,
   using the existing `PROPERTY_BOOKING_IDENTITY_HASH_KEY`/`APP_KEY` contract.
4. Activity records contain only a `has_identity` boolean. Receipts, PDFs,
   notifications, signed pages, browser state, logs, and booking snapshots do
   not receive the number.
5. Optional checkout with no identity input preserves an existing guest's
   protected identity instead of clearing it.
6. When public contact resolution finds a guest with protected identity, the
   submitted type and HMAC must match; checkout cannot replace that identity.

POB guest creation applies the default mandatory policy. A legacy guest without
identity can still be selected, but hold and payment actions remain disabled
until an authorized operator records an ID/passport through `GuestService`.
The terminal reports only that identity is present; it never decrypts or
prefills the stored number.

`BookingService` repeats the mandatory POB check at the transactional domain
boundary. Direct service calls therefore cannot bypass the Livewire policy.

## Immediate Walk-In Semantics

The terminal's compact **Now** action uses the active property's IANA timezone:

- arrival becomes the current property-local minute;
- departure becomes the following local date at the property's configured
  `check_out_until` time;
- any selected rate or resumed hold projection is cleared and availability is
  quoted again;
- unit availability, capacity, duration, closure, maximum-advance, pricing,
  tax, deposit, and concrete allocation rules remain authoritative.

POB pricing passes `AdvanceNoticePolicy::WaiveForOnSiteBooking` only when
`pob.enforce_advance_notice` is disabled. This waives the online minimum-notice
window but rejects arrivals older than the bounded walk-in grace period. Every
storefront, public discovery, administrative availability, and default pricing
call continues to use `AdvanceNoticePolicy::Enforce`.

## Compatibility Notes

- No migration or data rewrite is required.
- Existing protected identity records remain valid.
- Existing public installations retain optional identity by default.
- Existing POB installations become identity-required by default; set
  `PROPERTY_BOOKING_POB_GUEST_IDENTITY_REQUIRED=false` during a staged adoption
  if legacy operations need a temporary compatibility window.
- The POB advance-notice waiver is explicit and does not alter web booking
  behavior.

## Verification Ledger

Coverage includes:

- optional and required storefront validation;
- encrypted/HMAC persistence with no plaintext model storage;
- required and configuration-disabled POB booking paths;
- domain rejection of POB service calls with an unverified guest;
- protected upgrade of a selected legacy guest;
- current-minute availability against a 60-minute public notice rule;
- rejection outside the configured walk-in grace period;
- receipt and POB transaction regressions.

| Verification | Result |
| --- | --- |
| `php vendor/bin/pint --dirty` | Passed after formatting the changed PHP files |
| Focused identity/pricing/POB/receipt tests | 25 tests and 192 assertions passed before the final full runs |
| `php artisan test tests/Feature/PropertyBooking` | 90 tests and 1,515 assertions passed |
| `php artisan test` | 327 tests and 3,372 assertions passed |
| `npm.cmd run build` | Production Vite build passed; updated POB CSS emitted in `pob.min.css` |
| Blade, route, and configuration caches | Each compiled successfully; `php artisan optimize:clear` restored development state |
| Storefront browser QA | Six responsive captures plus one lightbox capture passed; identity controls asserted present |
| POB browser QA | 13 diagnostics and 12 responsive/print captures passed; **Now** action asserted at all terminal viewports |
| Browser diagnostics | No runtime, network, overflow, duplicate-ID, accessible-label, theme, or reduced-motion failures |

The retained screenshots and diagnostics are under
`.docs/PropertyBooking/qa/storefront/` and `.docs/PropertyBooking/qa/pob/`.
Visual review confirmed that the optional public identity pair and compact POB
**Now** action fit the existing desktop and mobile compositions.

The only environment warning was the existing Imagick build/runtime mismatch
(compiled against ImageMagick 1808 while 1810 is loaded). It did not fail any
media, document, build, or browser verification.
