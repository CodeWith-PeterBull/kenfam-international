<?php

/**
 * Verifies the hold-to-booking journey: Continue, checkout ownership, placement, and expiry sweeps.
 */

declare(strict_types=1);

namespace Tests\Feature\TravelTours;

use App\Modules\TravelTours\Bookings\Enums\BookingChannel;
use App\Modules\TravelTours\Bookings\Enums\BookingStatus;
use App\Modules\TravelTours\Bookings\Enums\ParticipantType;
use App\Modules\TravelTours\Bookings\Enums\PaymentMethod;
use App\Modules\TravelTours\Bookings\Models\BookingStatusHistory;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\Bookings\Services\BookingLifecycleService;
use App\Modules\TravelTours\Catalog\Enums\PublicationStatus;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Pricing\Enums\AdjustmentType;
use App\Modules\TravelTours\Pricing\Models\ParticipantRate;
use App\Modules\TravelTours\Pricing\Models\Promotion;
use App\Modules\TravelTours\Pricing\Models\TourRatePlan;
use App\Modules\TravelTours\Scheduling\Enums\HoldStatus;
use App\Modules\TravelTours\Scheduling\Models\AvailabilityHold;
use App\Modules\TravelTours\Scheduling\Models\TourDeparture;
use App\Modules\TravelTours\Storefront\Livewire\BookingCheckout;
use App\Modules\TravelTours\Storefront\Livewire\DepartureSelector;
use App\Modules\TravelTours\Storefront\Services\BookingAccessUrlService;
use App\Modules\TravelTours\Storefront\Services\CheckoutSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Prove that seats are held only for the session that asked, that checkout
 * writes exactly one booking from the held quote, and that time limits are
 * enforced by both the pages and the scheduled sweeps.
 */
final class TravelToursCheckoutTest extends TestCase
{
    use RefreshDatabase;

