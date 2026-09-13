# Laravel Aureon Last-Login Tracking

Date: 2026-07-16
Branch: `feature/laravel-aureon-base-engine`

## Purpose

Record when each user most recently completed an authenticated sign-in, for
"last seen" columns in user lists and dormant-account checks — without
querying the system activity log.

## Behaviour

`users.last_login_at` (nullable timestamp) is stamped by
`App\Models\User::recordLogin()` at the exact point a session becomes
authenticated:

- **Password-only sign-in** — `AuthenticatedSessionController::store()`, on the
  non-2FA success path (after session regeneration).
- **Two-factor sign-in** — `TwoFactorController::verify()`, after the OTP is
  verified and the session established. It is deliberately NOT stamped at the
  password step, because a 2FA account is logged straight back out there while
  the challenge is pending.

The column is server-controlled: it is absent from `User::$fillable` and
written via `forceFill()` (same pattern as the 2FA columns). It is cast to a
`datetime`.

## Boundaries / not covered

- Remember-me cookie re-authentication (returning sessions that never POST to
  `/login`) does not update the timestamp; only explicit credentialed sign-in
  does. Revisit if adopting sites need "last active" rather than "last login".
- No UI surface yet (e.g. profile "last login" line or a user-list column);
  add when the users/roles management module lands.

## Files

- `database/migrations/2026_07_16_090000_add_last_login_at_to_users_table.php`
- `app/Models/User.php` (`recordLogin()`, cast)
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- `app/Http/Controllers/Auth/TwoFactorController.php`

## Verification

```powershell
php artisan test    # 74 passed (308 assertions)
```

Covering tests: `AuthenticationTest` (stamp on success, no stamp on failure),
`TwoFactorChallengeTest` (no stamp at the password/challenge step),
`TwoFactorVerificationTest` (stamp only after the OTP is verified). Also
confirmed against the live dev database via `php artisan tinker`.
