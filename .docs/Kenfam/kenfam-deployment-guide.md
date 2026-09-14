# Kenfam Deployment And Adoption Guide

Status: deployment plan, not evidence of a hosted Kenfam release.
Do not reuse Aureon's live database, APP_KEY, sessions or uploaded private media.

## 1. Prerequisites

Private Git origin and reviewed release SHA; PHP/extensions compatible with the
locked Composer dependencies; Composer; a supported production database;
scheduler/queue worker capability; HTTPS and a web root pointing to public.
Match the lock file, currently Laravel 12.64.0, rather than following an
unrelated Laravel-version installation command.

Required client environment: APP_NAME, APP_URL, APP_ENV=production,
APP_DEBUG=false, fresh APP_KEY, database, mail, cache/session/queue and storage.
COMMERCE_ENABLED=false, PROPERTY_BOOKING_ENABLED=false,
TRAVEL_TOURS_ENABLED=true only when the current phase is deployment-approved.
Keep identity hash keys stable/versioned and private. Changing keys without a
rotation plan breaks lookup/decryption.

## 2. Release Preparation

Install locked dependencies locally/CI and build assets:
```powershell
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
```
Use a separate development/CI install for tests; --no-dev omits test tooling.
Run all phase gates before packaging. Include public/build with its manifest
and hashed assets plus generated public Aureon/client static resource output.
Do not publish .env, source maps with sensitive material, private storage,
test databases, vendor credentials or local browser profiles.

If shared hosting cannot run npm, upload the complete local build artifact.
A manifest without matching asset files is not a complete deployment.

## 3. Server Release Sequence

Back up database and media before schema changes. Validate the release path and
document root. Install Composer dependencies if not included in the artifact.
Set environment secrets outside Git, preserve storage, then run:
```bash
php artisan optimize:clear
php artisan migrate --force
php artisan optimize
php artisan queue:restart
```

Never migrate:fresh on staging with retained content or production. Never run a
demonstration seeder as routine deployment. The production-safe access catalogue
is `TravelToursAccessSeeder`; the local-only `TravelToursDemoSeeder` also creates
fictional catalogue data and deterministic operator credentials, so it must be
invoked only in disposable review environments.

Configure cron for schedule:run every minute and the accepted queue worker
supervisor. Shared-host restrictions require a documented alternative, not an
assumption that queued notifications run automatically.

## 4. Storage And Public Assets

The public storage symlink points only to storage/app/public. Create it with
`php artisan storage:link` when the host supports Laravel's configured link, or
create the equivalent reviewed symlink when shared-host restrictions require
it. Verify target existence/permissions and an HTTP test object, then remove
the object.
Do not replace an existing directory blindly. Private travel identity documents
stay on the private disk outside that symlink and require authorized downloads.

Vite/static-copy supplies resources/aureon/assets and resources/kenfam/assets
to their public destinations. Confirm the actual build configuration instead of
copying to a guessed path. HTTP-check theme JS/CSS, bootstrap, fonts, client
logo/favicon/social card and module assets for correct MIME type.

## 5. Smoke Checklist

| Surface | Check |
| --- | --- |
| Root | Kenfam identity, no DB-dependent disabled-module failure |
| Public tours | Only approved published content, filters and real imagery |
| Private booking | Signature expiry, noindex/no-store, no private data leakage |
| Auth/dashboard | 2FA/profile/role redirect and scoped navigation |
| POB/payment | Approved roles, open-shift requirement, receipt and retries |
| Storage | Public images load; private documents cannot be fetched publicly |
| Mail/queue | Real queued delivery and failed-job monitoring |
| Reports | Institution logo/contact, both orientations and paper colors |
| Module flags | Commerce/Property routes and seed/schedule activity absent |
| Build/cache | No stale hot file, asset 404, HTML MIME fallback or stale route cache |

## 6. Pre-Release Verification

Run the application gates from a development/CI install before packaging:

```bash
composer validate --no-check-publish
composer audit --locked --format=summary
php artisan test
php scripts/probe-travel-tours-foundation.php
php scripts/audit-travel-tours-docblocks.php
php vendor/bin/pint --test app/Modules/TravelTours tests/Feature/TravelTours tests/Feature/HomePageTest.php
php artisan view:clear
php artisan view:cache
php artisan route:list --name=travel-tours
npm run build
npm run qa:travel-tours-storefront
```

The browser harness expects the application at `http://127.0.0.1:8011` by
default. Set `AUREON_QA_URL` when using another origin and set `APP_URL` to the
same origin before seeding so generated media URLs do not cross hosts.

For a disposable review database only, populate the public test catalogue with:

```bash
php artisan db:seed --class="App\\Modules\\TravelTours\\Database\\Seeders\\TravelToursDemoSeeder"
```

Do not run that command on an adopted content database. The fixture is
idempotent and non-destructive, but its fictional journeys and local-only
operator accounts are not production records.

## 7. Rollback And Operations

Rollback application code only after reviewing schema compatibility. Database
rollback is not a default response to a UI error; restore/backfill under a
reviewed recovery plan. Preserve APP_KEY, hash keys and media. Record deployment
SHA, migrations applied, build artifact identity and smoke results.

Monitor failed jobs, payment/booking operation conflicts, reconciliation alerts
and storage/conversion failures without logging PII. Rotate credentials through
the host's secret process, not .env.example commits. Restart long-running workers
after configuration/module changes.
