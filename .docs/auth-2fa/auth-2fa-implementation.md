# Authentication Interfaces & Email-OTP Two-Factor Authentication

Date: 2026-07-15
Branch: `feature/laravel-aureon-base-engine`
Benchmark: CSK backend (`C:\Users\Peter Maina\Desktop\projects\CSK\backend`) and
`C:\Users\Peter Maina\Desktop\projects\CSK\.docs\2FA_IMPLEMENTATION_SUMMARY.md`

## Outcome

All Breeze authentication interfaces were rebranded from stock Tailwind onto a
lean Aureon full-page shell, the profile page moved onto the dashboard shell
with a Livewire self-service Security panel, and a complete email-OTP
two-factor subsystem was adapted from the CSK benchmark with its known
defects fixed. Registration now assigns the least-privileged `viewer` role.
The Tailwind Breeze leftovers (guest/app layouts, navigation, 13 components,
stock dashboard view) were removed after a grep-verified zero-reference pass.

## How the challenge works

1. `POST /login` (stock `LoginRequest` credential + throttle checks).
2. `AuthenticatedSessionController::requiresTwoFactorChallenge()` gates in
   order: global `two-factor.enabled` → `exempt_emails` → per-user
   `two_factor_enabled` opt-in.
3. When required: the pending user id + remember-me choice are stashed in the
   session (`two_factor:user_id`, `two_factor:remember`), the user is logged
   out (challenge routes live in the `guest` group), the session id is
   rotated, an OTP is generated, stored as a keyed digest, and emailed.
4. `GET /two-factor/challenge` renders the masked-email challenge screen.
5. `POST /two-factor/verify` (throttled) verifies via `TwoFactorService`:
   DB rate limit → liveness/expiry → constant-time digest comparison. On
   success: email auto-verified if needed (`Verified` event), `Auth::login`
   honouring remember-me, challenge keys dropped, **session regenerated, then**
   the verified-session row recorded, `auth.two_factor.challenge_passed`
   appended to the system activity stream, intended redirect (never onto
   `verification.notice`).
6. `POST /two-factor/resend` (throttled) always issues a fresh code.

## Configuration contract (`config/two-factor.php`)

| Key | Default | Env | Notes |
| --- | --- | --- | --- |
| `enabled` | `false` | `TWO_FACTOR_ENABLED` | Master switch; subsystem inert when off |
| `code.length` | 6 | `TWO_FACTOR_CODE_LENGTH` | Enforced by the `digits:` rule too |
| `code.expiry_minutes` | 10 | `TWO_FACTOR_CODE_EXPIRY` | |
| `session.ttl_hours` | 24 | `TWO_FACTOR_SESSION_TTL` | FOUNDATION (see below) |
| `rate_limit.max_attempts` | 5 | — | Counted failures per window |
| `rate_limit.decay_minutes` | 15 | — | Sliding window |
| `channels.mail` | `true` | `TWO_FACTOR_MAIL_ENABLED` | Only implemented channel |
| `log_attempts` | `true` | — | Also powers the DB rate limiter |
| `cleanup.attempt_retention_days` | 90 | — | `two-factor:prune` retention |
| `exempt_emails` | `[]` | `TWO_FACTOR_EXEMPT_EMAIL_1..3` | Break-glass bypass |

## Database

- `users` + `two_factor_enabled` (bool, default **false**),
  `two_factor_code_hash` (string 64, hidden), `two_factor_code_expires_at`,
  `two_factor_confirmed_at`, index `idx_2fa_code_lookup`. All columns carry
  `->comment()` (silent on SQLite, materialized on MySQL).
- `two_factor_sessions` — FOUNDATION table (audit + future remember-device).
- `two_factor_attempts` — immutable audit log; SHA-256 of attempted codes
  only; feeds the DB rate limiter; pruned daily.

## Deviations from the CSK benchmark (all deliberate)

