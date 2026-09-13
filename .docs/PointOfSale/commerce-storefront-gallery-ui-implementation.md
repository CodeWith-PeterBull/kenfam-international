# Commerce Storefront Gallery and UI Refinements Implementation

## Record status

- **Module:** Commerce
- **Master phase:** 2, storefront and web ordering
- **Increment:** Product gallery slider + full-screen lightbox, nav hover fix, share divider
- **Branch:** `feature/commerce-refinements-fixes`
- **Implementation date:** 2026-07-22
- **Status:** Implemented and verified
- **Predecessor work:** Storefront product sharing, WhatsApp ordering, and SEO

## Objective

Turn the product page image area from a static main-image-plus-thumbnail swap
into a proper gallery slider with arrows, pagination dots, and a theme-aware
full-screen lightbox, benchmarked against the Woodmart product page. Fix two UI
defects at the same time: the header icon buttons whose glyph disappeared on
hover, and the cramped spacing between the share row and the product assurances.

## Locked decisions

| Topic | Decision |
| --- | --- |
| Slider library | Swiper, added via npm and **dynamic-imported** only when a gallery is present, so it code-splits into its own chunk instead of loading on every storefront page. This is the storefront's first third-party runtime library, chosen because more Swiper use cases are expected. |
| Full-screen mode | An in-page overlay lightbox (not the native Fullscreen API) so it works on iOS Safari and follows the theme tokens for background and controls. |
| Thumbnails | Kept as a plain vertical tab list and synced to the slider by hand, preserving the existing layout and accessibility without a second Swiper instance. |

## Implemented scope

1. Rebuilt the product gallery markup as a Swiper slider: one slide per gallery
   image, side arrows revealed on hover, dynamic pagination dots along the
   bottom edge, an expand button, and the discount badge overlaid.
2. Added `product-gallery.js`, a Swiper module (Navigation, Pagination,
   Keyboard, A11y, Zoom) that wires the slider, syncs the thumbnail tabs, and
   builds a theme-aware full-screen lightbox on first expand (arrows, dots,
   keyboard, pinch/zoom, Escape-to-close, scroll lock, focus restore).
3. Loaded the gallery module on demand from `storefront.js` so Swiper and its
   CSS ship as a separate async chunk fetched only on the product page.
4. Added slider, arrow, pagination, expand, and lightbox styles to the module
   storefront CSS, all driven by the existing theme tokens for light and dark.
5. Fixed the header icon-button hover defect at its root and added a faint
   divider with spacing between the share row and the product assurances.

## Root-cause fix: invisible icon on hover

The header search, cart, and account controls are `<a class="commerce-icon-button">`
elements rendered as direct children of `.commerce-primary-nav`. The text-link
rule `.commerce-primary-nav > a:hover { color: var(--theme-primary) }` did not
carry the `:not(.commerce-icon-button)` guard that its sibling rules already
used, so on hover it set the icon-button colour to the primary colour with a
higher specificity than `.commerce-icon-button:hover`. The button filled with
the primary colour and the glyph, inheriting `currentColor`, became the same
colour as its background.

The fix adds the missing `:not(.commerce-icon-button)` guard to that rule so the
icon-button's own hover colour (the readable `--theme-on-primary` contrast
colour) wins. The active/pressed state was also folded into the icon-button
hover rule so the glyph stays readable while the button is being clicked. A
headless browser diagnostic confirmed the fix: under a forced hover the button
colour is now `rgb(255, 255, 255)` on a `rgb(112, 35, 58)` primary background
(and white on green under a custom theme), where both previously resolved to the
primary colour.

## Image fit refinements

A follow-up pass tightened how images fill their frames, verified with a headless
diagnostic that reads the rendered versus natural image dimensions:

- The main gallery and thumbnails use `object-fit: contain` (no cropping) and the
  main-image padding was reduced (54px to 30px desktop, 34px to 18px mobile) so
  the product uses more of the frame.
- The lightbox image now scales up to fill the viewport instead of staying at its
  natural size. Swiper's zoom-container image carried `max-width/height` with no
  width or height, so a small image never grew; the image is now sized to the
  container and contained. The lightbox slide was changed from a centred grid to
  a stretching flex so the zoom container keeps a definite height — otherwise a
  tall image's `height: 100%` fell back to its intrinsic aspect ratio and
  overflowed the dialog (measured at 1100x2200 in a 920px-tall dialog before the
  fix, 1100x900 and contained after).

The seeded demonstration images are intentionally tiny (48x96 to 201x200 px), so
they look soft when enlarged; the fit logic is correct and real product imagery
renders crisply.

## Behaviour contract

- The slider shows arrows only on fine-pointer devices on hover; on touch the
  arrows and expand button stay visible because there is no hover to trigger
  them. The previous/next arrow hides at the first/last slide.
- Clicking a thumbnail moves the slider; swiping the slider marks the matching
  thumbnail active and scrolls it into view.
- The expand button opens the lightbox at the current slide. The lightbox locks
  body scroll, traps initial focus on the close control, restores focus on
  close, and closes on backdrop click, the close button, or Escape.
- Single-image products degrade gracefully: no arrows, dots, or thumbnails, but
  the expand-to-lightbox affordance remains.
- With JavaScript disabled the first image still renders as a normal image.

## File inventory

Added module implementation:

- `app/Modules/Commerce/Resources/js/product-gallery.js`

Updated module implementation:

- `app/Modules/Commerce/Resources/views/storefront/catalog/show.blade.php`
- `app/Modules/Commerce/Resources/js/storefront.js`
- `app/Modules/Commerce/Resources/css/storefront.css`

Updated integration files:

- `package.json` (Swiper dependency)
- `README.md`

Added verification assets:

- `scripts/qa-commerce-storefront-gallery.mjs`
- `.docs/dev/commerce-storefront-gallery-qa/*`

## Verification results

| Gate | Result |
| --- | --- |
| `node --check` (storefront.js, product-gallery.js) | Passed |
| `npm.cmd run build` | Vite build passed; Swiper split into `product-gallery` async JS and CSS chunks (main storefront JS unchanged at ~4 kB) |
| `php artisan test tests/Feature/Commerce` | 96 tests, 1,079 assertions passed |
| `php artisan test` | 216 tests, 1,693 assertions passed |
| `node scripts/qa-commerce-storefront-gallery.mjs` | All slider, thumbnail-sync, hover-contrast, divider, and lightbox checkpoints passed |

Browser QA verifies the Swiper initialisation, slide count, arrows, pagination
dots, thumbnail tabs, and the expand button; the thumbnail-to-slide sync; the
nav icon-button hover contrast in the default and a custom theme (computed
colours); the share/assurances divider border; and the lightbox opening,
scroll lock, controls, theme-aware light/dark rendering, and clean close with
scroll restore. It also asserts the gallery async chunk loads without a 404 and
that no runtime or network errors occur. Evidence, including five screenshots
and `diagnostics.json`, is stored under `.docs/dev/commerce-storefront-gallery-qa`.

## Deferred boundary

Per-slide captions, video slides, and a vertical thumbnail slider for very large
galleries remain out of scope. The lightbox uses the in-page overlay only; a
native Fullscreen API layer can be added later where it is reliable.
