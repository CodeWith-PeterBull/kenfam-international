# Property Booking Point Of Booking Implementation

**Module:** `App\Modules\PropertyBooking`

**Branch:** `feature/property-booking-pob`

**Phase:** 4 - Point of Booking

**Status:** Complete and accepted.

**Prepared:** 2026-09-01

## 1. Delivery Scope

Phase 4 supplies the property-booking equivalent of the Commerce POS while
retaining accommodation-specific language and invariants. It adds:

- property-scoped reception-register configuration;
- receptionist-owned opening, monitoring, reconciliation, and closing shifts;
- a full-width Point of Booking terminal at `/pob`;
- property-local availability and current rate lookup;
- protected guest lookup and inline guest creation;
- expiring booking holds, resume, authoritative repricing, and discard;
- exact split tender, cash tender/change, and duplicate-reference protection;
- immutable browser and PDF receipt projections;
- 58 mm and 80 mm browser-print instructions with manual or post-checkout
  auto-prompt behavior;
- dashboard navigation, dedicated POB CSS/JavaScript, and focused regression
  coverage.

The implementation does not depend on the Commerce namespace. Shared host
services remain limited to authentication, authorization, system activity,
institution profile resolution, PDF rendering, dashboard layout, and Vite.

## 2. Module Structure

