# Aureon Auth Interface Rebrand + Email-OTP 2FA Module

App root (all paths relative to it):
`Custom Templates Builds/laravel-aureon`

## Context

Laravel Aureon's auth surfaces are 100% stock Tailwind Breeze (login, register, password flows, verify-email, confirm-password on `x-guest-layout`; profile on `x-app-layout`) — visually disconnected from the DreamPOS/Bootstrap dashboard shell. The CSK benchmark (`C:\Users\Peter Maina\Desktop\projects\CSK\backend` + `C:\Users\Peter Maina\Desktop\projects\CSK\.docs\2FA_IMPLEMENTATION_SUMMARY.md`) provides two proven patterns to adapt: (1) rebranding all Breeze auth views onto a Bootstrap `full-page-layout`, and (2) a self-contained **email-OTP 2FA subsystem** (6-digit code mailed after password login; env-gated `TWO_FACTOR_ENABLED` default off; DB-audited rate limiting). CSK has **no TOTP/QR/recovery codes** — its 2FA is email OTP only.

**CSK defects we must NOT port as-is** (verified): inert "24h skip" (stores pre-regenerate session id → never matches); no route-level throttle on verify/resend; cleanup methods never scheduled; zero automated 2FA tests; plaintext OTP at rest; auth pages load the full dashboard bundle because `Route::is()` guards check wrong route names; `confirm-password.blade.php` left stock.

**User decisions (locked):**
1. **Email OTP, CSK parity**, defects fixed. No TOTP.
2. **Self-service Security panel** on profile as a Livewire 4 component (password-confirmed enable/disable). Per-user flag defaults **false (opt-in)** — deviation from CSK's admin-granted default-true; admin toggle deferred to users/roles module.
3. **Remember-device = foundation only**: keep `two_factor_sessions` table/model/service methods fully documented with the future device-token design, but the login gate challenges **every** login (no dead branch). Fix the regenerate-ordering so the foundation is correct.
4. **Registration stays enabled**, rebranded, auto-assigns Spatie role `viewer`; keep stock single `name` field (do NOT port CSK first/last split).

**Standards:** every migration column gets `->comment()`; every file/function meaningfully documented; Livewire 4.2 conventions (class-based components remain fully supported — chosen for this security component: stable FQCN for `Livewire::test()`, Pint coverage, heavy docblocks in PHP not Blade); dashboard asset family only (`public/build` + `--aureon-*` tokens, no palette literals); shared partials, no one-off pages; docs in concern folder `.docs/auth-2fa/` + pointer under `.docs/dev/`; **no commits — Peter commits himself**.

**Verified facts:** `resources/css/style.css` contains the DreamPOS auth classes (121 matches: `account-page`, `login-wrapper`, `pass-group`, `toggle-password`, `authent-content`); phpunit.xml forces `MAIL_MAILER=array` + `QUEUE_CONNECTION=sync` (queued notification test-safe); Livewire config supports `App\Livewire` class components + `resources/views/livewire` views; 29 existing tests green.

## Key design decisions

| # | Decision | Rationale |
|---|---|---|
| D1 | OTP hashed at rest: `two_factor_code_hash` string(64) = `hash_hmac('sha256', $code, config('app.key'))`; `hash_equals` compare | 6-digit plaintext/plain-sha256 is brute-forceable offline; HMAC needs APP_KEY. Consequence: resend always generates a **fresh** code (old plaintext unrecoverable) |
| D2 | `two_factor_enabled` default **false** (opt-in) | Self-service model; keeps all 29 existing tests green untouched |
| D3 | Gate does NOT call `hasValidSession()`; in `verify()` run `session()->regenerate()` **before** `createVerifiedSession()` | Foundation-only remember-device, stored session_id now actually matches for future use |
| D4 | `isRateLimited()` counts only failures with `failure_reason != 'rate_limited'`; drop CSK's dead `lockout_minutes` key; add `availableInMinutes()` helper for the error message | CSK counted its own block-events, extending lockout forever |
| D5 | Named route limiters in `AppServiceProvider::boot()`: `two-factor` (10/min) and `two-factor-resend` (3 per 5 min), keyed by `session('two_factor:user_id')` ?: IP | Transport backstop + mail-spam guard |
| D6 | Livewire component **class-based**: `app/Livewire/Profile/TwoFactorSettings.php` + `resources/views/livewire/profile/two-factor-settings.blade.php` | Security logic belongs in a docblocked PHP class, not a Blade SFC |
| D7 | Lean `full-page-layout`: explicit minimal includes (bootstrap.min.css, tabler-icons, style.css, Vite `aureon-dashboard.css` + new `aureon-auth.css`; bootstrap.bundle.min.js + new vanilla `aureon-auth.js`). No jQuery/feather/slimscroll/loader/Livewire on auth pages, no `Route::is()` guards | Fixes CSK bloated-auth-bundle defect |
| D8 | Notification stays `ShouldQueue` (parity). Dev needs a queue worker (`composer dev` runs one); `MAIL_MAILER=log` → codes in `storage/logs/laravel.log` | Document as the #1 "email never arrived" trap |
| D9 | Registration: `$user->assignRole(Role::findOrCreate('viewer', 'web'))` | Idempotent on unseeded DBs |
| D10 | Cleanups as artisan `two-factor:prune`, scheduled `daily()` in `routes/console.php` | Fixes unscheduled-cleanup defect; manually runnable |

