# Commerce POS Terminal Implementation

**Status:** Complete. Master Phase 3 gate passed.

**Branch:** `feature/commerce-pos-terminal`

**Started:** 2026-07-17

## Objective

Complete master Phase 3 by exposing the existing transactional POS engine as a
permission-gated, full-width cashier workspace. A cashier must be able to use
their own open till, find products by barcode, SKU, or name, maintain a cart,
select a reusable customer, hold or resume a sale, settle it with one or more
tenders, and print or reprint a receipt. Till managers must be able to configure
registers and open, reconcile, and close sessions.

## Factual baseline

The implementation starts from the tested module contracts delivered before
this interface:

- `TillService` atomically opens and closes sessions, enforces one open session
  per register and cashier, recomputes expected cash, and records variance.
- `OrderService` creates POS holds without stock movement and reprices resumed
  holds before assigning a number and committing stock.
- `PosCheckoutService` requires the cashier's own open till and wraps order,
  stock, payment, and activity writes in one transaction.
- `PaymentService` supports split tenders, exact outstanding-balance checks,
  sanitized references/metadata, cash change, and till cash projection updates.
- Products, customers, orders, payments, registers, and till sessions already
  use the shared module models and selective ULID route contract.
- `access-pos` and `manage-tills` already exist in the developer-owned
  permission catalogue and explicit model policies.

No parallel product, customer, cart, sale, payment, or stock persistence will
be introduced.

## Implementation plan

### 1. Register and till administration

- Add a `RegisterService` as the only register mutation boundary, with normalized
  unique codes, active-state protection, system activity, and safe error text.
- Add typed Livewire forms and permission-gated components for register create,
  edit, activation, till opening, closing, filtering, and reconciliation.
- Keep routes and thin controllers under `PointOfSale/Http` and views under the
  module's `pos` and `livewire/pos` namespaces.
- Prevent deactivation of a register with an open session. Historical registers
  remain in place rather than being deleted from transaction relationships.

### 2. Cashier terminal

- Use a dedicated module-owned, full-width POS layout that loads Aureon
  Bootstrap/dashboard assets, loader, theme persistence, and Livewire without
  the normal dashboard sidebar.
- Require `access-pos` on every terminal request and repeat authorization in
  every mutating Livewire action.
- Resolve the current user's own open till on every request. Never accept a till
  or cashier identifier from the browser as trusted transaction ownership.
- Keep the active cart in Livewire state as product IDs and integer quantities.
  Every preview re-fetches published products, stock, and current prices through
  `CartCalculator`; checkout recalculates again inside `OrderService`.
- Support exact barcode/SKU entry, name/SKU/barcode browsing, quantity controls,
  customer selection, fixed discount with reason, cart clearing, and stable
  loading/error states.
- Persist holds as existing `pos/held` orders. Resume only holds owned by the
  current till and cashier, and reprice before final payment. Discarding a hold
  records a cancelled no-stock state so till closing is unblocked without
  erasing history.
- Support configured cash, mobile-money, card, and bank-transfer tenders. The
  interface may submit amounts, cash tendered, and non-sensitive references;
  it never accepts trusted totals or payment credentials.

### 3. Receipts

- Protect receipt routes with authentication, verified email, Commerce
  permissions, POS-channel checks, and cashier ownership when the user lacks
  broad order visibility.
- Provide an accessible browser receipt with a print action and a module-owned
  PDF receipt adapter using the shared two-pass PDF renderer.
- Load immutable order-item, customer, payment, register, till, and cashier
  snapshots. Never render payment metadata, internal notes, cost values, or
  sequential database IDs.
- Audit PDF reprints using order ULIDs and non-sensitive output context.

### 4. Verification

- Feature-test route permissions, register/till CRUD and lifecycle, cashier
  ownership, lookup/cart behavior, hold/resume/discard, split payment, stock,
  receipt authorization, print HTML, and valid PDF output.
