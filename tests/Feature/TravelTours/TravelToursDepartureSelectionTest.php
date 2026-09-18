<?php

/**
 * Verifies public departure availability, participant selection, and live quoting.
 */

declare(strict_types=1);

namespace Tests\Feature\TravelTours;

use App\Enums\UserType;
use App\Models\User;
use App\Modules\TravelTours\Bookings\Enums\BookingStatus;
use App\Modules\TravelTours\Bookings\Enums\ParticipantType;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\Catalog\Enums\PublicationStatus;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Database\Seeders\TravelToursAccessSeeder;
use App\Modules\TravelTours\Pricing\Enums\AdjustmentType;
use App\Modules\TravelTours\Pricing\Models\ParticipantRate;
use App\Modules\TravelTours\Pricing\Models\Promotion;
use App\Modules\TravelTours\Pricing\Models\TourRatePlan;
use App\Modules\TravelTours\Scheduling\Enums\DepartureStatus;
use App\Modules\TravelTours\Scheduling\Models\TourDeparture;
use App\Modules\TravelTours\Storefront\Livewire\DepartureSelector;
use App\Modules\TravelTours\Support\TravelToursRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Prove that the selector lists only what can be booked, prices only from
 * persisted rates, and explains every state in which no quote exists.
 */
final class TravelToursDepartureSelectionTest extends TestCase
{
    use RefreshDatabase;

