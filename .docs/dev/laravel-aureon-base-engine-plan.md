# Laravel Aureon Base Engine Plan

## Objective

Build a reusable Laravel base engine named `laravel-aureon` under `Custom Templates Builds`. It will combine the Aureon static corporate template as the public frontend benchmark with the CSK backend application as the dashboard, authentication, asset-pipeline, and module-architecture benchmark.

Installation, baseline conformance, benchmark asset migration, and the first functional dashboard shell are complete. Public Aureon Blade conversion and business modules remain the next major phases.

## Current implementation status

Completed on 2026-07-14:

- Laravel installation promoted from the nested temporary directory into the application root.
- Composer/npm benchmark package baseline reconciled and lockfiles validated.
- Breeze Blade authentication retained and verified.
- Permission and Media Library migrations published and applied.
- Spatie role middleware aliases and `HasRoles` user support wired.
- Four base roles and a local system administrator seeded idempotently.
- All six DreamPOS dashboard resource families migrated from the CSK benchmark.
- All Aureon public-template assets namespaced under `resources/aureon/assets`.
- Vite static-copy and compiled-entry pipeline implemented.
- Aureon-branded dashboard layout, index, blank page, theme persistence, loader, and responsive navigation implemented.
- Desktop/mobile authenticated browser QA captured under `.docs/dev/dashboard-qa`.

Next implementation phase:

- Convert the Aureon public header, footer, loader, theme controller, hero, and first public pages to Blade.
- Delegate 2FA, users/roles UI, events, posts, team, expenditure, and media modules against the established dashboard shell.

## Active branch and folder

- Branch: `feature/laravel-aureon-base-engine`
- Folder: `Custom Templates Builds/laravel-aureon`
- Planning docs: `laravel-aureon/.docs/dev/`

## Benchmarks assimilated

### Backend benchmark

Path:

```text
C:\Users\Peter Maina\Desktop\projects\CSK\backend
```

Observed package baseline:

- Laravel 12 and PHP 8.2.
- Breeze 2.3 for auth scaffolding.
- Livewire 4.2.
- Spatie Permission 6.24 for roles and permissions.
- Spatie Medialibrary 11.21.
- Spatie Honeypot 4.7.
- Barryvdh DOMPDF 3.1.
- Intervention Image Laravel 1.5.
- Opcodes Log Viewer 3.24.
- Laravel Boost, Pail, Pint, Sail, PHPUnit, Collision, Mockery, Faker.

Observed frontend package baseline:

- Vite 7.
- Laravel Vite Plugin 2.
- Vite Static Copy 3.
- Tailwind 3 plus `@tailwindcss/forms`.
- Alpine, Axios, PostCSS, Autoprefixer, Concurrently.

Observed resource architecture:

```text
resources/
|-- css/
|-- fonts/
|-- img/
|-- js/
|-- plugins/
|-- scss/
`-- views/
```

Observed Blade view groups:

```text
resources/views/
|-- admin/
|-- applicant/
|-- auth/
|-- components/
|-- corporate/
|-- dashboards/
|-- emails/
|-- errors/
|-- frontendpages/
|-- layouts/
|-- livewire/
|-- member/
|-- profile/
|-- reports/
`-- vendor/
```

Key benchmark layouts and partials:

- `resources/views/layouts/dashboard-layout.blade.php`
- `resources/views/layouts/full-page-layout.blade.php`
- `resources/views/layouts/mainlayout.blade.php`
- `resources/views/layouts/partials/head-css.blade.php`
- `resources/views/layouts/partials/vendor-scripts.blade.php`
- `resources/views/layouts/partials/sidebar.blade.php`
- `resources/views/layouts/partials/sidebar-admin.blade.php`
- `resources/views/layouts/partials/topbar.blade.php`
- `resources/views/layouts/partials/topbar-admin.blade.php`
- `resources/views/layouts/partials/theme-settings.blade.php`

Observed dashboard views:

- `resources/views/dashboards/admin/index.blade.php`
- `resources/views/dashboards/member/index.blade.php`
- `resources/views/dashboards/corporate/index.blade.php`
- `resources/views/dashboards/applicant/index.blade.php`

