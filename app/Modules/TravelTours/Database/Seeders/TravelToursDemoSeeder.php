<?php

/**
 * Seeds an opt-in, publication-ready TravelTours demonstration catalogue.
 *
 * The seeder is idempotent by stable codes, preserves unrelated adopter data,
 * and is deliberately excluded from the host DatabaseSeeder.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Database\Seeders;

use App\Modules\TravelTours\Bookings\Enums\BookingMode;
use App\Modules\TravelTours\Bookings\Enums\ParticipantType;
use App\Modules\TravelTours\Catalog\Enums\ContentItemType;
use App\Modules\TravelTours\Catalog\Enums\DestinationType;
use App\Modules\TravelTours\Catalog\Enums\PublicationStatus;
use App\Modules\TravelTours\Catalog\Enums\TourDifficulty;
use App\Modules\TravelTours\Catalog\Enums\TourType;
use App\Modules\TravelTours\Catalog\Models\Destination;
use App\Modules\TravelTours\Catalog\Models\ItineraryActivity;
use App\Modules\TravelTours\Catalog\Models\ItineraryDay;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Catalog\Models\TourCategory;
use App\Modules\TravelTours\Catalog\Models\TourCategoryAssignment;
use App\Modules\TravelTours\Catalog\Models\TourContentItem;
use App\Modules\TravelTours\Catalog\Models\TourDestinationAssignment;
use App\Modules\TravelTours\Catalog\Models\TourExtra;
use App\Modules\TravelTours\Catalog\Models\TourFaq;
use App\Modules\TravelTours\Pricing\Models\ParticipantRate;
use App\Modules\TravelTours\Pricing\Models\TourRatePlan;
use App\Modules\TravelTours\Scheduling\Enums\DepartureStatus;
use App\Modules\TravelTours\Scheduling\Models\TourDeparture;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Create realistic local data for storefront, accessibility, and browser QA. */
final class TravelToursDemoSeeder extends Seeder
{
    /** Reconcile operators and manifest-defined journeys without deleting data. */
    public function run(): void
    {
        if (! config('travel-tours.enabled', false)) {
            return;
        }

        $manifest = require dirname(__DIR__).'/Data/demo-catalog.php';
        $this->call(TravelToursDemoOperatorSeeder::class);

        DB::transaction(function () use ($manifest): void {
            foreach ($manifest as $position => $item) {
                $this->seedJourney($item, $position + 1);
            }
        }, 3);
    }

