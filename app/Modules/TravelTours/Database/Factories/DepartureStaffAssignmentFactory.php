<?php

/** Define an operational staff assignment for one departure. */
declare(strict_types=1);

namespace App\Modules\TravelTours\Database\Factories;

use App\Models\User;
use App\Modules\TravelTours\Scheduling\Models\DepartureStaffAssignment;
use App\Modules\TravelTours\Scheduling\Models\TourDeparture;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DepartureStaffAssignment> */
final class DepartureStaffAssignmentFactory extends Factory
{
    protected $model = DepartureStaffAssignment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['departure_id' => TourDeparture::factory(), 'user_id' => User::factory(), 'role' => 'tour_lead', 'is_lead' => true];
    }
}
