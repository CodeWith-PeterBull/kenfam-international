# Laravel Aureon Installation Commands

## Execution status

Completed and conformed on 2026-07-14.

The generated project was installed at `laravel-aureon/laravel-aureon-install-tmp` rather than as a sibling of `laravel-aureon`. It was safely promoted into the `laravel-aureon` application root while preserving this documentation. The temporary directory no longer exists.

The required packages, Breeze Blade scaffolding, SQLite database, Permission and Media Library migrations, storage link, Vite build, and validation steps are complete. Treat the commands below as a reproducible installation reference, not as pending work.

This document contains the user-run installation commands for the Laravel Aureon base engine. Do not run these commands from the parent project root accidentally; run them from `Custom Templates Builds` exactly as shown.

The `laravel-aureon` folder already contained `.docs/dev` planning context, so the Laravel project was created in a temporary folder and then moved into `laravel-aureon`.

## 1. Open the repository folder

```powershell
cd "C:\Users\Peter Maina\Desktop\projects\kingster-education-html-template-2023-11-27-05-11-02-utc\Custom Templates Builds"
```

## 2. Create the Laravel 12 project in a temporary folder

```powershell
composer create-project laravel/laravel laravel-aureon-install-tmp "^12.0"
```

## 3. Copy the generated Laravel app into `laravel-aureon`

```powershell
robocopy ".\laravel-aureon-install-tmp" ".\laravel-aureon" /E /XD ".git" "node_modules" "vendor" /XF ".env"
if ($LASTEXITCODE -le 7) { $global:LASTEXITCODE = 0 }
```

## 4. Remove the temporary install folder

```powershell
Remove-Item ".\laravel-aureon-install-tmp" -Recurse -Force
```

## 5. Enter the Laravel app

```powershell
cd ".\laravel-aureon"
```

## 6. Install the benchmark Composer package baseline

These mirror the direct Composer dependencies currently present in `C:\Users\Peter Maina\Desktop\projects\CSK\backend`.

```powershell
composer require barryvdh/laravel-dompdf:^3.1 intervention/image-laravel:^1.5 livewire/livewire:^4.2 opcodesio/log-viewer:^3.24 spatie/laravel-honeypot:^4.7 spatie/laravel-medialibrary:^11.21 spatie/laravel-permission:^6.24
```

```powershell
composer require --dev laravel/boost:^2.2 laravel/breeze:^2.3 laravel/pail:^1.2.2 laravel/pint:^1.24 laravel/sail:^1.41
```

The Laravel skeleton already includes these benchmark-aligned packages or equivalents:

- `laravel/framework:^12.0`
- `laravel/tinker:^2.10.1`
- `fakerphp/faker:^1.23`
- `mockery/mockery:^1.6`
- `nunomaduro/collision:^8.6`
- `phpunit/phpunit:^11.5.3`

## 7. Install Breeze Blade authentication

```powershell
php artisan breeze:install blade
```

Two-factor authentication is intentionally not installed in this first pass. It will be copied or rebuilt later from the benchmark application after the base dashboard and role architecture are in place.

## 8. Install the benchmark npm package baseline

```powershell
npm install
```

```powershell
npm install --save-dev @tailwindcss/forms@^0.5.2 @tailwindcss/vite@^4.0.0 alpinejs@^3.4.2 autoprefixer@^10.4.2 axios@^1.11.0 concurrently@^9.0.1 laravel-vite-plugin@^2.0.0 postcss@^8.4.31 tailwindcss@^3.1.0 vite@^7.0.7 vite-plugin-static-copy@^3.2.0
```

## 9. Create environment and app key

```powershell
Copy-Item ".env.example" ".env"
php artisan key:generate
```

## 10. Use SQLite for the first local baseline

Use this lightweight local database only for the first base-engine bootstrapping pass. We can switch to MySQL or another configured database once the modules become concrete.

```powershell
New-Item -ItemType File -Force -Path ".\database\database.sqlite" | Out-Null
```

```powershell
(Get-Content ".env") -replace "^DB_CONNECTION=.*", "DB_CONNECTION=sqlite" -replace "^DB_HOST=.*", "# DB_HOST=127.0.0.1" -replace "^DB_PORT=.*", "# DB_PORT=3306" -replace "^DB_DATABASE=.*", "# DB_DATABASE=laravel" -replace "^DB_USERNAME=.*", "# DB_USERNAME=root" -replace "^DB_PASSWORD=.*", "# DB_PASSWORD=" | Set-Content ".env"
```

## 11. Publish and migrate the first package tables

```powershell
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
php artisan migrate
```

Optional config publishing can wait until the module using the package is implemented:

```powershell
php artisan vendor:publish --tag="log-viewer-config"
php artisan vendor:publish --tag="honeypot-config"
php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider" --tag="config"
```

## 12. Create the storage link

```powershell
php artisan storage:link
```

## 13. Build and serve

```powershell
npm run build
```

```powershell
php artisan serve
```

For concurrent development after the Vite and dashboard scripts are reconciled:

```powershell
composer run dev
```

## 14. First validation after install

```powershell
php artisan about
php artisan route:list
php artisan test
npm run build
```

## Expected post-install handoff

After these commands are complete, Codex should reconcile the generated Laravel app with this documentation, then continue with:

1. Dashboard layout/resource migration from the CSK benchmark.
2. Aureon brand kit and public frontend Blade layout conversion.
3. Auth, roles, sample modules, and base dashboard views.
4. Updated implementation documentation under `.docs/dev`.
