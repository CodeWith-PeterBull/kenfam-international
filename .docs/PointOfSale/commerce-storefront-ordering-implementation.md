# Commerce Storefront Ordering Implementation

## Record status

- **Module:** Commerce
- **Master phase:** 2, storefront and web ordering
- **Increment:** Session cart, checkout, signed order access, administration,
  mail, and order documents
- **Branch:** `feature/commerce-storefront-checkout`
- **Implementation date:** 2026-07-17
- **Status:** Complete; Phase 2 gate passed
- **Predecessor commit:** `f82b637 feat(commerce): add storefront catalog and demo data`

## Objective

Complete the operational Phase 2 gate without duplicating the tested Commerce
domain layer. A guest must be able to build a server-repriced session cart,
place a pickup or delivery order through Livewire, receive a queued confirmation
notification, and use signed confirmation, tracking, and order-document URLs.
Authorized staff must be able to inspect and manage customers, orders, lifecycle
transitions, manual payment confirmations, cancellation/restock, and order PDF
documents from the dashboard.

## Verified starting point

- Products, categories, inventory, customers, unified orders, immutable order
  items, payments, registers, tills, and stock movements already exist.
- `CartCalculator` accepts only product IDs and integer quantities and derives
  all prices, discounts, taxes, and totals from current server-side products.
- `OrderService` locks products and stock, persists historical snapshots,
  assigns order numbers, commits stock, transitions fulfillment, and restores
  stock on eligible cancellation.
- `PaymentService` owns payment records and order paid projections.
- Customer, order, and payment policies already map to the approved Commerce
  permission catalogue.
- Institution-aware mail templates and the orientation-aware two-pass PDF
  renderer are application contracts ready for domain-module adoption.

## Architecture decisions

1. Session cart storage contains only an integer product ID and integer
   quantity map. It never stores client-provided names, prices, tax, totals, or
   stock values.
2. Every cart read and mutation re-fetches publicly visible products. Checkout
   constructs `CartItemData`, `CustomerSnapshotData`, and `OrderPlacementData`
   and delegates final writes to `OrderService`.
3. The cart service removes stale product IDs, rejects unavailable quantities,
   and returns an immutable calculated snapshot for presentation.
4. Checkout supports store pickup and configured flat-fee delivery. Public
   payment preferences remain manual mobile money, bank transfer, and cash on
   delivery; no gateway credentials or card data are accepted.
5. Successful checkout creates one pending payment-preference record in the
   same outer transaction as order placement, clears the cart only after the
   transaction succeeds, and queues mail after commit.
6. Public order pages use the order ULID plus Laravel temporary signatures.
   Sequential IDs, customer email query parameters, and internal notes are
   never exposed.
7. Confirmation has a short-lived signed URL. Tracking and printable order
   documents receive independently generated, configurable signed URLs.
8. Customer administration uses `CustomerService`; order administration uses
   `OrderService` and `PaymentService`. Livewire components perform validation,
   authorization, and presentation only.
9. The order document uses the shared `RendersPdfReports` contract and an
   explicit Commerce report body that supports portrait and landscape.
10. The confirmation notification is queued, institution-aware, contains no
    sensitive payment data, and links to signed tracking and document routes.

## Planned implementation

### Storefront state and orchestration

- Add immutable cart snapshot data and a session cart service under
  `app/Modules/Commerce/Storefront`.
- Add an order-access URL service and checkout orchestration service.
- Extend `PaymentService` with idempotent pending web-payment preference
  creation.
- Add configuration for session keys, signed-link lifetimes, public payment
  methods, and checkout delivery behavior.

### Livewire storefront

- Add a reusable product purchase component, header cart indicator, cart
  manager, checkout form object, and checkout component.
- Add cart and checkout views plus signed confirmation/tracking views.
- Add accessible live regions, explicit input labels, keyboard-safe controls,
  loading/disabled states, and deterministic responsive dimensions.
- Update catalog cards and product detail to expose add-to-cart actions without
  introducing client-owned pricing.

### Signed access and documents

- Add public cart, checkout, confirmation, tracking, and order-document routes.
- Add a thin storefront order controller and an orientation-aware Commerce
  order-summary report view.
- Add a queued order confirmation notification with signed tracking/document
  links.

### Administration

- Add module-owned customer and order controllers, route entries, Livewire form
  objects, management components, and dashboard views.
