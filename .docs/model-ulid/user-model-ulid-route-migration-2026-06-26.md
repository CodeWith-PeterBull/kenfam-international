# User Model ULID Route Migration

## Scope

User browser routes now resolve through a separate `users.ulid` column while preserving the integer `users.id` as the canonical internal key for authentication, foreign keys, Sanctum tokens, Spatie roles, Mediable attachments, Livewire state, reports, and query filters.

This mirrors the existing Contribution, Receipt, and Withdrawal URL pattern: browser-facing links expose ULIDs; domain and reporting internals continue to use integer IDs.

## Implementation Notes

- Added `users.ulid` as a 26-character unique route key, backfilled existing rows, and made it non-null after the backfill.
- `App\Models\User` now generates a ULID on creation and returns `ulid` from `getRouteKeyName()`.
- Replaced the user resource route with explicit named routes using `{user:ulid}`.
- Updated investor ledger routes and admin service API management routes to bind managed users by ULID.
- Updated user profile, contribution, receipt, withdrawal, ploughback, dashboard, legacy DataTable, and delete-modal links to generate routes from the `User` model instead of raw integer IDs.
- Integer user IDs remain valid inside form state, Livewire filters, database relationships, and controller/service internals.

## Verification

Targeted verification for this migration:

```bash
php artisan test tests/Feature/Users
php artisan test tests/Feature/ManualContributionReceiptTest.php
php artisan test tests/Feature/Contributions/AdminContributionsManagerTest.php
php artisan test tests/Feature/Admin/AdminLedgerRosterTest.php tests/Feature/Statements/AccountStatementTest.php
php artisan test tests/Feature/ServiceApi
php artisan test
vendor/bin/pint --dirty
```
