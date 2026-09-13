# Commerce POS Add-to-Cart Sound Implementation

## Record status

- **Module:** Commerce (Point of Sale)
- **Increment:** Configurable cashier beep on a successful add-to-cart
- **Branch:** `feature/commerce-refinements-fixes`
- **Implementation date:** 2026-07-23
- **Status:** Implemented and verified

## Objective

Give the cashier an optional audible confirmation when a product is successfully
added to the POS cart, without changing the existing visual feedback (the
products section blurs during the Livewire round-trip and must remain). The sound
is a single invoked step in the add flow and is switchable from configuration.

## Design

The feature is split into one server signal and one client player, mirroring the
existing cancelable `commerce:receipt-print` client-event pattern.

| Layer | Responsibility |
| --- | --- |
| Server (`Terminal::beepAddToCart`) | Invoked on the success path of `addProduct()` (after the cart is updated). When `commerce.pos.sound.add_to_cart_enabled` is on, it dispatches the `commerce-pos-cart-added` Livewire browser event. Failure paths return before this call, so the beep only follows a genuine add. `increase()` delegates to `addProduct()`, so quantity bumps beep too; `decrease()`/`remove()` do not. |
| Client (`pos.js`) | On `livewire:init`, registers `Livewire.on('commerce-pos-cart-added', playAddToCartBeep)` and binds a one-time audio unlock. `playAddToCartBeep()` synthesises a short 880 Hz sine tone with the Web Audio API. |

The server owns the on/off decision, so the client stays a simple "on event →
beep" listener. The blur is untouched: it is `wire:loading` CSS on the products
section and is independent of this dispatch.

## Sound and browser policy

- The beep is generated with the Web Audio API (an ~80 ms sine tone with a short
  gain envelope to avoid clicks), so it needs no audio asset and stays fully
  self-contained.
- Browsers block audio until a user gesture. The terminal is click-heavy, so the
  `AudioContext` is resumed on the first `pointerdown`/`keydown`. Because adding a
  product is itself a click, the context unlocks on that same gesture and even
  the first beep plays. If `AudioContext` is unavailable, the player no-ops.

## Configuration

```php
// config/commerce.php → pos
'sound' => [
    'add_to_cart_enabled' => (bool) env('COMMERCE_POS_ADD_TO_CART_SOUND', true),
],
```

Environment default: `COMMERCE_POS_ADD_TO_CART_SOUND=true`.

## File inventory

Modified:

- `app/Modules/Commerce/Config/commerce.php`, `.env.example`
- `app/Modules/Commerce/PointOfSale/Livewire/Terminal.php`
- `app/Modules/Commerce/Resources/js/pos.js`
- `tests/Feature/Commerce/CommercePosInterfaceTest.php`
- `README.md`

## Verification results

| Gate | Result |
| --- | --- |
| `node --check pos.js` | Passed |
| `vendor\bin\pint.bat` (Terminal, config, test) | Passed |
| `php artisan test tests/Feature/Commerce/CommercePosInterfaceTest.php` | 11 tests passed (3 new) |
| `php artisan test` | 228 tests, 1,759 assertions passed |
| `npm.cmd run build` | Vite build passed (`pos2.js`) |
| Client beep wiring QA (headless) | Listener registered on `livewire:init`, unlock gestures bound, event produced exactly one 880 Hz sine beep |

The server tests assert the event is dispatched on a successful add when the
sound is enabled, is not dispatched when the config is disabled, and is not
dispatched when the add fails (minimum quantity above available stock). The
client QA loads the built `pos.js` with a stubbed Livewire and an instrumented
`AudioContext` and confirms the end-to-end event-to-beep path.

## Deferred boundary

Only the add-to-cart beep is delivered. Other cashier cues (errors, checkout
completion) and sound tuning (volume, frequency, alternate tones) can reuse the
same `beepAddToCart`/`playAddToCartBeep` shape when needed.
