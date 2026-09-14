<?php

/** Define an idempotent consent-aware public tour inquiry. */
declare(strict_types=1);

namespace App\Modules\TravelTours\Database\Factories;

use App\Modules\TravelTours\Inquiries\Enums\InquiryStatus;
use App\Modules\TravelTours\Inquiries\Enums\InquiryType;
use App\Modules\TravelTours\Inquiries\Models\TourInquiry;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TourInquiry> */
final class TourInquiryFactory extends Factory
{
    protected $model = TourInquiry::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'reference' => 'INQ-'.fake()->unique()->numerify('########'),
            'operation_key' => 'inquiry-'.fake()->unique()->uuid(),
            'inquiry_type' => InquiryType::General,
            'contact_name' => fake()->name(),
            'contact_email' => fake()->safeEmail(),
            'contact_phone' => fake()->e164PhoneNumber(),
            'whatsapp_preferred' => false,
            'requested_destinations' => ['Nairobi'],
            'adult_count' => 2,
            'message' => fake()->paragraph(),
            'source' => 'website',
            'status' => InquiryStatus::New,
            'consent_recorded_at' => now(),
            'consent_purpose' => 'Respond to this travel inquiry',
            'consent_version' => 'fixture-v1',
        ];
    }
}
