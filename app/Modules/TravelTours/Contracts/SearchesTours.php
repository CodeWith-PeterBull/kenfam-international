<?php

/**
 * Defines an injectable TravelTours application-service boundary.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Public tour discovery boundary. */
interface SearchesTours
{
    /** @param array<string, mixed> $filters */
    public function search(array $filters = [], int $perPage = 12): LengthAwarePaginator;
}