```text
app/Modules/PropertyBooking/
|-- PointOfBooking/
|   |-- Data/
|   |   |-- Documents/
|   |   `-- PobTenderData.php
|   |-- Events/
|   |-- Exceptions/
|   |-- Http/Controllers/
|   |-- Livewire/
|   |   |-- Admin/
|   |   |-- Forms/
|   |   `-- Terminal.php
|   |-- Printing/
|   |   |-- Contracts/
|   |   |-- Data/
|   |   |-- Drivers/
|   |   `-- ReceiptPrinterManager.php
|   `-- Services/
|-- Resources/
|   |-- css/pob.css
|   |-- js/pob.js
|   `-- views/
|       |-- layouts/pob.blade.php
|       |-- livewire/pob/
|       |-- pob/
|       `-- reports/booking-receipt.blade.php
`-- Routes/pob.php
```

Stable Livewire aliases are registered by `PropertyBookingServiceProvider`:

- `property-booking.pob.terminal`;
- `property-booking.pob.admin.reception-register-manager`;
- `property-booking.pob.admin.reception-shift-manager`.

## 3. Routes And Authorization

| Route | Capability and ownership contract |
| --- | --- |
| `GET /pob` | Authenticated, verified, active user with `ACCESS_POB`; terminal query returns only shifts the user may operate. |
| `GET /pob/receipts/{booking}` | Paid POB booking only; owning receptionist or property-scoped booking/shift/payment reviewer. |
| `GET /pob/receipts/{booking}/pdf` | Same receipt authorization; streams through the institutional PDF engine. |
| `GET /admin/accommodation/pob/registers` | `MANAGE_SHIFTS`; every query is property scoped. |
| `GET /admin/accommodation/pob/shifts` | `MANAGE_SHIFTS`; every query is property scoped. |

The system-administrator `Gate::before` contract remains authoritative for an
active administrator. Inactive accounts remain denied. Non-global operators
must have an explicit row in `property_booking_property_user`.

## 4. Register Contract

`ReceptionRegisterService` is the only write path used by the administration
component. It:

- normalizes the code to uppercase and enforces uniqueness per property;
- restricts persisted fields to the explicit register payload;
- validates configured printer drivers, print modes, and paper widths;
- defaults omitted printer settings from `property-booking.php`;
- blocks register deactivation while an open shift exists;
- retains register and financial history instead of deleting operational data;
- records create, update, activation, and deactivation system activity.

Printer configuration is stored per register:

| Field | Supported values |
| --- | --- |
| Driver | `browser` by default; additional drivers must implement `ReceiptPrinterDriver`. |
| Mode | `manual`, `auto_prompt`. |
| Roll width | `58`, `80` millimetres. |
| Printer name | Optional operator-facing label; browser JavaScript cannot silently select a device. |

## 5. Reception Shift Contract

`ReceptionShiftService` owns opening and closing transitions.

Opening requires:

1. an active register;
2. an active receptionist with `ACCESS_POB`;
3. property access for both the manager and receptionist;
4. a non-negative exact opening float;
5. no open shift for either the register or receptionist.

The nullable unique guard columns provide database enforcement in addition to
the service checks. While open, `expected_cash_minor` starts at the opening
float and is increased inside each committed cash-payment transaction.

Closing:

1. locks the shift;
2. rejects closed shifts and cross-property actors;
3. rejects unresolved held bookings;
4. recalculates completed cash less recorded cash refunds from payment rows;
5. stores expected, counted, and signed variance values;
6. clears both open guards and stamps closer/time;
7. dispatches `ReceptionShiftVarianceDetected` after commit when the absolute
   variance reaches the configured threshold.

## 6. Booking And Payment Transactions

### New booking

`PobCheckoutService::checkout()` coordinates one outer database transaction:

1. validate tender count and exact applied total;
2. lock the selected rate plan and recalculate price from persisted rules;
3. create immutable booking and stay snapshots with register, shift, and
   receptionist ownership;
4. lock and allocate one concrete available unit;
5. record each completed tender;
6. update aggregate payment status and the shift cash projection;
7. reject and roll back unless paid minor units equal current total minor
   units exactly;
8. record completion activity and return the loaded aggregate.

No browser-provided total, tax, rate, or concrete unit identifier is accepted
as authoritative.

### Split tender

- Money uses integer minor units throughout the service layer.
- Cash may carry a larger tendered amount; persisted change is the difference.
- Non-cash tenders require a normalized bounded reference.
- A completed method/reference pair cannot be reused.
- Tender rows must exactly settle the booking and cannot exceed the configured
  maximum.

### Holds

- A hold consumes a concrete unit and records an expiry time.
- Resume is restricted to the shift owner or an explicitly authorized
  supervisor operating that shift.
- Confirmation recalculates the current rate under lock and preserves booking
  ID and ULID.
- Discard releases the active unit assignment and expires the booking.
- An unresolved hold prevents shift close.

## 7. Terminal Interface

The full-width POB layout is independent of the dashboard shell and provides:

- current property, register, receptionist, and shift context;
- explicit shift selection for supervisors with more than one operable shift;
- local arrival/departure and occupancy input with a property-local **Now**
  action for immediate walk-ins;
- rate and accommodation lookup with current availability and totals;
- selected-stay summary and internal/public notes;
- property-safe guest lookup, permission-protected guest creation, and
  configurable protected ID/passport enforcement;
- active-hold browser with resume and discard actions;
- bounded split-payment rows, apply-total action, and cash tender input;
- responsive desktop/mobile composition, keyboard-visible controls, semantic
  labels, light/dark tokens, and reduced-motion handling.

`pob.js` only controls browser print and receipt auto-prompt behavior. Domain
writes remain Livewire/service operations.

## 8. Receipt And Printing Contract

`BookingReceiptDataFactory` creates one canonical receipt projection consumed
by both browser and PDF views. It uses booking/stay snapshots rather than live
catalog names and excludes internal notes, identity documents, actor metadata,
and raw contact details. Email or phone is masked before presentation.

`BookingReceiptService` uses `RendersPdfReports` and
`ResolvesInstitutionProfile`, preserving the existing institutional logo,
name, contact, footer, and report metadata contract. Thermal receipt PDFs are
portrait-only.

`ReceiptPrinterManager` resolves a configured `ReceiptPrinterDriver`. The
built-in browser driver:

- supports 58 mm and 80 mm print CSS;
- opens the standards-based browser dialog;
- auto-prompts only after a successful checkout redirect with
  `?print=checkout` and register mode `auto_prompt`;
- never claims silent printing or automatic device selection.

Native or network printer adoption should add a new driver and adapter-specific
JavaScript/service integration without changing booking or receipt services.

## 9. Environment Contract

```dotenv
PROPERTY_BOOKING_PAYMENT_METHODS=cash,mobile_money,card,bank_transfer
PROPERTY_BOOKING_MAXIMUM_TENDERS=4
PROPERTY_BOOKING_POB_SEARCH_RESULTS=18
PROPERTY_BOOKING_POB_GUEST_IDENTITY_REQUIRED=true
PROPERTY_BOOKING_POB_ENFORCE_ADVANCE_NOTICE=false
PROPERTY_BOOKING_POB_WALK_IN_PAST_GRACE_MINUTES=15
PROPERTY_BOOKING_SHIFT_VARIANCE_THRESHOLD_MINOR=10000
PROPERTY_BOOKING_RECEIPT_PRINT_DRIVER=browser
PROPERTY_BOOKING_RECEIPT_PRINT_MODE=manual
PROPERTY_BOOKING_RECEIPT_PAPER_WIDTH_MM=80
```

The complete Property Booking environment block is maintained together in
`.env.example`. Configuration changes require `php artisan config:clear` in
development or a new `php artisan config:cache` deployment build.

## 10. Verification Coverage

Focused Phase 4 coverage is located in:

- `PropertyBookingPobTransactionTest.php`;
- `PropertyBookingPobInterfaceTest.php`;
- `PropertyBookingPobReceiptTest.php`;
- `PropertyBookingSchemaTest.php`.

The acceptance commands are:

```bash
php artisan test tests/Feature/PropertyBooking
php vendor/bin/pint --dirty
npm run build
php artisan route:list --name=property-booking.pob
php artisan view:cache
php artisan config:cache
npm run qa:property-booking-pob
```

Final acceptance evidence on 2026-09-01:

| Check | Result |
| --- | --- |
| Property Booking feature suite | 63 tests, 1,202 assertions |
| Full application regression | 300 tests, 3,059 assertions |
| Code style | `php vendor/bin/pint --dirty` passed |
| Production assets | Vite built the POB CSS/JavaScript entries successfully |
| Routing | Five named POB routes registered |
| Compiled framework state | Route inspection, view cache, and config cache passed |
| Runtime autoload probe | 13 module, service, printer, route, view, and manifest checks passed |
| Browser QA | 13 diagnostics and 12 captures passed with no runtime or network errors |

The browser matrix covers desktop, laptop, tablet, mobile, light and dark
themes, reduced motion, register and shift administration, browser-printer
dialog invocation, and receipt screen/print rendering. Dark-mode receipts are
explicitly asserted to retain a white document surface with readable dark text.
The generated diagnostics and captures are stored in
`.docs/PropertyBooking/qa/pob/`.

## 11. Operational Adoption

1. Set `PROPERTY_BOOKING_ENABLED=true` and the required POB environment values.
2. Run normal application migrations; Phase 4 adds no migration because the
   register, shift, booking, assignment, and payment schema was established in
   Phase 1.
3. Build Vite assets so `pob.css` and `pob.js` exist in the manifest.
4. Assign managers `MANAGE_SHIFTS` and property scope.
5. Assign receptionists the established `booking-receptionist` role and
   property scope.
6. Configure at least one active register and open a shift before taking a POB
   booking.
7. Keep the queue worker active for later after-commit variance notifications.

For optional demonstration data, use the command already documented by the
foundation phase:

```bash
php artisan db:seed --class="App\\Modules\\PropertyBooking\\Database\\Seeders\\PropertyBookingDemoSeeder"
```

Do not couple the module demo seeder to the root production seeder.

## 12. Deliberate Phase Boundary

Phase 4 does not add general booking administration, cancellation/no-show,
check-in/check-out, room moves, readiness operations, dashboards, or dedicated
operational notification listeners. Those remain Phase 5 concerns and must use
the booking, assignment, payment, shift, activity, and event contracts delivered
here.
