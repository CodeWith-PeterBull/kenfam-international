# Commerce Operations Handbook

**Status:** Operator reference for the implemented Commerce workflows.

**Updated:** 2026-08-18

## 1. Audience and Operating Rule

This handbook is for catalog editors, inventory controllers, order operators,
cashiers, till managers, and system administrators. It explains how to use the
current interface and what each action commits.

The central operating rule is simple: use the supplied Livewire workspaces and
domain services. Do not repair totals, stock, payments, or till cash by editing
database rows. Commerce uses locked transactions, immutable snapshots, and an
append-only stock ledger to keep its two sales channels consistent.

## 2. Responsibility Matrix

| Work | Required permission |
| --- | --- |
| View Commerce overview | `view-commerce-dashboard` |
| View or export catalog | `view-products` |
| Change products/categories | `manage-products` |
| View or export inventory | `view-inventory` |
| Adjust stock | `manage-inventory` |
| View orders and documents | `view-orders` |
| Advance/cancel orders and record payments | `manage-orders` |
| Manage reusable customers | `manage-customers` |
| Sell through POS | `access-pos` |
| Configure registers and reconcile tills | `manage-tills` |

Page access does not imply mutation access. Livewire actions authorize again at
the record boundary. An inactive account is denied even if roles remain
assigned.

## 3. Daily Opening Checklist

Before public or counter trading begins:

1. Confirm the institution identity, contact email, phone, and logos are
   correct because they appear in storefront actions, mail, reports, and
   receipts.
2. Confirm `COMMERCE_ENABLED=true` and that the public `/shop` route loads.
3. Check `/admin/commerce` for orders requiring action, low/out-of-stock items,
   active tills, and recent payments.
4. Confirm published products have the intended selling price, tax behavior,
   media, and stock policy.
5. Confirm production queue workers and mail transport are healthy.
6. Confirm each selling location has an active register with the intended
   receipt width and print mode.
7. Open one till for each cashier who will transact. A cashier and a register
   may each own only one open session.
8. Ask the cashier to verify the register/session band on `/pos` before taking
   payment.

## 4. Commerce Overview

Open `/admin/commerce`. The range selector supports 7, 30, and 90 days and is
stored in the URL so a filtered view can be bookmarked.

Interpret the indicators carefully:

| Indicator | Meaning |
| --- | --- |
| Payments collected | Completed payment amounts whose `paid_at` falls in the range |
| Order value | Numbered, placed, non-held, non-cancelled order totals in the range |
| Orders received | Numbered, placed, non-held orders, including later cancellations |
| Average order value | Integer order value divided by contributing non-cancelled orders |
| Orders requiring action | Current web orders in pending, confirmed, processing, or ready |
| Stock alerts | Tracked stock at or below its product threshold |
| Open tills | Sessions still open and without a closure timestamp |
| Active customers | Reusable customer records that are not archived |

An order value is not collected cash. Held POS orders are not sales, and
cancelled orders contribute no order value. Follow each panel link only when the
signed-in operator has the corresponding downstream permission.

## 5. Catalog Operations

### 5.1 Create a category

1. Open `/admin/commerce/catalog`.
2. In Product categories, choose **Add category**.
3. Enter the name, optional parent, description, sort order, SEO fields, and
   optional JPEG, PNG, or WebP image.
4. Save the category.
5. Use the active/hidden action to control whether it is available to public
   catalog navigation.

Category hierarchy is cycle-protected. The current workflow hides categories
instead of destructively deleting them. Replacing or removing a category image
is recorded in system activity.

### 5.2 Create a product

1. Choose **Add product**.
2. Set the category, name, SKU, optional manufacturer barcode, descriptions,
   quantity limits, unit label, tax rate, dimensions, specifications, featured
   state, and SEO metadata.
3. Enter regular, optional sale, and internal cost prices in major currency
   units. Inputs are converted to integer minor units without floating-point
   arithmetic.
4. Leave the internal barcode empty to assign one after creation, or generate
   it from the form. Internal and manufacturer barcodes must remain unique.
