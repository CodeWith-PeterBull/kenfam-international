# Commerce Filtered PDF Reports Implementation (Phase 1)

## Record status

- **Module:** Commerce
- **Increment:** Filtered PDF register reports for products, orders, and inventory
- **Branch:** `feature/commerce-refinements-fixes`
- **Implementation date:** 2026-07-23
- **Status:** Implemented and verified
- **Foundation reused:** `RendersPdfReports` / `PdfReportService` (see `.docs/PdfReports/pdf-report-templates.md`)

## Objective

Give Commerce administrators downloadable PDF register reports for the product
catalogue, orders, and inventory, driven by the same filters already present on
each admin list screen. The work reuses the shared institutional report engine
rather than introducing a second PDF path, and it exposes downloads through
Livewire so the export reflects the operator's live on-screen filter state.

## Locked decisions

| Topic | Decision |
| --- | --- |
| Phase 1 scope | Product catalogue, order register, stock levels, and stock movement history. Customer, till, and dashboard-summary reports are Phase 2. |
| Permissions | Reuse the existing `view-products`, `view-orders`, and `view-inventory` domain permissions (a report is a read of already-viewable data). |
| Prices | Selling prices only. The effective selling price is emphasised typographically (bold); a discounted list price is struck through. Supplier cost price is excluded by construction. |
| Row bound | 2,000 rows synchronous with a printed "showing first N of M" note; queued generation for larger sets is deferred. |
| Delivery | Livewire actions returning `response()->streamDownload(...)` with `wire:loading` feedback, not separate controller routes, so the export uses the component's live filter state. |

## Architecture

A thin reporting layer under `app/Modules/Commerce/Reporting/`:

- **Filters** (`Reporting/Filters/*`): immutable, normalized DTOs built from the
  same request keys the admin lists use (`ProductReportFilters`,
  `OrderReportFilters`, `StockLevelReportFilters`, `StockMovementReportFilters`).
- **Row projections** (`Reporting/Data/Rows/*`): display-safe, immutable rows
  carrying business identifiers and selling values only. No cost price, no
  internal ULID, no payment credential reaches a row.
- **Report builders** (`Reporting/Reports/*` extending `CommerceListReport`):
  each builds a bounded, eager-loaded query mirroring the list filters, maps to
  rows, assembles a `ReportContext::forUser(...)` (with human-readable applied
  filters), and renders through `RendersPdfReports`. Public `rows()`,
  `total()`, and `filterLabels()` methods keep the logic unit-testable.
- **Report body views** (`commerce::reports.pdf.*`): landscape register bodies
  that `@extends` the shared portrait/landscape layouts and a shared metrics /
  filters / truncation-note partial.
- **Export actions**: `ProductCatalog::exportPdf`, `OrderManager::exportPdf`,
  `InventoryManager::exportStockLevelsPdf`, and
  `InventoryManager::exportMovementsPdf` authorize, build filters from their live
  state, stream the PDF, and record a `commerce.*.report_exported` activity.

The shared report styles partial gained generic `.text-right`, amount-emphasis,
and truncation-note utility classes.

## Reports

| Report | View | Filters | Emphasis |
| --- | --- | --- | --- |
| Product catalogue | `product-catalogue` | search, status, category | Selling price bold; list price struck when on sale |
| Order register | `order-register` | search, channel, status, payment | Order total bold; balance shown |
| Stock levels | `stock-levels` | search, stock state | On-hand bold; derived state (Healthy/Low/Out/Untracked) |
| Stock movement history | `stock-movements` | search, movement type | Signed change bold; running balance |

## Filters and privacy

- Each export uses the component's current filter properties, so a filtered
  screen produces a matching report. The applied filters print in the header.
- Row projections expose only display-safe fields. A regression test seeds a
  distinctive cost price and asserts it never appears in any product row.

## File inventory

Added:

- `app/Modules/Commerce/Reporting/Filters/{Product,Order,StockLevel,StockMovement}ReportFilters.php`
- `app/Modules/Commerce/Reporting/Data/Rows/{Product,Order,StockLevel,StockMovement}Row.php`
- `app/Modules/Commerce/Reporting/Reports/{CommerceListReport,ProductCatalogReport,OrderRegisterReport,StockLevelReport,StockMovementReport}.php`
- `app/Modules/Commerce/Resources/views/reports/pdf/{product-catalogue,order-register,stock-levels,stock-movements}.blade.php`
- `app/Modules/Commerce/Resources/views/reports/pdf/partials/summary.blade.php`
- `tests/Feature/Commerce/CommerceReportExportTest.php`

Updated:

- `app/Modules/Commerce/Catalog/Livewire/Admin/ProductCatalog.php`
- `app/Modules/Commerce/Orders/Livewire/Admin/OrderManager.php`
- `app/Modules/Commerce/Inventory/Livewire/Admin/InventoryManager.php`
- `app/Modules/Commerce/Resources/views/livewire/admin/{product-catalog,order-manager,inventory-manager}.blade.php`
- `resources/views/reports/partials/styles.blade.php`
- `README.md`

## Verification results

| Gate | Result |
| --- | --- |
| `vendor\bin\pint.bat --test` (reporting + tests) | Passed |
| `php artisan test tests/Feature/Commerce/CommerceReportExportTest.php` | 9 tests, 58 assertions passed |
| `php artisan test` | 225 tests, 1,751 assertions passed |
| Render smoke against demo data | Product 2p, order 2p, stock 2p, movement 4p — all valid `%PDF` |
| Rendered layout preview | Product/order/stock report previews visually confirmed under `.docs/dev/commerce-reports-qa` |

The tests cover representative data, empty data, page breaks, both orientations,
filter application, selling-price-only privacy, filename sanitisation, the four
Livewire `assertFileDownloaded` paths, and permission gating.

Note: the environment lacks a PDF rasteriser (poppler/ghostscript), so the
visual check rendered the report Blade to standalone HTML and screenshotted it;
the PDF byte output itself is verified by the feature tests.

## Deferred boundary (Phase 2)

Customer register, till/Z session summary, and dashboard summary reports; the
portrait price-list and low-stock summary variants; and queued generation for
exports beyond the 2,000-row synchronous cap.
