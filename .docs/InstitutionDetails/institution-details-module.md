# Institution Details Module

## Status

Complete and verified on `feature/institution-details-communications-foundation` on 15 July 2026.

Institution Details is the canonical singleton organization profile for Laravel Aureon. It supplies presentation-safe identity, contact, address, social, and logo data to mail notifications, PDF reports, and future CMS modules. Deployment secrets such as SMTP credentials, API tokens, and queue connections remain environment-managed and never enter this module.

## Factual baseline

- CSK's Institution Details model, migration, seeder, configuration, controller, Livewire component, Blade page, mail templates, and report integration were reviewed before implementation.
- Runtime versions are Laravel `12.64.0`, Livewire `4.3.3`, Spatie Media Library `11.23.2`, and Spatie Permission `6.25.0`.
- Livewire form objects and validation were checked against <https://livewire.laravel.com/docs/4.x/forms>.
- Laravel's configuration cache contract was checked against <https://laravel.com/docs/12.x/configuration>.
- The existing `RecordsSystemActivity` contract remains the only cross-module audit write boundary.

## Architecture decisions

1. Record `id=1` is the deterministic institution profile. The administration workflow creates or updates it and does not delete it.
2. Database values override static `config/institution.php` values. Config and ENV remain installation-safe fallbacks before migration, seed, or database availability.
3. Config contains serializable values only. Database queries and closures are excluded so `php artisan config:cache` remains valid.
4. `ResolvesInstitutionProfile` is the consumer boundary. `InstitutionProfileResolver` returns one typed `InstitutionProfileData` snapshot per request or job and exposes `forget()` after mutations.
5. Missing `institution_details` or `media` tables resolve to fallback data instead of breaking authentication mail, CLI commands, queues, or reports.
6. `main_logo` and `logo_icon` are single-file media collections accepting PNG or JPEG assets. Explicit DTO flags identify custom media without guessing from URL structure.
7. The Livewire 4 editor uses a dedicated form object, two-way bindings, nested social validation, upload previews, dirty feedback, and explicit saves.
8. Route middleware protects page access and the component re-authorizes in `boot()` on every Livewire request. Mutating methods re-authorize independently.
9. Profile and media changes are transactionally recorded through `RecordsSystemActivity` with changed field names, not full before/after personal data.

## Implementation inventory

| Path | Action | Responsibility |
| --- | --- | --- |
| `config/institution.php` | Create | Serializable fallback identity and centralized Aureon logo paths/URLs |
| `.env.example` | Modify | Document presentation-safe fallback variables |
| `database/migrations/2026_07_15_160000_create_institution_details_table.php` | Create | Singleton-ready schema with a `comment()` on every column |
| `app/Models/InstitutionDetail.php` | Create | Casts, fillable fields, and two single-file media collections |
| `database/factories/InstitutionDetailFactory.php` | Create | Deterministic test fixtures |
| `database/seeders/InstitutionDetailsSeeder.php` | Create | Idempotent `id=1` fallback seed |
| `database/seeders/DatabaseSeeder.php` | Modify | Run role and institution seeders before local administrator setup |
| `database/seeders/RoleSeeder.php` | Modify | Grant newly created Permission models reliably on existing installations |
| `app/Contracts/ResolvesInstitutionProfile.php` | Create | Stable read contract for all consumers |
| `app/Data/InstitutionProfileData.php` | Create | Typed presentation DTO, formatted address/social line, and local logo data URI |
| `app/Services/InstitutionProfileResolver.php` | Create | Database-first, cache-safe, migration-tolerant profile resolution |
| `app/Services/InstitutionDetailsService.php` | Create | Transactional save, media replace/remove, resolver invalidation, and activity writes |
| `app/Livewire/Forms/InstitutionDetailsForm.php` | Create | Typed field state, validation, and normalized persistence payload |
| `app/Livewire/Admin/InstitutionDetailsEditor.php` | Create | Authorized editor workflow and upload handling |
| `resources/views/livewire/admin/institution-details-editor.blade.php` | Create | Responsive identity, contact, address, social, media, and preview controls |
| `app/Http/Controllers/Admin/InstitutionDetailController.php` | Create | Dashboard page endpoint |
| `resources/views/admin/institution-details/index.blade.php` | Create | Shared-layout page host without nested dashboard wrappers |
| `app/Support/CmsPermission.php` | Modify | Add view/manage and communication permissions |
| `resources/views/layouts/partials/sidebar-admin.blade.php` | Modify | Permission-aware Configuration navigation |
| `routes/web.php` | Modify | Add verified, permission-protected administration route |
| `resources/css/aureon-dashboard.css` | Modify | Stable brand previews and responsive editor presentation |
| `scripts/qa-dashboard.mjs` | Modify | Authenticated desktop/mobile configuration-page diagnostics and captures |
| `tests/Feature/InstitutionDetails/*` | Create | Fallback, precedence, access, Livewire, media, validation, and audit coverage |

