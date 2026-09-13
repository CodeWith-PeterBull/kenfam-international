# Hostinger Document Root And Static Asset Incidents

**Application:** Laravel Aureon
**Host:** Hostinger shared hosting
**Domain:** `https://aureon.metasoftdevs.com`
**Recorded:** 2026-09-07
**Status:** Application entry point, Aureon static assets, and public storage
delivery restored and verified.

## 1. Deployment Topology

The Hostinger domain document root and Git checkout currently resolve to:

```text
/home/u523034728/domains/aureon.metasoftdevs.com/public_html/
    .htaccess
    laravel-aureon/
        laravel-aureon/
            app/
            bootstrap/
            public/
            resources/
            storage/
            vendor/
```

The duplicated `laravel-aureon/laravel-aureon` segment results from deploying a
repository that already contains the application folder into a deployment
folder with the same name. The arrangement is operational with the explicit
front-controller rewrite below, although a later deployment may simplify the
checkout target.

## 2. Incident: Domain Root Returned 403

### Symptoms

- `https://aureon.metasoftdevs.com/` returned Hostinger HTTP 403.
- The physical `public/index.php` file existed and was readable.
- A static file addressed directly beneath the nested Laravel `public` folder
  returned HTTP 200.
- Addressing `public/index.php` directly reached PHP and returned HTTP 500,
  proving that file ownership and PHP execution were available.

### Root cause

The domain pointed at `public_html`, while Laravel's front controller lived at
`public_html/laravel-aureon/laravel-aureon/public/index.php`. The first rewrite
sent `/` to the `public` directory rather than directly to `index.php`.
Hostinger refused to list that directory, so the request stopped at HTTP 403.

The original rewrite also used an obsolete `/aureon/...` URL segment that did
not correspond to the subdomain's document-root-relative path.

### Applied root rewrite

`public_html/.htaccess`:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On

    # Send the domain root directly to Laravel's front controller.
    RewriteRule ^$ laravel-aureon/laravel-aureon/public/index.php [L,QSA]

    # Forward assets and application routes into Laravel's public directory.
    RewriteCond %{REQUEST_URI} !^/laravel-aureon/laravel-aureon/public/
    RewriteRule ^(.+)$ laravel-aureon/laravel-aureon/public/$1 [L,QSA]
</IfModule>
```

Laravel's standard `public/.htaccess` remains unchanged and continues routing
non-file requests to the front controller.

## 3. Incident: Direct Front Controller Returned 500

### Evidence

Laravel CLI reported:

```text
Environment: local
Debug Mode: ENABLED
URL: localhost:8000
```

The application log reported:

```text
No application encryption key has been specified.
```

### Root cause and resolution

The production `.env` had not been fully initialized before configuration was
cached. `APP_KEY` was empty and the application retained local defaults.

The deployment was corrected with a persistent encryption key and these
production values:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://aureon.metasoftdevs.com
```

The Laravel caches were then rebuilt:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

`APP_KEY` must be retained between deployments. Regenerating it after encrypted
production values, cookies, or payloads exist will invalidate those values.

## 4. Incident: Storefront Assets Return 404 And HTML MIME Types

### Symptoms

`/shop` renders its HTML, but the browser rejects its theme, Bootstrap, script,
font, and brand requests. Representative failures are:

```text
/aureon/assets/js/theme-init.js
/aureon/assets/css/theme.css
/aureon/assets/css/theme-controller.css
/aureon/assets/vendor/bootstrap/bootstrap.min.css
/aureon/assets/vendor/bootstrap/bootstrap.bundle.min.js
/aureon/assets/vendor/lucide/lucide.min.js
/aureon/assets/brand/logo.png
/aureon/assets/brand/logo-light.png
/aureon/assets/brand/logo-icon.png
```

The requests return an HTML 404 response. Browsers consequently report both the
initial 404 and a strict MIME-type error because HTML cannot be interpreted as
CSS or JavaScript.

### Verified root cause

The Blade calls are correct:

```php
asset('aureon/assets/css/theme.css')
```

The source asset contract is committed under:

```text
resources/aureon/assets/
```

The Vite static-copy target publishes it to:

```text
public/aureon/assets/
```

Both `public/build` and `public/aureon` are generated outputs and are ignored by
Git. The deployed `public` directory contained `build` but did not contain
`aureon`, so the Hostinger Git deployment published only half of the required
frontend release artifacts.

### Applied server repair

The Aureon kit is static and can be copied without Node when `public/build` has
already been supplied:

```bash
DOMAIN="aureon.metasoftdevs.com"
ROOT="$HOME/domains/$DOMAIN/public_html"
APP="$ROOT/laravel-aureon/laravel-aureon"

cd "$APP"
mkdir -p public/aureon/assets
cp -a resources/aureon/assets/. public/aureon/assets/

find public/aureon -type d -exec chmod 755 {} +
find public/aureon -type f -exec chmod 644 {} +
```

### Supported Hostinger release workflow

