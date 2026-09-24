# Claude Review Remediation Status

Review source: `concern_file.md`, dated 2026-09-14.

This file records implementation disposition without editing or weakening the
independent review. A concern is marked resolved only when a named test or
probe exercises the correction.

| ID | Status | Disposition and evidence |
| --- | --- | --- |
| C-01 | Resolved | Homepage assertion now matches the rendered copy and the test pins the institutional founded year. Full suite passes: 142 tests, 2,357 assertions. |
| C-02 | Resolved | `BookingAccessUrlService` reads the documented `booking.*` keys; all three environment variables are published. Focused URL-expiry tests cover confirmation, tracking, and document lifetimes. |
| C-03 | Resolved | `ScaledDecimal` and `MoneyFormatter` provide integer-safe exponent-aware output. Storefront, admin, report, signed-page, and notification renders no longer divide by 100. Tests cover zero-, two-, and three-decimal currencies. |
| C-04 | Response boundary resolved; projections still open | Signed booking HTML and PDF responses send private/no-store, noindex, and nosniff headers. M4 delivered the lean communications layer, but purpose-limited confirmation/tracking/report projections were not part of it and remain open post-release. |
| C-05 | Resolved by M4 (`14d7d1a`) | Queue name, after-commit dispatch, retries and backoff are configuration-owned and gated on `notifications.enabled`; `StaffRecipientResolver` resolves operational recipients from `CONFIRM_PAYMENTS`. Evidence: `TravelToursNotificationsTest` and the rendered notifications under `qa/communications-m4/`. |
| C-06 | Resolved by M6 (`e5554c8`) | `PrintsBookingReceipts` is bound to `BrowserReceiptPrinterDriver` with a typed print instruction; the desk prints a 58/80 mm receipt once after checkout and not on reload, and a print failure never rolls back the sale. Evidence: `qa/pob-m6-desk/` and `qa/pob-m6-desk-realigned/`. |
| C-07 | Split by owning phase | Each phase must classify and test the settings it makes live. K2 owns media/catalog settings, K3 owns pricing/scheduling, K5 owns booking/payment, K6 owns POB, and K7 owns notification settings. |
| C-08 | Resolved by M2 (`eddd9b0`) | Holds carry an owner token and an expiry; placement is idempotent on the hold ULID; the expired-hold page and the release/expiry console commands close the quote lifecycle. Evidence: `TravelToursCheckoutTest` and `qa/storefront-m2-checkout/`. |
| C-09 | Split across K2, K3, K5, and K6 | Permission and policy scope lands with the actions it protects. K2 starts with catalog view/manage/publish and resource-scoped queries; later phases own their corresponding capabilities. |
| C-10 | Open, with no waiver | System activity remains unwired: no TravelTours service writes `SystemActivity`. M4 delivered notifications, not the audit layer. Services retain deterministic tests and stable aggregate identifiers, so the integration stays additive; carried post-release alongside P-1. |

## Structural And QA Follow-Up

The storefront closeout resolves three review gaps beyond C-01 through C-03:

- `TravelToursDisabledModuleTest` proves that a disabled module registers no
  routes, views, policies, event listeners, or contract bindings.
- `TravelToursDemoSeeder` and its separate operator/access seeders provide an
  opt-in, idempotent public catalogue without mutating `DatabaseSeeder`.
- `qa:travel-tours-storefront` supplies committed diagnostics and screenshots
  for the currently implemented public pages.

Admin and POB Livewire components, Forms, owned assets, authorization actions,
and browser harnesses remain phase work, not review oversights that can be
closed by placeholder files. Root-level Policies/Events/Listeners should be
relocated into their bounded contexts before K2-K7 materially expand them.

## Release Rule

No assigned concern may be relabelled complete because an interface exists.
The concern is a hard gate for its owning phase, not a blanket prerequisite for
unrelated earlier work. The current phase map is authoritative in
`travel-tours-k1-reconciliation.md`.