- Preserve the existing transaction-service rollback/repricing suite.
- Run Pint, route/config/view checks, the complete Laravel suite, Vite production
  build, and a dedicated Chromium POS harness across desktop, tablet, mobile,
  light, and dark states.

## Non-goals

- No returns, refunds, exchanges, layaway, customer credit, suspended stock
  reservation, direct ESC/POS driver, cash drawer hardware, or card gateway.
- No multi-location stock, register-specific pricing, offline synchronization,
  or browser-local transaction persistence.
- No payment credentials or raw card/mobile-money payloads are accepted or
  logged.

## Delivery record

### Register and till administration

- `RegisterService` owns normalized register creation, update, activation, and
  open-session deactivation protection. Every mutation records structured
  system activity.
- Typed Livewire forms and the `RegisterManager` and `TillManager` components
  provide searchable, paginated register configuration plus till assignment,
  opening-float capture, expected cash, counted cash, and variance closure.
- Register and till summaries reuse the dashboard's established responsive
  `aureon-stat` card contract. Register metrics render three equal desktop
  columns; till metrics render four, and both collapse cleanly on mobile while
  retaining theme-aware surfaces, borders, icons, values, and labels.
- Thin module controllers and isolated routes expose `/admin/commerce/pos/*`
  only to `manage-tills` users. Both admin and role-composed sidebars expose the
  tools only when the matching permission is present.

### Access roles and adoption reconciliation

| Identity | Effective access |
| --- | --- |
| Active system administrator | Platform-wide Gate bypass, including terminal, registers, till sessions, and supervisory receipts |
| `pos-cashier` demo role | `access-pos` only; terminal plus receipts owned by the signed-in cashier |
| Till manager role composition | `manage-tills`; register and till administration without terminal sales |
| Cashier supervisor role composition | `access-pos` and `manage-tills`; terminal sales plus register/till administration |
| Order reviewer role composition | `view-orders`; order workspace and supervisory receipt review |

- `AppServiceProvider` now codifies the active system-administrator super-user
  invariant before permission evaluation. This prevents a foundational admin
  from receiving a 403 when an adopting database has stale role-permission
  rows, while inactive accounts remain denied.
- `CommerceAccessDemoSeeder` calls the canonical `RoleSeeder` before creating
  Commerce fixtures. Existing installations therefore receive newly introduced
  Commerce permissions and the `system-admin` role is reconciled whenever the
  optional demo graph is seeded.
- The module-owned `pos-cashier` role contains only `access-pos`. Its local-only
  `cashier@aureon.test` account retains the `viewer` base role for dashboard
  routing and receives the Commerce role as an additional assignment.
- The seeded POS transaction is owned by that cashier instead of the host
  administrator. The terminal header and till assignment selector display the
  operator's actual Commerce responsibility rather than hard-coding
  "Cashier" for every account.
- The terminal toolbar exposes both register and till-session shortcuts to
  users with `manage-tills`.
- The POS administration route group declares `web`, `auth`, `verified`, and
  `permission:manage-tills` in one ordered middleware stack. Laravel route
  registrar attributes are replaced rather than merged by a second
  `middleware()` call; the previous declaration therefore omitted the web
  session stack and caused Spatie to report "User is not logged in" for real
  browser requests even though `actingAs()` feature tests passed.
- A browser-session regression now posts valid credentials through `/login`
  before requesting both register and till administration. This protects the
  route against future middleware-stack regressions that direct `actingAs()`
  authorization checks cannot detect.

### Cashier terminal

- `/pos` uses a dedicated full-width Aureon layout with the shared loader,
  persisted light/dark theme, centralized branding, compact navigation, and no
  dashboard sidebar.
- The Livewire terminal derives the authenticated cashier's open till on every
  request. Browser state contains only product IDs, integer quantities,
  selected customer ID, discount input, notes, and tender input.
