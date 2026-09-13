<?php

namespace App\Services;

use App\Models\TwoFactorAttempt;
use App\Models\TwoFactorSession;
use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Email-OTP two-factor authentication engine.
 *
 * Owns the full code lifecycle: generation, hashed-at-rest storage,
 * delivery, constant-time verification, database-audited rate limiting,
 * verified-session bookkeeping (foundation), and housekeeping.
 *
 * Adapted from the CSK backend benchmark with these deliberate deviations
 * (full rationale in `.docs/auth-2fa/auth-2fa-implementation.md`):
 *
 *  - D1: the active code is stored as an APP_KEY-keyed HMAC-SHA256 digest
 *    (`users.two_factor_code_hash`), never plaintext. Consequently a
 *    "resend" always issues a FRESH code — the original plaintext is
 *    unrecoverable by design — which also invalidates the previous code.
 *  - D3: verified-session rows are recorded AFTER session regeneration and
 *    the login gate does not consult them yet (remember-device foundation).
 *  - D4: the rate limiter ignores `rate_limited` block events when counting
 *    failures, so a lockout cannot extend itself indefinitely.
 */
class TwoFactorService
{
    /**
     * Audit failure reasons persisted to two_factor_attempts.failure_reason.
     */
    public const FAILURE_INVALID_CODE = 'invalid_code';

    public const FAILURE_EXPIRED = 'expired';

    public const FAILURE_RATE_LIMITED = 'rate_limited';

    /**
     * Generate a fresh OTP for the user, persist its digest + expiry, and
     * dispatch the notification.
     *
     * Any previously pending code is implicitly invalidated because only one
     * digest is stored per user.
     *
     * @return bool True when the notification was dispatched (or queued).
     */
    public function generateAndSendCode(User $user): bool
    {
        $code = $this->generateCode();

        // forceFill: the 2FA columns are deliberately NOT mass assignable —
        // this service is their only writer.
        $user->forceFill([
            'two_factor_code_hash' => $this->hashCode($code),
            'two_factor_code_expires_at' => now()->addMinutes(
                (int) config('two-factor.code.expiry_minutes', 10)
            ),
        ])->save();

        return $this->sendCode($user, $code);
    }

    /**
     * Re-issue a challenge code.
     *
     * Deviation D1: the CSK benchmark re-sent the stored plaintext code when
     * it was still valid. Aureon stores only a keyed digest, so the original
     * plaintext is unrecoverable — a resend therefore ALWAYS generates a
     * fresh code, which doubles as invalidation of the previous one.
     */
    public function resendCode(User $user): bool
    {
        return $this->generateAndSendCode($user);
    }

