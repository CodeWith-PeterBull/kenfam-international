# System Activity Log Module

## Status

Complete and verified on `feature/laravel-aureon-base-engine` on 15 July 2026.

This concern owns the reusable audit trail presented at `/admin/system-activity` and the authorization boundary for the application-file viewer at `/logs`. Database activities and application log files are deliberately separate: the activity table records structured business and administration events, while Opcodes Log Viewer exposes framework and runtime log files.

## Factual baseline

### Installed application versions

- Laravel Framework: `12.64.x`
- Livewire: `4.3.3` (Composer constraint `^4.2`)
- Opcodes Log Viewer: `3.24.2`
- Spatie Laravel Permission: `6.25.0`

### CSK benchmark reviewed

- `app/Models/SystemActivity.php`
- `database/migrations/2026_03_07_120058_create_system_activities_table.php`
- `app/Livewire/SystemActivityLog.php`
- `resources/views/livewire/system-activity-log.blade.php`
- `app/Http/Controllers/Admin/SystemActivityController.php`
- `resources/views/admin/system-activity/index.blade.php`
- `database/seeders/RolesAndPermissionsSeeder.php`
- `app/Providers/AppServiceProvider.php`
- `config/log-viewer.php`
- `routes/web.php`

The benchmark establishes an immutable, dot-notated activity stream with a nullable user, polymorphic affected model, request metadata, JSON properties, filters, summary counts, a details modal, and an admin route. It also publishes Log Viewer at `/logs`.

### Livewire 4 guidance reviewed

- Components and computed properties: <https://livewire.laravel.com/docs/4.x/components>
- URL query parameters: <https://livewire.laravel.com/docs/4.x/url>
- Pagination and filter resets: <https://livewire.laravel.com/docs/4.x/pagination>
- Persistent authorization middleware: <https://livewire.laravel.com/docs/4.x/security>
- Component testing: <https://livewire.laravel.com/docs/4.x/testing>

The Aureon implementation therefore uses typed public filter state, `#[Url]`, `#[Computed]`, `#[Locked]`, `WithPagination`, explicit page resets, persistent route middleware, server-side permission checks, and `Livewire::test()` coverage.

## Architecture decisions

1. `SystemActivity` remains a read-oriented Eloquent record. Generic writes belong to an injectable service instead of accumulating module-specific static methods on the model.
2. Future modules depend on `RecordsSystemActivity`; `SystemActivityService` is the default singleton implementation.
3. Event names use lowercase dot notation: `{module}.{action}`, for example `post.published` or `event.updated`.
4. Activity records retain the actor, severity, source, polymorphic subject, request metadata, correlation/batch UUID, sanitized properties, and creation time.
5. Subject identifiers are strings so integer, UUID, and ULID model keys are all supported.
6. Sensitive property keys such as passwords, tokens, secrets, authorization headers, cookies, and recovery codes are recursively replaced with `[REDACTED]` before persistence.
7. The administration UI is read-only. Retention and deletion require a separate, explicit operational policy and will not be hidden in this module.
8. `view_system_activities` protects the structured activity explorer. `view_application_logs` independently protects `/logs`, its API, and downloads; `manage_application_logs` protects destructive file actions.
9. Only `system-admin` receives all three permissions in the foundation seeder. There is no environment-based Log Viewer bypass.
10. Log Viewer remains the package-owned interface; Aureon configures its route, return URL, middleware, theme, and authorization callback without forking vendor code.

## Implementation inventory

