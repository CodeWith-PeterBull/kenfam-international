# Laravel Aureon Delegation Context

## Foundation available to delegated engineers

As of 2026-07-14, the Laravel app, Breeze auth, package migrations, Spatie roles, dashboard resource pipeline, Aureon asset namespace, shared dashboard shell, index, blank page, automated tests, and responsive browser QA are operational.

Use `layouts.dashboard-layout` for administration modules. Use `admin.dashboard` and `admin.blank` as the route and view conventions to follow. Do not copy CSK business routes or branding into new modules; adapt only the reusable dashboard patterns.

This document is the context packet for fellow engineers or specialized agents that will help build modules on top of the Laravel Aureon base.

## Project summary

Laravel Aureon is a reusable Laravel application shell for corporate websites that need both:

- A polished public corporate website based on the Aureon Bootstrap 5 template.
- A DreamPOS-style administrative dashboard based on the CSK backend benchmark.

The public website and the dashboard are related by brand, auth, and content models, but their assets should stay cleanly separated.

## Non-negotiable architecture rules

- Keep the Laravel app modular and Blade-friendly for later production adoption.
- Preserve reusable layouts and partials. Do not create one-off full-page copies when a layout or component belongs in a shared partial.
- Keep public Aureon frontend assets under an Aureon namespace.
- Keep dashboard/DreamPOS assets under the benchmark dashboard resource structure.
- Use Vite and `vite-plugin-static-copy` for dashboard and static asset movement.
- Preserve Bootstrap 5 behavior in the public template.
- Preserve Breeze auth conventions unless a module has a documented reason to extend them.
- Use Spatie Permission for roles and permissions.
- Document every module under `.docs/dev`.

## Current known sources

Backend benchmark:

```text
C:\Users\Peter Maina\Desktop\projects\CSK\backend
```

Frontend benchmark:

```text
Custom Templates Builds/custom-corporate-template
```

Laravel Aureon target:

```text
Custom Templates Builds/laravel-aureon
```

## Suggested delegation work packets

### 2FA module

Goal:

- Recreate or adapt two-factor authentication from the benchmark after Breeze is stable.

Expected outputs:

- Migration/config updates if required.
- User model changes.
- Auth views and routes.
- Recovery codes or equivalent flow if present in the benchmark.
- Tests for enable, confirm, challenge, and disable flows.
- Documentation under `.docs/dev/2fa-module.md`.

### Users, roles, and permissions module

Goal:

- Build admin user management using Spatie Permission.

Expected outputs:

- Seeded base roles.
- User index, create, edit, show, and status controls.
- Role assignment UI.
- Permission policy or middleware documentation.
- Dashboard sidebar entries.
- Tests for authorization boundaries.
- Documentation under `.docs/dev/users-roles-permissions.md`.

### Events module

Goal:

- Build events for public listing and admin management.

Expected outputs:

- Event model, migration, factory, seeder.
- Admin CRUD.
- Public event listing and detail views.
- Dashboard event stats.
- Optional registration scaffold if benchmark behavior is required.
- Documentation under `.docs/dev/events-module.md`.

### Content/posts module

Goal:

- Power insights, articles, and news-style pages from Laravel.

Expected outputs:

- Post model, migration, taxonomy approach, status workflow.
- Admin CRUD.
- Public listing/detail Blade views based on Aureon layouts.
- Media library integration if images are required.
- Documentation under `.docs/dev/content-module.md`.

### Team module

Goal:

- Manage leadership and team profiles.

Expected outputs:

- Team member model, migration, factory, seeder.
- Admin CRUD.
- Public listing/detail components.
- Dashboard team stats.
- Documentation under `.docs/dev/team-module.md`.

### Expenditure module

Goal:

- Provide sample operational finance data for dashboard demonstration.

Expected outputs:

- Expenditure model, migration, category support.
- Admin index and simple create/edit flow.
- Dashboard summary cards and chart-ready arrays.
- Documentation under `.docs/dev/expenditure-module.md`.

### Frontend Blade conversion

Goal:

- Convert Aureon static pages into Blade layouts, partials, and page views.

Expected outputs:

- Public layout.
- Header, footer, page-loader, theme-controller, and hero partials.
- Home page Blade view.
- Initial company/capability/insight/contact page views.
- Asset-path helper convention.
- Documentation under `.docs/dev/aureon-blade-conversion.md`.

## Shared verification commands

Run from `laravel-aureon` after installation:

```powershell
php artisan test
php artisan route:list
npm run build
```

Add module-specific validation commands to the module documentation.

## Handoff expectation

Every agent should leave:

- A short implementation note in `.docs/dev`.
- A list of files changed.
- Any required manual follow-up.
- Verification commands run and their result.
- Known gaps or deferred items.
