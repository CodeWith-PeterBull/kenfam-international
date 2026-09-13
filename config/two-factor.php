<?php

/*
|--------------------------------------------------------------------------
| Two-Factor Authentication (Email OTP)
|--------------------------------------------------------------------------
|
| Configuration for the Aureon email-OTP two-factor authentication
| subsystem, adapted from the CSK backend benchmark. A six-digit code is
| emailed to the user after a successful password login; the session is
| only established once the code is verified on the challenge screen.
|
| The subsystem is inert unless BOTH switches are on:
|   1. the global `enabled` flag below (env TWO_FACTOR_ENABLED), and
|   2. the per-user `users.two_factor_enabled` preference, enabled by default
|      in managed account creation and editable by administrators or users.
|
| Full design notes, benchmark deviations, and the operations runbook live
| in `.docs/auth-2fa/`.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    |
    | Governs the whole subsystem. When false (the safe default) no user is
    | ever challenged regardless of their personal opt-in flag, and the
    | profile Security panel explains that preferences take effect once the
    | system flag is enabled. Toggle per environment via TWO_FACTOR_ENABLED.
    |
    */

    'enabled' => env('TWO_FACTOR_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | One-time code shape and lifetime
    |--------------------------------------------------------------------------
    |
    | `length` is the number of digits generated (and enforced by the
    | challenge form request's `digits:` rule). `expiry_minutes` is how long
    | a dispatched code stays valid; expired codes are rejected and the user
    | must request a resend.
    |
    */

    'code' => [
        'length' => (int) env('TWO_FACTOR_CODE_LENGTH', 6),
        'expiry_minutes' => (int) env('TWO_FACTOR_CODE_EXPIRY', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Verified-session lifetime (FOUNDATION — not enforced yet)
    |--------------------------------------------------------------------------
    |
    | Rows written to `two_factor_sessions` expire after this many hours.
    | The login gate deliberately does NOT consult these rows yet: every
    | login is challenged. The table, model, and service methods exist as a
    | documented foundation for a future "remember this device" feature
    | (see the TwoFactorSession model docblock and `.docs/auth-2fa/`).
    |
    */

    'session' => [
        'ttl_hours' => (int) env('TWO_FACTOR_SESSION_TTL', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | Code-verification rate limiting
    |--------------------------------------------------------------------------
    |
    | Database-audited sliding window: after `max_attempts` counted failures
    | (invalid or expired codes — blocked "rate_limited" retries are NOT
    | counted, so the lockout cannot extend itself) within `decay_minutes`,
    | further verification attempts are rejected until the oldest counted
    | failure ages out of the window. Route-level throttling on the verify
    | and resend endpoints provides an additional transport backstop.
    |
    */

    'rate_limit' => [
        'max_attempts' => 5,
        'decay_minutes' => 15,
    ],

    /*
    |--------------------------------------------------------------------------
    | Delivery channels
    |--------------------------------------------------------------------------
    |
    | Mail is the only implemented channel. A future SMS (or other) channel
    | should be added here and honoured in TwoFactorCodeNotification::via();
    | the CSK benchmark reserved an SMS slot but never implemented it, so it
    | is intentionally omitted rather than shipped dead.
    |
    */

    'channels' => [
        'mail' => (bool) env('TWO_FACTOR_MAIL_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Attempt auditing
    |--------------------------------------------------------------------------
    |
    | When true every verification attempt (success or failure) is recorded
    | in `two_factor_attempts` with a SHA-256 hash of the submitted code —
    | never the plaintext. The rate limiter reads this table, so disabling
    | logging also disables the database-backed rate limiting.
    |
    */

    'log_attempts' => true,

    /*
    |--------------------------------------------------------------------------
    | Housekeeping retention
    |--------------------------------------------------------------------------
    |
    | The `two-factor:prune` artisan command (scheduled daily) deletes
    | expired verified-session rows and audit attempts older than this many
    | days. 90 days matches the CSK benchmark's forensic retention window.
    |
    */

    'cleanup' => [
        'attempt_retention_days' => 90,
    ],

    /*
    |--------------------------------------------------------------------------
    | Exempt accounts
    |--------------------------------------------------------------------------
    |
    | Emails listed here bypass the challenge entirely even when the master
    | switch and their personal flag are on. Intended for break-glass or
    | machine accounts in constrained environments; keep the list short and
    | documented. Empty env slots are filtered out.
    |
    */

    'exempt_emails' => array_filter([
        env('TWO_FACTOR_EXEMPT_EMAIL_1'),
        env('TWO_FACTOR_EXEMPT_EMAIL_2'),
        env('TWO_FACTOR_EXEMPT_EMAIL_3'),
    ]),

];