| Path | Action | Responsibility |
| --- | --- | --- |
| `app/Contracts/RecordsSystemActivity.php` | Create | Stable write contract for all CMS modules |
| `app/Enums/SystemActivitySeverity.php` | Create | Supported severity values and presentation labels |
| `app/Models/SystemActivity.php` | Create | Casts, relationships, query scopes, and immutable audit record behavior |
| `app/Services/SystemActivityService.php` | Create | Context capture, validation, redaction, and persistence |
| `app/Support/CmsPermission.php` | Create | Canonical permission names used by routes, providers, seeders, and tests |
| `database/migrations/*_create_system_activities_table.php` | Create | Indexed audit schema with a `comment()` on every column |
| `database/factories/SystemActivityFactory.php` | Create | Deterministic model/test fixtures |
| `app/Models/User.php` | Modify | Add the actor-to-activities relationship |
| `database/seeders/RoleSeeder.php` | Modify | Create audit permissions idempotently and grant them to `system-admin` |
| `app/Providers/AppServiceProvider.php` | Modify | Bind the recorder and secure Log Viewer |
| `config/log-viewer.php` | Create | Publish package config and expose the viewer at `/logs` |
| `app/Http/Controllers/Admin/SystemActivityController.php` | Create | Return the dashboard page with an explicit response type |
| `app/Livewire/Admin/SystemActivityIndex.php` | Create | Authorized filters, metrics, pagination, details, and refresh behavior |
| `resources/views/admin/system-activity/index.blade.php` | Create | Dashboard page host for the Livewire component |
| `resources/views/livewire/admin/system-activity-index.blade.php` | Create | Responsive audit explorer and accessible details dialog |
| `resources/views/layouts/partials/sidebar-admin.blade.php` | Modify | Permission-aware governance navigation |
| `routes/web.php` | Modify | Add the verified and permission-protected activity route |
| `tests/Feature/SystemActivity/*` | Create | Model, service, Livewire, access, and Log Viewer verification |
| `tests/Feature/Dashboard/RoleSeederTest.php` | Modify | Assert permission creation and assignment |
| `scripts/qa-dashboard.mjs` | Modify | Add desktop/mobile activity-page browser diagnostics and captures |
| `README.md` | Modify | Advance the CMS feature ledger and record module routes |

## Delivered behavior

### Structured activity storage

- The append-oriented `system_activities` table stores a correlation UUID, nullable actor, dot-notated event, enum-backed severity, source, description, polymorphic subject, request metadata, sanitized JSON context, and creation time.
- Subject identifiers use strings to support integer, UUID, and ULID keys without changing the schema.
- The migration defines all 16 columns with `comment()` documentation, a nullable `users` foreign key using `nullOnDelete()`, and six query indexes aligned with the explorer filters.
- `SystemActivity` exposes actor/user compatibility, polymorphic subject, search, type, severity, actor, and date-range scopes. It has no update timestamp because audit records are not edited by the administration workflow.

### Reusable recording service

- `RecordsSystemActivity` is the stable dependency for upcoming CMS modules; `AppServiceProvider` resolves it to the singleton `SystemActivityService`.
- The service validates event/source/description/correlation/subject input, supports console and HTTP execution, removes query strings from recorded URLs, and captures method, route, IP, and bounded user-agent data when available.
- Context normalization supports arrays, Eloquent models, date/time objects, enums, Laravel `Arrayable`, `JsonSerializable`, and stringable values. Recursive depth, item count, and string sizes are bounded.
- Sensitive keys are recursively replaced with `[REDACTED]`. This is defense in depth; feature services must still submit only the minimum operational context.

### Administration explorer

- `/admin/system-activity` uses the shared Aureon dashboard layout and requires authentication, verified email, and `view_system_activities`.
- The Livewire component re-authorizes in `boot()` on every request and provides URL-backed search, event, severity, actor, date, row-count, and sort state; pagination resets when filters change.
- Four summary metrics, responsive records, accessible icon controls, empty/loading states, and a locked record-details dialog are implemented. The table scrolls inside its own mobile container without widening the document.
- Sidebar governance links render only when the signed-in user has the corresponding permission.

### Application log viewer

- Opcodes Log Viewer is published at `/logs`; its package UI and API retain package middleware and share the application's `LogViewer::auth()` permission callback.
- Viewing and downloading require `view_application_logs`. File/folder deletion requires the additional `manage_application_logs` gate. No local or environment-based bypass exists.
- Discovery is limited to Laravel `*.log` files, the return URL is `/admin/system-activity`, and the dashboard exposes the viewer only to authorized users.
- `system-admin` receives all three activity/log permissions. Other foundational roles receive none until a later role-management decision grants them explicitly.