Observed route architecture:

- Public frontend routes at `/`, `/about`, `/membership`, `/services`, `/events`, `/contact`, `/apply`.
- Breeze profile routes under `auth`.
- Role dashboard routing under `/admin`, `/member`, `/corporate`, and `/user`.
- Spatie role middleware names such as `role:system-admin`, `role:member`, `role:corporate-admin`, and `role:user`.

### Frontend benchmark

Path:

```text
Custom Templates Builds/custom-corporate-template
```

The active Aureon template provides:

- 73 generated static pages.
- Bootstrap 5, custom CSS, vanilla JavaScript, local Swiper, Lucide, and local fonts.
- Centralized brand assets under `assets/brand/`.
- Central theme and typography tokens in `assets/css/theme.css`.
- Component CSS in `assets/css/corporate.css`, hero slider CSS, and theme-controller CSS.
- Shared source partials for head, header, footer, loader, theme controller, and search.
- Full desktop mega menu, tablet/mobile offcanvas accordion navigation, loader, theme switcher, hero slider design system, and floating header.

The Laravel conversion must preserve the Aureon source architecture rather than copy generated HTML page-by-page without structure.

## Target Laravel architecture

After installation, `laravel-aureon` should become a normal Laravel application root while preserving this `.docs/dev` context.

Recommended public frontend Blade structure:

```text
resources/views/aureon/
|-- layouts/
|   `-- public.blade.php
|-- partials/
|   |-- head.blade.php
|   |-- page-loader.blade.php
|   |-- header.blade.php
|   |-- theme-controller.blade.php
|   |-- footer.blade.php
|   `-- scripts.blade.php
|-- pages/
|   |-- home.blade.php
|   |-- company/
|   |-- capabilities/
|   |-- industries/
|   |-- work/
|   |-- insights/
|   |-- events/
|   `-- contact.blade.php
`-- components/
```

Recommended dashboard Blade structure:

```text
resources/views/layouts/
|-- dashboard-layout.blade.php
|-- full-page-layout.blade.php
`-- partials/
    |-- head-css.blade.php
    |-- vendor-scripts.blade.php
    |-- sidebar.blade.php
    |-- topbar.blade.php
    |-- footer.blade.php
    `-- theme-settings.blade.php

resources/views/dashboards/
|-- admin/index.blade.php
`-- blank.blade.php
```

Recommended asset structure:

```text
resources/
|-- aureon/
|   `-- assets/
|       |-- brand/
|       |-- css/
|       |-- fonts/
|       |-- images/
|       |-- js/
|       `-- vendor/
|-- css/
|-- fonts/
|-- img/
|-- js/
|-- plugins/
`-- scss/
```

Recommended published public paths:

```text
public/
|-- aureon/
|   `-- assets/
`-- build/
```

Vite should retain the benchmark `vite-plugin-static-copy` model and add an Aureon target so template assets remain easy to replace and inspect.

## Implementation phases

### Phase 1: User-run Laravel installation

Status: complete.

Use `.docs/dev/laravel-aureon-installation-commands.md`.

Expected output:

- Laravel 12 app under `laravel-aureon`.
- Breeze Blade auth installed.
- Benchmark Composer and npm package baseline installed.
- SQLite local database ready for first boot.
- Spatie Permission and Media Library migrations published and migrated.
- `npm run build`, `php artisan route:list`, and `php artisan test` available.

### Phase 2: Reconcile generated Laravel app

Status: complete.

Codex should inspect the fresh app and update:

- `composer.json` scripts to align with the benchmark `dev` and `test` commands.
- `package.json` scripts and dependencies.
- `vite.config.js` to include benchmark output naming and static copy targets.
- `.env.example` with safe documented defaults.
- `.gitignore` if the generated app introduces missing Laravel ignores.

No dashboard or frontend port should happen before this baseline is stable.

### Phase 3: Dashboard resource and layout migration

Status: resource migration and base layout complete; module-specific navigation will grow with implemented modules.