- Add customer create/update/archive workflows with filters and pagination.
- Add order filters, detail inspection, legal lifecycle transitions, unpaid web
  cancellation, manual payment confirmation, and portrait/landscape document
  downloads.
- Extend the dashboard Commerce navigation using existing permissions.

### Verification and documentation

- Add focused cart, checkout, signed-route, mail, PDF, customer-admin, and
  order-admin feature tests.
- Extend storefront browser QA across desktop, tablet, and mobile light/dark
  states, including cart and checkout accessibility assertions.
- Run Pint, Composer validation, Blade compilation, route inspection, Vite
  production build, module tests, the complete application suite, an autoloaded
  runtime probe, and Chromium QA.
- Reconcile this record, `pos-module-plan.md`, and `README.md` to measured final
  results only.

## Guardrails

- No public request may submit a trusted price, tax, discount, delivery fee, or
  order total.
- No online payment gateway, card capture, refund engine, coupon engine,
  account portal, or product variation support is introduced.
- A failed checkout must leave the session cart intact and write no partial
  order, payment, stock movement, or stock projection.
- Confirmation-mail failure must be logged without reversing an already
  committed order.
- Public order views must not render `internal_note`, unit cost, activity data,
  reusable customer IDs, payment metadata, or sequential database IDs.
- Admin mutations require both route middleware and action-level policy checks.
- Existing `.docs/model-ulid/` files remain unrelated and untouched.

## Final implementation

### Storefront cart and checkout

- `StorefrontCartSnapshot` provides an immutable presentation and placement
  boundary around the existing `CartCalculation`.
- `CartSessionService` persists only a sorted product-ID/quantity map. It
  removes hidden products, applies minimum/maximum quantity constraints,
  checks current stock, re-fetches current prices, and recalculates every read.
- `AddToCart`, `CartIndicator`, and `CartManager` provide reusable Livewire 4
  product actions, synchronized cart counts, quantity changes, removal, clear,
  live feedback, loading states, and session-safe checkout navigation.
- `CheckoutForm` owns contact, fulfillment, address, configured payment-method,
  note, and confirmation validation. `Checkout` accepts no money fields and
  redirects through Livewire's native redirect mechanism.
- `StorefrontCheckoutService` wraps order placement and pending-payment
  preference creation in one outer transaction. It resolves or creates a
  reusable customer first, using account ownership or an exact guest
  name/email/phone tuple to avoid merging shared contact details. The order
  still stores its independent immutable snapshot, and the cart is cleared
  only after the service returns a committed order.
- Livewire's published application configuration selects Bootstrap pagination
  globally. Checkout choice icons use ignored DOM boundaries so Lucide's SVG
  replacement cannot confuse Livewire morphing or duplicate option copy.

### Payment, mail, signed access, and documents

- `PaymentService::recordPendingPreference()` creates one idempotent pending
  record for an eligible unpaid web order. `recordCompleted()` reuses the
  pending record when staff confirms payment, preventing stale duplicates.
- `OrderConfirmationNotification` is queued with `afterCommit()`, resolves the
  current institution profile, and contains independent temporary signed
  tracking and document links.
- `OrderAccessUrlService` applies configurable confirmation, tracking, and
  document lifetimes to order ULID routes.
- `OrderAccessController` rejects non-web aggregates, loads only required
  public relationships, and marks HTML/PDF responses private, no-store, and
  no-index. Views do not expose internal notes, cost, payment metadata, reusable
  customer IDs, or integer database IDs.
- `OrderDocumentService` adapts order snapshots to `RendersPdfReports`; the
  module report body renders and downloads valid A4 portrait and landscape
  PDFs through the shared two-pass renderer.

### Customer and order administration

- Customer administration includes permission-gated search, state filters,
  pagination, optional user-account linking, full contact/default-address
  create and edit, archival, and restoration through `CustomerService`.
- Order administration includes channel/status/payment filters, aggregate
  metrics, item and customer snapshot inspection, legal fulfillment advances,
  manual web-payment confirmation, eligible unpaid cancellation with exact
  stock restoration, and audited portrait/landscape downloads.
- Routes enforce `manage-customers` or `view-orders`; every Livewire mutation
  repeats policy authorization at the action boundary.
- The module provider owns both Livewire aliases, and the administration
  sidebar exposes Customers and Orders only when their capabilities permit it.

### Presentation and QA

- The public cart, checkout, confirmation, and tracking views use the existing
  Aureon theme/font tokens, centered responsive branding, explicit focus
  states, live regions, stable controls, labeled inputs, dark mode, and
  reduced-motion behavior.
