# User Management Module

## Status

Implemented and verified on `feature/user-roles-permissions-management`.

## Purpose

This module extends the Laravel Aureon account domain with a reusable corporate user profile, profile-photo media, account classification, account status, and a permission-protected Livewire administration workflow. The existing `users.name` field remains the unique-facing username/display handle; personal names belong to the profile.

## Reviewed sources

- Aureon Laravel 12.64 / Livewire 4.3.3 application conventions.
- CSK `User`, `UserProfile`, `UserForm`, `UserService`, and `UserManagement` implementation.
- Supplied Livewire 4 notes covering properties, actions, forms, events, and lifecycle hooks.
- Livewire 4 official properties, validation, pagination, and upload documentation.
- Spatie Media Library 11 model/collection documentation.
- Spatie Permission 6 role and permission guidance.

The CSK implementation provides the profile/service split and CRUD workflow reference. Its component contains an unresolved authorization TODO and trusts mutable record identifiers; those behaviors are deliberately not carried into Aureon.

## Domain contract

### Account record

`users` remains responsible for authentication and account state:

- `name`: username, retained for Breeze compatibility.
- `email`, password, verification, two-factor state, and last login: existing security contract.
- `user_type`: enum-backed account classification using a portable string column.
- `is_active`: login eligibility and administrative account status.

`UserType` starts with `system-admin`, `content-manager`, `editor`, and `viewer`. It is a workflow/classification helper, not an authorization mechanism. `can()`, route permission middleware, and Gate checks remain authoritative.

### Profile record

`user_profiles` is a one-to-one extension containing:

- first, middle, and last names;
- date of birth;
- identification type and ID/passport number;
- phone number;
- job title;
- biography;
- timestamps and a unique, cascading `user_id` relationship.

Profile fields are nullable at schema level so existing accounts and lean self-registration remain migratable. The administration create/update workflow requires first and last names. Identification numbers are unique when present.

### Profile photo

The `User` model implements Spatie Media Library's `HasMedia` contract and owns a single-file `profile_photo` collection on the public disk. Accepted media are JPEG, PNG, and WebP images. SVG is excluded. The original is used responsively so the base engine does not require an image-conversion worker.

### Role synchronization

The selected user type always contributes its matching base role. Administrators may assign additional existing roles. The service synchronizes the final role set inside the same transaction as account/profile changes. Permission checks never use `user_type` as a substitute for Spatie permissions.

## Security and integrity invariants

- Every Livewire request is authorized in `boot()`; mutating actions re-authorize the manage permission.
- Selected database IDs use Livewire's `#[Locked]` attribute and every action reloads its target server-side.
- Public component methods accept only validated scalar identifiers and never accept client-hydrated models as authority.
- Inactive users cannot authenticate.
- An administrator cannot deactivate or delete their own account.
- The last active system administrator cannot be deactivated, demoted, or deleted.
- Passwords are validated with Laravel defaults and use the model's hashed cast.
- Email changes clear verification state.
- Profile photo uploads are independently validated before Media Library persistence.
- User, profile, role, and media mutations are transactional and recorded through `RecordsSystemActivity` without passwords or uploaded file contents.

## Livewire design

- `Admin\UserManagement`: listing, filtering, pagination, modal state, locked selection, and authorized actions.
- `Forms\UserForm`: typed form state, runtime uniqueness rules, normalization, and create/update payloads.
- `UserManagementService`: transactional account/profile/media/role mutations and lifecycle safeguards.
- Computed properties own database-backed user and role lists.
- Filter lifecycle hooks reset pagination.
- `WithFileUploads` owns the temporary profile-photo upload.
- Bootstrap modals remain view state controlled by Livewire; no duplicate client-side source of truth is introduced.

## Self-profile integration

The Breeze profile information form remains a conventional HTTP form and is expanded rather than replaced. It edits username, email, personal details, bio, and profile photo through the same service contract. Password, two-factor, and account-deletion cards retain their established ownership.

## Planned file ownership

- `app/Enums/UserType.php`
- `app/Enums/IdentificationType.php`
- `app/Models/UserProfile.php`
- `app/Models/User.php`
- `app/Services/UserManagementService.php`
- `app/Livewire/Forms/UserForm.php`
- `app/Livewire/Admin/UserManagement.php`
- user/profile migrations, factory, seeder, request, controller, Blade, route, navigation, and CSS files
- `tests/Feature/UserManagement/*`
- expanded profile, authentication, and seeder tests

## Verification target

- Focused model/service/Livewire/route/profile tests.
- Full `php artisan test` suite.
- PHP syntax checks, route inspection, cached Blade compilation, and production Vite build.
- Authenticated desktop/mobile browser checks for user administration and profile editing.

## Final implementation record

### Delivered domain layer

- Added `UserType` (`system-admin`, `content-manager`, `editor`, `viewer`) and `IdentificationType` backed enums.
- Added indexed `users.user_type`, indexed `users.is_active`, and a unique username constraint through an additive migration.
- Added the commented `user_profiles` schema and `UserProfile` model/factory.
- Extended `User` with typed casts, the profile relation, display-name/initial helpers, user-type checks, and a single-file Spatie `profile_photo` collection.
- Updated the development seeder to align the administrator's type, role, active state, and profile; updated self-registration to create an active viewer.

