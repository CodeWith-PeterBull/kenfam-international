# Commerce Barcode Generation and Label Printing

## Context

The POS can scan an internal `barcode` today (exact match on Enter, keyboard-wedge), but it cannot generate barcodes or print labels, and it holds only one barcode per product. A real minimart needs to: (1) auto-assign an internal barcode when a product is created, (2) also record an optional manufacturer barcode and match it on scan, and (3) print physical labels. This is the highest-value, lowest-risk barcode step: it fits the current "simple product, single stock balance" model and needs one additive column plus one new package — no rework of stock, orders, or pricing.

Branch: `feature/commerce-refinements-fixes` (already created off `feature/laravel-aureon-base-engine`; baseline green at 199 tests / 1,629 assertions).

Benchmark UI: the DreamsPOS "Print Barcode" page (product table with image/name/SKU/barcode/quantity, paper-size selector, "show store name / product name / price" toggles, Generate/Reset/Print). Its page styles already ship in the theme: `resources/scss/pages/_barcode.scss` is imported by `resources/scss/main.scss` and compiled into the served `resources/css/style.css`.

## Locked decisions (defaults chosen, not open questions)

| Topic | Decision | Reason |
| --- | --- | --- |
| Two barcode columns | Keep `products.barcode` as the internal store barcode (Code 128, auto-generated). Add `products.manufacturer_barcode` (optional, manual, EAN/UPC). | Matches the user's "manufacturer_barcode on top of product_barcode". |
| Internal barcode value | `PREFIX` + zero-padded product id, e.g. `MM0000000145`. Prefix from config, default `MM`. | Unique by construction (id is unique), simple, no counter table. Matches the strategy's "simple sequential store-prefixed" guidance. |
| Auto-generate | On create, if the internal barcode field is blank, `ProductService` generates it after insert. A "Generate" button on the form fills it explicitly too. | Satisfies "auto-generate-on-create option" while keeping it editable. |
| Scan and search | POS scan and both product searches match `barcode`, `manufacturer_barcode`, or `sku`. | User: "checked on product scan". |
| Barcode library | `picqer/php-barcode-generator` (pure PHP, GD PNG). GD confirmed available. | DOMPDF cannot run JS, so labels need server-side images. No client library required. |
| Label output | A4 label sheet PDF through the existing `RendersPdfReports` engine, embedding Code 128 PNG data URIs. | User: "into the existing PDF engine". Engine is A4-only; a sheet of label cells is the correct fit. |
| Preview | The management component shows the same server-generated barcode image inline (data URI), so preview equals print. No new JS asset is vendored. | Print/preview fidelity; avoids adding JsBarcode. Theme `_barcode` styles already present. |
| Component shape | One independent Livewire component with its own route, plus a per-row "Print label" action on the catalog list that opens it with the product preselected. | User: "independent component that can be included under product crud pages". |
| Authorization | View/print gated by `VIEW_PRODUCTS`; auto-generation runs inside product create (`MANAGE_PRODUCTS`). | Matches existing catalog permission model. |

## 1. Data model (one additive migration)

New migration `..._add_manufacturer_barcode_to_products_table.php`:

- Add `manufacturer_barcode` `string(80)` nullable unique, commented, placed after `barcode`.
- Add index only if needed for lookup (the unique constraint already indexes it).

Model `Product.php`: add `manufacturer_barcode` to `$fillable`. No cast needed (string).

`ProductService::productPayload()` already uppercases and null-collapses `sku`/`barcode`; add `manufacturer_barcode` to that same normalization list and to the `Arr::only` allow-list.

## 2. Barcode generation service (new package + service)

- `composer require picqer/php-barcode-generator` (run first; verify GD PNG output with a temp script).
- New `App\Modules\Commerce\Catalog\Support\BarcodeImageGenerator` (final readonly): `pngDataUri(string $value, string $symbology = 'code128'): string`. Wraps picqer, returns `data:image/png;base64,...`. Single reusable point for preview and PDF.
- New `App\Modules\Commerce\Catalog\Support\InternalBarcodeGenerator` (or a private method on `ProductService`): `forProduct(Product $product): string` returns `config('commerce.barcode.internal_prefix').str_pad((string)$product->id, config length, '0', LEFT)`.
- `config/commerce.php`: add a `barcode` block — `internal_prefix` (`MM`), `symbology` (`code128`), `label` defaults (columns per A4 row, show store name / product name / price / sku booleans). Add matching `.env.example` keys.

## 3. Auto-generation on create

In `ProductService::createProduct()` (inside the existing transaction, after `$product->save()`): if `$product->barcode` is blank, set it to the generated internal value and `save()` again (id now exists). Record the assigned barcode in the existing `commerce.product.created` activity properties. Unique by construction; add a defensive uniqueness check.

Form support: `ProductForm` gains no new required field for the internal barcode (it already has `barcode`); add a `generateInternalBarcode()` action on `ProductCatalog` that previews a candidate value in the form for new products, so the "Generate" button works before save.

## 4. Manufacturer barcode field

- `ProductForm.php`: add `public string $manufacturerBarcode = ''`; rule `['nullable','string','max:80', Rule::unique('products','manufacturer_barcode')->ignore($this->product)]`; include in `fillFromProduct()`, `payload()` (trim, null-collapse), and `validationAttributes()`.
- `resources/views/livewire/admin/product-catalog.blade.php`: add the Manufacturer barcode input beside the existing Barcode field (line 139 area); relabel the existing field "Internal barcode" with a Generate button.

