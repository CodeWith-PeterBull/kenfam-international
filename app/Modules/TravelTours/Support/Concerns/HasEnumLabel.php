<?php

/**
 * Provides a narrowly shared TravelTours implementation concern.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Support\Concerns;

use Illuminate\Support\Str;

/** Supplies a predictable display label to string-backed domain enums. */
trait HasEnumLabel
{
    /** Convert the enum case into a stable human-readable interface label. */
    public function label(): string
    {
        return Str::headline($this->value);
    }
}
