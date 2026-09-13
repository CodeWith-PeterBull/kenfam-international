# Aureon CMS Homepage Page Adoption Guide

**Route:** `/` (`home`)  
**Controller:** `App\Http\Controllers\HomeController`  
**View:** `resources/views/welcome.blade.php`  
**Registry:** `config/aureon-home.php`

## Purpose

The root page is the public, database-independent introduction to the Aureon CMS platform. It presents implemented modules, sends visitors into registered public module routes, exposes honest custom-quote pricing, and provides one shared account/workspace entry point.

The page deliberately does not resolve Institution Details or query module tables. It must remain renderable during first deployment, before fixtures are installed, and while an adopter is configuring the database.

## Adding A Module Card

Add one entry to `modules` in `config/aureon-home.php` using the existing entries as the canonical shape.

| Key | Requirement |
| --- | --- |
| `key` | Stable lowercase anchor identifier. |
| `number` | Two-character display order. |
| `name`, `label`, `description` | Concise public copy grounded in implemented behavior. |
| `status` | Release state shown only when the module link is available. |
| `enabled` | Module environment/config flag. |
| `public_route` | Named public route; the controller verifies it with `Route::has()`. |
| `icon` | Existing Lucide icon name. |
| `capabilities` | Three short, factual capability statements. |
| `scenarios` | Representative adopter contexts, not promises of unsupported features. |
| `pricing`, `pricing_note` | Use scoped pricing language unless a maintained price contract exists. Quote actions use the shared contact-card trigger. |
| `screenshots` | At least ten curated local image records with `src`, descriptive `alt`, short `label`, and explicit `role`. |

If a module is disabled or its route is not registered, its card remains descriptive but the live-module link is withheld and the state changes to `Available for adoption`.

## Showcase Assets

Store curated homepage media under:

`resources/aureon/assets/images/home/modules/`

The Vite static-copy pipeline publishes that directory beneath `public/aureon/assets`. Use WebP, strip metadata, keep each image below roughly 150 KB where practical, and retain a stable 16:10 stage crop. Never link the public page directly to `.docs`, temporary QA output, or `storage/app`.

The current source captures are maintained in the Commerce and Property Booking QA directories. The homepage copies are intentionally optimized derivatives and should be refreshed when a module's primary workflow materially changes. See `module-gallery-showcase.md` for the current public manifest and selection rules.

Each module gallery should cover the public journey, its operations dashboard,
important sidebar destinations, the primary transaction workspace, and at least
one role-specific view. The `role` value is visible in the stage and thumbnail;
use an operational audience such as `Cashier`, `Receptionist`, `Property
manager`, or `Administrator`, rather than a generic feature category.

The stage expander opens one shared Bootstrap full-screen viewer. Its title,
audience, counter, image, previous/next controls, and Left/Right keyboard
navigation are synchronized with the originating filmstrip. Preserve the
`data-gallery-*` and `data-lightbox-*` hooks when changing markup; JavaScript is
deliberately idempotent and supports both pre- and post-`DOMContentLoaded`
execution so delayed production bundles cannot leave the gallery inert.

## SEO And Sharing

`config/aureon-home.php` owns the title, description, social image, and public product name. The view emits canonical, robots, Open Graph, Twitter Card, Organization, SoftwareApplication, and module ItemList metadata.

The same registry owns `asset_version`. The homepage appends it to the 64px PNG
favicon and emits both `icon` and `shortcut icon` declarations. Increment this
value whenever the centralized favicon changes so browser and CDN icon caches
request the replacement immediately.

Keep claims factual, preserve one `h1`, give each showcase image meaningful alternative text, and validate structured data after changing module metadata. Configure the public URL through `APP_URL` before caching configuration in production.

## Shared Contact Card

Meta Software Developers contact details are centralized in the `provider` block of `config/aureon-home.php`. The reusable presentation component is:

```blade
<x-aureon-contact-card :contact="$provider" :product-name="$productName" />
```

Place the component once near the end of a Bootstrap-enabled page. Any quote action can open it without duplicating contact markup:

```html
<button type="button" data-aureon-contact-trigger aria-haspopup="dialog" aria-controls="aureonContactCard">
    Get a quote
</button>
```

`resources/js/aureon-home.js` opens the modal and safely closes a containing mobile offcanvas before transferring focus. The component owns email, telephone, WhatsApp, website, and location links. Keep E.164 telephone values and WhatsApp digits separate from human-readable numbers.

## Deployment

The public product label remains environment configurable with `AUREON_PRODUCT_NAME`. Provider contact details are maintained as reviewed public presentation data in `config/aureon-home.php` and are independent from SMTP sender settings.

After changing registry data or assets, run:

```bash
php artisan config:clear
npm run build
php artisan test tests/Feature/HomePageTest.php
npm run qa:homepage
```

On shared hosting, publish both `public/build` and the static-copy output at `public/aureon/assets`. The homepage uses the existing centralized logos, page loader, Bootstrap bundle, Lucide bundle, and theme controller.

Compiled Vite CSS and JavaScript entries use content-hashed filenames. Do not
replace these with stable output names: Hostinger serves build assets with a
long public cache lifetime, and a stable URL can continue returning a previous
bundle after the underlying file is replaced. Deploy the generated files and
their matching `manifest.json` as one release; Blade's `@vite` directive then
emits a new URL whenever entry content changes.

The shared `layouts.partials.loader-bootstrap` partial owns the loader's small
critical style block as well as its timing bootstrap. Keep this include in the
document head after `theme-init.js` and before page styles. The inline critical
rules prevent an unstyled loader first paint while page-specific Vite bundles
load; full page styles may extend the loader but must not be required for its
fixed, centered, theme-aware presentation.

## Browser Review Checklist

- Desktop, tablet, and mobile headers expose navigation, theme settings, and account access.
- Both screenshot filmstrips expose at least ten views and update the visible image, role, caption, alternative text, count, and pressed state.
- Previous/next controls and Left/Right keys traverse the complete gallery.
- The expand control opens the selected image full screen; modal arrows and Left/Right keys update both the viewer and originating filmstrip, and Escape restores focus.
- Light and dark modes preserve readable cards, tags, buttons, header, mobile offcanvas, and footer.
- Keyboard focus is visible; skip navigation and back-to-top controls work.
- No text overlaps at 360 px, 768 px, 1024 px, or wide desktop widths.
- Every enabled module link resolves and every unavailable module withholds a dead link.