| # | Deviation | Rationale |
| --- | --- | --- |
| D1 | OTP stored as `hash_hmac('sha256', code, APP_KEY)`, never plaintext; resend therefore ALWAYS regenerates | A 6-digit space is one million values — plaintext or unkeyed hashes are trivially recovered from a DB leak |
| D2 | `two_factor_enabled` defaults **false** (self-service opt-in via profile Security panel) | CSK was admin-granted default-true; Aureon has no user-management module yet and favours explicit consent |
| D3 | Login gate does NOT consult `hasValidSession()`; `verify()` regenerates the session **before** recording it | CSK stored the pre-regeneration id so its "24h skip" never fired; Aureon challenges every login and keeps the store as a correct foundation |
| D4 | Rate limiter ignores `rate_limited` block events; `lockout_minutes` config dropped; `availableInMinutes()` added | CSK counted its own block rows, extending the lockout indefinitely; its lockout_minutes was never enforced |
| D5 | Route-level `throttle:two-factor` (10/min) and `throttle:two-factor-resend` (3 per 5 min) named limiters, keyed by pending user id | CSK had no transport throttle on the challenge endpoints |
| D6 | Class-based Livewire component (`App\Livewire\Profile\TwoFactorSettings`) | Security logic belongs in a docblocked, Pint-covered class with a stable FQCN for `Livewire::test()` |
| D7 | Lean `full-page-layout` with explicit includes (no jQuery/feather/slimscroll/loader/Livewire on auth pages) | CSK's `Route::is()` guards matched nothing, shipping the full dashboard bundle to auth pages |
| D8 | Kept: notification is queued (`ShouldQueue`) | Parity; see runbook trap below |
| D9 | Registration assigns `viewer` via `Role::findOrCreate` | Idempotent on unseeded DBs; RoleSeeder stays canonical |
| D10 | Cleanups exposed as `two-factor:prune`, scheduled daily in `routes/console.php` | CSK shipped cleanup methods but never scheduled them |
| — | Challenge screen shows a **masked** email; no decorative social buttons; `confirm-password` rebranded | CSK disclosed the full email pre-auth, shipped dead social buttons, and forgot confirm-password |
| — | 2FA columns are NOT in `$fillable`; `TwoFactorService` writes via `forceFill()` | Removes the mass-assignment surface CSK carried |
| — | Single `name` field kept on registration | CSK's first/last split was not ported (Breeze convention preserved) |

## System activity integration

The module appends to the foundation audit stream via the
`RecordsSystemActivity` contract:

- `auth.two_factor.challenge_passed` — `TwoFactorController::verify()` success.
- `auth.two_factor.enabled` / `auth.two_factor.disabled` — profile Security
  panel transitions (severity: notice).

Fine-grained attempt forensics stay in `two_factor_attempts` (every attempt,
IP, UA-less by design, hashed code, failure reason).

## Remember-device: FOUNDATION only (future work)

The store (`two_factor_sessions`), model scopes, and service methods
(`createVerifiedSession`, `hasValidSession`, `invalidateUserSessions`) exist,
are ordering-correct, and are covered by tests — but the login gate does not
consult them: **every login is challenged while 2FA is active.** The designed
extension (documented on the `TwoFactorSession` model docblock):

1. Add a `device_token_hash` column.
2. On verify, issue a `Str::random(60)` token in an encrypted, HTTP-only,
   long-lived cookie; store its hash with the TTL.
