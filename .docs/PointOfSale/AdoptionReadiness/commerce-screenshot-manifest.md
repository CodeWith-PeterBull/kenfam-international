# Commerce Screenshot Manifest

**Status:** Phase 4.4 evidence index and Phase 4.5 capture contract.

**Updated:** 2026-09-06

## 1. Purpose

This manifest separates existing implementation evidence from the final release
matrix. A screenshot is supporting evidence, not proof by itself: every capture
must remain paired with diagnostics, automated assertions, and the exact build
and fixture state that produced it.

The repository currently retains 73 Commerce PNG captures across focused QA
folders. They demonstrate delivered surfaces, but they were produced across
several feature increments and do not yet constitute one fresh Phase 4.5 release
run. Nine contextual product-image contact sheets are retained separately as
JPEG review evidence and are not included in that PNG count.

## 2. Evidence Rules

Every final capture must:

1. Use deterministic local/test data, never real customer or payment details.
2. Record route, viewport, theme, reduced-motion state, authenticated identity,
   and relevant fixture ULID/order number in diagnostics.
3. Wait for the Aureon loader and Livewire network activity to settle.
4. Verify zero browser runtime errors, failed requests, broken images, duplicate
   IDs, document-level horizontal overflow, and incoherent text overlap.
5. Preserve visible focus or interaction state where that behavior is under
   review.
6. Use full-page and viewport-only captures only when each proves a distinct
   layout fact.
7. Capture print media separately from dark/light screen presentation.
8. Redact secrets and avoid storing signed URLs, session cookies, mail transport
   credentials, raw payment metadata, or customer tax identifiers.
9. Store machine-readable `diagnostics.json` beside generated screenshots.
10. Keep temporary browser profiles, server logs, and generated customer data
    out of committed release evidence unless a failure diagnosis requires them.

## 3. Standard Viewports and Modes

| Label | Width | Suggested height | Required modes |
| --- | ---: | ---: | --- |
| Desktop | 1440 | 1000 | Light and selected dark states |
| Laptop | 1080 | 900 | Dark for dense layouts |
| Tablet | 820 | 1180 | Light or dark by matrix |
| Mobile | 390 | 844 | Light and selected dark states |

At least one public and one authenticated mobile case must use reduced motion.
Theme persistence must be tested by navigation/reload, not only by forcing a
DOM attribute immediately before capture.

## 4. Existing Storefront Evidence

Directory: `.docs/dev/commerce-qa/`

| File | Existing evidence |
| --- | --- |
| `catalog-desktop.png` | Desktop public catalog and product grid |
| `catalog-tablet.png` | Tablet header and catalog layout |
| `catalog-mobile.png` | Mobile header/catalog containment |
| `product-detail-desktop.png` | Product detail content and buying controls |
| `product-detail-mobile-dark.png` | Mobile dark product detail |
| `cart-desktop.png` | Session cart and order summary |
| `checkout-desktop.png` | Desktop checkout structure |
| `checkout-laptop-dark.png` | Dark checkout at laptop width |
| `checkout-mobile-dark.png` | Mobile dark checkout containment |
| `order-confirmation-desktop.png` | Signed confirmation page |
| `order-tracking-mobile-dark.png` | Signed tracking page on mobile dark |
| `diagnostics.json` | Harness assertions and browser findings |

`server*.log` and `server*.err` in this folder are diagnostic remnants, not
curated screenshots. They should not be cited as visual release evidence.

## 5. Existing Product Gallery Evidence

Directory: `.docs/dev/commerce-storefront-gallery-qa/`

| File | Existing evidence |
| --- | --- |
| `gallery-desktop-light.png` | Desktop gallery sizing and controls |
| `gallery-mobile-light.png` | Mobile gallery initialization and fit |
| `lightbox-desktop-light.png` | Full-screen lightbox, light theme |
| `lightbox-desktop-dark.png` | Full-screen lightbox, dark theme |
| `nav-hover-default.png` | Product-page icon hover visibility |
| `diagnostics.json` | Swiper, lightbox, overflow, and focus checks |

