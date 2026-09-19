<?php

/**
 * End-to-end proof of the two booking journeys the module ships: web and desk.
 */

declare(strict_types=1);

namespace Tests\Feature\TravelTours;

use App\Enums\UserType;
use App\Models\User;
use App\Modules\TravelTours\Bookings\Enums\BookingChannel;
use App\Modules\TravelTours\Bookings\Enums\BookingStatus;
use App\Modules\TravelTours\Bookings\Enums\ParticipantType;
use App\Modules\TravelTours\Bookings\Enums\PaymentRecordStatus;
use App\Modules\TravelTours\Bookings\Enums\PaymentStatus;
use App\Modules\TravelTours\Bookings\Livewire\Admin\BookingManager;
use App\Modules\TravelTours\Bookings\Models\BookingPayment;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\Catalog\Enums\PublicationStatus;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Database\Seeders\TravelToursAccessSeeder;
use App\Modules\TravelTours\Notifications\BookingPaymentConfirmedNotification;
use App\Modules\TravelTours\Notifications\BookingPaymentRecordedNotification;
use App\Modules\TravelTours\Notifications\TourBookingConfirmedNotification;
use App\Modules\TravelTours\Notifications\TourBookingPlacedNotification;
use App\Modules\TravelTours\PointOfBooking\Enums\ShiftStatus;
use App\Modules\TravelTours\PointOfBooking\Livewire\Terminal;
use App\Modules\TravelTours\PointOfBooking\Models\BookingRegister;
use App\Modules\TravelTours\PointOfBooking\Services\BookingShiftService;
use App\Modules\TravelTours\Pricing\Models\ParticipantRate;
use App\Modules\TravelTours\Pricing\Models\TourRatePlan;
use App\Modules\TravelTours\Scheduling\Models\AvailabilityHold;
use App\Modules\TravelTours\Scheduling\Models\TourDeparture;
use App\Modules\TravelTours\Scheduling\Services\DepartureAvailabilityService;
use App\Modules\TravelTours\Storefront\Livewire\BookingCheckout;
use App\Modules\TravelTours\Storefront\Livewire\DepartureSelector;
use App\Modules\TravelTours\Storefront\Services\BookingAccessUrlService;
use App\Modules\TravelTours\Support\TravelToursRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Walk each journey through the real components and services in order and
 * assert the outcome at every hand-off: seats, money, status, mail, and the
 * customer's private pages.
 */
final class TravelToursBookingJourneyTest extends TestCase
{
    use RefreshDatabase;