This Hostinger shared-hosting environment does not provide npm. Frontend assets
must therefore be compiled locally against the exact commit being deployed:

```bash
npm ci
npm run build
```

Upload the resulting `public/build` directory to the deployment's
`public/build`. The uploaded directory must include `manifest.json`; Laravel's
Vite integration uses that manifest to resolve content-hashed production
entries. Compiled CSS and JavaScript filenames must contain a hash because
Hostinger's CDN caches `/build` resources for seven days.

The Git checkout already supplies `resources/aureon/assets`. Publish that
uncompiled static kit after every deployment with the server-side copy command
above. This avoids requiring Node while preserving the intended public URL
contract.

The completed release must contain both of these paths:

```text
public/build/manifest.json
public/aureon/assets/css/theme.css
```

### Verification

```bash
test -f public/build/manifest.json \
    && echo "Vite manifest: PRESENT" \
    || echo "Vite manifest: MISSING"

test -f public/aureon/assets/css/theme.css \
    && echo "Aureon theme: PRESENT" \
    || echo "Aureon theme: MISSING"

curl -sS -D - -o /dev/null \
    "https://aureon.metasoftdevs.com/aureon/assets/css/theme.css"

curl -sS -D - -o /dev/null \
    "https://aureon.metasoftdevs.com/aureon/assets/js/theme-init.js"

curl -sS -D - -o /dev/null \
    "https://aureon.metasoftdevs.com/aureon/assets/brand/logo.png"
```

Expected results:

- every request returns HTTP 200;
- CSS returns a CSS content type;
- JavaScript returns a JavaScript content type;
- PNG files return an image content type;
- `/shop` has no `/aureon/assets` 404 or MIME-type errors.

### Production verification result

Verified from outside the Hostinger host on 2026-09-07 after the static copy:

| Resource | Result | Content type |
| --- | --- | --- |
| `/aureon/assets/css/theme.css` | HTTP 200 | `text/css; charset=UTF-8` |
| `/aureon/assets/js/theme-init.js` | HTTP 200 | `application/x-javascript; charset=UTF-8` |
| `/aureon/assets/brand/logo.png` | HTTP 200 | `image/png` |
| `/shop` | HTTP 200 | `text/html; charset=utf-8` |

The original HTML MIME mismatch is resolved because each static request now
terminates at a real file instead of falling through to Laravel's HTML 404.

Do not change the application URLs to include the physical nested checkout path.
`/aureon/assets/...` is the stable public URL; deployment must provide the files
at that contract.

## 5. Resolved Issue: `storage:link` Cannot Call `exec()`

Hostinger disables PHP's `exec()` function. Laravel therefore reports:

```text
Call to undefined function Illuminate\Filesystem\exec()
```

This is independent of the root 403 and missing theme assets. The standard
public-storage contract was created through the shell instead:

```bash
cd "$APP"

if [ ! -e public/storage ]; then
    ln -s "$APP/storage/app/public" "$APP/public/storage"
fi

ls -la public/storage
```

The resulting link is owned by the hosting account and resolves to:

```text
public/storage
    -> /home/u523034728/domains/aureon.metasoftdevs.com/public_html/laravel-aureon/laravel-aureon/storage/app/public
```

Production verification created a temporary file in `storage/app/public` and
requested it through the canonical public URL:

```text
GET https://aureon.metasoftdevs.com/storage/storage-check.txt
HTTP/2 200
Content-Type: text/plain; charset=UTF-8
Body: storage-link-ok
```

The probe file was removed after verification. This proves that the web server
can traverse the symlink and serve the current application's public-disk files.
Profile photos, institutional branding, Commerce media, Property Booking media,
and their generated conversions can therefore use their existing `/storage`
URLs without changing application code.

The absolute link remains valid while the deployment path remains stable. A
future checkout-path change must recreate it, and production backups must cover
`storage/app/public` because neither Git nor the symlink itself preserves media.

## 6. Required Deployment Gate

Every Hostinger Git deployment must verify, in order:

1. The root `.htaccess` targets the actual checkout path and explicitly routes
   `/` to `public/index.php`.
2. `.env` exists, `APP_KEY` is stable, production mode is active, debug is off,
   and the canonical HTTPS URL is configured.
3. Composer production dependencies are installed.
4. `public/build/manifest.json` exists.
5. `public/aureon/assets` exists and contains brand, CSS, fonts, images,
   JavaScript, and vendor assets.
6. `storage` and `bootstrap/cache` are writable by the application owner.
7. Laravel caches are rebuilt only after environment configuration is complete.
8. Root, login, dashboard, `/shop`, one Vite asset, and one Aureon asset return
   the expected status and content type.
9. Temporary public diagnostics such as `test-index.html` and deployment
   archives such as `public/build.zip` are removed after validation.

The current Git deployment mechanism cannot execute npm. Every release must
pair a locally generated, commit-matched `public/build` upload with publication
of the Git-tracked `resources/aureon/assets` source into `public/aureon/assets`.
Shipping only `public/build` is insufficient for this application.