## 6. Existing Product Sharing Evidence

Directory: `.docs/dev/commerce-storefront-share-qa/`

| File | Existing evidence |
| --- | --- |
| `product-share-desktop-light.png` | Complete share group in light theme |
| `product-share-desktop-dark.png` | Share group contrast in dark theme |
| `product-share-mobile-light.png` | Mobile wrapping/containment |
| `product-share-copy-toast.png` | Clipboard feedback state |
| `diagnostics.json` | Link, label, icon, toast, and overflow checks |

## 7. Existing Catalog and Inventory Administration Evidence

Directory: `.docs/dev/dashboard-qa/`

| File | Existing evidence |
| --- | --- |
| `commerce-catalog-desktop.png` | Product/category administration desktop |
| `commerce-catalog-mobile.png` | Mobile tables and controls containment |
| `commerce-inventory-desktop.png` | Stock projection and movement workspace |
| `commerce-inventory-mobile.png` | Mobile inventory layout and scrollers |

These captures are generated by the broader dashboard harness; their
diagnostics live with the dashboard QA output rather than a dedicated Commerce
folder.

## 8. Existing Barcode Evidence

Directory: `.docs/dev/commerce-barcode-qa/`

| File | Existing evidence |
| --- | --- |
| `catalog-product-form-barcodes.png` | Internal/manufacturer barcode form fields |
| `barcode-workspace-desktop-light.png` | Label-run workspace, desktop light |
| `barcode-workspace-desktop-dark.png` | Label workspace dark theme |
| `barcode-workspace-mobile-dark.png` | Mobile dark label workspace |
| `diagnostics.json` | Component, labels, PDF, theme, and overflow checks |

## 9. Existing Commerce Dashboard Evidence

Directory: `.docs/dev/commerce-dashboard-qa/`

| File | Existing evidence |
| --- | --- |
| `desktop-light-30-days.png` | Full desktop 30-day overview |
| `desktop-light-30-days-viewport.png` | First-viewport desktop composition |
| `laptop-dark-30-days.png` | Full laptop dark overview |
| `laptop-dark-30-days-viewport.png` | First-viewport laptop dark composition |
| `tablet-light-7-days.png` | Full tablet 7-day overview |
| `tablet-light-7-days-viewport.png` | Tablet first viewport |
| `mobile-dark-90-days-reduced-motion.png` | Full mobile dark, 90 days, reduced motion |
| `mobile-dark-90-days-reduced-motion-viewport.png` | Mobile first viewport |
| `diagnostics.json` | Metrics, panels, chart, fallback table, links, and layout checks |

## 10. Existing POS and Till Evidence

Directory: `.docs/dev/commerce-pos-qa/`

| File | Existing evidence |
| --- | --- |
| `terminal-desktop-light.png` | Full-width terminal desktop light |
| `terminal-laptop-dark.png` | Dense terminal laptop dark |
| `terminal-tablet-light.png` | Tablet terminal composition |
| `terminal-mobile-dark.png` | Mobile terminal stacking/controls |
| `register-admin-desktop-light.png` | Register directory desktop |
| `register-admin-mobile-dark.png` | Register directory mobile dark |
| `register-printer-dialog-dark.png` | Driver, mode, width, and label form |
| `till-admin-desktop-dark.png` | Till history/reconciliation desktop dark |
| `till-admin-mobile-light.png` | Till management mobile light |
| `receipt-desktop-light.png` | Protected screen receipt |
| `receipt-print-dark.png` | Light-paper print result from dark screen mode |
| `cashier-sales-history-desktop-light.png` | Expanded cashier-owned active/history sales projection on desktop |
| `cashier-sales-history-mobile-dark.png` | Expanded cashier-owned sales history in the compact mobile dark layout |
| `diagnostics.json` | Terminal, ownership, responsive, receipt, print, and theme checks |

