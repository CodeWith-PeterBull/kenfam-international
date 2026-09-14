# Kenfam Brand And Asset Register

## 1. Central Ownership

Client public resources live under resources/kenfam/assets and are copied to
public/kenfam/assets by the build. The institution/branding resolver selects
logos and social images for shared layouts, mail and reports. Generic TravelTours
views may not hard-code a Kenfam path or company name.

The draft contains the assets below; their existence is not visual approval.

| Asset | Intended use | Provenance and verification |
| --- | --- | --- |
| brand/logo-light.png | Dark header/footer | Earlier download of official Kenfam-Web-White.png; confirm rights/version |
| brand/logo-dark.png | Light header and print | Derived draft; inspect alpha/contrast before use |
| brand/favicon.png | Browser favicon | Draft derivative; validate real PNG MIME and production URL |
| brand/social-card.webp | Social share card | Draft composite; validate legibility and crawler delivery |
| images/kenfam-travel-hero.webp | Client homepage imagery | Generated illustration; not evidence of an included tour/accommodation |

Earlier source URL:
https://kenfam.co.ke/wp-content/uploads/2018/12/Kenfam-Web-White.png
Do not interpret a historical URL as current brand approval. Record source,
license/permission, author, date, transformation and approving reviewer for every
replacement. Do not copy the existing site's tour gallery as product inventory.

## 2. Asset Acceptance

Check transparency, intrinsic dimensions, light/dark contrast and mobile logo
bounds. Provide full/small/icon variants only when genuinely needed by the
existing scaffold. Avoid unsupported claims embedded in a social image.
Use compressed WebP/AVIF where supported for photos and PNG for required brand
variants. Do not upscale small logos into screenshots or hero backgrounds.

Gallery images require alt text, caption, focal point and display order. Preserve
original source separately from optimized delivery files where licensing permits.
Synthetic images must be marked in editorial metadata and not sold as literal
photographs of accommodation, equipment or included activities.

## 3. Theme And Components

Reuse Aureon centralized color/type tokens, light default/system/dark behavior,
pre-paint theme initialization, header/footer and smooth nonblocking loader.
Client theme defaults belong in branding configuration. Module CSS consumes
semantic tokens, not a parallel hard-coded palette.

Validate logo variants on public, auth, dashboard, emails, A4 documents and roll
receipts. Browser print must remain light paper even when application mode is
dark. Generated media is not a substitute for rendering and screenshot QA.

## 4. Hosting Checks

Vite manifest upload alone does not copy non-Vite resources. Test logo, favicon,
theme-init, CSS, JS and social URLs for HTTP 200 and correct content type.
Version/cache-bust changed assets deliberately. A route returning HTML at an
image/CSS URL is a deployment error, not a browser cache problem.
Private traveler documents never enter this public resource tree.