    /** Enable the module with short, deterministic limits. */
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('c', 32)),
            'travel-tours.enabled' => true,
            'travel-tours.booking.hold_minutes' => 15,
            'travel-tours.booking.pending_minutes' => 60,
            'travel-tours.storefront.payment_methods' => ['mobile_money', 'bank_transfer', 'cash'],
        ]);
    }

    /** Continue holds the quoted seats for this session and lands on checkout. */
    public function test_continue_holds_the_quoted_seats_and_redirects_to_checkout(): void
    {
        [$tour, $departure] = $this->tourWithDeparture();

        $component = Livewire::test(DepartureSelector::class, ['tour' => $tour])
            ->set('form.adults', '2')
            ->set('form.children', '1')
            ->call('continue')
            ->assertHasNoErrors();

        $hold = AvailabilityHold::query()->sole();
        $component->assertRedirect(route('travel-tours.storefront.checkout', $hold->ulid));
        $this->assertSame(HoldStatus::Active, $hold->status);
        $this->assertSame(3, $hold->seat_count);
        $this->assertSame($departure->getKey(), $hold->departure_id);
        $this->assertSame(25_000_00, $hold->quoted_total_minor);

        Livewire::test(DepartureSelector::class, ['tour' => $tour])
            ->set('form.adults', '2')
            ->set('form.children', '1')
            ->call('continue')
            ->assertHasNoErrors();

        $this->assertSame(1, AvailabilityHold::query()->count(), 'Repeating the same selection reuses the active hold.');
    }

    /** A hold that no longer fits is refused with the reason on the selector. */
    public function test_continue_refuses_when_seats_ran_out_since_quoting(): void
    {
        [$tour, $departure] = $this->tourWithDeparture(capacity: 2);

        $component = Livewire::test(DepartureSelector::class, ['tour' => $tour])->set('form.adults', '2');
        AvailabilityHold::factory()->create(['departure_id' => $departure->getKey(), 'seat_count' => 1, 'adult_count' => 1]);

        $component->call('continue')
            ->assertHasErrors(['selection'])
            ->assertNoRedirect();
    }

    /** Only the session that created a hold may open its checkout. */
    public function test_checkout_belongs_to_the_session_that_created_the_hold(): void
    {
        [$tour] = $this->tourWithDeparture();
        Livewire::test(DepartureSelector::class, ['tour' => $tour])->call('continue');
        $hold = AvailabilityHold::query()->sole();

        $this->withoutVite()->get(route('travel-tours.storefront.checkout', $hold->ulid))
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertSee('Complete your booking')
            ->assertSee('Traveller 1');

        $this->flushSession();

        $this->get(route('travel-tours.storefront.checkout', $hold->ulid))->assertForbidden();
    }

    /** An expired hold explains itself and lets the visitor hold again. */
    public function test_expired_hold_shows_the_expired_page_and_allows_a_fresh_hold(): void
    {
        [$tour] = $this->tourWithDeparture();
        Livewire::test(DepartureSelector::class, ['tour' => $tour])->call('continue');
        $hold = AvailabilityHold::query()->sole();
        $hold->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->withoutVite()->get(route('travel-tours.storefront.checkout', $hold->ulid))
            ->assertStatus(410)
            ->assertSee('Your held places have been released');

        Livewire::test(DepartureSelector::class, ['tour' => $tour])->call('continue')->assertHasNoErrors();

        $this->assertSame(2, AvailabilityHold::query()->count(), 'A new hold is created once the old one expired.');
        $this->assertSame(HoldStatus::Active, AvailabilityHold::query()->latest('id')->first()->status);
    }

    /** Checkout validates every traveller and the terms before touching the service. */
    public function test_checkout_validates_customer_travellers_and_terms(): void
    {
        [$tour] = $this->tourWithDeparture();
        Livewire::test(DepartureSelector::class, ['tour' => $tour])->set('form.adults', '1')->set('form.children', '1')->call('continue');
        $hold = AvailabilityHold::query()->sole();

        Livewire::test(BookingCheckout::class, ['hold' => $hold])
            ->call('place')
            ->assertHasErrors(['form.firstName', 'form.lastName', 'form.email', 'form.phone', 'form.participants.1.first_name', 'form.participants.1.date_of_birth', 'form.acceptTerms'])
            ->assertHasNoErrors(['form.participants.0.first_name'])
            ->assertSee("Enter traveller 2's date of birth")
            ->assertSee('Please accept the booking terms');

        $this->assertSame(0, TourBooking::query()->count());
    }

    /** A complete checkout places one pending booking from the held quote, idempotently. */
    public function test_checkout_places_the_booking_from_the_held_quote(): void
    {
        [$tour, $departure] = $this->tourWithDeparture();
        Livewire::test(DepartureSelector::class, ['tour' => $tour])->set('form.adults', '2')->set('form.children', '1')->call('continue');
        $hold = AvailabilityHold::query()->sole();

        $component = Livewire::test(BookingCheckout::class, ['hold' => $hold])
            ->set('form.firstName', 'Amina')
            ->set('form.lastName', 'Wanjiru')
            ->set('form.email', 'Amina@Example.test')
            ->set('form.phone', '+254 700 000 001')
            ->set('form.participants.1.first_name', 'Brian')
            ->set('form.participants.1.last_name', 'Wanjiru')
            ->set('form.participants.2.first_name', 'Chloe')
            ->set('form.participants.2.last_name', 'Wanjiru')
            ->set('form.participants.2.date_of_birth', now()->subYears(9)->toDateString())
            ->set('form.preferredMethod', 'bank_transfer')
            ->set('form.specialRequests', 'Vegetarian meals for all travellers.')
            ->set('form.acceptTerms', true)
            ->call('place')
            ->assertHasNoErrors();

        $booking = TourBooking::query()->with(['participants', 'customer', 'priceLines'])->sole();
        $component->assertRedirect();
        $this->assertStringContainsString(route('travel-tours.storefront.bookings.confirmation', $booking, false), $component->effects['redirect'] ?? '');
        $this->assertSame(BookingStatus::Pending, $booking->status);
        $this->assertSame(BookingChannel::Web, $booking->channel);
        $this->assertSame(PaymentMethod::BankTransfer, $booking->preferred_payment_method);
        $this->assertSame(25_000_00, $booking->total_minor);
        $this->assertSame(7_500_00, $booking->deposit_required_minor);
        $this->assertSame($departure->getKey(), $booking->departure_id);
        $this->assertSame('amina@example.test', $booking->customer->email);
        $this->assertSame($tour->policy_version, $booking->terms_version);
        $this->assertSame('Vegetarian meals for all travellers.', $booking->special_requests);
        $this->assertSame(['Amina Wanjiru', 'Brian Wanjiru', 'Chloe Wanjiru'], $booking->participants->sortBy('sequence')->map(fn ($p): string => $p->first_name.' '.$p->last_name)->values()->all());
        $this->assertTrue($booking->participants->firstWhere('sequence', 1)->is_lead);
        $this->assertSame(ParticipantType::Child, $booking->participants->firstWhere('sequence', 3)->participant_type);
        $this->assertSame(HoldStatus::Consumed, $hold->fresh()->status);
        $this->assertNotNull($booking->pending_expires_at);

        $component->call('place')->assertHasNoErrors();
        $this->assertSame(1, TourBooking::query()->count(), 'Submitting twice never creates a second booking.');

        $this->withoutVite()->get(route('travel-tours.storefront.checkout', $hold->ulid))->assertRedirect();
    }

    /** The confirmation page explains the chosen payment path, and the document renders promotion credits. */
    public function test_confirmation_page_shows_next_steps_for_the_preferred_method(): void
    {
        [$tour] = $this->tourWithDeparture();
        Promotion::factory()->create(['code' => 'SAVE10', 'name' => 'Ten percent off', 'adjustment_type' => AdjustmentType::Percentage, 'adjustment_value' => 10_00, 'applies_to_all_tours' => true, 'maximum_uses' => null]);
        Livewire::test(DepartureSelector::class, ['tour' => $tour])->set('form.promotionCode', 'SAVE10')->call('continue')->assertHasNoErrors();
        $hold = AvailabilityHold::query()->sole();
        Livewire::test(BookingCheckout::class, ['hold' => $hold])
            ->set('form.firstName', 'Amina')->set('form.lastName', 'Wanjiru')
            ->set('form.email', 'amina@example.test')->set('form.phone', '0700000001')
            ->set('form.preferredMethod', 'mobile_money')->set('form.acceptTerms', true)
            ->call('place')->assertHasNoErrors();
        $booking = TourBooking::query()->sole();

        $urls = app(BookingAccessUrlService::class);
        $this->withoutVite()->get($urls->confirmation($booking))
            ->assertOk()
            ->assertSee('Secure your places with the deposit')
            ->assertSee('Mobile Money')
            ->assertSee('KES 2,700.00')
            ->assertSee('Please pay by')
            ->assertSee('Amina Wanjiru')
            ->assertSee('Booking summary (PDF)')
            ->assertSee('Private tracking link');

        $this->assertSame(1_000_00, $booking->discount_total_minor);
        $this->get($urls->document($booking))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    /** The sweeps release expired holds and expire unpaid pending bookings, leaving paid ones alone. */
    public function test_sweeps_release_expired_holds_and_expire_unpaid_pending_bookings(): void
    {
        [, $departure] = $this->tourWithDeparture();
        $stale = AvailabilityHold::factory()->create(['departure_id' => $departure->getKey(), 'expires_at' => now()->subMinute()]);
        $live = AvailabilityHold::factory()->create(['departure_id' => $departure->getKey(), 'expires_at' => now()->addMinutes(10)]);
        $overdue = TourBooking::factory()->create(['departure_id' => $departure->getKey(), 'status' => BookingStatus::Pending, 'pending_expires_at' => now()->subMinute()]);
        $paid = TourBooking::factory()->create(['departure_id' => $departure->getKey(), 'status' => BookingStatus::Pending, 'pending_expires_at' => now()->subMinute(), 'paid_minor' => 1_000_00]);
        $future = TourBooking::factory()->create(['departure_id' => $departure->getKey(), 'status' => BookingStatus::Pending, 'pending_expires_at' => now()->addHour()]);

        $this->artisan('travel-tours:release-expired-holds')->expectsOutputToContain('Released 1 expired hold')->assertSuccessful();
        $this->artisan('travel-tours:expire-pending-bookings')->expectsOutputToContain('Expired 1 pending booking')->assertSuccessful();

        $this->assertSame(HoldStatus::Expired, $stale->fresh()->status);
        $this->assertSame(HoldStatus::Active, $live->fresh()->status);
        $this->assertSame(BookingStatus::Expired, $overdue->fresh()->status);
        $this->assertNotNull($overdue->fresh()->expired_at);
        $this->assertSame(BookingStatus::Pending, $paid->fresh()->status);
        $this->assertSame(BookingStatus::Pending, $future->fresh()->status);
        $this->assertSame(1, BookingStatusHistory::query()->where('booking_id', $overdue->getKey())->where('new_status', BookingStatus::Expired->value)->count());
        $this->assertSame(0, app(BookingLifecycleService::class)->expirePending(), 'A second sweep finds nothing to expire.');
        $this->assertSame(0, TourBooking::query()->capacityConsuming()->whereKey($overdue->getKey())->count(), 'Expired bookings stop consuming capacity.');
    }

    /** Operation keys stay within the 64-character column and differ per quote and generation. */
    public function test_checkout_session_keys_are_bounded_and_rotate(): void
    {
        $session = app(CheckoutSession::class);
        $first = $session->holdOperationKey('fingerprint-a');

        $this->assertSame(64, strlen($first));
        $this->assertSame($first, $session->holdOperationKey('fingerprint-a'));
        $this->assertNotSame($first, $session->holdOperationKey('fingerprint-b'));

        $session->rotate();

        $this->assertNotSame($first, $session->holdOperationKey('fingerprint-a'));
    }

    /**
     * Create a published tour (adult 10,000 / child 5,000 / infant 0; 30% deposit) with one open departure.
     *
     * @return array{Tour, TourDeparture}
     */
    private function tourWithDeparture(int $capacity = 10): array
    {
        $tour = Tour::factory()->create([
            'status' => PublicationStatus::Published, 'published_at' => now()->subDay(),
            'terms' => 'Full payment is due 45 days before departure.', 'cancellation_summary' => 'Deposits are non-refundable.', 'policy_version' => '2026-09',
        ]);
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
