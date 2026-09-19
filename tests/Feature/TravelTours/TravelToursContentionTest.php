<?php

/**
 * Verifies that capacity is never oversold when holds and placements compete for the last seats.
 */

declare(strict_types=1);

namespace Tests\Feature\TravelTours;

use App\Modules\TravelTours\Bookings\Enums\ParticipantType;
use App\Modules\TravelTours\Bookings\Exceptions\AvailabilityException;
use App\Modules\TravelTours\Catalog\Enums\PublicationStatus;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Contracts\CalculatesTourQuotes;
use App\Modules\TravelTours\Pricing\Data\ParticipantMix;
use App\Modules\TravelTours\Pricing\Data\TourQuoteRequest;
use App\Modules\TravelTours\Pricing\Models\ParticipantRate;
use App\Modules\TravelTours\Pricing\Models\TourRatePlan;
use App\Modules\TravelTours\Scheduling\Data\AvailabilityHoldRequest;
use App\Modules\TravelTours\Scheduling\Enums\HoldStatus;
use App\Modules\TravelTours\Scheduling\Models\AvailabilityHold;
use App\Modules\TravelTours\Scheduling\Models\TourDeparture;
use App\Modules\TravelTours\Scheduling\Services\AvailabilityHoldService;
use App\Modules\TravelTours\Scheduling\Services\DepartureAvailabilityService;
use App\Modules\TravelTours\Storefront\Livewire\DepartureSelector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Transaction-level contention: the hold service serialises on the departure
 * row, so the second request for the last seat is refused, a released or
 * expired hold frees the seat again, and the sweep never touches live holds.
 * A multi-process MariaDB probe is recorded as deferred in the delivery plan
 * because no MariaDB server is available in this workstation.
 */
final class TravelToursContentionTest extends TestCase
{
    use RefreshDatabase;

    /** Enable the module with a deterministic key. */
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('x', 32)), 'travel-tours.enabled' => true, 'travel-tours.booking.hold_minutes' => 15]);
    }

    /** Two owners racing for the last seat: the first holds it, the second is refused until it is freed. */
    public function test_the_last_seat_is_held_once(): void
    {
        [$tour, $departure, $plan] = $this->tourWithDeparture(capacity: 1);
        $holds = app(AvailabilityHoldService::class);
        $quote = app(CalculatesTourQuotes::class)->calculate(new TourQuoteRequest($departure->getKey(), $plan->getKey(), new ParticipantMix(1)));

        $first = $holds->create(new AvailabilityHoldRequest('race-a', $quote, 'owner-a'));

        try {
            $holds->create(new AvailabilityHoldRequest('race-b', $quote, 'owner-b'));
            $this->fail('The second owner must not receive the same seat.');
        } catch (AvailabilityException $exception) {
            $this->assertSame('The requested seats are no longer available.', $exception->getMessage());
        }
        $this->assertSame(1, AvailabilityHold::query()->count());
        $this->assertSame(0, app(DepartureAvailabilityService::class)->check($departure)->availableSeats);

        $holds->release($first, 'owner-a', null);
        $second = $holds->create(new AvailabilityHoldRequest('race-b', $quote, 'owner-b'));

        $this->assertSame(HoldStatus::Active, $second->status);
        $this->assertSame(HoldStatus::Released, $first->fresh()->status);
    }

    /** Through the storefront: the second visitor sees the refusal on Continue and the seat count stays honest. */
    public function test_two_visitors_cannot_both_continue_with_the_last_seat(): void
    {
        [$tour, $departure] = $this->tourWithDeparture(capacity: 1);

        Livewire::test(DepartureSelector::class, ['tour' => $tour])->call('continue')->assertHasNoErrors();
        $this->flushSession();

        Livewire::test(DepartureSelector::class, ['tour' => $tour])
            ->assertSet('departureId', null)
            ->assertSee('Every listed departure is fully booked.')
            ->call('continue')
            ->assertHasErrors(['selection']);

        $this->assertSame(1, AvailabilityHold::query()->count());
        $this->assertSame(0, app(DepartureAvailabilityService::class)->check($departure)->availableSeats);
    }

    /** The expiry sweep frees only stale holds; a live hold keeps its seat through the sweep. */
    public function test_expiry_sweep_frees_stale_holds_only(): void
    {
        [$tour, $departure, $plan] = $this->tourWithDeparture(capacity: 2);
        $holds = app(AvailabilityHoldService::class);
        $quote = app(CalculatesTourQuotes::class)->calculate(new TourQuoteRequest($departure->getKey(), $plan->getKey(), new ParticipantMix(1)));
        $stale = $holds->create(new AvailabilityHoldRequest('stale', $quote, 'owner-a'));
        $live = $holds->create(new AvailabilityHoldRequest('live', $quote, 'owner-b'));
        $stale->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->assertSame(1, $holds->releaseExpired());
        $this->assertSame(HoldStatus::Expired, $stale->fresh()->status);
        $this->assertSame(HoldStatus::Active, $live->fresh()->status);
        $this->assertSame(1, app(DepartureAvailabilityService::class)->check($departure)->availableSeats);
        $this->assertSame(0, $holds->releaseExpired(), 'A second sweep finds nothing.');
    }

    /**
     * Create a published tour with one adult fare and one open departure.
     *
     * @return array{Tour, TourDeparture, TourRatePlan}
     */
    private function tourWithDeparture(int $capacity): array
    {
        $tour = Tour::factory()->create(['status' => PublicationStatus::Published, 'published_at' => now()->subDay(), 'policy_version' => '2026-09']);
        $plan = TourRatePlan::factory()->create(['tour_id' => $tour->getKey(), 'is_default' => true, 'currency' => 'KES', 'tax_rate_basis_points' => 0, 'deposit_value' => 30_00, 'minimum_participants' => 1, 'maximum_participants' => 10]);
        ParticipantRate::factory()->create(['rate_plan_id' => $plan->getKey(), 'participant_type' => ParticipantType::Adult, 'minimum_age' => 18, 'amount_minor' => 10_000_00]);
        $departure = TourDeparture::factory()->create(['tour_id' => $tour->getKey(), 'rate_plan_id' => $plan->getKey(), 'capacity' => $capacity]);

        return [$tour, $departure, $plan];
    }
}
