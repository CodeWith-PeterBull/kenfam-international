# Laravel Aureon Auth Interfaces & Two-Factor Authentication

Date: 2026-07-15

Branch: `feature/laravel-aureon-base-engine`

## Pointer

This is the handoff stub for the authentication rebrand + email-OTP 2FA
module. The full contract lives in the concern folder:

- `.docs/auth-2fa/auth-2fa-plan.md` — approved implementation plan.
- `.docs/auth-2fa/auth-2fa-implementation.md` — architecture, configuration
  contract, benchmark deviations (D1–D10), system-activity integration,
  remember-device foundation design, runbook, files changed, verification,
  and known gaps.

## At a glance

- All Breeze auth screens (login, register, forgot/reset password,
  verify-email, confirm-password) now extend the new lean
  `layouts.full-page-layout`; the profile page moved onto
  `layouts.dashboard-layout` with a Livewire Security panel
  (`<livewire:profile.two-factor-settings />`).
- Email-OTP 2FA: `config/two-factor.php` (env-gated, default off), hashed
  codes, DB-audited + route-level rate limiting, queued mail, scheduled
  `two-factor:prune`, `auth.two_factor.*` system activities.
- Registration assigns the `viewer` role.
- Tailwind Breeze leftovers deleted after a zero-reference sweep.

## Verify

```powershell
php artisan test          # 71 passed (299 assertions)
php artisan route:list --name=two-factor
npm.cmd run build
npm.cmd run qa:auth       # server on :8012; AUREON_QA_2FA=1 for the full challenge
```
