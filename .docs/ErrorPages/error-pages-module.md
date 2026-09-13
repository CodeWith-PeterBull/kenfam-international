# Error Pages Module

**Status:** Implemented and verified  
**Benchmark:** CSK Laravel dashboard error views and `resources/img/server-status` artwork  
**Owner:** Laravel Aureon base engine

## Purpose

Provide branded, responsive exception pages that remain usable when the normal dashboard shell, database-backed institution profile, or Vite manifest is unavailable. The module covers HTTP 401, 403, 404, 419, 500, and 503 responses.

## Planned implementation

- Reuse the CSK status illustrations already present under `resources/img/server-status/`.
- Keep stable semantic filenames so adopters can replace artwork without editing Blade templates.
- Render all statuses through one dependency-light Blade component.
- Load Bootstrap, Tabler Icons, and a dedicated error stylesheet through stable `public/build` paths.
- Respect the persisted Aureon light/dark setting without loading the dashboard shell.
- Provide status-specific actions instead of sending every failure to browser history.
- Mark 419 responses as non-cacheable and request origin HTTP-cache eviction.
- Provide a 419 recovery action that clears session-scoped form state and Cache Storage before navigating to a cache-busted login page.
- Cover status, artwork, actions, recovery script, and response headers with feature tests.

## 419 recovery boundary

Browsers do not expose password-manager or complete HTTP-cache deletion to arbitrary JavaScript. Recovery therefore combines the mechanisms the application can safely control:

1. `Cache-Control: no-store` prevents the expired response from being reused.
2. `Clear-Site-Data: "cache"` asks supported secure-context browsers to evict origin HTTP cache.
3. Cache Storage entries and `sessionStorage` are cleared client-side.
4. Known Aureon/Livewire form-draft keys are removed without deleting theme preferences or unrelated persistent settings.
5. Navigation uses a cache-busting query parameter so the login form is fetched with a fresh CSRF token.

Cookies are intentionally retained. Clearing all cookies or origin storage from an exception page would remove unrelated application state and is not required to recover from an expired CSRF token.

## Artwork adoption contract

Replace files in `resources/img/server-status/` while preserving these names:

- `401-unauthorized.png`
- `403-forbidden.png`
- `404-not-found.png`
- `419-page-expired.png`
- `500-server-error.png`
- `503-maintenance.gif`
- `503-service-unavailable.png`

Run `npm run build` after replacement so Vite's static-copy pipeline republishes them to `public/build/img/server-status/`.

## Final implementation record

- Added `resources/views/components/error-page.blade.php` as the shared standalone exception shell.
- Added dedicated 401, 403, 404, 419, 500, and 503 views under `resources/views/errors/`.
- Added `resources/css/aureon-errors.css` with responsive light/dark presentation independent of dashboard startup.
- Confirmed every source illustration is byte-identical to the CSK benchmark asset.
- Added the stable artwork replacement guide at `resources/img/server-status/README.md`.
- Added 419 response finalization in `bootstrap/app.php` for no-store, no-cache, expiry, and cache-clear headers.
- Added client recovery for session form state, known draft keys, Cache Storage, and cache-busted login navigation.
- Added `tests/Feature/ErrorPages/ErrorPageRenderingTest.php` covering all states, assets, headers, and recovery behavior.

## Verification

- Six exception statuses render through Laravel's production exception pipeline.
- The 419 response exposes the expected recovery controls and normalized headers.
- Blade view cache succeeds.
- Vite production build publishes `build/css/aureon-errors.css` and the status artwork.
- Full application suite passes with 119 tests and 595 assertions.
