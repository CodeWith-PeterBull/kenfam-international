<?php

/**
 * Provides a narrowly shared TravelTours implementation concern.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Support\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

/** Supplies immutable public ULIDs while retaining integer relational keys. */
trait HasUlid
{
    /** Register generation, normalization, validation, and immutability guards. */
    public static function bootHasUlid(): void
    {
        static::creating(static function (Model $model): void {
            $ulid = $model->getAttribute('ulid');
            if (blank($ulid)) {
                $model->setAttribute('ulid', (string) Str::ulid());

                return;
            }

            $ulid = strtoupper(trim((string) $ulid));
            if (! Str::isUlid($ulid)) {
                throw new InvalidArgumentException('The supplied Travel & Tours ULID is invalid.');
            }
            $model->setAttribute('ulid', $ulid);
        });

        static::updating(static function (Model $model): void {
            if ($model->isDirty('ulid')) {
                throw new LogicException('Travel & Tours ULIDs are immutable.');
            }
        });
    }

    /** Bind public routes through the immutable ULID. */
    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
