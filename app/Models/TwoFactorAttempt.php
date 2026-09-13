<?php

namespace App\Models;

use App\Services\TwoFactorService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An immutable two-factor verification attempt audit record.
 *
 * Written by {@see TwoFactorService::recordAttempt()} for every
 * verification attempt (success and failure) while `two-factor.log_attempts`
 * is on. Doubles as the data source for the database-backed rate limiter:
 * {@see TwoFactorService::isRateLimited()} counts recent failed
 * rows whose `failure_reason` is not `rate_limited`, so block events never
 * extend the lockout window they report on.
 *
 * Rows carry only a SHA-256 digest of the submitted code — never plaintext —
 * and are pruned after `two-factor.cleanup.attempt_retention_days` by the
 * scheduled `two-factor:prune` command.
 */
class TwoFactorAttempt extends Model
{
    /**
     * Audit rows are immutable: only created_at exists, managed manually.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Mass-assignable attributes; rows are only written by TwoFactorService.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'ip_address',
        'attempted_code_hash',
        'success',
        'failure_reason',
        'created_at',
    ];

    /**
     * Attribute casting.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /**
     * The user this attempt was recorded against (null when unresolvable).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to failed attempts.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('success', false);
    }

    /**
     * Scope to attempts recorded within the last N minutes.
     */
    public function scopeRecent(Builder $query, int $minutes): Builder
    {
        return $query->where('created_at', '>=', now()->subMinutes($minutes));
    }
}
