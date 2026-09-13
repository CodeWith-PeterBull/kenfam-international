# Commerce Storefront Sharing and SEO Implementation

## Record status

- **Module:** Commerce
- **Master phase:** 2, storefront and web ordering
- **Increment:** Product-page social sharing, WhatsApp ordering, and product/organization SEO
- **Branch:** `feature/commerce-refinements-fixes`
- **Implementation date:** 2026-07-22
- **Status:** Implemented and verified
- **Predecessor commit:** `d7987df Merge branch 'feature/commerce-refinements-fixes' into feature/laravel-aureon-base-engine`

## Objective

Give the public product detail page a first-class sharing surface, a direct
WhatsApp ordering path, and product-specific structured data so a shared link
renders a rich preview on every platform. All contact and social identity must
come from the existing Institution Details resolver, and both the share group
and the WhatsApp-order button must be independently switchable from the commerce
configuration without a schema change.

## Locked decisions

| Topic | Decision |
| --- | --- |
| Placement | The share group and the "Buy via WhatsApp" button live on the product single page only. `/shop` is unchanged but inherits the site-wide SEO additions. |
| TikTok / Instagram | Facebook, X, and WhatsApp use real web share intents. TikTok, Instagram, and Copy write the product link to the clipboard (they have no web URL-share intent). A native Share button (Web Share API) is revealed where the browser supports it. |
| WhatsApp number | Resolved from an Institution Details social entry whose platform mentions "whatsapp" (parsing the url or handle), then falling back to the institution primary phone. The button is hidden when neither resolves. No migration. |

## Implemented scope

1. Added a `storefront.sharing` block (enable flag plus an optional platform
   allow-list) and a `storefront.whatsapp_order` block (enable flag) to the
   commerce configuration, each backed by an environment variable.
2. Added `InstitutionContact`, a pure helper built from `InstitutionProfileData`
   that resolves the WhatsApp ordering number, builds the prefilled `wa.me`
   order link, exposes the `sameAs` URL list, and derives the X handle for the
   Twitter card.
3. Added a config-gated `share.blade.php` partial that renders Facebook, X,
   WhatsApp, TikTok, Instagram, Copy, and native-share controls with inline
   monochrome brand SVGs and hover labels.
4. Extended `storefront.js` with clipboard copy (secure-context `writeText` with
   a legacy `execCommand` fallback), a transient toast, native Web Share, and
   popup share windows, wired through the existing `initialize()` seam.
5. Extended the product page with `og:type=product`, product OG price metadata,
   Product JSON-LD, the WhatsApp order button, and the share partial.
6. Extended the storefront layout with a variable `og:type`, `twitter:site`,
   image alt tags, and site-wide Organization JSON-LD, all inherited by `/shop`.
7. Added share, toast, and WhatsApp-button styles to the module storefront CSS
   (light/dark aware) and rebuilt the Vite bundle.

## Guardrails

- The share group and WhatsApp button are additive to the product page only; no
  cart, checkout, or catalog behaviour changes.
- Contact and social identity come exclusively from the institution resolver.
  The institution social list stays free-form (`platform`/`handle`/`url`); no
  new column or enum was introduced.
- The WhatsApp number is digits-only, drops a leading international `00`, and
  requires at least seven digits; the button hides rather than link to a broken
  `wa.me` when no number resolves.
- JSON-LD price is the decimal major-unit string via `ScaledDecimal`, never the
  persisted minor integer. Currency is `config('commerce.currency.code')`.
- TikTok and Instagram cannot receive a prefilled web URL share, so they copy
  the link with a platform hint instead of pretending to open a share intent.
- Brand marks are inline monochrome SVGs (`fill=currentColor`) so they theme
  correctly and add no icon-font or CDN dependency.

## Configuration contract

| Key | Env | Default | Effect |
| --- | --- | --- | --- |
| `commerce.storefront.sharing.enabled` | `COMMERCE_STOREFRONT_SHARING_ENABLED` | `true` | Renders or hides the entire product share group. |
| `commerce.storefront.sharing.platforms` | `COMMERCE_STOREFRONT_SHARING_PLATFORMS` | empty (all) | Comma-separated allow-list (`facebook,x,whatsapp,tiktok,instagram,copy`); empty means all supported platforms. |
| `commerce.storefront.whatsapp_order.enabled` | `COMMERCE_STOREFRONT_WHATSAPP_ORDER_ENABLED` | `true` | Renders or hides the "Buy via WhatsApp" button. |

