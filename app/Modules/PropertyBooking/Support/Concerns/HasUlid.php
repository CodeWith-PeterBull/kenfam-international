<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Support\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

/**
 * Gives a Property Booking aggregate an immutable public route identifier.
 *
 * Internal integer keys remain canonical for joins and locking. ULIDs are
 * generated before insertion and are safe to expose in routes and documents.
 */
trait HasUlid
{
    /**
     * Register ULID generation, normalization, and immutability guards.
     *
     * @throws InvalidArgumentException when an explicitly supplied ULID is invalid
     * @throws LogicException when persisted route identity is changed
     */
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
                throw new InvalidArgumentException('The supplied ULID is not valid.');
            }

            $model->setAttribute('ulid', $ulid);
        });

        static::updating(static function (Model $model): void {
            if ($model->isDirty('ulid')) {
                throw new LogicException('Property Booking ULID identifiers are immutable.');
            }
        });
    }

    /**
     * Resolve implicit route binding through the immutable ULID column.
     */
    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