    /** Enable the module with the real grants and fake delivery. */
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('j', 32)), 'travel-tours.enabled' => true, 'travel-tours.storefront.payment_methods' => ['mobile_money', 'bank_transfer']]);
        $this->seed(TravelToursAccessSeeder::class);
        Notification::fake();
    }

    /** Web: select → hold → checkout → pending → agent records → manager confirms payment and booking → confirmed and documented. */
    public function test_web_journey_from_selection_to_confirmed_booking(): void
    {
        [$tour, $departure] = $this->tourWithDeparture(capacity: 4);
        $availability = app(DepartureAvailabilityService::class);
        $urls = app(BookingAccessUrlService::class);

        Livewire::test(DepartureSelector::class, ['tour' => $tour])
            ->set('form.adults', '2')->set('form.children', '1')
            ->assertSee('Total for 3 travellers')
            ->call('continue')->assertHasNoErrors();
        $hold = AvailabilityHold::query()->sole();
        $this->assertSame(1, $availability->check($departure)->availableSeats, 'The hold reserves three of four seats.');

        Livewire::test(BookingCheckout::class, ['hold' => $hold])
            ->set('form.firstName', 'Amina')->set('form.lastName', 'Wanjiru')
            ->set('form.email', 'amina@example.test')->set('form.phone', '0700000001')
            ->set('form.participants.1.first_name', 'Brian')->set('form.participants.1.last_name', 'Wanjiru')
            ->set('form.participants.2.first_name', 'Chloe')->set('form.participants.2.last_name', 'Wanjiru')
            ->set('form.participants.2.date_of_birth', now()->subYears(9)->toDateString())
            ->set('form.preferredMethod', 'mobile_money')->set('form.acceptTerms', true)
            ->call('place')->assertHasNoErrors();
        $booking = TourBooking::query()->sole();

        $this->assertSame(BookingStatus::Pending, $booking->status);
        $this->assertSame(BookingChannel::Web, $booking->channel);
        $this->assertSame(1, $availability->check($departure)->availableSeats, 'Placement converts the hold into booked seats without double counting.');
        Notification::assertSentOnDemand(TourBookingPlacedNotification::class, fn ($n, $c, AnonymousNotifiable $to): bool => $to->routes['mail'] === 'amina@example.test');
        $this->withoutVite()->get($urls->confirmation($booking))->assertOk()->assertSee('Secure your places with the deposit')->assertSee('KES 7,500.00');

        $agent = $this->operator(TravelToursRole::BOOKING_AGENT);
        $manager = $this->operator(TravelToursRole::MANAGER);
        Livewire::actingAs($agent)->test(BookingManager::class)
            ->call('openPayment', $booking->getKey())
            ->set('payment.method', 'mobile_money')->set('payment.amount', '7500.00')->set('payment.reference', 'MPESA-QX1')->set('payment.provider', 'M-PESA')
            ->call('recordPayment')->assertHasNoErrors();
        $payment = BookingPayment::query()->sole();
        $this->assertSame(PaymentRecordStatus::Pending, $payment->status);
        Notification::assertSentTo($manager, BookingPaymentRecordedNotification::class);
        $this->assertSame(0, $booking->fresh()->paid_minor, 'Evidence alone settles nothing.');

        Livewire::actingAs($manager)->test(BookingManager::class)
            ->call('openDetails', $booking->getKey())
            ->call('confirmPayment', $payment->getKey())->assertHasNoErrors()
            ->call('confirmBooking', $booking->getKey())->assertHasNoErrors();

        $booking->refresh();
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame(PaymentStatus::Partial, $booking->payment_status);
        $this->assertSame(7_500_00, $booking->paid_minor);
        Notification::assertSentOnDemand(BookingPaymentConfirmedNotification::class);
        Notification::assertSentOnDemand(TourBookingConfirmedNotification::class);
        Notification::assertCount(4);

        $this->withoutVite()->get($urls->tracking($booking))->assertOk()->assertSee('Your places are confirmed')->assertSee('MPESA-QX1');
        $this->get($urls->document($booking))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    /** Desk: manager opens shift → operator books a walk-in with cash → confirmed at once → receipt → manager closes balanced. */
    public function test_desk_journey_from_shift_to_balanced_close(): void
    {
        [$tour, $departure] = $this->tourWithDeparture(capacity: 4);
        $manager = $this->operator(TravelToursRole::MANAGER);
        $agent = $this->operator(TravelToursRole::BOOKING_AGENT);
        $shifts = app(BookingShiftService::class);
        $shift = $shifts->open(BookingRegister::factory()->create(), $agent, $manager, 2_000_00);

        Livewire::actingAs($agent)->test(Terminal::class)
            ->call('selectDeparture', $departure->getKey())
            ->call('toggleCustomerForm')
            ->set('customerFirstName', 'Grace')->set('customerLastName', 'Achieng')->set('customerPhone', '0711000111')
            ->call('createCustomer')->assertHasNoErrors()
            ->call('applyTotal', 0)
            ->call('completeBooking')->assertHasNoErrors()
            ->assertRedirect();
        $booking = TourBooking::query()->sole();

        $this->assertSame(BookingChannel::BookingDesk, $booking->channel);
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame(PaymentStatus::Paid, $booking->payment_status);
        $this->assertSame(3, app(DepartureAvailabilityService::class)->check($departure)->availableSeats);
        $this->assertSame(12_000_00, $shifts->expectedCashMinor($shift));
        Notification::assertNotSentTo($manager, BookingPaymentRecordedNotification::class);

        $this->withoutVite()->actingAs($agent)->get(route('travel-tours.pob.receipts.show', ['booking' => $booking->ulid, 'print' => 'checkout']))->assertOk()->assertSee('Booking receipt')->assertSee('Cash');

        $closed = $shifts->close($shift, $manager, 12_000_00);
        $this->assertSame(ShiftStatus::Closed, $closed->status);
        $this->assertSame(0, $closed->variance_minor);
    }

    /**
     * Create a published tour (adult 10,000 / child 5,000 / infant 0; 30% deposit) with one open departure.
     *
     * @return array{Tour, TourDeparture}
     */
    private function tourWithDeparture(int $capacity): array
    {
        $tour = Tour::factory()->create(['status' => PublicationStatus::Published, 'published_at' => now()->subDay(), 'policy_version' => '2026-09']);
        $plan = TourRatePlan::factory()->create(['tour_id' => $tour->getKey(), 'is_default' => true, 'currency' => 'KES', 'tax_rate_basis_points' => 0, 'deposit_type' => 'percentage', 'deposit_value' => 30_00, 'minimum_participants' => 1, 'maximum_participants' => 10]);
        foreach ([[ParticipantType::Adult, 18, null, 10_000_00], [ParticipantType::Child, 2, 17, 5_000_00], [ParticipantType::Infant, 0, 1, 0]] as [$type, $minimum, $maximum, $amount]) {
            ParticipantRate::factory()->create(['rate_plan_id' => $plan->getKey(), 'participant_type' => $type, 'minimum_age' => $minimum, 'maximum_age' => $maximum, 'amount_minor' => $amount]);
        }
        $departure = TourDeparture::factory()->create(['tour_id' => $tour->getKey(), 'rate_plan_id' => $plan->getKey(), 'capacity' => $capacity]);

        return [$tour, $departure];
    }

    /** Create an active non-administrator operator holding one module role. */
    private function operator(string $role): User
    {
        $user = User::factory()->create(['user_type' => UserType::Viewer, 'is_active' => true]);
        $user->assignRole($role);

        return $user->fresh();
    }
}
