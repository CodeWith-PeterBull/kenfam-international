<?php

/** Define an active, expiring departure-capacity hold. */
declare(strict_types=1);

namespace App\Modules\TravelTours\Database\Factories;

use App\Modules\TravelTours\Scheduling\Enums\HoldStatus;
use App\Modules\TravelTours\Scheduling\Models\AvailabilityHold;
use App\Modules\TravelTours\Scheduling\Models\TourDeparture;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AvailabilityHold> */
final class AvailabilityHoldFactory extends Factory
{
    protected $model = AvailabilityHold::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $quote = ['currency' => 'KES', 'total_minor' => 125_000_00, 'lines' => []];

        return [
            'departure_id' => TourDeparture::factory(),
            'operation_key' => 'hold-'.fake()->unique()->uuid(),
            'owner_token_hash' => hash('sha256', fake()->uuid()),
            'adult_count' => 1,
            'seat_count' => 1,
            'currency' => 'KES',
            'quoted_total_minor' => 125_000_00,
            'quote_fingerprint' => hash('sha256', json_encode($quote, JSON_THROW_ON_ERROR)),
            'quote_snapshot' => $quote,
            'status' => HoldStatus::Active,
            'expires_at' => now()->addMinutes(20),
        ];
    }
}