## Phase 1 — Data layer, config, service, notification

**New files:**
- `config/two-factor.php` — `enabled` (env `TWO_FACTOR_ENABLED`, false), `code.length`/`code.expiry_minutes` (6/10, env), `session.ttl_hours` (24, marked FOUNDATION), `rate_limit` (5 attempts / 15 min decay), `channels.mail`, `log_attempts`, `cleanup.attempt_retention_days` (90), `exempt_emails` (3 env slots, array_filter). Every block commented.
- 3 migrations (timestamped after `2026_07_14_173444`, **every column `->comment()`**):
  1. `add_two_factor_columns_to_users_table` — `two_factor_enabled` bool default false; `two_factor_code_hash` string(64) nullable; `two_factor_code_expires_at` timestamp nullable; `two_factor_confirmed_at` timestamp nullable; index `idx_2fa_code_lookup (two_factor_enabled, two_factor_code_expires_at)`; after `remember_token`.
  2. `create_two_factor_sessions_table` — CSK schema (user_id FK cascade, session_id string(40), ip_address string(45), user_agent text nullable, verified_at useCurrent, expires_at nullable, timestamps, 2 indexes). File docblock: FOUNDATION — gate doesn't consult it yet; documents device-token extension.
  3. `create_two_factor_attempts_table` — CSK audit schema (nullable user_id FK, ip_address, attempted_code_hash string(64) nullable, success bool, failure_reason string(50) nullable, created_at only, 3 indexes).
  - Note: table/column comments are silent no-ops on SQLite, materialize on MySQL — keep them.
- `app/Models/TwoFactorSession.php` (fillable, casts, `user()`, `scopeValid`; class docblock = remember-device design note) and `app/Models/TwoFactorAttempt.php` (`$timestamps=false`, `scopeFailed`, `scopeRecent`).
- `app/Services/TwoFactorService.php` — `generateAndSendCode`, `resendCode` (delegates to generate, D1), `verifyCode` (rate check → expiry → `hash_equals`; records every attempt; on success clears hash + sets `two_factor_confirmed_at`), `isRateLimited` (D4), `availableInMinutes`, `createVerifiedSession`/`hasValidSession`/`invalidateUserSessions` (docblocked `@internal FOUNDATION`), `cleanupExpiredSessions`/`cleanupOldAttempts`, protected `hashCode`/`generateCode`/`recordAttempt`/`sendCode` (try/catch + Log::error). Uses `forceFill()` — 2FA columns stay **out of `$fillable`**.
- `app/Notifications/TwoFactorCodeNotification.php` — ShouldQueue, `(string $code, int $expiryMinutes)`, `via()` gated by `channels.mail`, markdown `emails.two-factor-code`.
- `resources/views/emails/two-factor-code.blade.php` — mail::message with code panel, expiry, security notice, login button; Aureon wording, `$userName` = `name`.
- `app/Console/Commands/PruneTwoFactorData.php` + edit `routes/console.php` (`Schedule::command('two-factor:prune')->daily()`).

**Modify:** `app/Models/User.php` — add `two_factor_code_hash` to `$hidden`; casts for the 3 new attrs; `twoFactorSessions()`/`twoFactorAttempts()` relations with docblocks. No `$fillable` additions.

**Gate 1:** `php artisan migrate` (dev sqlite); temporary scratchpad verification script (bootstrap app, throwaway user: generate → wrong code ×5 → rate-limited → travel past decay → correct code verifies → cleanup counts; delete script after); `php artisan test` — 29 still green.

## Phase 2 — Login gate, 2FA routes/controller, auth rebrand

