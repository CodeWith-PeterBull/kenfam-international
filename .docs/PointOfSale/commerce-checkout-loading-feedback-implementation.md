# Commerce Storefront Checkout Loading Feedback Implementation

## Record status

- **Module:** Commerce (Storefront)
- **Increment:** Loading affordances on the storefront checkout
- **Branch:** `feature/commerce-refinements-fixes`
- **Implementation date:** 2026-07-23
- **Status:** Implemented and verified

## Objective

Give the shopper clear feedback while the checkout is working: the Place order
button must show a loading state while the order is being placed, and a status
line below the form must show while form inputs are syncing to the server.

## Changes

| Concern | Change |
| --- | --- |
| Place order button | The lock icon swaps to an inline spinner and the label swaps to "Placing order…" while the `place` action runs (`wire:loading` / `wire:target="place"`); the button also disables during placement. |
| Field-sync status | A new status line below the checkout form shows "Updating your order…" with a spinner during any component request except placement (`wire:loading.delay` + `wire:target.except="place"`), so it reacts to the blur/live field syncs without competing with the button. |
| Styles | Added a reusable `.commerce-spinner` (CSS-only rotating ring inheriting `currentColor`) and `.commerce-checkout-status`, both theme-token driven, with a reduced-motion fallback. |

The two indicators are deliberately scoped so they never both fire for the same
request: the button owns the `place` submit, the status line owns everything
else. The spinner needs no image asset.

## File inventory

Modified:

- `app/Modules/Commerce/Resources/views/livewire/storefront/checkout.blade.php`
- `app/Modules/Commerce/Resources/css/storefront.css`
- `tests/Feature/Commerce/CommerceStorefrontOrderingTest.php`
- `README.md`

## Verification results

| Gate | Result |
| --- | --- |
| `vendor\bin\pint.bat` (test) | Passed |
| `php artisan test tests/Feature/Commerce/CommerceStorefrontOrderingTest.php` | 9 tests passed (1 new markup guard) |
| `php artisan test` | 229 tests, 1,764 assertions passed |
| `npm.cmd run build` | Vite build passed |
| Headless checkout QA | With the network throttled to widen the window: the status line appeared during a field sync and the Place order button showed the disabled spinner + "Placing order…" during submission (validation blocked the incomplete guest form, so no order was created). Screenshots confirm both states. |

The PHP test guards the markup (status element, `wire:target.except="place"`,
spinner class, and both labels); the browser QA proves the states actually
trigger and render.
