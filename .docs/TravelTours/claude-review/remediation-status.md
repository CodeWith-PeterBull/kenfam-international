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
| C-04 | K1 response boundary resolved; DTO work assigned to K7 | Signed booking HTML and PDF responses now send private/no-store, noindex, and nosniff headers. Purpose-limited confirmation/tracking/report projections remain a K7 document-adapter acceptance item. |
| C-05 | Assigned to K7 | Queue ownership, module guards, operational recipient resolution, notification content, and Mailpit evidence belong to the complete communications layer. Existing events remain a foundation contract, not accepted delivery evidence. |
| C-06 | K1 misleading binding resolved; implementation assigned to K6 | The log-only `BrowserReceiptPrinter` was removed and `PrintsBookingReceipts` is intentionally unbound. K6 must introduce a typed instruction/result and real browser or device driver before advertising printing. |
| C-07 | Split by owning phase | Each phase must classify and test the settings it makes live. K2 owns media/catalog settings, K3 owns pricing/scheduling, K5 owns booking/payment, K6 owns POB, and K7 owns notification settings. |
| C-08 | Assigned to K5 | Quote version, expiry, checkout repricing, and hold/quote interaction are acceptance criteria for the public booking vertical slice. |
| C-09 | Split across K2, K3, K5, and K6 | Permission and policy scope lands with the actions it protects. K2 starts with catalog view/manage/publish and resource-scoped queries; later phases own their corresponding capabilities. |
| C-10 | Assigned to K7, with no waiver | System activity is an operational cross-cutting layer. K7 must wire every accepted write before operational release; earlier phases retain deterministic service tests and must expose stable aggregate identifiers for later audit integration. |

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