**Modify:**
- `app/Providers/AppServiceProvider.php` — register the two named limiters (D5).
- `routes/auth.php` — inside existing `guest` group: `GET two-factor/challenge` (`two-factor.challenge`), `POST two-factor/verify` + `throttle:two-factor` (`two-factor.verify`), `POST two-factor/resend` + `throttle:two-factor-resend` (`two-factor.resend`).
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php` — inject `TwoFactorService`; in `store()` after `authenticate()`: if `requiresTwoFactorChallenge($user)` → stash `two_factor:user_id` + `two_factor:remember`, `Auth::logout()`, send code, redirect challenge; else stock path. `protected requiresTwoFactorChallenge()`: global flag → exempt email → per-user flag (NO hasValidSession call; docblock explains D3).
- `vite.config.js` — append `resources/css/aureon-auth.css`, `resources/js/aureon-auth.js` to `candidateInputs`.

**New:**
- `app/Http/Requests/Auth/VerifyTwoFactorRequest.php` — `code => required|string|digits:{config}`, custom messages.
- `app/Http/Controllers/Auth/TwoFactorController.php` — `protected challengedUser(): ?User` (de-dupes CSK's triplicated lookup); `show()` (masked email via `Str::mask` — improvement over CSK's full disclosure); `verify()` (on success: auto `markEmailAsVerified()` + `Verified` event if unverified, `Auth::login($user, remember)`, forget session keys, **regenerate then createVerifiedSession** (D3), intended-redirect with `verification.notice` guard); `resend()`.
- `resources/views/layouts/full-page-layout.blade.php` — name reserved by base-engine plan; lean shell per D7: head = `title-meta` + `theme-settings` partials + minimal CSS + `@vite` + `@stack('styles')`; `<body class="account-page">` → `main-wrapper` → `@yield('content')`; bootstrap.bundle + `@vite('resources/js/aureon-auth.js')` + `@stack('scripts')`.
- `resources/css/aureon-auth.css` — token-consuming (`--aureon-*` only): auth card polish, brand logo sizing, `.aureon-otp-input` (large/letter-spaced/centered).
- `resources/js/aureon-auth.js` — documented vanilla module: `.toggle-password` eye toggle for `.pass-group`, OTP numeric-only filtering.
- **7 rebranded views**, all `@extends('layouts.full-page-layout')`, CSK Bootstrap pattern (`account-content > login-wrapper > login-content`; `mb-3 > form-label`; `input-group` + `ti ti-*`; `@error → is-invalid` + `invalid-feedback d-block`; `pass-group` toggles; DreamPOS checkbox; `session('status')` alert; Aureon logos `asset('aureon/assets/brand/logo-light.png')`; footer partial; **no fake social buttons**):
  `auth/login`, `auth/register` (single `name`), `auth/forgot-password`, `auth/reset-password`, `auth/verify-email`, `auth/confirm-password` (**rebranded — CSK forgot this one**), `auth/two-factor-challenge` (masked email, `.aureon-otp-input` with `inputmode=numeric` + maxlength from config, expiry hint, verify + resend forms, back-to-login, security notice).

**Gate 2:** `php artisan view:cache`; `route:list` shows 3 new routes; `npm.cmd run build`; existing auth tests green (same routes/fields).

## Phase 3 — Profile overhaul, Livewire panel, registration role

- Rewrite `resources/views/profile/edit.blade.php` → `@extends('layouts.dashboard-layout')` with DreamPOS `page-header` + Bootstrap cards, each `@include`-ing a **rewritten** partial (keep shared-partial convention): `update-profile-information-form` (PATCH `profile.update`, keep `MustVerifyEmail` block, `profile-updated` alert), `update-password-form` (PUT `password.update`, `updatePassword` bag, pass-group), **Security card** → `<livewire:profile.two-factor-settings />`, `delete-user-form` (Bootstrap modal `#delete-account-modal`, DELETE `profile.destroy`, `userDeletion` bag, small script reopens modal on validation errors — replaces Alpine). `ProfileController` unchanged.
- **New** `app/Livewire/Profile/TwoFactorSettings.php` (D6): `public bool $enabled`, `public string $currentPassword`; `mount()`; `enable()` / `disable(TwoFactorService $service)` both validating `['currentPassword' => ['required', 'current_password:web']]`, `forceFill` the flag; disable also clears pending hash/expiry + `invalidateUserSessions()`; heavy docblocks (threat model, opt-in rationale, doc pointer).
- **New** `resources/views/livewire/profile/two-factor-settings.blade.php` — status badge, last-verified `diffForHumans()`, muted notice when global flag off ("preference takes effect when system 2FA is enabled"), password input, enable/disable button with `wire:loading`, errors + success flash.
- Modify `app/Http/Controllers/Auth/RegisteredUserController.php` — `assignRole(Role::findOrCreate('viewer', 'web'))` before `Registered` event (D9), commented.

