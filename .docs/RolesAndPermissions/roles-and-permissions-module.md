# Roles And Permissions Module

## Status

Implemented and verified on `feature/user-roles-permissions-management`.

## Purpose

This module provides a maintainable administration interface for composing Spatie roles from the CMS permission catalogue. It complements user management without turning authorization identifiers into mutable content.

## Benchmark finding

The CSK benchmark contains role and permission seeders and role-assignment use in user management, but no reusable role-and-permission manager component was found. Aureon therefore adapts the available architecture and implements the missing manager against the installed Spatie Permission 6.25 contract.

## Authorization model

- Permissions are developer-owned capabilities declared in `CmsPermission`.
- Roles are database-owned bundles of those capabilities.
- Users inherit permissions through roles; the manager does not assign direct user permissions.
- Application routes, views, and actions check permissions through Laravel Gate.

This follows Spatie's guidance that permissions remain the stable capabilities checked by application code while roles group those capabilities for users.

## Management scope

The Livewire manager provides:

- searchable role catalogue and member/permission counts;
- create custom role;
- edit role label/name and permission membership;
- inspect the complete code-owned permission catalogue;
- delete an unused custom role after explicit confirmation;
- grouped permission selection for readable administration.

It intentionally does not create, rename, or delete permissions in the UI. A permission that is not represented in application code cannot safely grant real behavior.

## Structural role safeguards

`system-admin`, `content-manager`, `editor`, and `viewer` are base role identifiers because `UserType` refers to them. They cannot be renamed or deleted. `system-admin` is fully locked and always receives every foundational permission. Permission membership for the other base roles may be configured.

Custom roles may be renamed and deleted only when they have no assigned users. All mutations use Spatie's built-in role/permission methods so its cache lifecycle remains authoritative.

## Livewire and service design

- `Admin\RolesAndPermissionsManager`: authorization, filter state, computed catalogue, locked selected role, and modal actions.
- `Forms\RoleForm`: role name and permission selection with runtime uniqueness/existence rules.
- `RoleManagementService`: transactional mutation, structural-role invariants, cache-safe Spatie APIs, and activity records.
- `boot()` enforces view access on every Livewire request; every mutation separately enforces manage access.
- Role IDs are locked and reloaded server-side before use.

## Permissions introduced

- `view_users`
- `manage_users`
- `view_roles_and_permissions`
- `manage_roles_and_permissions`

The system administrator receives all permissions through the idempotent `RoleSeeder`. Viewer and manager permissions remain separate so read-only access can be delegated later.

## Planned file ownership

- `app/Services/RoleManagementService.php`
- `app/Livewire/Forms/RoleForm.php`
- `app/Livewire/Admin/RolesAndPermissionsManager.php`
- `app/Support/CmsPermission.php`
- `database/seeders/RoleSeeder.php`
- admin controller/page, route, sidebar, component Blade, and CSS files
- `tests/Feature/RolesAndPermissions/*`

## Verification target

- Route and action authorization tests.
- Role create/update/delete tests, including cache-visible permission changes.
- Structural-role, assigned-user, duplicate-name, invalid-permission, and forged-ID safeguards.
- Full application test and browser QA baselines.

## Final implementation record

### Delivered authorization catalogue

- Expanded `CmsPermission` with separate view/manage capabilities for users and roles/permissions.
- Added labels, groups, and descriptions to a single developer-owned catalogue used by seeding and UI presentation.
- Kept `foundational()` as the canonical identifier list derived from the catalogue.
- Updated `RoleSeeder` to use `UserType` base identifiers and `syncPermissions()` so system-admin always exactly receives the current foundational set.

### Delivered role mutation layer

`RoleManagementService` now provides:

- transactional custom-role creation;
- custom-role rename and permission updates;
- permission membership updates for configurable non-admin base roles;
- code-owned permission allow-list enforcement;
- assigned-user protection before custom-role deletion;
- immutable base-role identifiers and deletion protection;
- a fully locked system-admin role;
- Spatie-native `syncPermissions()` use for automatic cache invalidation;
- structured create/update/delete activity records.

### Delivered Livewire workflow

- Added `RoleForm` with runtime name uniqueness and permission allow-list validation.
- Added `Admin\RolesAndPermissionsManager` with Gate authorization in `boot()`, action-level manage checks, locked selection, computed role/catalogue/statistic data, grouped selection controls, and confirmation state.
- Added theme-safe base-role, custom-role, and locked-state badges with explicit light/dark foreground and surface tokens.
- Added a responsive role table, member/permission counts, full read-only capability catalogue, role editor, and protected deletion dialog.
- Added permission-protected `/admin/roles-and-permissions` routing and shared sidebar navigation.

### Files added

- `app/Http/Controllers/Admin/RolePermissionController.php`
- `app/Livewire/Admin/RolesAndPermissionsManager.php`
- `app/Livewire/Forms/RoleForm.php`
- `app/Services/RoleManagementService.php`
- `resources/views/admin/roles-and-permissions/index.blade.php`
- `resources/views/livewire/admin/roles-and-permissions-manager.blade.php`
- `tests/Feature/RolesAndPermissions/RolesAndPermissionsManagementTest.php`

### Existing files extended

- `app/Support/CmsPermission.php`
- `database/seeders/RoleSeeder.php`
- `routes/web.php`
- dashboard sidebar/CSS, README ledger, and dashboard QA harness

### Verified behaviors

- Guest, missing-view-permission, and missing-manage-permission boundaries.
- Custom-role create, rename, permission replacement, and deletion.
- Spatie-visible permission changes after `syncPermissions()`.
- Base-role rename/delete protection and system-admin edit lock.
- Assigned custom-role deletion protection.
- Invalid/non-catalogue permission rejection.
- Structured role activity records.
- Idempotent seeding of four base roles and all foundational permissions.

The dedicated role-management suite passes 6 tests. It is also included in the complete 101-test, 484-assertion Laravel baseline documented in the user-management record.