## Share behaviour contract

- **Facebook / X / WhatsApp:** anchors to the real web share intent, opened by
  `storefront.js` in a sized popup window (`data-share-window`).
- **TikTok / Instagram:** buttons that copy the product link and toast a
  platform hint (`data-share-copy` + `data-share-hint`).
- **Copy link:** copies the product link and toasts "Product link copied".
- **Native Share:** hidden by default and revealed only when `navigator.share`
  exists (`data-share-native`), invoking the OS share sheet.
- Clipboard writes prefer `navigator.clipboard.writeText` in a secure context
  and fall back to a hidden-textarea `execCommand('copy')`. A failed copy toasts
  a "copy the address bar link" message rather than failing silently.

## SEO contract

- The product page emits `og:type=product`, `product:price:amount` /
  `product:price:currency` OG tags, and a Product JSON-LD block with `name`,
  `image`, `description`, `sku`, `brand`, and an `offers` object carrying the
  decimal `price`, `priceCurrency`, `availability` (`InStock`/`OutOfStock` from
  stock state), `url`, and `seller`.
- The layout emits site-wide Organization JSON-LD (`name`, `url`, `logo`,
  `email`, `telephone`, `sameAs`) filtered of empty values, plus `twitter:site`
  from the resolved X handle when present and `og:image:alt` / `twitter:image:alt`.
- `/shop` inherits the Organization structured data and Twitter metadata but
  keeps `og:type=website`.

## File inventory

Added module implementation:

- `app/Modules/Commerce/Support/InstitutionContact.php`
- `app/Modules/Commerce/Resources/views/storefront/partials/share.blade.php`

Updated module implementation:

- `app/Modules/Commerce/Config/commerce.php`
- `app/Modules/Commerce/Resources/views/layouts/storefront.blade.php`
- `app/Modules/Commerce/Resources/views/storefront/catalog/show.blade.php`
- `app/Modules/Commerce/Resources/js/storefront.js`
- `app/Modules/Commerce/Resources/css/storefront.css`

Updated integration files:

- `.env.example`
- `README.md`

Added verification assets:

- `tests/Feature/Commerce/InstitutionContactTest.php`
- `tests/Feature/Commerce/CommerceStorefrontShareTest.php`
- `scripts/qa-commerce-storefront-share.mjs`
- `.docs/dev/commerce-storefront-share-qa/*`

## Verification results

| Gate | Result |
| --- | --- |
| `vendor\bin\pint.bat --test` (module + tests) | Passed |
| `php artisan test tests/Feature/Commerce/InstitutionContactTest.php` | 4 tests passed |
| `php artisan test tests/Feature/Commerce/CommerceStorefrontShareTest.php` | 5 tests, 36 assertions passed |
| `npm.cmd run build` | Vite production build passed |
| `php artisan test` | 216 tests, 1,693 assertions passed |
| `node scripts/qa-commerce-storefront-share.mjs` | All share, WhatsApp, SEO, and clipboard checkpoints passed |

`InstitutionContactTest` covers WhatsApp number resolution from a social
url/handle, the national-number and primary-phone fallbacks, the null case, the
`wa.me` order-url build, the `sameAs` list, and the X-handle derivation.
`CommerceStorefrontShareTest` asserts the rendered share group and WhatsApp
button, the primary-phone fallback, both config flags, the hidden-button case,
and the Product plus Organization JSON-LD with the correct price, currency, and
availability.

Browser QA drives the public product page with a headless Chromium browser and
verifies the share icon group with hover labels, the brand SVGs, the WhatsApp
button and its `wa.me` href, `og:type=product`, the Twitter card, the Product
and Organization JSON-LD, and the absence of horizontal overflow across desktop
light, desktop dark, and mobile. It grants clipboard permission and clicks Copy
to confirm the real "Product link copied" success toast. Evidence, including the
four screenshots and `diagnostics.json`, is stored under
`.docs/dev/commerce-storefront-share-qa`.

The QA harness force-dismisses the shared bootstrap page loader (whose minimum
visible window would otherwise cover the screenshot) before each capture.

## Deferred boundary

Server-side share analytics, per-product Open Graph imagery beyond the primary
gallery image, and richer schema.org fields (reviews, aggregate ratings) remain
out of scope. WhatsApp ordering is a prefilled message hand-off, not an
in-platform commerce integration.