- `PosCartService` re-fetches published products, current stock, and active
  prices for every preview. `OrderService` and `PosCheckoutService` perform the
  final locked recalculation and atomic order, stock, payment, till, and audit
  writes.
- Exact barcode/SKU lookup, text discovery, quantity controls, reusable customer
  selection, reasoned fixed discount, hold/resume/discard, configured split
  tenders, cash received, change preview, and no-open-till state are complete.
- Discarding a hold preserves it as a cancelled audit record without assigning
  an order number or touching stock. A cashier can manipulate only holds owned
  by their current till.

### Receipts and reports

- Completed POS sales redirect to an authenticated browser receipt with print,
  new-sale, and PDF actions. HTML and PDF responses prevent shared caching.
- Cashiers can view only their own receipts; `view-orders` and `manage-tills`
  supervisors may review all completed POS receipts. Web orders, held orders,
  cancelled orders, and unrelated cashiers remain inaccessible.
- `PosReceiptService` uses the shared two-pass portrait PDF engine and
  institutional profile. Output contains immutable sale lines, totals, safe
  payment references, register, and cashier context, but no metadata, cost,
  internal note, credential, or sequential database identifier.
- PDF rendering records `commerce.pos.receipt_printed` activity.

### Configuration and resources

- `COMMERCE_POS_PAYMENT_METHODS`, `COMMERCE_POS_MAX_TENDERS`, and
  `COMMERCE_POS_PRODUCT_RESULTS` are environment-overridable module settings.
- POS CSS and vanilla JavaScript are dedicated Vite entries. The UI uses stable
  product, cart, tender, and action dimensions across desktop, tablet, and
  mobile, with explicit dark and print media states.
- Receipt print media always establishes a light paper color scheme and explicit
  white backgrounds with dark text for every table layer. Bootstrap dark-mode
  state can remain active on screen without leaking into the browser print
  preview or physical/PDF output.
- `scripts/qa-commerce-pos.mjs` authenticates against a real server and verifies
  terminal interactions, responsive layout, image loading, accessible names,
  duplicate IDs, text/document overflow, theme persistence, register/till
  statistic-card rendering, receipt rendering, and dark-theme print table
  colors through computed styles. An
  `AUREON_QA_ADMIN_ONLY=1` mode isolates administration QA when no cashier till
  is open.

### Verification evidence

- Pint passes on all changed PHP files.
- Focused POS interface and transaction suites pass, including permissions,
  register/till lifecycle, ownership, lookup, minimum-stock guards,
  hold/resume/discard, repricing, split tender, rollback, receipt access, and
  valid PDF output: 14 tests and 111 assertions.
- The uncached complete Laravel regression passes: 187 tests and 1,486
  assertions.
- The Vite 7.3.6 production build completes with 66 transformed modules and
  seven static-copy groups.
- Chromium QA passes eight interactive captures: four terminal layouts plus
  register and till administration on desktop/mobile across light/dark modes.
  There are zero runtime errors, failed requests, broken product images,
  incomplete statistic cards, duplicate IDs, unlabeled controls, or
  viewport/text overflows. Receipt and print-media checks remain available when
  a receipt path is supplied. Evidence is under `.docs/dev/commerce-pos-qa/`.

The final access regression additionally verifies the complete route matrix:
cashier-only users cannot administer registers or tills, till-only managers
cannot sell through the terminal, active system administrators retain all POS
surfaces even when their stored Commerce permission assignments are stale, a
real database-backed browser session reaches both administration pages after
login, and the optional demonstration seeder remains idempotent while creating
exactly one least-privileged cashier.

### Residual boundaries

Returns, refunds, exchanges, direct ESC/POS hardware, offline operation,
register-specific pricing, multiple stock locations, payment gateways, and
customer credit remain explicit later modules. Phase 4 owns broader reporting,
alerts, adoption guidance, and deployment hardening rather than reopening the
completed transaction contracts.