    /**
     * Reconcile one public journey graph from a reviewed manifest row.
     *
     * @param  array<string, mixed>  $item
     */
    private function seedJourney(array $item, int $position): void
    {
        [$categoryName, $categorySlug, $categoryIcon] = $item['category'];
        [$destinationName, $destinationSlug, $destinationCode, $countryCode, $timezone, $latitude, $longitude] = $item['destination'];

        $category = TourCategory::query()->updateOrCreate(
            ['slug' => $categorySlug],
            [
                'name' => $categoryName,
                'description' => 'Curated '.$categoryName.' for escorted, private, and group travel.',
                'icon_key' => $categoryIcon,
                'sort_order' => $position,
                'is_active' => true,
                'meta_title' => $categoryName.' tours',
                'meta_description' => 'Explore thoughtfully planned '.$categoryName.'.',
            ],
        );
        $destination = Destination::query()->updateOrCreate(
            ['slug' => $destinationSlug],
            [
                'type' => DestinationType::Region,
                'country_code' => $countryCode,
                'code' => $destinationCode,
                'name' => $destinationName,
                'short_description' => 'Explore '.$destinationName.' through considered routes and locally informed experiences.',
                'description' => 'A destination profile prepared for TravelTours discovery and itinerary planning.',
                'latitude' => $latitude,
                'longitude' => $longitude,
                'timezone' => $timezone,
                'is_featured' => (bool) ($item['featured'] ?? false),
                'is_active' => true,
                'status' => PublicationStatus::Published,
                'sort_order' => $position,
                'meta_title' => $destinationName.' tours and journeys',
                'meta_description' => 'Discover escorted and custom journeys in '.$destinationName.'.',
                'published_at' => now()->subDay(),
            ],
        );
        $tour = Tour::query()->updateOrCreate(
            ['code' => $item['code']],
            [
                'slug' => $item['slug'],
                'type' => TourType::from($item['type']),
                'status' => PublicationStatus::Published,
                'name' => $item['name'],
                'tagline' => $item['tagline'],
                'short_description' => $item['summary'],
                'description' => $item['description'],
                'duration_days' => $item['days'],
                'duration_nights' => $item['nights'],
                'minimum_age' => 5,
                'difficulty' => TourDifficulty::from($item['difficulty']),
                'minimum_participants' => 1,
                'maximum_participants' => $item['capacity'],
                'languages' => ['English'],
                'booking_mode' => BookingMode::Approval,
                'meeting_point_name' => 'Confirmed airport or group meeting point',
                'meeting_point_details' => 'Final meeting instructions are supplied with confirmed travel documents.',
                'end_point_name' => 'Confirmed return transfer point',
                'terms' => 'Demonstration terms only. Final inclusions, payment schedule, cancellation policy, and travel requirements must be confirmed before booking.',
                'cancellation_summary' => 'Cancellation terms depend on the confirmed departure and supplier commitments.',
                'policy_version' => 'demo-2026-09',
                'is_featured' => (bool) ($item['featured'] ?? false),
                'sort_order' => $position,
                'meta_title' => $item['name'].' | Escorted travel',
                'meta_description' => $item['summary'],
                'published_at' => now()->subDay(),
            ],
        );

        TourCategoryAssignment::query()->updateOrCreate(
            ['tour_id' => $tour->id, 'category_id' => $category->id],
            ['is_primary' => true, 'sort_order' => 1],
        );
        TourDestinationAssignment::query()->updateOrCreate(
            ['tour_id' => $tour->id, 'sequence' => 1],
            ['destination_id' => $destination->id, 'role' => 'primary', 'is_overnight' => true],
        );

        $this->seedTourContent($tour, $destination, $item);
        $ratePlan = $this->seedPricing($tour, $item);
        $this->seedDepartures($tour, $ratePlan, $item, $position, $timezone);
        $this->attachImage($tour, 'tour_cover', $item['image']);
        $this->attachImage($destination, 'destination_cover', $item['image']);
    }

    /**
     * Reconcile itinerary, structured content, FAQs, and one optional service.
     *
     * @param  array<string, mixed>  $item
     */
    private function seedTourContent(Tour $tour, Destination $destination, array $item): void
    {
        foreach ($item['itinerary'] as $index => [$title, $description]) {
            $day = ItineraryDay::query()->updateOrCreate(
                ['tour_id' => $tour->id, 'day_number' => $index + 1],
                [
                    'start_destination_id' => $destination->id,
                    'end_destination_id' => $destination->id,
                    'title' => $title,
                    'description' => $description,
                    'meals' => $index === 0 ? ['Dinner'] : ['Breakfast'],
                    'accommodation' => $destination->name.' selected accommodation',
                    'sort_order' => $index + 1,
                ],
            );
            ItineraryActivity::query()->updateOrCreate(
                ['itinerary_day_id' => $day->id, 'sequence' => 1],
                [
                    'destination_id' => $destination->id,
                    'title' => $title,
                    'description' => $description,
                    'location_name' => $destination->name,
                    'is_included' => true,
                    'is_optional' => false,
                ],
            );
        }

        $content = [
            ContentItemType::Highlight->value => $item['highlights'],
            ContentItemType::Inclusion->value => ['Selected accommodation', 'Daily breakfast and listed meals', 'Scheduled transfers and guided activities'],
            ContentItemType::Exclusion->value => ['International airfare unless stated', 'Travel insurance and personal expenses'],
            ContentItemType::Requirement->value => ['A valid passport and destination-specific entry documents', 'Travel insurance appropriate to the confirmed journey'],
        ];
        foreach ($content as $type => $lines) {
            foreach ($lines as $index => $line) {
                TourContentItem::query()->updateOrCreate(
                    ['tour_id' => $tour->id, 'type' => $type, 'sort_order' => $index + 1],
                    ['content' => $line],
                );
            }
        }

        $faqs = [
            'What is the booking process?' => 'Submit an inquiry for the departure you prefer. The travel desk will confirm availability, inclusions, and the applicable payment schedule.',
            'Can this journey be arranged privately?' => 'Yes. Dates, pacing, accommodation, and inclusions can be reviewed for a private or closed-group itinerary.',
        ];
        foreach ($faqs as $question => $answer) {
            $order = array_search($question, array_keys($faqs), true) + 1;
            TourFaq::query()->updateOrCreate(
                ['tour_id' => $tour->id, 'sort_order' => $order],
                ['question' => $question, 'answer' => $answer, 'is_active' => true],
            );
        }

        TourExtra::query()->updateOrCreate(
            ['tour_id' => $tour->id, 'code' => 'PRIVATE-TRANSFER'],
            [
                'name' => 'Private airport transfer',
                'description' => 'A dedicated transfer where the standard group transfer does not apply.',
                'pricing_unit' => 'per_booking',
                'amount_minor' => 1250000,
                'currency' => 'KES',
                'participant_types' => [ParticipantType::Adult->value, ParticipantType::Child->value],
                'minimum_quantity' => 0,
                'maximum_quantity' => 2,
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 1,
            ],
        );
    }