- The Commerce Chromium harness now covers catalog search, add-to-cart, header
  count synchronization, cart, checkout delivery switching, product detail,
  signed confirmation, and signed tracking at desktop/tablet/mobile sizes.
- Fourteen final captures and machine-readable diagnostics are retained under
  `.docs/dev/commerce-qa/`. The final run found no horizontal overflow,
  unlabeled controls, duplicate IDs, failed media, loader stalls, browser
  runtime errors, or failed network responses.

### Adoption settings

| Environment key | Default | Purpose |
| --- | --- | --- |
| `COMMERCE_PAYMENT_METHODS` | `mobile_money,bank_transfer,cash_on_delivery` | Comma-separated manual methods exposed by checkout after enum validation |
| `COMMERCE_CART_SESSION_KEY` | `commerce.storefront.cart` | Isolated host-session key for the ID/quantity map |
| `COMMERCE_CONFIRMATION_LINK_MINUTES` | `120` | Short confirmation-page lifetime |
| `COMMERCE_TRACKING_LINK_DAYS` | `90` | Customer tracking-link lifetime |
| `COMMERCE_DOCUMENT_LINK_DAYS` | `30` | Public order-document lifetime |

These keys are present in `.env.example`; production values must be set before
configuration caching.

## File ownership map

| Concern | Module-owned files |
| --- | --- |
| Cart state | `Storefront/Data/StorefrontCartSnapshot.php`, `Storefront/Services/CartSessionService.php` |
| Checkout | `Storefront/Livewire/Checkout.php`, `Storefront/Livewire/Forms/CheckoutForm.php`, `Storefront/Services/StorefrontCheckoutService.php` |
| Signed access | `Storefront/Services/OrderAccessUrlService.php`, `Storefront/Http/Controllers/OrderAccessController.php`, `Resources/views/storefront/orders/` |
| Mail and PDF | `Orders/Notifications/OrderConfirmationNotification.php`, `Orders/Services/OrderDocumentService.php`, `Resources/views/reports/order-summary.blade.php` |
| Customers | `Customers/Livewire/`, `Customers/Services/CustomerService.php`, `Http/Controllers/Admin/CustomerController.php` |
| Orders | `Orders/Livewire/`, `Orders/Services/PaymentService.php`, `Http/Controllers/Admin/OrderController.php` |
| Public UI | `Resources/views/livewire/storefront/`, `Resources/views/storefront/{cart,checkout,orders}/`, `Resources/css/storefront.css` |
| Admin UI | `Resources/views/livewire/admin/{customer-manager,order-manager}.blade.php`, `Resources/views/admin/{customers,orders}/` |
| Livewire configuration | `config/livewire.php` (`pagination_theme` is `bootstrap`) |
| Verification | `tests/Feature/Commerce/CommerceStorefrontOrderingTest.php`, `CommerceCustomerOrderAdminTest.php`, `scripts/qa-commerce.mjs` |

## Verification evidence

- `php vendor/bin/pint --dirty`: passed after formatting changed PHP files.
- `node --check scripts/qa-commerce.mjs`: passed.
- `php artisan route:list --name=commerce`: 12 module routes resolved.
- `php artisan config:cache` followed by `config:clear`: adopter environment
  settings serialize and reload successfully.
- `php artisan test tests/Feature/Commerce`: the focused storefront and
  administration review passed 13 tests and 115 assertions; the complete
  Commerce suite remains covered by the full application run.
- `php artisan test`: 178 tests and 1,386 assertions passed across the complete
  Laravel application.
- `npm.cmd run build`: passed with 64 modules transformed and seven static-copy
  groups published. Existing unresolved-at-build asset notices remain runtime
  URL contracts and did not fail browser loading.
- `node scripts/qa-commerce.mjs`: passed with 13 core captures in the final
  local run, including a post-Livewire-morph 1080px dark checkout capture.
  Signed confirmation and mobile dark tracking captures remain verified by the
  temporary signed-order fixture run.
- A temporary bootstrapped PHP fixture verified real signed URLs against the
  running application; its order/customer data and script were removed after
  QA.

## Residual boundary

- Automated gateways, card storage, refunds, coupons, customer account order
  history, variations, and shipping-rate integrations remain explicit future
  extensions.
- The next master-plan increment is the operational POS terminal interface.
- The unrelated untracked `.docs/model-ulid/` material was not edited or made
  part of this increment.