## Adoption requirements

1. Inject `RecordsSystemActivity` into the module's application/service layer; do not write audit rows from Blade, Livewire views, controllers, or model observers by default.
2. Record the activity only after authorization and validation have succeeded. Place the write in the same database transaction when domain data and audit evidence must commit atomically.
3. Use lowercase `{module}.{action}` event names and a short human-readable description. Pass the authenticated actor and the affected persisted model where they exist.
4. Store changed field names and meaningful before/after state only. Never submit passwords, access tokens, authorization headers, cookies, recovery codes, entire requests, or unnecessary personal data.
5. Add focused tests for the module event name, actor, subject, description, severity, properties, and rollback behavior. Add the new event to user-facing filters only through stored data; the explorer discovers types dynamically.
6. Keep retention, export, and destructive audit maintenance in a separately authorized operations concern. The current explorer remains read-only.

## Reusable write contract

Upcoming module services should inject the contract and record an event after a successful mutation, inside the same database transaction when the audit row must be atomic with the domain change:

```php
use App\Contracts\RecordsSystemActivity;
use App\Enums\SystemActivitySeverity;

public function __construct(
    private readonly RecordsSystemActivity $activities,
) {}

$this->activities->record(
    activityType: 'post.published',
    description: "Post {$post->title} was published",
    actor: $administrator,
    subject: $post,
    properties: ['from_status' => 'draft', 'to_status' => 'published'],
    severity: SystemActivitySeverity::Notice,
);
```

Callers must not include credentials or entire request payloads. The recorder provides defense-in-depth redaction, not permission to persist unnecessary personal or secret data.

## Authorization matrix

| Surface | Guest | Authenticated without permission | `system-admin` |
| --- | --- | --- | --- |
| `/admin/system-activity` | Redirect to login | HTTP 403 | Allowed |
| Livewire activity requests | Re-applies route middleware | HTTP 403 | Allowed |
| `/logs` | HTTP 403 | HTTP 403 | Allowed with `view_application_logs` |
| Log Viewer API and downloads | HTTP 403 | HTTP 403 | Allowed with `view_application_logs` |
| Destructive Log Viewer actions | HTTP 403 | HTTP 403 | Allowed with `manage_application_logs` |

## Verification record

| Check | Result |
| --- | --- |
| Focused activity tests | `16` tests and `72` assertions passed |
| Full Laravel suite | `45` tests and `155` assertions passed |
| Composer metadata | `composer validate --no-check-publish` passed |
| PHP formatting | `php vendor/bin/pint --test --dirty` passed |
| PHP syntax | New and modified PHP files passed `php -l` |
| Blade compilation | `php artisan view:cache` passed |
| Configuration lifecycle | `php artisan config:cache` and `php artisan config:clear` passed |
| Database schema | Migration applied; Laravel reported 16 columns, six indexes, and the nullable `users` foreign key |
| Role seeding | `RoleSeeder` completed repeatedly and assigned the three permissions only to `system-admin` |
| Route surface | `/admin/system-activity` and all 16 Log Viewer routes under `/logs` were present |
| Temporary bootstrap verification | Contract binding, actor, subject, severity, source, URL, route, and recursive redaction passed inside a rolled-back transaction; script removed |
| Vite production build | Passed with 60 transformed modules and seven static-copy targets |
| Authenticated Chromium QA | Desktop 1440x1000 and mobile 390x844 passed with no runtime/network errors or document overflow |

Browser evidence is retained at `.docs/dev/dashboard-qa/system-activity-desktop.png`, `.docs/dev/dashboard-qa/system-activity-mobile.png`, and `.docs/dev/dashboard-qa/diagnostics.json`. The three disposable `source=qa` records used to make the table observable were removed after capture, and zero remain.

The build still reports the foundation's known runtime-resolved asset-path warnings. Browser QA confirms the referenced fonts, dashboard art, sidebar images, and Livewire assets resolve correctly.

## Known local warning

The local Imagick extension was compiled against ImageMagick `1808` while `1810` is loaded. This warning is unrelated to the activity module but remains relevant to production readiness.