5. Configure whether stock is tracked.
6. Upload up to the configured gallery limit using JPEG, PNG, or WebP files.
7. Save. New products are drafts and receive a zero-stock projection.

A draft does not appear in the storefront or POS. Add stock where required,
review the public presentation, then explicitly publish it. Archive products
that should no longer be sold. Historical order-item snapshots remain intact.

### 5.3 Update media

Open the product editor to add or remove gallery images. The service checks the
persisted gallery cap and verifies that a removed media row belongs to that
product and collection. Do not delete media rows directly.

### 5.4 Print barcode labels

1. Open `/admin/commerce/catalog/barcodes` or use a product row's barcode icon.
2. Search and add products to the label run.
3. Set each quantity and choose columns and visible store/name/price fields.
4. Keep the run at or below the configured maximum, default 300 labels.
5. Generate the A4 PDF and print using the browser/PDF workflow.

POS exact lookup checks the internal barcode, optional manufacturer barcode,
and SKU. The label workspace prints the internal Code 128 barcode.

### 5.5 Export the filtered catalog

Apply search, status, and category filters, then choose **Export PDF**. The
landscape report reflects the current Livewire filter state, includes selling
prices, and excludes cost price. Synchronous output is capped at the first 2,000
matching rows and states when the result is truncated.

## 6. Inventory Operations

### 6.1 Understand the two records

`stocks.on_hand` is the current projection used for fast reads. Every change is
also represented by an immutable stock movement containing the signed change
and before/after balances. These values must reconcile.

### 6.2 Post a manual adjustment

1. Open `/admin/commerce/inventory`.
2. Search by product or filter by Healthy, Low, Out, or Untracked.
3. Choose the adjustment action on the intended stock row.
4. Select increase or decrease.
5. Enter a positive quantity and a meaningful reason.
6. Confirm the resulting projection and movement row.

`InventoryService` locks the product and stock projection, applies the signed
movement, prevents a disallowed negative tracked balance, writes both records
in one transaction, and records `commerce.stock.adjusted` activity.

### 6.3 Respond to low stock

1. Open the low-stock panel from the Commerce overview or filter Inventory.
2. Confirm whether the item is tracked and inspect its configured threshold.
3. Review recent movements for an unexpected deduction or manual adjustment.
4. If physical stock exists, post a reasoned adjustment increase.
5. If no stock exists, leave the projection accurate and pause/archive the
   product when business policy requires it.
6. Verify the resulting public/POS availability.

One low-stock alert is emitted only when stock crosses from above the threshold
into the positive low band. One out-of-stock alert is emitted when positive
stock reaches zero or below. Further deductions in the same state do not repeat
the alert; replenishment above the threshold rearms it.

### 6.4 Export inventory reports

The Inventory workspace exposes two filter-aware PDFs:

- **Stock levels:** current on-hand, threshold, and derived state.
- **Movement history:** movement type, signed change, and running balance.

Both reuse the shared institution report layout and enforce `view-inventory`.

## 7. Storefront Order Intake

### 7.1 What checkout commits

A valid checkout does not trust totals from the browser. It stores only product
IDs and quantities in the session, then re-fetches published products, current
prices, quantity limits, and stock before placement.

On successful placement, one transaction:

1. Resolves or creates a reusable customer.
2. Writes an independent immutable customer/address snapshot to the order.
3. Creates immutable selling-price order-item snapshots.
4. Assigns a `WEB` order number.
5. Commits tracked stock and movement rows.
6. Records one pending manual payment preference.
7. Clears the session cart only after commit.
8. Queues the customer acknowledgement and emits the staff new-order event.

Guest details are therefore persisted in the reusable `customers` table. A
guest is reused only when the normalized first name, last name, email, and phone
tuple matches exactly; otherwise a new customer is created. The order snapshot
remains independent even when a reusable customer is edited later.

### 7.2 Pickup order

Pickup stores the configured store-pickup fulfillment type and no delivery fee.
The operator should verify contact details, payment preference, customer note,
and ordered quantities in the order details before processing.

### 7.3 Delivery order

Delivery requires the checkout address fields and applies the configured flat
delivery fee in minor units. The current engine does not quote zones, distance,
weight, carriers, or live shipping rates.