### Delivered mutation layer

`UserManagementService` now owns:

- transactional create and update across account, profile, roles, media, and activity history;
- self-profile updates without exposing access-control fields;
- deterministic type-to-base-role synchronization plus optional custom roles;
- activation/deactivation and managed/self deletion;
- email verification invalidation when an address changes;
- replace/remove profile-photo behavior using randomized filenames;
- self-action and last-active-system-administrator safeguards;
- structured audit events that list changed field names without logging passwords, IDs, biographies, or file contents.

Inactive accounts are rejected after credential verification and immediately logged out. Self-deletion validates the last-administrator invariant before logout so Laravel's remember-token update cannot re-persist a deleted model.

### Delivered Livewire workflow

- Added typed `UserForm` state with runtime unique/enum/role validation and normalized payload methods.
- Added `Admin\UserManagement` with URL-backed search/type/status/role filters, guarded page size, Bootstrap pagination, computed statistics, computed queries, locked selection, upload validation, and create/edit/view/delete/status actions.
- Added responsive Bootstrap administration views, compact icon actions, tooltips/titles, desktop/mobile-safe table containment, profile-photo previews, account/profile/access form sections, and explicit empty/loading/error states.
- Added explicit light/dark theme role-badge foregrounds and surfaces so access labels remain legible independently of DreamPOS badge utilities.
- Added permission-aware routes and sidebar navigation for `/admin/users`.

### Delivered self-profile workflow

- Expanded the Breeze profile request/controller/view to include username, personal names, DOB, ID/passport, phone, job title, biography, and profile photo.
- Preserved the existing password, email verification, two-factor, and account-deletion ownership boundaries.
- Updated topbar/sidebar identity presentation to use the profile display name and photo with initials fallback.

### Files added

- `app/Enums/IdentificationType.php`
- `app/Enums/UserType.php`
- `app/Http/Controllers/Admin/UserController.php`
- `app/Livewire/Admin/UserManagement.php`
- `app/Livewire/Forms/UserForm.php`
- `app/Models/UserProfile.php`
- `app/Services/UserManagementService.php`
- `database/factories/UserProfileFactory.php`
- `database/migrations/2026_07_16_120000_add_management_fields_to_users_table.php`
- `database/migrations/2026_07_16_120100_create_user_profiles_table.php`
- `resources/views/admin/users/index.blade.php`
- `resources/views/livewire/admin/user-management.blade.php`
- `tests/Feature/UserManagement/UserManagementTest.php`

### Existing files extended

- User model/factory, registration, login, profile request/controller/views, database seeder, CMS permissions, routes, sidebar, topbar, dashboard CSS, README, auth/profile tests, and dashboard QA harness.

### Verification

- PHP syntax: all `app`, `database`, and `routes` PHP files passed.
- Laravel Pint: passed on the complete dirty PHP set.
- Additive local migration and seed: completed successfully.
- Route discovery: both user/access routes registered with permission middleware.
- Blade cache compilation: passed.
- Focused user-management suite: 7 tests, 52 assertions passed.
- Combined user/access/auth/profile/seeder suite: 29 tests, 148 assertions passed before the final media-removal case was added.
- Complete Laravel suite after final implementation: 101 tests, 484 assertions passed.
- Dashboard QA JavaScript syntax: passed; new desktop/mobile user and role scenarios are present.
- `git diff --check`: passed; only the repository's existing CRLF normalization notice remains.

### Environment-limited checks

- `npm.cmd run build` reached Vite but sandbox policy denied the esbuild child process (`spawn EPERM`). The required escalation was unavailable because the approval reviewer reported a usage-limit condition.
- `npm.cmd run qa:dashboard` could not start the Chromium debugging endpoint under the same process restriction. No browser assertions or new screenshots are claimed from this run.
- The requested Tinker contract probe reached PsySH but its external history-file write was denied; escalation was unavailable for the same approval-reviewer condition. Equivalent enum/profile/role/permission contracts are covered by the passing automated suite.

## Account-level two-factor control

The user-management form exposes the existing `users.two_factor_enabled`
preference in create and edit workflows. New managed accounts start with the
switch enabled, while an administrator may explicitly disable it before the
account is created or during a later edit. Directory and detail views surface
the saved preference so security posture is visible without opening the form.

The field remains excluded from `User::$fillable`. `UserManagementService`
applies the validated boolean through `forceFill()` inside the account
transaction. Disabling 2FA also clears any pending OTP hash and expiry and
removes verified-session foundation rows, matching the self-service security
panel's state-cleanup contract. The global `TWO_FACTOR_ENABLED` switch remains
authoritative: a user's enabled preference only causes a challenge while that
environment-level switch is active.

Regression coverage verifies the enabled create default, edit-state hydration,
persisted preference, pending-challenge cleanup, and verified-session removal.
The focused user-management suite passes with 8 tests and 61 assertions; the
complete Laravel suite passes with 120 tests and 604 assertions.