    /** Enable the module with deterministic limits. */
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('q', 32)),
            'travel-tours.enabled' => true,
            'travel-tours.booking.maximum_participants' => 8,
        ]);
    }

    /** The public page lists bookable departures with live seats and hides the rest. */
    public function test_tour_page_lists_only_bookable_departures_with_live_seats(): void
    {
        [$tour, $open] = $this->tourWithDeparture();
        $closed = TourDeparture::factory()->create(['tour_id' => $tour->getKey(), 'code' => 'DEP-CLOSED', 'status' => DepartureStatus::Closed]);
        $past = TourDeparture::factory()->create([
            'tour_id' => $tour->getKey(), 'code' => 'DEP-PAST',
            'starts_at' => now()->subMonth(), 'ends_at' => now()->subMonth()->addDays(4), 'booking_closes_at' => now()->subMonths(2),
        ]);
        TourBooking::factory()->create(['departure_id' => $open->getKey(), 'status' => BookingStatus::Confirmed, 'seat_count' => 3]);

        $this->withoutVite()->get(route('travel-tours.storefront.tours.show', $tour->slug))
            ->assertOk()
            ->assertSee($open->code)
            ->assertSee('7 places available')
            ->assertSee('From')
            ->assertSee('KES 10,000.00')
            ->assertDontSee($closed->code)
            ->assertDontSee($past->code);
    }

    /** The quote follows the participant mix and is always priced from persisted rates. */
    public function test_quote_recalculates_when_the_participant_mix_changes(): void
    {
        [$tour, $departure] = $this->tourWithDeparture();

        Livewire::test(DepartureSelector::class, ['tour' => $tour])
            ->assertSet('departureId', $departure->getKey())
            ->assertSee('Total for 1 traveller')
            ->assertSee('KES 10,000.00')
            ->set('form.adults', '2')
            ->set('form.children', '1')
            ->set('form.infants', '1')
            ->assertHasNoErrors()
            ->assertSee('Total for 4 travellers')
            ->assertSee('KES 25,000.00')
            ->assertSee('Secure your places with a deposit of')
            ->assertSee('KES 7,500.00');
    }

    /** Participant limits are validated per field and as a total. */
    public function test_participant_mix_is_validated_before_any_quote(): void
    {
        [$tour] = $this->tourWithDeparture();

        $component = Livewire::test(DepartureSelector::class, ['tour' => $tour])
            ->set('form.adults', '0')
            ->assertHasErrors(['form.adults'])
            ->assertSee('At least one adult travels on every booking.')
            ->assertDontSee('Total for');

        $component->set('form.adults', '5')->set('form.children', '4')
            ->assertHasErrors(['form.adults'])
            ->assertSee('A booking cannot exceed 8 participants in total.');
    }

    /** Sold-out departures stay visible, cannot be chosen, and the quote explains a short fall. */
    public function test_sold_out_departures_are_listed_but_not_selectable(): void
    {
        [$tour, $departure] = $this->tourWithDeparture(capacity: 2);
        TourBooking::factory()->create(['departure_id' => $departure->getKey(), 'status' => BookingStatus::Pending, 'seat_count' => 1]);

        $component = Livewire::test(DepartureSelector::class, ['tour' => $tour])
            ->assertSet('departureId', $departure->getKey())
            ->assertSee('Only 1 place left')
            ->set('form.adults', '2')
            ->assertSee('Only 1 place remains on this departure.')
            ->assertDontSee('Total for');

        TourBooking::factory()->create(['departure_id' => $departure->getKey(), 'status' => BookingStatus::Confirmed, 'seat_count' => 1]);

        $component->set('form.adults', '1')
            ->assertSee('Fully booked')
            ->assertSee('This departure is fully booked.')
            ->assertSeeHtml('disabled');

        Livewire::test(DepartureSelector::class, ['tour' => $tour])
            ->assertSet('departureId', null)
            ->assertSee('Every listed departure is fully booked.');
    }

    /** Promotion codes are validated by the calculator and reported on the field. */
    public function test_promotion_codes_are_applied_or_refused_inline(): void
    {
        [$tour] = $this->tourWithDeparture();
        Promotion::factory()->create([
            'code' => 'SAVE10', 'name' => 'Ten percent off', 'adjustment_type' => AdjustmentType::Percentage,
            'adjustment_value' => 10_00, 'applies_to_all_tours' => true, 'maximum_uses' => null,
        ]);

        $component = Livewire::test(DepartureSelector::class, ['tour' => $tour])
            ->set('form.promotionCode', 'NOPE')
            ->assertHasErrors(['form.promotionCode'])
            ->assertSee('The supplied promotion is not applicable to this quote.')
            ->assertDontSee('Total for');

        $component->set('form.promotionCode', 'save10')
            ->assertHasNoErrors()
            ->assertSee('Ten percent off')
            ->assertSee('KES 1,000.00')
            ->assertSee('KES 9,000.00');
    }

    /** A departure without a public rate plan is listed but priced on request. */
    public function test_departure_without_a_public_rate_plan_is_priced_on_request(): void
    {
        $tour = Tour::factory()->create(['status' => PublicationStatus::Published, 'published_at' => now()->subDay()]);
        $private = TourRatePlan::factory()->create(['tour_id' => $tour->getKey(), 'is_public' => false]);
        ParticipantRate::factory()->create(['rate_plan_id' => $private->getKey(), 'amount_minor' => 10_000_00]);
        $departure = TourDeparture::factory()->create(['tour_id' => $tour->getKey(), 'rate_plan_id' => $private->getKey()]);

        Livewire::test(DepartureSelector::class, ['tour' => $tour])
            ->assertSet('departureId', $departure->getKey())
            ->assertSee('Price on request')
            ->assertSee('Pricing for this departure is available on request.')
            ->assertDontSee('KES 10,000.00');
    }

    /** Visitors never reach an unpublished tour's selector; catalog viewers may preview it. */
    public function test_unpublished_tours_are_hidden_from_visitors_but_previewable_by_staff(): void
    {
        $this->seed(TravelToursAccessSeeder::class);
        [$tour, $departure] = $this->tourWithDeparture(published: false);

        Livewire::test(DepartureSelector::class, ['tour' => $tour])->assertNotFound();

        $editor = User::factory()->create(['user_type' => UserType::Viewer, 'is_active' => true]);
        $editor->assignRole(TravelToursRole::TOUR_EDITOR);
        Livewire::actingAs($editor->fresh())
            ->test(DepartureSelector::class, ['tour' => $tour])
            ->assertSet('departureId', $departure->getKey())
            ->assertSee('Total for 1 traveller');
    }

    /**
     * Create a published tour with one public plan (adult 10,000 / child 5,000 / infant 0) and one open departure.
     *
     * @return array{Tour, TourDeparture}
     */
    private function tourWithDeparture(int $capacity = 10, bool $published = true): array
    {
        $tour = Tour::factory()->create($published
            ? ['status' => PublicationStatus::Published, 'published_at' => now()->subDay()]
            : ['status' => PublicationStatus::Draft]);
        $plan = TourRatePlan::factory()->create([
            'tour_id' => $tour->getKey(), 'is_default' => true, 'currency' => 'KES', 'tax_rate_basis_points' => 0,
            'deposit_type' => 'percentage', 'deposit_value' => 30_00, 'minimum_participants' => 1, 'maximum_participants' => 10,
        ]);
        foreach ([[ParticipantType::Adult, 18, null, 10_000_00], [ParticipantType::Child, 2, 17, 5_000_00], [ParticipantType::Infant, 0, 1, 0]] as [$type, $minimum, $maximum, $amount]) {
            ParticipantRate::factory()->create([
                'rate_plan_id' => $plan->getKey(), 'participant_type' => $type,
                'minimum_age' => $minimum, 'maximum_age' => $maximum, 'amount_minor' => $amount,
            ]);
        }
        $departure = TourDeparture::factory()->create(['tour_id' => $tour->getKey(), 'rate_plan_id' => $plan->getKey(), 'capacity' => $capacity]);

        return [$tour, $departure];
    }
}
