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
| C-04 | Open; required before K7 document acceptance | Raw booking/customer model graphs still need purpose-limited document and signed-page DTOs. PDF responses also need explicit private/no-store and noindex headers. |
| C-05 | Open; required before K7 notification acceptance | Listener/notification queue ownership, module guards, configured queue assignment, and operational recipient resolution require a dedicated event-delivery slice. |
| C-06 | Open; required before K6 POB acceptance | The browser receipt logger is not proof of printer delivery. Replace it with tested instruction/result and driver contracts or remove the binding until implemented. |
| C-07 | Open; configuration authority review | Classify each setting as live or future; remove duplicate/inert authorities and prove every retained live option with a consumer test. |
| C-08 | Open; required before K5 checkout acceptance | Add quote version and expiry to the typed quote contract and enforce the configured quote lifetime independently of hold expiry. |
| C-09 | Open; required before K2/K6 administration acceptance | Expand permissions and resource scoping for publication, sensitive travelers, refunds, registers, and reconciliation. Align seeded role grants with the published access matrix. |
| C-10 | Open; required before transactional release | Add privacy-safe system activity recording to every accepted write service, including booking, payment, holds, customers, and inquiries. |

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

No open concern may be relabelled as complete because an interface exists. C-04,
C-05, C-06, C-08, C-09, and C-10 are hard gates for their affected phases.
C-07 must be reconciled before adopter-facing configuration is frozen.