### 10.1 Contextual Seeder Release Evidence

Directories:

```text
.docs/dev/commerce-context-seeder-qa/storefront/
.docs/dev/commerce-context-seeder-qa/pos/
```

This isolated S5 run used the `computers-it` context, a disposable SQLite
database/storage root, and an admin-owned demonstration till. It adds 18 PNG
captures and two machine-readable diagnostics files without replacing the
historical Commerce evidence above.

| Evidence group | Coverage | Result |
| --- | --- | --- |
| Storefront | Catalog, Livewire search/cart, checkout, product detail/gallery; desktop, laptop, tablet, and mobile; light and dark | 13 diagnostic cases and nine PNG captures passed |
| POS and administration | Product terminal, cart, open till, register/till administration, printer settings; desktop, laptop, tablet, and mobile; light and dark | Nine diagnostic cases and nine PNG captures passed |
| Media | 17/17 catalog images, 5/5 product-detail images, and 12/12 POS images loaded in each tested state | Passed |
| Accessibility/layout probes | Accessible control names, duplicate IDs, document width, text overflow, stable dimensions, and responsive rows | No findings |
| Runtime/network | Browser exceptions and failed requests | No findings |

These captures predate the featured-image pass and intentionally preserve its
centralized Aureon placeholders as historical seeder evidence. Current source
assets use generated featured images with branded secondary fallbacks.

### 10.2 Contextual Product Image Evidence

Directory:

```text
.docs/dev/commerce-context-image-qa/
```

The directory contains one contact sheet for each of the nine new merchant
contexts and `asset-manifest.json`, which records product identity, source role,
dimensions, byte size, MIME type, checksum, alt text, and category usage for all
216 contextual gallery sources. The 108 featured images are product-specific;
the 108 secondary images remain the documented fallback boundary.

The later shoe-store extension adds `shoe-store.jpg`,
`shoe-store-gallery.jpg`, and `shoe-store-asset-manifest.json` in the same
directory. Its 12 products use 36 generated catalog views rather than branded
fallbacks: one featured view and two alternate gallery angles per product.

### 10.3 Contextual Demo Data Interface Evidence

Directory:

```text
.docs/dev/commerce-demo-data-qa/
```

| File | Existing evidence |
| --- | --- |
| `desktop-light.png` | Ten-context desktop grid with archive acknowledgement exposed |
| `tablet-dark.png` | Two-column tablet composition and dark-theme token parity |
| `mobile-light.png` | Single-column mobile controls, media, and action flow |
| `diagnostics.json` | Authorization target, theme, radio state, media load, overflow, duplicate-ID, loader, runtime, and network checks |

## 11. Existing PDF Report Evidence

Directory: `.docs/dev/commerce-reports-qa/`

| File | Existing evidence |
| --- | --- |
| `product-catalogue-html.png` | Product report Blade layout preview |
| `order-register-html.png` | Order register layout preview |
| `stock-levels-html.png` | Stock-level layout preview |

These are rendered HTML previews of report Blade. Feature tests validate actual
PDF byte output. The environment used for that increment lacked a PDF rasterizer,
so these files must not be described as screenshots of rasterized PDF pages.

## 12. Phase 4.5 Required Capture Set

Fresh release captures should be stored under:

```text
.docs/dev/commerce-release-qa/
```

### 12.1 Public storefront

