<?php

/**
 * Resolves model factories from the module-owned factory namespace.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Support\Concerns;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/** Provide consistent factory discovery for models and custom pivot models. */
trait HasTravelToursFactory
{
    use HasFactory;

    /**
     * Resolve the colocated module factory by model class name.
     *
     * @return Factory<static>|null
     */
    protected static function newFactory(): ?Factory
    {
        $factory = 'App\\Modules\\TravelTours\\Database\\Factories\\'.class_basename(static::class).'Factory';

        return class_exists($factory) ? $factory::new() : null;
    }
}
