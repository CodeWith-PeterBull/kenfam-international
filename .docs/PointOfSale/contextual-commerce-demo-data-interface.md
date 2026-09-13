# Contextual Commerce Demo Data Interface

## 1. Purpose

The Commerce administration workspace exposes the approved contextual fixtures
at `/admin/commerce/demo-data`. This is a small Livewire control surface over
`commerce:demo-seed`; it does not introduce a second seeder, job, database table,
fixture owner, or import architecture.

The implementation targets the installed Laravel 12 and Livewire 4.2 runtime.
Its programmatic command call uses the command class plus a keyed parameters
array, which is also the documented Laravel 13 Artisan contract.

## 2. Access Contract

`manage-commerce-demo-data` is the sole interface capability. The route uses
Spatie permission middleware, and `DemoDataManager::boot()` plus `seed()` repeat
the authorization check because every public Livewire action is a callable
server endpoint. Active system administrators continue to inherit access from
the application's `Gate::before` super-user rule.

The role seeder registers the permission in the shared code-owned catalogue.
Adopters should grant it only to trusted administrators because archive mode can
change the publication state of adopter-owned products.

## 3. Form And Command Contract

The form accepts only:

- `context`: one of the ten keys returned by
  `CatalogDemoSeeder::supportedContexts()`;
- `archiveExisting`: a boolean that maps only to `--archive-existing`; and
- `archiveConfirmed`: a required acknowledgement when archive mode is active.

The browser never supplies a command name, seeder class, file path, or arbitrary
option. The component executes:

```php
Artisan::call(SeedCommerceDemo::class, [
    'context' => $context,
    '--archive-existing' => $archiveExisting,
]);
```

Keep mode remains the default and is idempotent. Archive mode preserves rows and
history, but marks all current products archived before the selected fixture
catalog is upserted and published.

## 4. Runtime Safeguards

- A five-minute atomic cache lock rejects concurrent demo-data runs.
- Livewire's submit lifecycle disables the form action and shows scoped loading
  feedback while the request is active.
- Non-zero command exits and thrown failures are presented without exposing an
  exception message to the browser.
- Full exceptions are reported through Laravel's configured logger.
- Success and failure attempts are written through `RecordsSystemActivity` with
  actor, context, mode, exit state, and safe aggregate metadata.
- A failure in activity recording is reported but cannot misrepresent an
  already successful seed command as failed.

## 5. Visual Context Selection

The interface presents eleven responsive radio cards with stable 4:3 media areas,
context descriptions, expected product/category counts, and current seeded and
published counts. The ten generated-catalog cards use the reviewed QA contact
sheets. `default-mixed.jpg` is a matching sheet composed from the ten original
mixed-catalog product sources.

Source thumbnails live under:

```text
resources/img/commerce/demo-contexts/
```

The existing Vite static-copy contract publishes them to:

```text
public/build/img/commerce/demo-contexts/
```

The selection, archive warning, command result, status badges, and loading state
use existing Aureon variables and retain light, dark, tablet, and mobile layouts.

## 6. Files

- `app/Modules/Commerce/DemoData/Http/Controllers/DemoDataController.php`
- `app/Modules/Commerce/DemoData/Livewire/Admin/DemoDataManager.php`
- `app/Modules/Commerce/DemoData/Livewire/Forms/DemoDataForm.php`
- `app/Modules/Commerce/Resources/views/admin/demo-data/index.blade.php`
- `app/Modules/Commerce/Resources/views/livewire/admin/demo-data-manager.blade.php`
- `app/Modules/Commerce/Resources/css/admin-dashboard.css`
- `app/Modules/Commerce/Routes/admin.php`
- `app/Modules/Commerce/CommerceServiceProvider.php`
- `app/Modules/Commerce/Support/CommercePermission.php`
- `resources/views/layouts/partials/sidebar-admin.blade.php`
- `resources/img/commerce/demo-contexts/*.jpg`
- `scripts/qa-commerce-demo-data.mjs`
- `tests/Feature/Commerce/CommerceDemoDataInterfaceTest.php`

## 7. Verification Contract

Focused tests cover guest redirect, explicit permission denial and access,
direct Livewire action authorization, context allowlisting, keep-mode command
execution, archive confirmation, archive-option behavior, lock contention,
command output, aggregate effects, and system activity recording.

Completed release evidence:

- focused authorization, command, archive, lock, and activity tests: 13 tests
  and 146 assertions passed with the adjacent dashboard/module-boundary tests;
- complete Commerce regression: 128 tests and 3,444 assertions passed;
- production Vite build: 114 modules transformed and all eleven context thumbnails
  copied to the public build contract;
- authenticated Chromium QA: desktop light, tablet dark, and mobile light all
  passed with ten loaded thumbnails, one selected radio, no horizontal or text
  overflow, no duplicate IDs, and no runtime or network failures.

Browser evidence and machine-readable diagnostics are retained under:

```text
.docs/dev/commerce-demo-data-qa/
```

## 8. Framework References

- Laravel Artisan programmatic execution:
  https://laravel.com/docs/12.x/artisan#programmatically-executing-commands
- Livewire 4 actions and action authorization:
  https://livewire.laravel.com/docs/4.x/actions
- Livewire 4 forms and validation:
  https://livewire.laravel.com/docs/4.x/forms
