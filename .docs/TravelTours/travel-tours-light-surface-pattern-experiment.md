# Travel Tours Light Surface Pattern Experiment

## Objective

Apply the reusable `rocking_grid_bg.webp` tile to the public travel canvas, dashboard content canvas, and booking-desk workspace in light mode. Dark mode retains its existing solid surfaces.

## Source Of Truth

- Source asset: `resources/img/bg_patterns/rocking_grid_bg.webp`
- Retained alternative asset: `resources/img/bg_patterns/bg_pattern.svg`
- Built asset: `/build/img/bg_patterns/rocking_grid_bg.webp`
- Storefront theme source: `app/Modules/TravelTours/Resources/assets/css/storefront.css`
- Dashboard theme source: `resources/css/aureon-dashboard.css`
- Booking-desk theme source: `app/Modules/TravelTours/Resources/assets/css/pob.css`

Vite already copies `resources/img` into `public/build/img`, so the theme styles reference the stable built asset path without introducing a second asset copy.

`rocking_grid_bg.webp` is the active lightweight repeating tile. `bg_pattern.svg` is retained as an available alternative but is not layered into the current interface.

## Scope

The storefront pattern is attached to `body.travel-storefront`. Transparent light sections, including the homepage tour area and the tour catalog results area, expose the patterned page canvas. Explicit soft and alternate sections, cards, navigation, and overlays retain their established surfaces.

The dashboard pattern is limited to `.page-wrapper.aureon-dashboard > .content`. It does not affect the sidebar, top bar, footer, offcanvas panels, modals, or component surfaces.

The booking desk applies the same token to `body.travel-tours-pob-shell`. Its cards, toolbars, dialogs, and print-safe receipt remain solid surfaces, while the surrounding operational canvas receives the pattern.

Both themes use one image token and one tile-size token. Their dark-mode token overrides the image with `none`, preserving the existing dark presentation.

## Verification

The storefront and dashboard browser harnesses assert that the pattern is present in light mode and absent in dark mode. Authenticated booking-desk captures apply the same mode assertion. Verification also covers the production asset build and representative desktop and mobile screenshots.

The homepage hero adds a slow image zoom from a top-right focal origin. Its duration is derived from the same transition and autoplay constants used by Swiper, and the animation is removed when the browser requests reduced motion.

## Verification Evidence

- `npm run build`: passed; both pattern assets were copied through the established Vite static-copy pipeline.
- `npm run qa:travel-tours-storefront`: passed with 15 inspections, including light homepage/catalog pattern checks, dark-mode exclusion, repeated slider navigation, 7.72-second hero-image timing, and reduced-motion suppression.
- `npm run qa:travel-tours-admin`: passed with 11 captures, including desktop-light and mobile-dark booking-desk coverage plus dashboard pattern assertions.
- Visual review: desktop-light homepage, catalog, dashboard, and booking desk display the repeat cleanly; mobile-dark dashboard and booking desk retain solid dark canvases.
- Known external condition: blocked Google Fonts requests are retained as `externalWarnings` in QA diagnostics and do not mask local asset or runtime failures.