## 7. Homepage Gallery, Favicon, And Property Fixture Media

### Homepage gallery release check

The homepage gallery requires the Vite-built `aureon-home.css` and
`aureon-home.js` entries from the same commit. A successful HTTP response is not
enough: confirm the deployed `public/build/manifest.json` points to the uploaded
content-hashed files, clear Laravel's cached views, and refresh the page after an
upload:

```bash
cd "$APP"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

The homepage initializer is ready-state aware and idempotent. Gallery image,
caption, role, selected thumbnail, counter, full-screen viewer, and keyboard
navigation must all change together during the post-deployment browser check.

#### 2026-09-08 stale homepage bundle diagnosis

The deployed Blade view and cache-busted bundles were current, but the normal
fixed bundle URLs still returned the prior release from Hostinger's CDN:

| Resource | Cached bytes | Current bytes | CDN evidence |
| --- | ---: | ---: | --- |
| `aureon-home.min.css` | 25,913 | 28,972 | `max-age=604800`, `HIT`, age over 8,300 seconds |
| `aureon-home2.js` | 2,769 | 4,674 | `max-age=604800`, `HIT`, age over 8,300 seconds |

This explained why new lightbox HTML existed while the browser still displayed
the old two-column thumbnail grid and lacked gallery behavior. Laravel cache
clearing and replacing the physical files could not invalidate the CDN objects.
The Vite output contract now includes `[hash]` in compiled CSS and JavaScript
entry filenames. A changed bundle therefore receives a new public URL and does
not depend on a manual CDN purge.

After uploading the build, confirm the rendered homepage references filenames
similar to these rather than the old fixed names:

```text
/build/css/aureon-home-<content-hash>.min.css
/build/js/aureon-home-<content-hash>.js
```

Upload the entire generated `public/build` directory. Never combine a manifest
from one build with CSS or JavaScript files from another build.

### Favicon cache behavior

The centralized favicon is served from
`public/aureon/assets/brand/favicon.png`. Hostinger's CDN and browsers can cache
site icons independently of page HTML. The homepage therefore appends the
`config/aureon-home.php` `asset_version` value to its icon URLs. After replacing
the source favicon:

1. increment `asset_version` or set a new `AUREON_HOME_ASSET_VERSION`;
2. republish `resources/aureon/assets`;
3. rebuild configuration and views;
4. verify the versioned favicon URL returns HTTP 200 with `image/png`.

### Missing Property Booking demonstration images

The public storage link only exposes `storage/app/public`; it does not create
Media Library conversions. The 2026-09-08 production probe established the
exact state:

| Resource | Result |
| --- | --- |
| `/storage/78/aureon-city-suites-exterior.webp` | HTTP 200 |
| `/storage/78/conversions/aureon-city-suites-exterior-card.jpg` | HTTP 404 |

Commerce images remaining available, together with the Property original's
HTTP 200, proves the link and seeded source media are healthy. Regenerate only
missing Property Booking conversions, then drain the database queue:

```bash
cd "$APP"
php artisan media-library:regenerate 'App\Modules\PropertyBooking\Catalog\Models\Property' --only-missing --force --no-interaction
php artisan media-library:regenerate 'App\Modules\PropertyBooking\Catalog\Models\UnitType' --only-missing --force --no-interaction
php artisan media-library:regenerate 'App\Modules\PropertyBooking\Catalog\Models\PropertyCategory' --only-missing --force --no-interaction
php artisan queue:work --stop-when-empty --tries=3
```

The `--only-missing` option preserves existing conversions. `--force` is
required by this command in production. The queue worker completes queued
`thumb`, `card`, and `detail` jobs used by storefront and administration views.

Only when the original URL also returns 404 should the module-owned catalog
seeder be rerun before regeneration:

```bash
php artisan db:seed --class="App\\Modules\\PropertyBooking\\Database\\Seeders\\PropertyBookingCatalogDemoSeeder" --no-interaction
php artisan queue:work --stop-when-empty --tries=3
```

`PropertyBookingCatalogDemoSeeder::syncMedia()` checks physical originals,
preserves usable adopter media, and rebuilds only an incomplete collection.
Its sources are Git-tracked under
`app/Modules/PropertyBooking/Resources/demo/accommodation`.

Verify both originals and conversions before closing the incident:

```bash
test -f app/Modules/PropertyBooking/Resources/demo/accommodation/aureon-city-suites-exterior.webp \
    && echo "Property fixture source: PRESENT"
find storage/app/public -type f | grep -E 'aureon-city|deluxe-king|city-studio|family-suite' | head -20
curl -I "https://aureon.metasoftdevs.com/storage/78/conversions/aureon-city-suites-exterior-card.jpg"
curl -I "https://aureon.metasoftdevs.com/stays"
```

Back up `storage/app/public` as persistent application data and exclude it from
deployment cleanup. Recreating `public/storage` alone cannot recover deleted
media files.