    /**
     * Reconcile a public KES plan and a complete participant rate set.
     *
     * @param  array<string, mixed>  $item
     */
    private function seedPricing(Tour $tour, array $item): TourRatePlan
    {
        $plan = TourRatePlan::query()->updateOrCreate(
            ['tour_id' => $tour->id, 'code' => 'DEMO-STANDARD'],
            [
                'name' => 'Standard escorted rate',
                'description' => 'Public demonstration rate per traveler, subject to final departure confirmation.',
                'currency' => 'KES',
                'tax_inclusive' => true,
                'tax_rate_basis_points' => 0,
                'deposit_type' => 'percentage',
                'deposit_value' => 3000,
                'balance_due_days' => 45,
                'is_refundable' => true,
                'booking_restrictions' => ['demonstration' => true],
                'is_active' => true,
                'is_public' => true,
                'is_default' => true,
                'minimum_participants' => 1,
                'maximum_participants' => $item['capacity'],
                'display_order' => 1,
            ],
        );
        foreach ([
            ParticipantType::Adult->value => [18, null, $item['adult_minor']],
            ParticipantType::Child->value => [2, 17, $item['child_minor']],
            ParticipantType::Infant->value => [0, 1, 0],
        ] as $type => [$minimumAge, $maximumAge, $amount]) {
            ParticipantRate::query()->updateOrCreate(
                ['rate_plan_id' => $plan->id, 'participant_type' => $type],
                ['minimum_age' => $minimumAge, 'maximum_age' => $maximumAge, 'amount_minor' => $amount, 'tax_inclusive' => true, 'is_active' => true],
            );
        }

        return $plan;
    }

    /**
     * Reconcile two safely future-dated departures for a journey.
     *
     * @param  array<string, mixed>  $item
     */
    private function seedDepartures(Tour $tour, TourRatePlan $plan, array $item, int $position, string $timezone): void
    {
        foreach ([4, 8] as $sequence => $monthsAhead) {
            $start = CarbonImmutable::now('UTC')->addMonths($monthsAhead)->addDays($position)->setTime(5, 0);
            TourDeparture::query()->updateOrCreate(
                ['code' => $item['code'].'-'.($sequence + 1)],
                [
                    'tour_id' => $tour->id,
                    'rate_plan_id' => $plan->id,
                    'timezone' => $timezone,
                    'starts_at' => $start,
                    'ends_at' => $start->addDays($item['days'] - 1),
                    'booking_mode' => BookingMode::Approval,
                    'booking_opens_at' => now()->subDay(),
                    'booking_closes_at' => $start->subDays(21),
                    'capacity' => $item['capacity'],
                    'minimum_participants' => 4,
                    'waitlist_enabled' => true,
                    'status' => $sequence === 0 ? DepartureStatus::Guaranteed : DepartureStatus::Open,
                    'meeting_instructions' => 'Final reporting time and meeting point are issued with confirmed travel documents.',
                    'operational_notes' => 'Fictional demonstration departure for local evaluation only.',
                ],
            );
        }
    }

    /** Attach one prepared local image while preserving the source fixture. */
    private function attachImage(Tour|Destination $model, string $collection, string $filename): void
    {
        if ($model->getFirstMedia($collection) !== null) {
            return;
        }

        $path = dirname(__DIR__, 2).'/Resources/demo/travel/'.$filename;
        if (! is_file($path)) {
            throw new RuntimeException('Missing TravelTours demonstration image: '.$path);
        }

        $model->addMedia($path)->preservingOriginal()->toMediaCollection($collection, 'public');
    }
}
