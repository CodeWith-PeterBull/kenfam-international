# Laravel Aureon Dashboard Theme Controller

Date: 2026-07-15

Branch: `feature/laravel-aureon-base-engine`

## Purpose

The dashboard now adapts the useful DreamPOS settings runtime without changing the established Aureon dashboard shell. One first-party controller owns appearance state, the header mode button, the settings offcanvas, layout classes, CSS tokens, sidebar imagery, and the preloader lifecycle.

## Blade composition

- `resources/views/components/loader.blade.php`: branded `#global-loader` component using the DreamPOS loader hook.
- `resources/views/layouts/partials/theme-settings.blade.php`: small pre-paint bootstrap that restores persisted attributes before stylesheets render.
- `resources/views/layouts/partials/dashboard-settings.blade.php`: Bootstrap 5 settings offcanvas and desktop edge trigger.
- `resources/views/layouts/partials/topbar-admin.blade.php`: persistent light/dark button plus an accessible settings trigger.
- `resources/views/layouts/dashboard-layout.blade.php`: composes the pieces without changing the header/sidebar/page-wrapper hierarchy.

## Controller contract

`resources/js/aureon-dashboard.js` stores one JSON document under:

```text
laravel-aureon-dashboard-settings
```

Schema:

```json
{
  "mode": "light | dark | system",
  "layout": "default | mini | detached",
  "width": "fluid | box",
  "palette": "wine | teal | gold | graphite | custom",
  "customPrimary": "#70233a",
  "sidebar": "theme | light | dark | brand",
  "sidebarBackground": "none | sidebarbg1 ... sidebarbg6"
}
```

The legacy `laravel-aureon-dashboard-theme` value is read once for compatibility. New writes preserve it as the resolved light/dark value while the JSON object remains authoritative.

The controller applies `data-bs-theme`, `data-theme`, `data-layout`, `data-width`, `data-color`, `data-sidebar`, and `data-sidebar-background` to `<html>`. DreamPOS-compatible `mini-sidebar`, `layout-box-mode`, and `data-sidebarbg` body hooks are maintained where its existing runtime expects them.

Changes dispatch `aureon:dashboard-settings` on `window`. Future charts or module-specific components can listen for this event and read `event.detail` instead of inspecting controls.

## Theme tokens

The palette selector updates these central properties:

- `--aureon-primary`
- `--aureon-primary-dark`
- `--aureon-primary-rgb`
- `--aureon-secondary`
- `--aureon-accent`
- `--bs-primary-rgb`

The welcome panel consumes the active primary token and overlays the copied DreamPOS `img/bg/welcome-bg-01.svg` and `img/bg/welcome-bg-02.svg` artwork. This keeps the panel aligned with preset and custom colors.

## Sidebar images

The six controls use optimized `sidebar-thumb-01.webp` through `sidebar-thumb-06.webp` derivatives of the actual backgrounds. Only the selected `bg-01.jpg` through `bg-06.jpg` background is requested at full size, avoiding the original multi-megabyte SVG preview payload. The selected image is applied to the sidebar root while its generated scroll wrappers remain transparent. Theme-aware translucent overlays preserve menu contrast without hiding the artwork.

The logo rail follows application mode independently of the selected sidebar surface. Light mode uses a white rail with `logo.png`; dark mode uses the night rail with `logo-light.png`. Compact mode retains the centralized icon mark.

Dark sidebar active and hover states explicitly pair white text with a translucent light overlay. This fixes the inherited white-text-on-white-background defect while retaining the existing light-mode behavior.

## Preloader behavior

The loader keeps the DreamPOS `#global-loader` selector while adopting the active corporate frontend lifecycle. `resources/views/layouts/partials/loader-bootstrap.blade.php` runs before stylesheets, displays the centralized Aureon icon immediately, waits at least three seconds after a painted DOM, exits over 420 milliseconds, and has an eight-second hard fail-safe. Reduced-motion users bypass the hold and animation. The loader surface consumes the already-restored dashboard mode, so dark mode never flashes a white cover.

Durations are centralized in `config/aureon.php`. Dismissal sets `data-page-loader-state="ready"`, records the visible duration, hides the loader, and emits `aureon:page-loader-dismissed` for dependent components.

## Extension rules

1. Add new appearance state to the schema, early bootstrap, normalizer, offcanvas control, and reset defaults together.
2. Keep visual values in Aureon CSS variables; module styles should consume tokens instead of duplicating palette literals.
3. Do not introduce a second local-storage key or separate dark-mode controller.
4. Preserve the current dashboard DOM ownership boundaries when adding DreamPOS-compatible layouts.
5. Extend `scripts/qa-dashboard.mjs` whenever a new persisted setting affects responsive layout or contrast.

## Verification

Run:

```text
php artisan view:cache
php artisan test
npm.cmd run build
npm.cmd run qa:dashboard
```

Browser evidence is written to `.docs/dev/dashboard-qa/`.
