<?php

/** Define a valid ordered tour-destination assignment. */
declare(strict_types=1);

namespace App\Modules\TravelTours\Database\Factories;

use App\Modules\TravelTours\Catalog\Models\Destination;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Catalog\Models\TourDestinationAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TourDestinationAssignment> */
final class TourDestinationAssignmentFactory extends Factory
{
    protected $model = TourDestinationAssignment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['tour_id' => Tour::factory(), 'destination_id' => Destination::factory(), 'role' => 'visit', 'sequence' => 1];
    }
}