3. Gate on the cookie token hash (never `session_id` — session ids rotate
   per login, which is exactly why CSK's version was inert).
4. Invalidate rows on password change, 2FA disable, and "sign out other
   devices".

## Local development runbook

- **The #1 trap:** OTP mail is **queued**. A bare `php artisan serve` never
  sends it — run `composer run dev` (includes `queue:listen`) or a separate
  worker. Tests are unaffected (`QUEUE_CONNECTION=sync` in phpunit.xml).
- With `MAIL_MAILER=log`, delivered codes appear in
  `storage/logs/laravel.log`.
- Enable the subsystem per environment with `TWO_FACTOR_ENABLED=true`; users
  then opt in from `/profile` → Security.
- Manual pruning: `php artisan two-factor:prune` (also scheduled daily).

## Files

New: `config/two-factor.php`; migrations
`2026_07_15_100000|100100|100200_*`; `app/Models/TwoFactorSession.php`,
`TwoFactorAttempt.php`; `app/Services/TwoFactorService.php`;
`app/Notifications/TwoFactorCodeNotification.php`;
`app/Console/Commands/PruneTwoFactorData.php`;
`app/Http/Controllers/Auth/TwoFactorController.php`;
`app/Http/Requests/Auth/VerifyTwoFactorRequest.php`;
`app/Livewire/Profile/TwoFactorSettings.php`;
`resources/views/layouts/full-page-layout.blade.php`;
`resources/views/auth/two-factor-challenge.blade.php`;
`resources/views/emails/two-factor-code.blade.php`;
`resources/views/livewire/profile/two-factor-settings.blade.php`;
`resources/css/aureon-auth.css`; `resources/js/aureon-auth.js`;
`scripts/qa-auth.mjs`; tests `TwoFactorChallengeTest`,
`TwoFactorVerificationTest`, `TwoFactorSettingsTest`, `TwoFactorServiceTest`.

Modified: `app/Models/User.php` (hidden/casts/relations),
`app/Providers/AppServiceProvider.php` (named limiters),
`app/Http/Controllers/Auth/AuthenticatedSessionController.php` (gate),
`app/Http/Controllers/Auth/RegisteredUserController.php` (viewer role),
`routes/auth.php` (+3 routes), `routes/console.php` (schedule),
`vite.config.js` (+2 entries), `phpunit.xml` (env pin),
`database/factories/UserFactory.php` (`twoFactorEnabled` state),
all seven `resources/views/auth/*` views, `resources/views/profile/*`
(page + three partials), `.env.example`, `README.md`, `package.json`
(`qa:auth`), `tests/Feature/Auth/RegistrationTest.php`.

Removed (grep-verified zero references): `resources/views/dashboard.blade.php`,
`layouts/app|guest|navigation.blade.php`, `app/View/Components/*`, and 13
Tailwind Breeze components (`text-input`, `input-label`, `input-error`,
`primary/secondary/danger-button`, `auth-session-status`, `modal`,
`dropdown`, `dropdown-link`, `nav-link`, `responsive-nav-link`,
`application-logo`). `components/loader.blade.php` retained.

## Verification

```text
php artisan migrate                        3 migrations DONE (dev SQLite)
scratchpad service script                  33/33 checks passed (generate,
                                           HMAC-at-rest, 5x fail, lockout,
                                           D4 lift, verify, replay, resend,
                                           sessions, cleanups, prune)
php artisan test                           71 passed (299 assertions)
php artisan view:cache                     OK
php artisan route:list                     3 two-factor routes registered
npm.cmd run build                          OK (aureon-auth entries emitted)
vendor/bin/pint --dirty                    style clean
npm.cmd run qa:dashboard                   regression evidence in .docs/dev/dashboard-qa/
npm.cmd run qa:auth                        evidence in .docs/dev/auth-qa/
AUREON_QA_2FA=1 npm.cmd run qa:auth        full challenge flow (server with
                                           TWO_FACTOR_ENABLED=true)
```

## Known gaps / deferred

- Remember-device skip (foundation shipped, gate deferred — see above).
- Admin per-user 2FA override — arrives with the users/roles module
  (pattern: CSK `UserManagement::toggleTwoFactor`, which must also call
  `invalidateUserSessions` and clear pending code state).
- Honeypot fields on login/register (package installed, unwired — CSK also
  leaves auth forms bare; decide when the public site ships).
- SMS/other OTP channels (config slot documented, intentionally not shipped).
- DreamPOS `style.css` remains the heaviest auth-page asset; an auth-only
  slice is a future optimization.
