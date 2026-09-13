# Commerce Catalog Storefront Admin Links

**Branch:** `feature/commerce-catalog-storefront-links`
**Implemented:** 2026-09-07

## Purpose

Allow catalog operators to open a publicly available product directly from the
administration table without creating a preview path that bypasses storefront
publication rules.

## Contract

- Every catalog viewer receives a storefront action in the product row.
- Products satisfying the existing storefront visibility rule receive an
  external-link action that opens the slug route in a new tab with
  `noopener noreferrer` protection.
- Draft, archived, future-scheduled, and inactive-category products receive a
  disabled action explaining that the product is not currently public.
- Editing remains available through the existing administration action. No
  unpublished preview route and no authorization bypass are introduced.
- `Product::isVisibleInStorefront()` mirrors `visibleInStorefront()` while
  reusing the category relation already loaded by the administration table.
  The public controller uses the same model predicate to prevent drift between
  public route authorization and administration-link availability.

## Files

- `app/Modules/Commerce/Catalog/Models/Product.php`
- `app/Modules/Commerce/Storefront/Http/Controllers/CatalogController.php`
- `app/Modules/Commerce/Resources/views/livewire/admin/product-catalog.blade.php`
- `tests/Feature/Commerce/CommerceAdminInterfaceTest.php`

## Verification

The focused test covers a visible product plus draft, archived, scheduled, and
inactive-category products. It verifies model visibility, the one allowed URL,
protected new-tab attributes, disabled action counts, and the absence of public
links for every hidden state.

Verification completed with:

- focused catalog/storefront suite: 11 tests and 109 assertions;
- full Commerce regression: 137 tests and 4,137 assertions;
- Laravel Pint and Blade view compilation: passed.
