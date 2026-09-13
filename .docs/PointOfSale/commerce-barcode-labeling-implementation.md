# Commerce Barcode Generation and Label Printing — Implementation

**Status:** Implemented and verified.
**Branch:** `feature/commerce-refinements-fixes`
**Date:** 2026-07-19
**Baseline before:** 199 tests / 1,629 assertions. **After:** 207 tests / 1,657 assertions.

## What shipped

- Auto-assigned internal Code 128 barcode on product creation.
- Optional manufacturer barcode (EAN/UPC), matched on POS scan alongside the internal barcode and SKU.
- Independent barcode label workspace (DreamsPOS-style) that previews and prints an A4 Code 128 label sheet PDF, embeddable from catalog pages.
- Server-side barcode generation via `picqer/php-barcode-generator` (GD PNG) into the existing PDF engine — no second PDF engine, no client JS.

## Data model

| Field | Meaning |
| --- | --- |
| `products.barcode` (existing) | Internal store barcode. Auto-generated as `<prefix><zero-padded id>` (default `MM0000000145`) when created blank; editable. |
| `products.manufacturer_barcode` (new) | Optional external EAN/UPC. Nullable, unique. Manually entered on the product form. |

Migration: `2026_07_19_140000_add_manufacturer_barcode_to_products_table.php` (nullable, unique, commented). `Product::$fillable` and `ProductService::productPayload()` normalise (trim + uppercase + null-collapse) both barcode columns.

## Generation and auto-assignment

- `App\Modules\Commerce\Catalog\Support\BarcodeImageGenerator` — `png()` / `pngDataUri()` produce a Code 128 raster; the single generator used by both the on-screen preview and the printed PDF so they are identical.
- `ProductService::createProduct()` assigns the internal barcode after the row is saved (the id guarantees uniqueness) when the field is left blank, and records the value in the `commerce.product.created` activity.
- `ProductService::nextInternalBarcode()` previews the next value for the form "Generate" button (`ProductCatalog::generateInternalBarcode()`).
- Config: `config/commerce.php` `barcode` block (`internal_prefix`, `internal_pad_length`, `symbology`, `label.*`) with `.env.example` keys.

## Scan and search

`manufacturer_barcode` was added to every product-matching path:

- `Terminal::lookup()` — exact scan match (`barcode` OR `manufacturer_barcode` OR `sku`).
- `Terminal::products()` — POS browse filter.
- `ProductCatalog::filteredProducts()` — admin catalog search.

## Label workspace and PDF

- `App\Modules\Commerce\Catalog\Livewire\Admin\BarcodeLabelSheet` (+ `resources/views/livewire/admin/barcode-label-sheet.blade.php`): search/add products, per-row label quantity, choose internal vs manufacturer barcode, live preview image, and label options (labels per row, show store name / product name / price). `generate()` streams a downloadable PDF via Livewire.
- `App\Modules\Commerce\Catalog\Services\BarcodeLabelDocumentService`: expands selections into label cells (caching one image per distinct value), lays them out as a fixed-column table (DOMPDF has no CSS grid), and renders through `RendersPdfReports` at A4 portrait. Report view: `resources/views/reports/barcode-labels.blade.php`.
- Route `GET /admin/commerce/catalog/barcodes` (`commerce.admin.catalog.barcodes`, gated `VIEW_PRODUCTS`) via `CatalogBarcodeController`; accepts `?product=<ulid>` to preselect. Component registered in `CommerceServiceProvider`.
- Entry points: a "Print barcodes" sidebar item and catalog toolbar button, plus a per-row "Print label" icon that deep-links with the product preselected.

## Assets

Server-side generation only — no barcode JavaScript was vendored. The DreamPOS `_barcode` page styles were already compiled into the served `style.css`; the workspace uses standard dashboard/Bootstrap classes, so no SCSS rebuild was required.

## Verification

- Temp bootstrapped scripts (deleted after use): `BarcodeImageGenerator` produces a valid 246×60 Code 128 PNG data URI; `createProduct` auto-assigns a unique prefixed barcode; `BarcodeLabelDocumentService` renders a valid multi-page `%PDF` (888 KB / 2 pages for 3 products × 8 labels).
- `tests/Feature/Commerce/CommerceBarcodeTest.php` — 8 tests / 27 assertions: auto-generation, supplied/manufacturer barcode kept + uppercased, manufacturer uniqueness, generate action, POS scan matching internal/manufacturer/SKU, page authorization (guest redirect / 403 / 200), PDF download, and rejection when a product has no barcode.
- Full regression: 207 tests / 1,657 assertions. Pint clean. Vite build clean.
- Browser QA `scripts/qa-commerce-barcode.mjs` (evidence in `.docs/dev/commerce-barcode-qa/`): workspace renders with a live barcode preview, download button, dark theme, mobile (no overflow), and the catalog form shows both barcode fields + Generate button + toolbar action. Zero runtime/network errors.

## Notes and deferred items

- No new permissions were added; the workspace reuses `VIEW_PRODUCTS`. `RoleSeederTest` needed no change.
- Cross-column uniqueness (a value used as `barcode` on one product and `manufacturer_barcode` on another) is not enforced by single-column constraints — acceptable for this release.
- The PDF engine is A4-only; physical A5/A6 label pages would need a small paper-size extension to `ReportContext`/`PdfReportService`. A4 sheet grids cover the current need.
- Legacy products missing an internal barcode are not backfilled (the demo catalog already carries barcodes; auto-generation covers all new products). A one-off backfill command can be added for adopters migrating an existing catalog.
