# Commerce Document Adapters And Receipt Printing

**Status:** Implemented; automated gate passed

**Branch:** `feature/commerce-document-adapters`

**Prepared:** 2026-07-19

## Objective

Harden the existing order-summary and POS-receipt adapters without creating a
second PDF engine. Both document paths must use immutable view data, inherit the
institutional identity resolved by the host report system, and exclude internal
or credential-bearing fields by construction.

This delivery also makes receipt printing configurable per POS register. The
initial driver uses the browser print dialog with 58 mm and 80 mm thermal-roll
layouts. It may open that dialog automatically after a successful checkout or
leave printing as an explicit operator action.

## Planned Boundaries

1. Add immutable order and receipt projections containing only display-safe
   scalar values, line snapshots, and tender snapshots.
2. Update `OrderDocumentService` and `PosReceiptService` to render those
   projections through the existing `RendersPdfReports` implementation.
3. Preserve portrait and landscape order summaries, enforce portrait-only POS
   PDF receipts, and give both services stream/download parity.
4. Add register-owned print driver, mode, roll-width, and optional printer-label
   settings with migration comments, model casts, Livewire validation, service
   normalization, demo fixtures, and administration controls.
5. Resolve printer drivers through a module contract and configuration map. The
   browser driver emits a cancelable client event before calling `window.print()`
   so a separately approved kiosk or local bridge can intercept the job later.
6. Add auto-prompt behavior only to the post-checkout redirect. Reopening an old
   receipt remains quiet, and one completed sale is guarded against repeated
   automatic prompts in the same browser tab.
7. Verify document privacy, filenames, dispositions, orientations, long and
   multi-page content, cache policy, printer settings, manual/automatic behavior,
   responsive receipt layout, dark-screen/light-paper output, and regressions.

## Browser And Hardware Contract

Standard web browsers do not expose arbitrary installed-printer selection or
silent printing to normal application JavaScript. The `browser` driver therefore
configures receipt layout and may open the system print dialog, but the operator
or managed kiosk policy owns the physical printer, copies, cutting, and silent
dispatch. True silent ESC/POS or network printing remains an extension driver
that must use a trusted local bridge or kiosk deployment and must not receive raw
payment metadata or credentials.

## Implemented Architecture

### Immutable order boundary

`OrderDocumentDataFactory` is the only order-model mapper used by
`OrderDocumentService`. It produces `OrderDocumentData` and typed line values
under `Orders/Data/Documents`. Templates receive the projection as `$document`;
they no longer receive an `Order` model.

The projection includes only:

- business order number and customer display name;
- channel, fulfillment, order, and payment labels;
- placement timestamp;
- subtotal, discount, delivery, tax, and total in integer minor units;
- tax-inclusive state; and
- product name, SKU, quantity, unit selling price, line tax, and line total.

### Immutable receipt boundary

`PosReceiptDataFactory` maps the completed sale into `PosReceiptData`, typed
receipt lines, and typed payment values. The screen receipt and PDF receipt both
consume that same `$receipt` projection.

The projection includes register/cashier/customer display values, integer sale
totals, selling-price lines, and method/reference/amount/change tender values.
It deliberately has no internal identifier, ULID, internal/customer note,
customer contact field, unit cost, payment metadata, recorder, till graph, or
credential field. Control characters are collapsed at the mapping boundary.

### Institutional document adoption

Both services still call `RendersPdfReports`. `PdfReportService` therefore owns
the same two-pass rendering, page totals, local logo data URI, Institution
Details header/contact values, generated-by footer, and response hardening used
by every Aureon report. No Commerce-specific PDF engine was introduced.

`OrderDocumentService` retains portrait and landscape stream/download methods.
`PosReceiptService` now has matching stream/download methods but throws an
explicit `InvalidArgumentException` for landscape because a receipt is portrait
by contract. `ReportContext::sanitizeFilename()` is the shared header-safe
filename normalizer.

## Register Print Configuration

Migration
`2026_07_19_120000_add_receipt_printing_settings_to_registers_table.php` adds
four commented fields:

| Field | Default | Contract |
| --- | --- | --- |
| `receipt_print_driver` | `browser` | Key resolved from `commerce.pos.receipt_printing.drivers` |
| `receipt_print_mode` | `manual` | `manual` or `auto_prompt`, cast to `ReceiptPrintMode` |
| `receipt_paper_width` | `80` | `58` or `80`, cast to `ReceiptPaperWidth` |
| `receipt_printer_name` | `null` | Optional operator-facing device/queue label |

The Livewire register form validates every setting. `RegisterService` repeats
the driver, enum, and width checks at the service boundary so direct callers
cannot persist unsupported values. Changes continue through the existing
transaction and system-activity path.

The optional demonstration fixture establishes two scenarios:

| Register | Mode | Width | Label |
| --- | --- | --- | --- |
| Main demonstration counter | Post-sale prompt | 80 mm | Main counter receipt printer |
| Mobile demonstration register | Manual | 58 mm | Mobile receipt printer |

## Printer Driver Boundary

`ReceiptPrinterDriver` receives only `ReceiptPrinterSettingsData`, the immutable
receipt projection, and an `afterCheckout` flag. `ReceiptPrinterManager` resolves
the selected implementation from configuration and rejects missing or invalid
implementations.