## 8. Web Order Processing

Open `/admin/commerce/orders`, then search or filter by channel, fulfillment
status, and payment status.

### 8.1 Inspect before action

Open the order detail and confirm:

- order number and web channel;
- immutable customer/contact/address snapshot;
- fulfillment type and customer note;
- item name, SKU, quantity, selling price, tax, and total snapshots;
- payment preference, completed payments, paid amount, and balance;
- current fulfillment and payment states.

Internal notes, raw metadata, cost prices, and sequential database identifiers
are not customer-facing document fields.

### 8.2 Record a manual payment

1. Choose the payment action on an eligible web order.
2. Select the actual method.
3. Enter a positive amount no greater than the outstanding balance.
4. Add a sanitized external reference when one exists.
5. Confirm the payment.

The service reuses the pending preference where possible, persists a completed
payment, and derives the order payment state as partial or paid. Every completed
web payment queues its own customer confirmation. This action records a payment;
it does not call or verify an external gateway.

### 8.3 Advance fulfillment

The only legal forward path is:

```text
pending -> confirmed -> processing -> ready -> completed
```

Use the next-state action shown by the interface. Skipping or reversing states
is rejected. Reaching `ready` on a web order queues the dedicated customer-ready
message. `completed` ends the initial fulfillment lifecycle.

### 8.4 Cancel and restore stock

Cancellation is available only when all of these facts are true:

- channel is web;
- state is pending, confirmed, processing, or ready;
- aggregate payment state is unpaid and `paid_minor` is zero;
- stock was committed and has not already been released; and
- a reason of 1 to 255 characters is supplied.

The service locks the order, restores stock exactly once through order-cancel
movement rows, sets the cancelled state and timestamp, records warning-level
activity, and queues the customer cancellation message. Paid orders require a
future returns/refunds workflow and must not be forced through this action.

### 8.5 Download documents

Order operators can download portrait or landscape order summaries. Customers
receive temporary signed document links. Both modes use immutable display DTOs,
institution branding, safe filenames, private/no-store responses, and the
shared two-pass PDF renderer.

Filtered order-register export is separate: it uses current list filters,
excludes private payment metadata, and is capped at 2,000 rows.

## 9. Customer Directory

Open `/admin/commerce/customers` to search active, archived, or all reusable
customer records.

Operators can:

- create and edit names, company, contact, tax identifier, and default address;
- optionally link an available application user account;
- archive a customer without deleting order history; and
- restore an archived customer.

Changing a reusable customer never rewrites snapshots on historical orders.
Do not use customer merging to combine shared household or office contact data;
the existing checkout resolver intentionally requires a conservative identity
match.

## 10. Register Administration

Open `/admin/commerce/pos/registers` with `manage-tills`.

For each physical or logical endpoint, configure:

- stable name and code;
- optional location and description;
- active/inactive state;
- receipt driver key;
- manual or post-sale prompt mode;
- 58 mm or 80 mm roll width; and
- optional operator-facing printer/queue label.

An active register can receive a till. A register with an open till cannot be
deactivated. Environment print values seed defaults for new records; editing a
register owns the effective settings.

The built-in browser driver does not select a physical device or print
silently. It formats the receipt and opens the system dialog when requested.

## 11. Till Operations

### 11.1 Open a till

1. Open `/admin/commerce/pos/tills`.
2. Choose **Open till**.
3. Select an active register without an open session.
4. Select an active cashier who explicitly holds `access-pos`.
5. Enter a non-negative opening float and optional note.
6. Confirm the assignment.

The service locks cashier and register rows. It rejects an inactive register,
a register already open, or a cashier who already owns another open session.

### 11.2 Reconcile and close

1. Resolve every held order on the session by completing or discarding it.
2. Count physical cash independently.
3. Choose **Reconcile** on the open session.
4. Enter the non-negative counted cash and optional closing note.
5. Review the persisted expected, counted, and variance values.

Expected cash is recalculated at commit as:

```text
opening float + completed cash payments assigned to the till
```

Variance is:

```text
counted cash - expected cash
```

