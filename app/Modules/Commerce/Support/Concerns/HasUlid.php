<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Support\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

/**
 * Gives a Commerce aggregate an immutable, externally safe route identifier.
 *
 * Integer model keys remain canonical for persistence and relationships. The
 * ULID is generated before insertion, used for implicit route binding, and may
 * not be changed after the model exists.
 */
trait HasUlid
{
    /**
     * Register ULID generation and immutability guards for the owning model.
     *
     * @throws InvalidArgumentException when a controlled explicit ULID is invalid
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
                throw new LogicException('Commerce ULID identifiers are immutable.');
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
