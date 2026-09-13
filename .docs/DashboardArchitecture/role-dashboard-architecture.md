# Role Dashboard Architecture

**Status:** Implemented and verified  
**Benchmark:** CSK role redirect, dashboard controllers, and role-owned navigation shells  
**Aureon roles:** `system-admin`, `content-manager`, `editor`, `viewer`

## Purpose

Give every foundational Aureon account type a protected landing workspace while retaining one shared dashboard layout, theme controller, loader, footer, and asset pipeline. The structure must remain straightforward to extend when real CMS modules replace the initial sample data.

## Planned implementation

- Make `/dashboard` a role-routing endpoint rather than an administration view alias.
- Add `RedirectToRoleDashboard` and register it as `redirect.role.dashboard`.
- Keep route selection in one `DashboardRegistry` keyed by the enum-backed `UserType`.
- Add role-protected dashboard routes and controllers for all four base roles.
- Preserve permission-based access to cross-cutting administration tools independently of the landing dashboard role.
- Add content-manager, editor, and viewer index pages with concise sample operational data.
- Add working sample workspace routes so sidebar links never point to dead placeholders.
- Add role-specific topbar and sidebar partials for every non-admin role.
- Resolve the current user's shell through the registry inside `dashboard-layout.blade.php`.
- Show permission-assigned CMS tools in non-admin sidebars without exposing ungranted links.
- Test landing redirects, role isolation, shell selection, sample routes, and administration protection.

## Adoption rules

1. Add a base account classification to `UserType` before registering a dashboard.
2. Register its route, label, topbar, and sidebar exactly once in `DashboardRegistry`.
3. Protect the destination route with both `auth`/`verified` and the corresponding Spatie role middleware.
4. Keep controllers thin and return view-ready data; future modules own their queries and services.
5. Keep global shell behavior in `dashboard-layout`, not in individual dashboard views.
6. Put role navigation in its sidebar wrapper and only link to named, tested routes.
7. Gate shared tools by `CmsPermission`; a role name alone must not grant module capabilities.
8. Add role-routing and cross-role denial tests whenever a dashboard classification is added.

## Delivered route map

| Account type | Landing route | Sample workspaces |
| --- | --- | --- |
| `system-admin` | `admin.dashboard` | `admin.blank` |
| `content-manager` | `content-manager.dashboard` | `content-manager.queue`, `content-manager.calendar` |
| `editor` | `editor.dashboard` | `editor.drafts`, `editor.reviews` |
| `viewer` | `viewer.dashboard` | `viewer.library`, `viewer.saved` |

`/dashboard` is the neutral post-authentication endpoint. `RedirectToRoleDashboard` resolves its destination through `DashboardRegistry`; the destination route then independently enforces the matching Spatie role.

## Final implementation record

- Added `DashboardRegistry` as the canonical user-type, route, label, topbar, and sidebar map.
- Added and registered `RedirectToRoleDashboard`.
- Moved administration view-model ownership into `Admin\DashboardController`.
- Added content-manager, editor, and viewer dashboard controllers with working sample workspace actions.
- Added dedicated role index views and a shared role-overview partial.
- Added shared non-admin topbar/sidebar primitives plus role-owned wrapper partials.
- Updated `dashboard-layout.blade.php` to resolve the authenticated shell through the registry.
- Preserved permission-based access to users, roles, logs, activity, institution details, and templates independently of dashboard role.
- Restricted administration dashboard and blank workspace routes to `system-admin`.
- Added local seeded accounts for all four base account types.
- Centralized the existing 50px dashboard profile-photo treatment across all topbars.
- Rebuilt `DashboardAccessTest` around landing redirects, cross-role denial, sample routes, permission-derived tools, and seeded account integrity.

## Verification

- Dashboard access coverage passes with 13 tests and 75 assertions.
- Full application suite passes with 119 tests and 595 assertions.
- Route discovery, Blade compilation, Pint, Composer validation, and Vite production build pass.
- Existing Imagick/ImageMagick version warnings remain environmental and unchanged by this module.