A negative value is a shortage; a positive value is an overage. Any non-zero
variance records warning-level activity. A dedicated email is queued only when
the absolute variance meets or exceeds the configured material threshold.

## 12. POS Sale Procedure

### 12.1 Start the terminal

Sign in as the assigned cashier and open `/pos`. The terminal discovers that
cashier's open session on every Livewire request. Without one, it shows a
non-transactable state instead of accepting a sale.

### 12.2 Find and add products

- Scan or enter an exact internal barcode, manufacturer barcode, or SKU and
  submit the lookup.
- For ordinary discovery, search by SKU or product name.
- Select only published, in-stock products.
- Adjust quantities with the stable plus/minus controls.

Every preview and checkout re-fetches current product, price, quantity limits,
and stock. Browser state is not the source of truth.

### 12.3 Select the customer

Leave **Walk-in customer** for an anonymous counter sale, or search and select a
reusable customer. The completed order stores a snapshot of the selected
customer. The current terminal does not create a new customer inline; use the
Customer workspace first when the identity must be retained.

### 12.4 Discount

Enter the fixed discount supported by the terminal and provide the required
reason. The server recalculates totals and rejects a discount that violates the
cart contract. Percentage rules, coupons, per-line discounts, and role-specific
limits are not part of this version.

### 12.5 Hold, resume, or discard

- **Hold sale:** persists an unnumbered POS order in `held` state. It does not
  commit stock or create payments.
- **Resume:** available only to the owning cashier on the current till. Current
  products, prices, stock, and totals are recalculated before completion.
- **Discard:** converts the hold to a cancelled audit record without assigning
  an order number or touching stock.

Held orders block till closure and must be resolved first.

### 12.6 Collect payment

1. Select a configured payment method.
2. Use **Due** to apply the remaining balance or enter the exact applied amount.
3. Add split-tender rows up to the configured maximum.
4. For non-cash tenders, enter the external reference when business procedure
   requires it.
5. For cash, enter cash received. It must be at least the applied cash amount.
6. Confirm that applied tenders exactly equal the server-calculated total.
7. Review calculated cash change, then choose **Complete sale**.

POS checkout locks the till, products, stock, and held order where applicable.
It creates/completes the numbered POS order, commits stock, writes completed
payments, updates expected cash, records activity, and redirects to the receipt
only when the whole transaction succeeds.

### 12.7 Review cashier sales

The foldable **My sales** panel sits below held sales on the terminal. The same
read-only component appears on an eligible cashier's role dashboard and, for an
operator with both capabilities, the Commerce overview.

- **Active session** includes only completed sales assigned to the signed-in
  cashier's currently open till.
- **All session sales** includes that cashier's completed POS history and can be
  narrowed to today or the last 7, 30, or 90 days.
- Summary values are transaction count, gross sales, and average sale for the
  selected scope.
- Receipt actions open the existing ownership-protected receipt in a new tab.

Held orders, cancelled rows, web orders, and another cashier's transactions are
excluded from both rows and aggregates. Customer contact details, tender
references, costs, and internal notes are not displayed. A cashier without an
open till can still use **All session sales** from the dashboard.

Adopters may independently hide the terminal or dashboard placement with
`COMMERCE_POS_SALES_HISTORY_TERMINAL` and
`COMMERCE_POS_SALES_HISTORY_DASHBOARD`. This only changes presentation and never
deletes or archives transactions.

### 12.8 Receipt handling

The receipt page is private and ownership protected. The owning cashier,
`view-orders` supervisors, `manage-tills` supervisors, and active system
administrators can inspect a completed POS receipt.

- **Manual mode:** use the Print action when required.
- **Auto-prompt mode:** the first post-checkout visit requests the browser print
  dialog after the loader settles. Refresh does not repeatedly prompt.
- **PDF:** use the protected portrait PDF action for reprints or archiving.
- **New sale:** return to the terminal after receipt handling.

Print media always forces white paper and dark text, even when the application
screen is in dark mode. A historical receipt opened later remains quiet.

## 13. Operational Notifications