| Required file | Route/state | Viewport/theme |
| --- | --- | --- |
| `storefront-catalog-desktop-light.png` | Published catalog with filters/products | 1440 light |
| `storefront-catalog-mobile-dark.png` | Catalog and mobile menu | 390 dark |
| `storefront-product-desktop-light.png` | Gallery, price, stock, share, add-to-cart | 1440 light |
| `storefront-product-mobile-dark.png` | Product and gallery controls | 390 dark |
| `storefront-cart-tablet-light.png` | Cart quantities and server summary | 820 light |
| `storefront-checkout-pickup-desktop-light.png` | Pickup and payment choices | 1440 light |
| `storefront-checkout-delivery-mobile-dark.png` | Address, delivery fee, loading state | 390 dark |
| `storefront-confirmation-desktop-light.png` | Valid signed confirmation | 1440 light |
| `storefront-tracking-mobile-dark.png` | Valid signed tracking | 390 dark |

### 12.2 Commerce administration

| Required file | Route/state | Viewport/theme |
| --- | --- | --- |
| `admin-commerce-dashboard-desktop-light.png` | 30-day populated overview | 1440 light |
| `admin-commerce-dashboard-mobile-dark.png` | 90-day/reduced-motion overview | 390 dark |
| `admin-catalog-desktop-light.png` | Product/category lists | 1440 light |
| `admin-product-form-mobile-dark.png` | Long product editor and media controls | 390 dark |
| `admin-barcode-labels-tablet-light.png` | Multi-product label run | 820 light |
| `admin-inventory-desktop-light.png` | Projections and movements | 1440 light |
| `admin-inventory-adjustment-mobile-dark.png` | Adjustment dialog and validation | 390 dark |
| `admin-customers-desktop-light.png` | Active/archive directory | 1440 light |
| `admin-customer-form-mobile-dark.png` | Customer form on mobile | 390 dark |
| `admin-orders-desktop-light.png` | Filtered order register | 1440 light |
| `admin-order-details-mobile-dark.png` | Snapshot, payment, lifecycle actions | 390 dark |

### 12.3 POS, registers, tills, and receipts

| Required file | Route/state | Viewport/theme |
| --- | --- | --- |
| `pos-terminal-desktop-light.png` | Open till and populated cart | 1440 light |
| `pos-terminal-laptop-dark-split.png` | Split tender and cash change | 1080 dark |
| `pos-terminal-tablet-light-holds.png` | Held-order browser | 820 light |
| `pos-terminal-mobile-dark.png` | Full mobile sale flow | 390 dark |
| `pos-no-open-till-mobile-light.png` | Explicit non-transactable state | 390 light |
| `pos-registers-desktop-light.png` | Register directory and active states | 1440 light |
| `pos-register-print-settings-dark.png` | Driver/mode/width fields | 1080 dark |
| `pos-tills-desktop-dark.png` | Open/closed till history | 1440 dark |
| `pos-till-close-mobile-light.png` | Counted cash and reconciliation dialog | 390 light |
| `pos-receipt-screen-desktop-dark.png` | Dark application receipt screen | 1440 dark |
| `pos-receipt-print-from-dark.png` | White/dark print media contract | print media |
| `pos-cashier-sales-history-desktop-light.png` | Active/history tabs, metrics, filters, and protected receipts | 1440 light |
| `pos-cashier-sales-history-mobile-dark.png` | Foldable history and stacked sale rows | 390 dark |

### 12.4 Documents and reports

| Required file | Evidence |
| --- | --- |
| `document-order-portrait-page-1.png` | Rasterized customer/admin order PDF page |
| `document-order-landscape-page-1.png` | Landscape order PDF parity |
| `document-pos-receipt-80mm.png` | Rasterized 80 mm receipt output |
| `document-pos-receipt-58mm.png` | Rasterized compact 58 mm output |
| `report-product-catalogue-page-1.png` | Actual filtered product PDF |
| `report-order-register-page-1.png` | Actual filtered order PDF |
| `report-stock-levels-page-1.png` | Actual filtered stock PDF |
| `report-stock-movements-page-1.png` | Actual movement PDF |

Use a deterministic PDF rasterizer in Phase 4.5. If the release environment
still lacks one, record that as an unresolved evidence gap rather than replacing
PDF evidence with an unlabeled HTML preview.