## 5. Scan and search touch-points

Add `manufacturer_barcode` to:

- `Terminal.php` `lookup()` exact match: `->where('barcode',$v)->orWhere('manufacturer_barcode',$v)->orWhere('sku',$v)`.
- `Terminal.php` `products()` LIKE grid search.
- `ProductCatalog.php` admin list search (and update the placeholder text).

## 6. Print-barcode component (independent, DreamsPOS-style)

New `App\Modules\Commerce\Catalog\Livewire\Admin\BarcodeLabelSheet` + `resources/views/livewire/admin/barcode-label-sheet.blade.php`, reusing the theme `_barcode` page classes:

- Search and add products to a working list; per row show image, name, SKU, both barcodes, and a label-quantity input.
- Controls: which barcode to encode (internal default, manufacturer if present), label size / columns-per-A4-row preset, and show-store-name / show-product-name / show-price toggles (defaults from config).
- Live preview: inline server-generated barcode image per row via `BarcodeImageGenerator`.
- "Print" streams the A4 label-sheet PDF.

New route `GET /admin/commerce/catalog/barcodes` named `commerce.admin.catalog.barcodes` in `Routes/admin.php`, gated `permission:VIEW_PRODUCTS`, thin `CatalogBarcodeController@index` returning a page that hosts the component. Accepts an optional `?product=<ulid>` to preselect (used by the catalog row action). Add a sidebar entry and a "Print label" action on each catalog list row.

PDF: new `App\Modules\Commerce\Catalog\Services\BarcodeLabelDocumentService` producing the label sheet through `RendersPdfReports->stream/download`, with a new `resources/views/reports/barcode-labels.blade.php` (CSS grid of label cells; each cell = optional store name, product name, price, the barcode `<img>`, and the human-readable value). `ReportContext` built the same way the receipt/order adapters do; A4 portrait.

## 7. Assets

- Server-side only for barcodes (picqer) — no client JS to vendor.
- Confirm the `_barcode` page classes used by the component exist in `resources/scss/pages/_barcode.scss`; the file is already compiled into the served `style.css`, so no SCSS rebuild is expected. If the component needs a class not present, add it to `_barcode.scss` and recompile with the documented `sass` step, then `npm run build`.
- The catalog admin pages already load the dashboard layout (head-css + vendor-scripts); no new script include is required.

## Files

| Action | Path |
| --- | --- |
| New migration | `app/Modules/Commerce/Database/Migrations/..._add_manufacturer_barcode_to_products_table.php` |
| New service | `app/Modules/Commerce/Catalog/Support/BarcodeImageGenerator.php` |
| New service | `app/Modules/Commerce/Catalog/Services/BarcodeLabelDocumentService.php` |
| New Livewire + view | `app/Modules/Commerce/Catalog/Livewire/Admin/BarcodeLabelSheet.php`, `resources/views/livewire/admin/barcode-label-sheet.blade.php` |
| New controller + page | `app/Modules/Commerce/Catalog/Http/Controllers/Admin/CatalogBarcodeController.php`, `resources/views/admin/catalog/barcodes/index.blade.php` |
| New report view | `app/Modules/Commerce/Resources/views/reports/barcode-labels.blade.php` |
| Modify | `Product.php` (fillable), `ProductService.php` (auto-gen + payload), `ProductForm.php` (+ manufacturer field), `product-catalog.blade.php` (fields + row action + search placeholder), `Terminal.php` (scan + grid search), `ProductCatalog.php` (admin search), `Routes/admin.php` (route), `config/commerce.php` + `.env.example` (barcode block), sidebar partial, `composer.json` (picqer) |

## Verification

- Temp PHP script (autoloaded, bootstrapped): generate a Code 128 PNG with `BarcodeImageGenerator`, assert it is a valid non-empty PNG data URI; create a product with a blank internal barcode and assert `barcode` was auto-assigned and unique; delete the throwaway product.
- Feature tests under `tests/Feature/Commerce/`: manufacturer-barcode optional+unique validation; auto-generation on create (blank → assigned, provided → kept); POS `lookup()` matches internal, manufacturer, and SKU; label-sheet PDF returns a valid PDF for selected products and quantities; component authorization (guest redirect, non-permitted 403, permitted 200); update `RoleSeederTest` only if permissions change (none new expected — reuses `VIEW_PRODUCTS`/`MANAGE_PRODUCTS`).
- Full suite green; Pint clean.
- Browser QA (new `scripts/qa-commerce-barcode.mjs` cloned from the POS harness): the barcode page renders with theme styles, preview images load, the PDF endpoint returns 200, desktop + mobile, light + dark, no overflow or console errors. Screenshots to `.docs/dev/commerce-barcode-qa/`.
- Docs: `.docs/PointOfSale/commerce-barcode-labeling-implementation.md` records the schema change, generation contract, scan/search touch-points, component, assets, tests, and verification evidence.

## Risks / notes

- Cross-column uniqueness (a value used as `barcode` on one product and `manufacturer_barcode` on another) is not enforced by single-column unique constraints; acceptable for this release, noted in docs.
- PDF engine is A4-only; physical A5/A6 label pages would need a small paper-size extension to `ReportContext`/`PdfReportService` — out of scope here (A4 sheet grids cover the need).
- `picqer/php-barcode-generator` is the only new package; sequence the `composer require` first and verify GD output before building the label view.
