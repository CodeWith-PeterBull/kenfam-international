# Aureon Homepage Module Gallery Showcase

## Purpose

The public homepage uses curated application captures to show prospective
adopters how each ready module behaves across public, administrative, and
role-specific workflows. These are presentation assets, not runtime screenshots:
the homepage remains database-independent and does not expose authenticated
screens or application data dynamically.

## Commerce Gallery

The Commerce set uses the `hardware-construction` demonstration context so the
catalog, product detail, POS terminal, and administration views show a coherent
Kenyan merchant scenario rather than generic placeholders.

| Public asset | View | Audience | QA source |
| --- | --- | --- | --- |
| `commerce-storefront.webp` | Storefront catalog | Public | `.docs/dev/commerce-qa/catalog-desktop.png` |
| `commerce-product-detail.webp` | Product details | Public | `.docs/dev/commerce-qa/product-detail-desktop.png` |
| `commerce-dashboard.webp` | Sales and operations overview | Administrator | `.docs/dev/commerce-dashboard-qa/desktop-light-30-days-viewport.png` |
| `commerce-catalog-admin.webp` | Product catalog | Administrator | `.docs/dev/dashboard-qa/commerce-catalog-desktop.png` |
| `commerce-inventory.webp` | Inventory control | Administrator | `.docs/dev/dashboard-qa/commerce-inventory-desktop.png` |
| `commerce-demo-data.webp` | Merchant context selector | Administrator | `.docs/dev/commerce-demo-data-qa/desktop-light.png` |
| `commerce-pos.webp` | Sales terminal | Cashier | `.docs/dev/commerce-pos-qa/terminal-desktop-light.png` |
| `commerce-cashier-sales.webp` | Session sales history | Cashier | `.docs/dev/commerce-pos-qa/cashier-sales-history-desktop-light.png` |
| `commerce-registers.webp` | POS register directory | Supervisor | `.docs/dev/commerce-pos-qa/register-admin-desktop-light.png` |
| `commerce-tills.webp` | Till sessions and reconciliation | Supervisor | `.docs/dev/commerce-pos-qa/till-admin-desktop-dark.png` |
| `commerce-barcodes.webp` | Barcode and label workspace | Catalog manager | `.docs/dev/commerce-barcode-qa/barcode-workspace-desktop-light.png` |
| `commerce-role-access.webp` | Roles and permissions | System administrator | `.docs/dev/dashboard-qa/roles-permissions-desktop.png` |

## Property Management Gallery

| Public asset | View | Audience | QA source |
| --- | --- | --- | --- |
| `property-booking-storefront.webp` | Stay catalog | Public | `.docs/PropertyBooking/qa/storefront/desktop-light-catalog.png` |
| `property-booking-unit.webp` | Property details | Public | `.docs/PropertyBooking/qa/storefront/desktop-dark-property.png` |
| `property-booking-checkout.webp` | Guest checkout | Public | `.docs/PropertyBooking/qa/storefront/desktop-light-checkout.png` |
| `property-booking-dashboard.webp` | Accommodation operations overview | Property manager | `.docs/PropertyBooking/qa/catalog-availability/operations-dashboard-desktop-light.png` |
| `property-booking-properties.webp` | Property directory | Property manager | `.docs/PropertyBooking/qa/catalog-availability/desktop-light-properties.png` |
| `property-booking-amenities.webp` | Amenity catalogue | Property manager | `.docs/PropertyBooking/qa/catalog-availability/desktop-dark-amenities.png` |
| `property-booking-units.webp` | Unit and room inventory | Property manager | `.docs/PropertyBooking/qa/catalog-availability/tablet-light-units.png` |
| `property-booking-bookings.webp` | Booking desk | Booking agent | `.docs/PropertyBooking/qa/catalog-availability/operations-bookings-desktop-dark.png` |
| `property-booking-readiness.webp` | Housekeeping board | Housekeeping | `.docs/PropertyBooking/qa/catalog-availability/operations-readiness-tablet-dark-reduced-motion.png` |
| `property-booking-pob.webp` | Point-of-booking terminal | Receptionist | `.docs/PropertyBooking/qa/pob/terminal-desktop-light.png` |
| `property-booking-registers.webp` | Reception registers | Property manager | `.docs/PropertyBooking/qa/pob/register-admin-desktop-light.png` |
| `property-booking-shifts.webp` | Reception shifts | Property manager | `.docs/PropertyBooking/qa/pob/shift-admin-desktop-dark.png` |

## Asset Contract

- Public derivatives live in `resources/aureon/assets/images/home/modules/`.
- Every derivative is WebP, metadata-stripped, and normalized to 1280 x 800.
- Keep files small enough for lazy loading; the current set is below 100 KB per
  image and below 1 MB per complete 12-view module gallery.
- Do not include real customer, guest, payment, identity, or production data.
- Use seeded fixtures and retain one context across related transaction views.
- Keep `alt`, `label`, and `role` metadata in `config/aureon-home.php` aligned
  with the visible screen.

## Refresh Procedure

1. Seed or select the intended demonstration context in a local environment.
2. Run the module's established browser QA and confirm it passes.
3. Select captures that represent materially different workflows.
4. Normalize selected captures to 1280 x 800 WebP derivatives.
5. Update the config registry and this manifest together.
6. Run `php artisan test tests/Feature/HomePageTest.php`, `npm run build`, and
   `npm run qa:homepage`.

Homepage galleries use a lazy-loaded horizontal filmstrip. Arrow buttons and
Left/Right keys wrap across the complete set, while each selected view updates
the stage image, audience label, caption, and position announcement.
