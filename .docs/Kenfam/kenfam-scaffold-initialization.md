# Kenfam Scaffold Initialization Record

## Purpose

This record distinguishes the immutable Aureon import from Kenfam adoption and
the reusable TravelTours module. It prevents future maintainers from treating a
copied directory, configured remote, or draft route as evidence of completion.

## Source Provenance

| Item | Recorded value |
| --- | --- |
| Aureon source repository | `CodeWith-PeterBull/Custom-Templates-Builds` |
| Source subtree | `laravel-aureon/` |
| Source commit | `c7aaddbb40e85dd1796576ce350c8a4a97af0189` |
| Imported tree | `2e59b5dbd843099cabdf4ee7c2a64c2f1c451a74` |
| Independent baseline commit | `f4139b7 chore: initialize independent Aureon scaffold` |
| Baseline branch | `main` |
| Implementation branch | `feature/travel-tours-foundation` |

The tree of local `main` and the source commit's `laravel-aureon` subtree both
resolve to `2e59b5dbd843099cabdf4ee7c2a64c2f1c451a74`. The baseline is therefore a
clean tracked snapshot, not an approximate filesystem copy. The new baseline is
a root commit and does not import Aureon's parent Git history.

## Repository Boundary

The local repository is:

`C:\Users\Peter Maina\Desktop\projects\kenfam-international`

Configured remotes:

- `origin`: `https://github.com/CodeWith-PeterBull/kenfam-international.git`
- `aureon-upstream` fetch: `https://github.com/CodeWith-PeterBull/Custom-Templates-Builds.git`
- `aureon-upstream` push: `DISABLED`

At the time of this record, GitHub returned “repository not found” for `origin`.
Consequently, neither the baseline nor the feature branch is described as
published. Create the private repository under the intended account, verify
access, then push `main` before publishing the feature branch. Do not replace
the read-only upstream URL with a writable client remote.

## Adoption Layer

The feature branch changes the host application, not the Aureon baseline:

- Composer and npm package identity use Kenfam-specific names.
- `config/kenfam.php` owns client name, contact and public brand paths.
- `config/institution.php` and the database-driven institution profile remain
  the cross-cutting mail/document identity source.
- `public/kenfam/assets/brand/` is the client brand asset boundary.
- `resources/kenfam/` contains source-owned client visual assets.
- The root route renders the Kenfam travel homepage rather than the Aureon CMS
  module sales page.
- Commerce and Property Booking source remains present only as a benchmark.

Institutional content provenance is recorded separately in
`kenfam-institutional-content-reference.md`. Facts that could not be verified
against an accessible official source are marked for client approval rather
than silently promoted to verified copy.

## Runtime Module Isolation

The deployment example and module configuration establish:

```dotenv
COMMERCE_ENABLED=false
PROPERTY_BOOKING_ENABLED=false
TRAVEL_TOURS_ENABLED=true
```

Disabled providers return before registering their migrations, routes, views,
permissions, dashboard entries, listeners or demo data. The TravelTours
provider is the sole entry point for its own resources. Application code must
not infer that retained Commerce or Property Booking classes are available at
runtime.

## Test Environment Policy

`phpunit.xml` supplies a non-production test key and explicitly keeps Commerce
and Property Booking disabled. Their inherited feature suites are excluded from
the Kenfam default suite because those tests require providers and fixtures that
the client runtime intentionally does not load. TravelTours, authentication,
shared dashboard, institution, logs, mail and document tests remain in scope.

Reference-module regression is performed in the Aureon source repository or in
a deliberately separate test configuration that enables one reference module.
It must not change Kenfam deployment defaults merely to make unrelated fixture
counts pass.

## Local Initialization

The ignored `.env` is created from `.env.example`, then receives a local key:

```powershell
Copy-Item .env.example .env
php artisan key:generate
```

No `.env`, credentials or generated client secrets belong in Git. A fresh
developer should then install dependencies, configure an isolated database,
run migrations, and execute the test commands listed in the deployment guide.

## Publication Gate

Before claiming repository initialization complete:

1. Confirm the private GitHub repository exists under the approved owner.
2. Confirm `git ls-remote origin` succeeds without exposing credentials.
3. Push local `main` and verify remote `main` points to `f4139b7`.
4. Push `feature/travel-tours-foundation` only after its focused tests pass.
5. Verify repository visibility is private and branch protection is intentional.
6. Record the remote commit IDs in this document and the implementation ledger.

Until all six checks pass, local Git initialization is complete and remote
publication remains pending.