    /**
     * Verify a submitted code against the user's pending challenge.
     *
     * Evaluation order (every branch records an audit row):
     *  1. rate limit  — sliding window over counted failures (D4);
     *  2. liveness    — a code must exist and be unexpired;
     *  3. correctness — constant-time comparison of keyed digests.
     *
     * On success the pending digest/expiry are cleared and
     * `two_factor_confirmed_at` is stamped.
     */
    public function verifyCode(User $user, string $code): bool
    {
        if ($this->isRateLimited($user)) {
            $this->recordAttempt($user, $code, false, self::FAILURE_RATE_LIMITED);

            return false;
        }

        if ($user->two_factor_code_hash === null
            || $user->two_factor_code_expires_at === null
            || $user->two_factor_code_expires_at->isPast()) {
            $this->recordAttempt($user, $code, false, self::FAILURE_EXPIRED);

            return false;
        }

        // hash_equals gives a constant-time comparison; both sides are
        // fixed-length HMAC digests so no length information leaks either.
        if (! hash_equals($user->two_factor_code_hash, $this->hashCode($code))) {
            $this->recordAttempt($user, $code, false, self::FAILURE_INVALID_CODE);

            return false;
        }

        $user->forceFill([
            'two_factor_code_hash' => null,
            'two_factor_code_expires_at' => null,
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->recordAttempt($user, $code, true, null);

        return true;
    }

    /**
     * Whether the user has exhausted the failure budget for the current
     * sliding window.
     *
     * Deviation D4: only genuine failures (invalid_code / expired) count —
     * the CSK benchmark also counted its own `rate_limited` block events,
     * which meant every rejected retry extended the lockout forever.
     */
    public function isRateLimited(User $user): bool
    {
        return $this->countedFailures($user)->count()
            >= (int) config('two-factor.rate_limit.max_attempts', 5);
    }

    /**
     * Conservative estimate (in whole minutes, minimum 1) of when a
     * rate-limited user may retry: when the oldest counted failure ages out
     * of the sliding window. Used for the challenge screen's error message.
     */
    public function availableInMinutes(User $user): int
    {
        $oldest = $this->countedFailures($user)->oldest('created_at')->first();

        if ($oldest === null) {
            return 0;
        }

        $decay = (int) config('two-factor.rate_limit.decay_minutes', 15);

        return max(1, (int) ceil(now()->diffInSeconds($oldest->created_at->addMinutes($decay), false) / 60));
    }

    /**
     * Record the passed challenge for the current (already regenerated)
     * session.
     *
     * @internal FOUNDATION — the login gate does not consult these rows yet;
     * they are an audit trail and the storage substrate for the future
     * remember-device feature (see the TwoFactorSession model docblock).
     * MUST be called after `session()->regenerate()` so the captured id is
     * the live one — the CSK benchmark recorded the pre-regeneration id,
     * which never matched again (defect fixed here).
     */
    public function createVerifiedSession(User $user): void
    {
        $user->twoFactorSessions()->create([
            'session_id' => session()->getId(),
            'ip_address' => (string) request()->ip(),
            'user_agent' => request()->userAgent(),
            'verified_at' => now(),
            'expires_at' => now()->addHours((int) config('two-factor.session.ttl_hours', 24)),
        ]);
    }

    /**
     * Whether the current session already passed a challenge within its
     * validity horizon.
     *
     * @internal FOUNDATION — intentionally unused by the login gate: session
     * ids rotate on every login, so a session-id match can only succeed
     * within one authenticated session. The future remember-device feature
     * will match a device-token hash instead (see `.docs/auth-2fa/`).
     */
    public function hasValidSession(User $user): bool
    {
        return $user->twoFactorSessions()
            ->where('session_id', session()->getId())
            ->valid()
            ->exists();
    }

    /**
     * Hard-delete all verified-session rows for the user.
     *
     * Called when the user disables 2FA (and, in future, on password change
     * or "sign out other devices").
     *
     * @return int Number of rows deleted.
     */
    public function invalidateUserSessions(User $user): int
    {
        return $user->twoFactorSessions()->delete();
    }

    /**
     * Housekeeping: delete verified-session rows past their expiry.
     * Invoked by the scheduled `two-factor:prune` command.
     *
     * @return int Number of rows deleted.
     */
    public function cleanupExpiredSessions(): int
    {
        return TwoFactorSession::query()
            ->where('expires_at', '<=', now())
            ->delete();
    }

    /**
     * Housekeeping: delete audit attempts older than the configured
     * retention window. Invoked by the scheduled `two-factor:prune` command.
     *
     * @return int Number of rows deleted.
     */
    public function cleanupOldAttempts(): int
    {
        $retentionDays = (int) config('two-factor.cleanup.attempt_retention_days', 90);

        return TwoFactorAttempt::query()
            ->where('created_at', '<', now()->subDays($retentionDays))
            ->delete();
    }

    /**
     * Query for failures that count against the rate-limit budget:
     * failed attempts within the decay window, excluding `rate_limited`
     * block events (D4).
     *
     * @return HasMany<TwoFactorAttempt, User>
     */
    protected function countedFailures(User $user)
    {
        return $user->twoFactorAttempts()
            ->where('success', false)
            ->where('failure_reason', '!=', self::FAILURE_RATE_LIMITED)
            ->where('created_at', '>=', now()->subMinutes(
                (int) config('two-factor.rate_limit.decay_minutes', 15)
            ));
    }

    /**
     * Generate a cryptographically random, zero-padded numeric code of the
     * configured length.
     */
    protected function generateCode(): string
    {
        $length = (int) config('two-factor.code.length', 6);
        $max = (10 ** $length) - 1;

        return str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
    }

    /**
     * Keyed digest of a code for at-rest storage and comparison (D1).
     *
     * HMAC with APP_KEY rather than a bare hash: a 6-digit space is only
     * one million values, so an unkeyed digest would be trivially reversed
     * offline from a leaked database dump.
     */
    protected function hashCode(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    /**
     * Persist an audit row for a verification attempt.
     *
     * Stores only a SHA-256 digest of the submitted code. Gated by
     * `two-factor.log_attempts`; note the rate limiter reads this table, so
     * disabling logging also disables database-backed rate limiting.
     */
    protected function recordAttempt(?User $user, ?string $code, bool $success, ?string $failureReason): void
    {
        if (! config('two-factor.log_attempts', true)) {
            return;
        }

        TwoFactorAttempt::query()->create([
            'user_id' => $user?->id,
            'ip_address' => (string) request()->ip(),
            'attempted_code_hash' => $code !== null ? hash('sha256', $code) : null,
            'success' => $success,
            'failure_reason' => $failureReason,
            'created_at' => now(),
        ]);
    }

    /**
     * Dispatch the OTP notification, swallowing (but logging) transport
     * failures so a mail outage degrades to "code never arrived + resend"
     * rather than a hard 500 on the login flow.
     */
    protected function sendCode(User $user, string $code): bool
    {
        try {
            $user->notify(new TwoFactorCodeNotification(
                $code,
                (int) config('two-factor.code.expiry_minutes', 10)
            ));

            return true;
        } catch (Throwable $exception) {
            Log::error('Failed to dispatch two-factor authentication code notification.', [
                'user_id' => $user->id,
                'exception' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