## 13. Interaction Evidence Without Extra Screenshots

Some facts are better recorded in `diagnostics.json` and tests than in a large
number of nearly identical images:

- catalog search/filter URL synchronization;
- cart count after Livewire morph;
- checkout loading and disabled-submit behavior;
- signed-link expiry and invalid-signature response;
- authorization matrices and receipt ownership;
- exact barcode/manufacturer/SKU lookup;
- hold ownership and current-price repricing;
- exact tender settlement and insufficient-stock rollback;
- notification event/listener mapping and queue delivery;
- module-disabled 404/sidebar/asset behavior;
- no duplicate IDs, unlabeled controls, runtime errors, or failed requests.

Capture one representative visual state and retain machine assertions for the
rest.

## 14. Harness Commands

Build first and start the application on an unused local port:

```powershell
npm.cmd run build
php artisan serve --host=127.0.0.1 --port=8012
```

The focused harnesses are:

```powershell
npm.cmd run qa:commerce
npm.cmd run qa:commerce-dashboard
npm.cmd run qa:commerce-demo-data
npm.cmd run qa:commerce-pos
node scripts/qa-commerce-barcode.mjs
node scripts/qa-commerce-storefront-gallery.mjs
node scripts/qa-commerce-storefront-share.mjs
node scripts/qa-dashboard.mjs
```

Use the established overrides where applicable:

```powershell
$env:AUREON_QA_URL='http://127.0.0.1:8012'
$env:AUREON_QA_EMAIL='admin@aureon.test'
$env:AUREON_QA_PASSWORD='password'
$env:AUREON_QA_PORT='8012'
$env:AUREON_QA_RECEIPT_PATH='/pos/receipts/<completed-order-ulid>'
```

Local credentials and ULIDs belong in runtime environment variables or
temporary scripts, not committed diagnostics. The POS harness may also use its
documented admin-only mode when no cashier till is intentionally open.

## 15. Filename Convention

Use lowercase kebab-case:

```text
<surface>-<state>-<viewport>-<theme>.png
```

Include only meaningful dimensions. Examples:

```text
admin-order-details-mobile-dark.png
pos-terminal-laptop-dark-split.png
storefront-checkout-delivery-mobile-dark.png
```

Do not encode dates, local ports, user emails, numeric database IDs, or signed
tokens into filenames.

## 16. Release Diagnostics Schema

The final `diagnostics.json` should record at least:

```json
{
  "commit": "<git-sha>",
  "built_at": "<iso-8601>",
  "base_url": "http://127.0.0.1:<port>",
  "database": "sqlite|mysql|mariadb",
  "cases": [],
  "runtime_errors": [],
  "failed_requests": [],
  "accessibility_violations": [],
  "warnings": []
}
```

Each case should identify route name, path without sensitive query values,
viewport, theme, reduced motion, loader settlement, overflow dimensions, image
failures, accessible-name findings, and output filename.

## 17. Final Visual Gate

Phase 4.5 visual sign-off requires:

- [ ] Every required surface has a current representative capture.
- [ ] Desktop, laptop, tablet, and mobile layouts are covered.
- [ ] Light, dark, persisted-theme, and reduced-motion cases are covered.
- [ ] Empty, loading, validation, authorization, and populated states have
      machine checks, with screenshots where visual behavior matters.
- [ ] Keyboard focus, dialogs, labels, landmarks, headings, table headers,
      chart fallback, and contrast pass.
- [ ] Public/customer evidence contains no internal or sensitive values.
- [ ] Screen dark mode cannot leak into print/PDF paper styling.
- [ ] Actual PDF pages, not only HTML bodies, are rasterized and inspected.
- [ ] Diagnostics contain no browser errors, failed requests, broken media,
      duplicate IDs, loader stalls, or unexplained overflow.
- [ ] Existing evidence is retained for implementation history; release evidence
      is generated from one documented commit and build.