## Delivered behavior

### Resolution contract

- `app(ResolvesInstitutionProfile::class)->current()` is the canonical read path.
- A database row takes precedence field by field; empty optional values fall back to installation defaults where appropriate.
- The request-scoped resolver memoizes reads and is explicitly invalidated after save or logo removal.
- Main logo URLs are absolute for HTML mail. Main logo filesystem paths become validated PNG/JPEG data URIs for offline DOMPDF rendering.

### Administration workflow

- `/admin/institution-details` renders inside the shared Aureon dashboard shell.
- Administrators can maintain full name, short name, descriptor, two emails, two phone numbers, website, physical/postal addresses, ordered social profiles, main logo, and logo icon.
- URLs, email addresses, row limits, nested social fields, image MIME types, and upload sizes are server-validated.
- Saves are explicit; empty strings normalize to `null`; empty social rows are removed before persistence.
- A custom logo can be replaced or removed while the centralized Aureon brand asset remains the fallback.

### Authorization and audit

| Surface | Required permission |
| --- | --- |
| View page or Livewire component | `view_institution_details` |
| Save fields, add/remove social rows, upload/remove logos | `manage_institution_details` |
| Open linked template preview page | `preview_communication_templates` |

Only `system-admin` receives these permissions from the foundation seeder. Successful changes emit `institution_detail.created`, `institution_detail.updated`, or `institution_detail.logo_removed` activities with source `institution-details`.

## Adoption contract

1. Inject `ResolvesInstitutionProfile`; do not query `InstitutionDetail` directly from notifications, report views, or future modules.
2. Inject `InstitutionDetailsService` for administration mutations; do not write the singleton or media collections from Blade/controllers.
3. Add presentation-safe fields through migration, model, DTO, resolver, form, editor, seeder/config fallback, tests, and this document in one change.
4. Keep credentials and transport configuration in ENV-backed Laravel config. Institution Details is public-facing identity, not a generic secret store.
5. Call `forget()` after any future out-of-band profile mutation so the same request/job cannot reuse stale values.

## Verification record

| Check | Result |
| --- | --- |
| Combined module tests | `12` tests and `67` assertions passed across Institution Details, mail, and PDF suites |
| Full Laravel suite | `83` tests and `370` assertions passed |
| Existing-installation migration | Institution migration applied successfully |
| Existing-installation seed | Role and Institution Details seeders completed after cache-safe Permission-model grant fix |
| Configuration lifecycle | `config:cache` passed; cached config was used during Tinker verification; cache then cleared |
| Live container verification | Tinker resolved profile `id=1` and the seeded institution name |
| PHP/Blade checks | Changed PHP files passed Pint; PHP syntax and Blade compilation passed |
| Authenticated Chromium QA | Institution Details passed at `390x844` and `1440x1000` with one shared wrapper, 16 inputs, two brand previews, no overflow, and no runtime/network errors |

Browser evidence is retained at `.docs/dev/dashboard-qa/institution-details-mobile.png`, `.docs/dev/dashboard-qa/institution-details-desktop.png`, and `.docs/dev/dashboard-qa/diagnostics.json`.

## Known local warning

The local Imagick extension was compiled against ImageMagick `1808` while `1810` is loaded. This pre-existing warning does not affect Spatie's tested PNG upload flow or DOMPDF rendering, but the local PHP extension should be rebuilt before production image processing is certified.