Copy and adapt the DreamPOS-derived dashboard resources from the CSK benchmark:

- `resources/css`
- `resources/scss`
- `resources/fonts`
- `resources/img`
- `resources/js`
- `resources/plugins`
- benchmark dashboard layouts and partials

Brand adaptation rules:

- Replace CSK-specific marks with Aureon assets.
- Keep dashboard behavior, density, and Vite static-copy architecture.
- Do not alter third-party vendor files beyond path adaptation.
- Keep the dashboard independent from the public Aureon frontend assets.

### Phase 4: Auth, role, and base dashboard routing

Status: base auth, role plumbing, and dashboard routes complete; route-level role enforcement is deferred until user management is implemented.

Start with Breeze login/register/profile.

Add first roles:

- `system-admin`
- `content-manager`
- `editor`
- `viewer`

Later compatibility roles can include:

- `member`
- `corporate-admin`
- `user`

Base dashboard routes:

- `/dashboard` redirects by role.
- `/admin/dashboard` for the main admin index.
- `/admin/blank` for the blank page.

Sample dashboard data can be static view-model arrays until the modules exist.

### Phase 5: Base dashboard index and blank page

Status: complete with static view-model data.

Create:

- A dashboard index with at-a-glance cards.
- A blank page for future module work.

Suggested sample stats:

- Posts
- Events
- Team members
- Expenditure
- Media assets
- Inquiries
- Pages
- Users

Suggested dashboard panels:

- Recent content updates.
- Upcoming events.
- Budget and expenditure snapshot.
- Team activity.
- System health.
- Pending approvals.

### Phase 6: Aureon public frontend Blade engine

Convert the static Aureon template into reusable Blade components:

- Head metadata partial.
- Brand loader partial.
- Header and mega menu partial.
- Mobile offcanvas accordion navigation.
- Theme controller partial.
- Footer partial.
- Hero slider partial.
- Page family sections.

Preserve these frontend behaviors:

- Local assets only.
- Theme and typography controller.
- Light and dark mode support.
- Page loader.
- Floating header.
- Hero slider controls and timeline.
- Centered logo behavior.
- Desktop, tablet, and mobile navigation hierarchy.

### Phase 7: Initial modules for delegation

The base should be ready for specialized agents to implement:

- 2FA module.
- Events module.
- Users, roles, and permissions module.
- Content/posts module.
- Team module.
- Expenditure module.
- Media library module.
- Audit/log-viewer integration.

Each module should include its own `.docs/dev` implementation note or update the central implementation log.

### Phase 8: Verification and QA

Minimum checks after each phase:

```powershell
php artisan about
php artisan route:list
php artisan test
npm run build
```

Frontend and dashboard QA should cover:

- Desktop, tablet, and mobile layouts.
- Auth screens.
- Dashboard index and blank page.
- Public home page.
- Header and mobile navigation.
- Theme controller persistence.
- Loader lifecycle.
- Asset paths from Vite build output.

## Key decisions

- Laravel installation will be user-run.
- The docs-first folder is preserved by installing Laravel into a temporary folder and copying it in.
- Breeze Blade is the initial auth baseline.
- 2FA is deferred until the dashboard/auth base is stable.
- The CSK backend is the dashboard and module architecture benchmark.
- The Aureon static template is the frontend and brand architecture benchmark.
- Dashboard assets and public frontend assets should remain separated.
- Generated static Aureon HTML should be converted into Blade partials and page views, not pasted as one-off pages.

## Immediate next step after user installation

When installation is complete, inspect:

```powershell
git -C "Custom Templates Builds" status --short --branch
Get-ChildItem "Custom Templates Builds\laravel-aureon" -Force
Get-Content "Custom Templates Builds\laravel-aureon\composer.json" -Raw
Get-Content "Custom Templates Builds\laravel-aureon\package.json" -Raw
Get-Content "Custom Templates Builds\laravel-aureon\vite.config.js" -Raw
```

Then reconcile the app baseline before copying dashboard resources or converting frontend pages.