The bundled `BrowserReceiptPrinterDriver` returns a browser-safe instruction:

```text
driver, strategy, mode, paperWidthMillimeters,
printableWidthMillimeters, printerName, autoPrompt,
supportsSilentPrinting, receiptKey
```

Before using `window.print()`, `pos.js` dispatches the cancelable
`commerce:receipt-print` event. An adopter's separately reviewed bridge may call
`preventDefault()` and handle the instruction. The event detail also carries
`reason=manual|checkout`. The browser driver always reports
`supportsSilentPrinting=false`.

To add a trusted driver, implement `ReceiptPrinterDriver`, register its key and
class in `commerce.pos.receipt_printing.drivers`, add the client integration that
intercepts the event, and expose the key to the intended registers. Never pass
raw payment metadata, credentials, cost prices, or Eloquent models across the
bridge.

## Checkout And Print Lifecycle

1. A successful terminal sale redirects to the protected receipt with
   `print=checkout`.
2. `ReceiptController` rechecks completed-POS state and cashier/supervisor
   ownership, builds the immutable projection, and resolves the current register
   driver.
3. Only `auto_prompt` plus the checkout marker enables `autoPrompt`. Reopening a
   historical receipt is quiet.
4. The page waits for the Aureon loader to settle, then requests printing. A
   session-storage receipt key prevents repeated automatic prompts after refresh.
5. Manual printing remains available regardless of mode.
6. Print CSS forces white paper and dark text from either screen theme and sizes
   the content to the selected roll's printable width. The 58 mm layout compacts
   branding/metadata and omits the separate unit-price column while retaining
   item totals.

Receipt responses remain `private, no-store`; PDF access remains authenticated,
ownership/supervisor protected, and activity recorded.

## Environment Defaults

```dotenv
COMMERCE_POS_RECEIPT_PRINT_DRIVER=browser
COMMERCE_POS_RECEIPT_PRINT_MODE=manual
COMMERCE_POS_RECEIPT_PAPER_WIDTH_MM=80
```

These are creation/fallback defaults. The register record owns the active
settings. Apply the schema with:

```powershell
php artisan migrate --no-interaction
```

## Verification Record

Completed on 2026-07-19:

- focused `CommerceDocumentAdapterTest`: 6 tests, 74 assertions;
- document projection privacy, exact totals, payment change, and control-field
  exclusion;
- sanitized order/receipt filenames and stream/download dispositions;
- portrait and landscape order PDFs plus multi-page output;
- portrait receipt PDF and explicit landscape rejection;
- register Livewire field/label presence, validation, persistence, and enum
  casts;
- config-resolved extension-driver proof;
- checkout-only automatic prompting, 58 mm profile, and no-store headers;
- `CommerceSchemaTest`: 3 tests, 223 assertions, including all migration column
  comments;
- complete reconciled Laravel baseline: 199 tests, 1,629 assertions;
- temporary bootstrapped PHP runtime probe against the migrated demonstration
  database: `DEMO-MOBILE-02` resolved the browser-dialog driver, manual mode,
  58 mm paper, no silent-print claim, and no private projection fields;
- Pint, Composer validation, PHP syntax, JavaScript syntax, `git diff --check`,
  Blade cache, route cache, and configuration cache passed.

The Vite build and fresh Chromium captures could not be rerun in this execution
session because the environment denied the required `esbuild`/browser child
process after its execution quota was reached. The source QA harness now checks
the register printer modal, driver instruction, 58/80 mm print width, dark-screen
light-paper rule, and auto-prompt event. Run the closeout commands below before
publishing this branch's browser evidence:

```powershell
npm.cmd run build
php artisan serve --host=127.0.0.1 --port=8012
$env:AUREON_QA_RECEIPT_PATH='/pos/receipts/<completed-order-ulid>'
npm.cmd run qa:commerce-pos
```

## File Ownership

| Concern | Files |
| --- | --- |
| Order view data | `Orders/Data/Documents/*`, `OrderDocumentDataFactory.php` |
| Receipt view data | `PointOfSale/Data/Documents/*`, `PosReceiptDataFactory.php` |
| PDF adapters | `OrderDocumentService.php`, `PosReceiptService.php`, Commerce report views |
| Print domain | `ReceiptPrintMode.php`, `ReceiptPaperWidth.php`, `PointOfSale/Printing/*` |
| Register settings | migration, `Register.php`, `RegisterForm.php`, `RegisterService.php`, register manager view |
| Browser lifecycle | `ReceiptController.php`, `Terminal.php`, receipt view, `pos.js`, `pos.css` |
| Defaults/fixtures | Commerce config, `.env.example`, register factory, transaction demo seeder |
| Verification | `CommerceDocumentAdapterTest.php`, POS/schema tests, `qa-commerce-pos.mjs` |

## Deferred Boundaries

- Browser JavaScript cannot enumerate or silently select installed printers.
- Copies, cash-drawer pulses, cutter commands, USB/network discovery, and device
  credentials are not part of the browser driver.
- A bundled ESC/POS, kiosk, or local-agent implementation remains a separately
  approved integration with its own authentication, transport, retry, and
  deployment threat model.
- Order operational notifications remain Chunk 4.3; this delivery emits no new
  mail or database notifications.
