<?php

namespace App\Models;

use App\Enums\SystemActivitySeverity;
use Database\Factories\SystemActivityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SystemActivity extends Model
{
    /** @use HasFactory<SystemActivityFactory> */
    use HasFactory;

    /**
     * Audit records are append-only and therefore have no update timestamp.
     */
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'batch_uuid',
        'user_id',
        'activity_type',
        'severity',
        'source',
        'description',
        'subject_type',
        'subject_id',
        'ip_address',
        'user_agent',
        'request_method',
        'route_name',
        'request_url',
        'properties',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'severity' => SystemActivitySeverity::class,
            'properties' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * User responsible for the activity, or null for a system process.
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Compatibility relation for modules that refer to the actor as a user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Domain model affected by this activity.
     */
    public function subject(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'subject_type', 'subject_id');
    }

    /**
     * Search the event name, description, request metadata, and actor identity.
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        $search = trim($search);

        if ($search === '') {
            return $query;
        }

        return $query->where(function (Builder $activityQuery) use ($search): void {
            $like = "%{$search}%";

            $activityQuery
                ->where('activity_type', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhere('ip_address', 'like', $like)
                ->orWhere('route_name', 'like', $like)
                ->orWhereHas('actor', function (Builder $actorQuery) use ($like): void {
                    $actorQuery
                        ->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
        });
    }

    public function scopeOfType(Builder $query, ?string $activityType): Builder
    {
        return $query->when(
            filled($activityType),
            fn (Builder $filteredQuery): Builder => $filteredQuery->where('activity_type', $activityType),
        );
    }

    public function scopeOfSeverity(Builder $query, ?string $severity): Builder
    {
        return $query->when(
            filled($severity),
            fn (Builder $filteredQuery): Builder => $filteredQuery->where('severity', $severity),
        );
    }

    public function scopeByActor(Builder $query, int|string|null $userId): Builder
    {
        return $query->when(
            filled($userId),
            fn (Builder $filteredQuery): Builder => $filteredQuery->where('user_id', $userId),
        );
    }

    public function scopeOccurredBetween(Builder $query, ?string $dateFrom, ?string $dateTo): Builder
    {
        return $query
            ->when(
                filled($dateFrom),
                fn (Builder $filteredQuery): Builder => $filteredQuery->whereDate('created_at', '>=', $dateFrom),
            )
            ->when(
                filled($dateTo),
                fn (Builder $filteredQuery): Builder => $filteredQuery->whereDate('created_at', '<=', $dateTo),
            );
    }
}
