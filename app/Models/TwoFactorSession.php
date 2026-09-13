<?php

namespace App\Models;

use App\Services\TwoFactorService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A verified two-factor challenge record — FOUNDATION model.
 *
 * One row is created by {@see TwoFactorService::createVerifiedSession()}
 * each time a user passes the email-OTP challenge. The login gate does NOT
 * consult these rows yet: every login is challenged while 2FA is active, and
 * the rows serve as an audit trail plus the storage foundation for a future
 * "remember this device" feature.
 *
 * Future extension design (do not build ad hoc — see `.docs/auth-2fa/`):
 *  - add a `device_token_hash` column (hash of a `Str::random(60)` token);
 *  - on successful verification, issue the plaintext token in an encrypted,
 *    HTTP-only, long-lived cookie scoped to the auth domain;
 *  - in the login gate, match the presented cookie token's hash against
 *    unexpired rows instead of `session_id` (session ids rotate on every
 *    login, which is exactly why the CSK benchmark's session-id skip never
 *    matched — the defect this design replaces);
 *  - invalidate rows on password change, 2FA disable, and explicit
 *    "sign out other devices" actions.
 */
class TwoFactorSession extends Model
{
    /**
     * Mass-assignable attributes; rows are only written by TwoFactorService.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'session_id',
        'ip_address',
        'user_agent',
        'verified_at',
        'expires_at',
    ];

    /**
     * Attribute casting for the temporal columns.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * The user who passed the challenge.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to rows that are still within their validity horizon.
     *
     * Used by the foundation gate helper
     * {@see TwoFactorService::hasValidSession()} and by the
     * future remember-device lookup.
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }
}