| Activity | Recipient rule |
| --- | --- |
| New web order | Active users explicitly holding `manage-orders` |
| Completed web payment | Immutable order snapshot email |
| Web order ready | Immutable order snapshot email |
| Eligible web cancellation | Immutable order snapshot email |
| Stock becomes low | Active users explicitly holding `manage-inventory` |
| Stock is depleted | Active users explicitly holding `manage-inventory` |
| Material till variance | Active users explicitly holding `manage-tills` |

Each event has its own switch, listener, queued message, bounded retry policy,
and current-state guard. Delivery re-queries the subject, so replenished stock,
disabled events, missing/invalid customer email, deleted records, inactive
users, or stale states fail closed.

The original customer order acknowledgement is independent from the staff
new-order alert. Do not replace either with a generic activity message.

## 14. Audit and Incident Review

Expected successful mutations create stable `commerce.*` entries in System
activity. Use that trail to answer who changed a product, adjusted stock,
advanced an order, recorded payment, completed a POS sale, or closed a till.

Use Application logs for unexpected exceptions and queue delivery failures.
Never paste customer addresses, payment metadata, card data, credentials, or
full request bodies into notes or logs.

## 15. Contextual Demonstration Catalogs

Contextual fixtures are optional development and demonstration data. Seed the
original mixed catalog with `php artisan commerce:demo-seed`, or pass one of the
supported context keys shown by `php artisan help commerce:demo-seed`.

The default behavior keeps existing products and upserts the selected context.
Use `--archive-existing` only when the operator deliberately wants the selected
context to become the active catalog. The option archives current products and
then republishes the selected products; it does not delete catalog or
transaction history. On a fresh local database:

```powershell
php artisan migrate:fresh --seed
php artisan commerce:demo-seed boutique-fashion
```

Operators holding `manage-commerce-demo-data` can perform the equivalent run at
`/admin/commerce/demo-data`. Select a visual merchant context, leave the default
keep mode active for an idempotent additive run, and submit. Archive mode is
available only after its separate acknowledgement is checked. Do not refresh or
start another run while the component reports that seeding is in progress.

Each new contextual product uses an optimized product-specific featured image
and a branded secondary gallery fallback. Replace the fallback with an approved
second view while retaining its path, then rerun the seeder; source checksums
ensure the copied media is refreshed. Treat all prices, stock, tax settings, and
regulated product labels as demonstration content requiring adopter review.

## 16. Common Problems

| Symptom | Checks and resolution |
| --- | --- |
| Commerce URL returns 404 | Confirm `COMMERCE_ENABLED=true`, provider registration, and cleared route/config caches |
| Authenticated user receives 403 | Confirm active account, verified email, exact permission, role reconciliation, and a fresh login session |
| Cashier cannot transact | Open a till assigned to that cashier on an active register |
| Till cannot close | Complete or discard every held order owned by the session |
| Register cannot deactivate | Close its open till first |
| Product absent from storefront/POS | Confirm published status, category visibility where relevant, valid quantity limits, and stock availability |
| Stock adjustment rejected | Confirm `manage-inventory`, positive quantity input, tracked balance, and oversell setting |
| Payment rejected | Confirm order is payable, amount is positive and not above balance, tender semantics, and non-cancelled/non-held state |
| Cancellation unavailable | Confirm web channel, eligible state, zero completed payment, committed/unreleased stock, and a reason |
| Notification missing | Check global/event switch, explicit recipient permission, active status, snapshot email, queue worker, failed jobs, and logs |
| Auto print does not open | Confirm register `auto_prompt`, first post-checkout visit, browser dialog policy, and that no bridge cancelled the print event |
| Dark receipt prints dark | Rebuild current POS assets and verify print media rather than screen styling |
| Signed customer link fails | Confirm signature and lifetime; expired links are intentionally rejected |

## 17. Daily Closing Checklist

1. Process or intentionally leave documented web orders in their correct state.
2. Resolve all POS holds.
3. Reconcile each open till using a physical cash count.
4. Investigate material variance and stock alerts through activity and movement
   history rather than direct row edits.
5. Confirm the queue has no failed Commerce jobs.
6. Confirm required order/receipt reports have been retained under the adopting
   organization's records policy.
7. Leave no shared cashier session authenticated on a public terminal.
