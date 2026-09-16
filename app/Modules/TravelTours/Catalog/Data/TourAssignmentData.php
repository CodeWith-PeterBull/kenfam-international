<?php

/**
 * Defines immutable category and destination assignments for one tour.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Data;

/** Carry validated relational assignments without accepting raw pivot state. */
final readonly class TourAssignmentData
{
    /**
     * @param  list<array{category_id: int, is_primary: bool, sort_order: int}>  $categories
     * @param  list<array{destination_id: int, role: string, sequence: int, is_overnight: bool}>  $destinations
     */
    public function __construct(
        public array $categories,
        public array $destinations,
    ) {}
}