**Gate 3:** `php artisan test`; manual profile smoke on dashboard shell (light/dark).

## Phase 4 — Tests

All `RefreshDatabase`; activate per-test via `config(['two-factor.enabled' => true])` + user with flag true (add `UserFactory` state `twoFactorEnabled()`); `Notification::fake()`. Pin `<env name="TWO_FACTOR_ENABLED" value="false"/>` in phpunit.xml for explicitness.

1. `tests/Feature/Auth/TwoFactorChallengeTest.php` — challenge redirect + guest + notification + session keys; bypasses (global off / per-user off / exempt email → straight to dashboard, authenticated); challenge page 200 with session, bounce to login without.
2. `tests/Feature/Auth/TwoFactorVerificationTest.php` — success (authenticated, hash cleared, confirmed_at set, email auto-verified + `Verified` event, sessions row matches **post-login** session id — proves D3 fix); invalid code → guest + `invalid_code` attempt row; expired (`travel(11)->minutes()`); DB rate limiting (5 fails block a correct code; `rate_limited` rows don't extend window); resend regenerates hash + second notification; 429 past route throttle.
3. `tests/Feature/Profile/TwoFactorSettingsTest.php` — `assertSeeLivewire`; `Livewire::test(TwoFactorSettings::class)` enable persists with correct password; wrong password → `assertHasErrors` + unchanged; disable clears pending fields + deletes sessions rows.
4. `tests/Feature/TwoFactorServiceTest.php` — hash-at-rest (DB ≠ plaintext, = HMAC), lifecycle, cleanup retention math, `two-factor:prune` exit 0.
5. Modify `tests/Feature/Auth/RegistrationTest.php` — assert `hasRole('viewer')`.

**Gate 4:** full `php artisan test`; `vendor/bin/pint --dirty`.

## Phase 5 — Docs, env, QA, cleanup

- `.env.example` — commented block: `TWO_FACTOR_ENABLED=false`, `TWO_FACTOR_CODE_LENGTH=6`, `TWO_FACTOR_CODE_EXPIRY=10`, `TWO_FACTOR_SESSION_TTL=24`, `TWO_FACTOR_MAIL_ENABLED=true`, `TWO_FACTOR_EXEMPT_EMAIL_1/2/3=`.
- `.docs/auth-2fa/auth-2fa-plan.md` (this plan) + `.docs/auth-2fa/auth-2fa-implementation.md` (final log: defect fixes, deviations D1–D10, files changed, dev runbook incl. queue-worker trap, remember-device extension design, verification results, known gaps).
- `.docs/dev/laravel-aureon-auth-2fa.md` — pointer note per repo handoff convention.
- `README.md` — auth/2FA section, new routes, env vars, verification steps.
- `scripts/qa-auth.mjs` — sibling of qa-dashboard.mjs (CDP, Brave headless, `AUREON_QA_URL` default `http://127.0.0.1:8012`), shots → `.docs/dev/auth-qa/`: login/register/forgot/verify-email/confirm-password/challenge, desktop+mobile, light+dark; best-effort full OTP flow by scraping the code from `storage/logs/laravel.log`.
- Conscious dead-code pass (grep-verified zero references first): `layouts/guest.blade.php`, `layouts/app.blade.php`, `layouts/navigation.blade.php`, orphaned Breeze Tailwind components (`text-input`, `input-label`, `input-error`, buttons, `auth-session-status`, `modal`, `dropdown*`, `nav-link*`, `application-logo`), stock `dashboard.blade.php`. Keep `loader.blade.php`. If any doubt → defer removal, log in implementation doc.

**Gate 5 (final):** `php artisan test` → `view:cache` → `route:list` → `npm.cmd run build` → serve on :8012 → `node scripts/qa-auth.mjs` + `node scripts/qa-dashboard.mjs` (profile regression) → re-run scratchpad service script → delete temp scripts.

## Risks / order dependencies

1. Users-table migration must precede factory/seeder use of new columns; FK cascade exercised by account deletion (SQLite FKs on by default in Laravel).
2. Queued notification: bare `php artisan serve` sends nothing — needs worker (`composer dev`). Document prominently.
3. `AuthenticationTest` direct-dashboard assertion stays green only because both defaults are off/false — don't change defaults.
4. Auto email-verify on OTP success must keep the `verification.notice` intended-URL guard.
5. DreamPOS `style.css` is the heaviest auth asset but required (`login-wrapper` etc.); note as future slim-down.
6. Throttle limiter reads session key before auth — fine in guest group as routed; falls back to IP.
7. No commits/merges — Peter handles git himself.
