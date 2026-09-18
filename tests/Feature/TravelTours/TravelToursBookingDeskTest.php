<?php

/**
 * Verifies register shifts, the cash ledger, assisted sales, receipts, and desk authorization.
 */

declare(strict_types=1);

namespace Tests\Feature\TravelTours;

use App\Enums\UserType;
use App\Models\User;
use App\Modules\TravelTours\Bookings\Data\BookingPaymentData;
use App\Modules\TravelTours\Bookings\Data\BookingRefundData;
use App\Modules\TravelTours\Bookings\Enums\BookingChannel;
use App\Modules\TravelTours\Bookings\Enums\BookingStatus;
use App\Modules\TravelTours\Bookings\Enums\ParticipantType;
use App\Modules\TravelTours\Bookings\Enums\PaymentMethod;
use App\Modules\TravelTours\Bookings\Enums\PaymentRecordStatus;
use App\Modules\TravelTours\Bookings\Enums\PaymentStatus;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\Bookings\Services\BookingPaymentService;
use App\Modules\TravelTours\Catalog\Enums\PublicationStatus;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Database\Seeders\TravelToursAccessSeeder;
use App\Modules\TravelTours\PointOfBooking\Data\ShiftMovementData;
use App\Modules\TravelTours\PointOfBooking\Enums\ShiftMovementType;
use App\Modules\TravelTours\PointOfBooking\Enums\ShiftStatus;
use App\Modules\TravelTours\PointOfBooking\Exceptions\PointOfBookingException;
use App\Modules\TravelTours\PointOfBooking\Livewire\Admin\ShiftManager;
use App\Modules\TravelTours\PointOfBooking\Livewire\Terminal;
use App\Modules\TravelTours\PointOfBooking\Models\BookingRegister;
use App\Modules\TravelTours\PointOfBooking\Models\BookingShift;
use App\Modules\TravelTours\PointOfBooking\Services\BookingShiftService;
use App\Modules\TravelTours\Pricing\Models\ParticipantRate;
use App\Modules\TravelTours\Pricing\Models\TourRatePlan;
use App\Modules\TravelTours\Scheduling\Models\TourDeparture;
use App\Modules\TravelTours\Support\TravelToursRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Prove that expected cash is always the signed movement ledger, that an
 * assisted sale reuses the storefront services with desk attribution, and
 * that every desk action is bounded by the shift and the operator's grants.
 */
final class TravelToursBookingDeskTest extends TestCase
{
    use RefreshDatabase;

    /** Enable the module with the real role grants. */
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('d', 32)), 'travel-tours.enabled' => true, 'travel-tours.pob.variance_threshold_minor' => 500_00]);
        $this->seed(TravelToursAccessSeeder::class);
    }

    /** One open shift per operator and per register; the float opens the ledger. */
    public function test_shifts_open_once_per_operator_and_register(): void
    {
        $service = app(BookingShiftService::class);
        $register = BookingRegister::factory()->create();
        $agent = $this->operator(TravelToursRole::BOOKING_AGENT);
        $other = $this->operator(TravelToursRole::BOOKING_AGENT);

        $shift = $service->open($agent, $register, 5_000_00);

        $this->assertSame(ShiftStatus::Open, $shift->status);
        $this->assertSame(5_000_00, $service->expectedCashMinor($shift));
        $this->assertSame(1, $shift->movements()->where('movement_type', ShiftMovementType::OpeningFloat->value)->count());
        $this->assertTrue($service->currentFor($agent)->is($shift));

        try {
            $service->open($agent, BookingRegister::factory()->create(), 0);
            $this->fail('An operator cannot open two shifts.');
        } catch (PointOfBookingException $exception) {
            $this->assertStringContainsString('already have an open shift', $exception->getMessage());
        }

        try {
            $service->open($other, $register, 0);
            $this->fail('A register cannot host two open shifts.');
        } catch (PointOfBookingException $exception) {
            $this->assertStringContainsString('Another operator', $exception->getMessage());
        }

        $inactive = BookingRegister::factory()->create(['is_active' => false]);
        $this->expectException(PointOfBookingException::class);
        $service->open($other, $inactive, 0);
    }

    /** Expected cash is float + cash payments + cash in − cash refunds − cash out, and closing records the variance. */
    public function test_expected_cash_follows_the_movement_ledger_and_closing_records_variance(): void
    {
        $shifts = app(BookingShiftService::class);
        $payments = app(BookingPaymentService::class);
        $agent = $this->operator(TravelToursRole::BOOKING_AGENT);
        $shift = $shifts->open($agent, BookingRegister::factory()->create(), 2_000_00);
        $booking = TourBooking::factory()->create(['status' => BookingStatus::Pending, 'total_minor' => 50_000_00, 'subtotal_minor' => 50_000_00, 'shift_id' => $shift->getKey(), 'register_id' => $shift->register_id, 'currency' => 'KES']);

        $payment = $payments->record($booking, new BookingPaymentData('cash-1', PaymentMethod::Cash, 10_000_00, 'KES', shiftId: $shift->getKey(), actorId: $agent->getKey()));
        $payments->record($booking, new BookingPaymentData('mobile-1', PaymentMethod::MobileMoney, 5_000_00, 'KES', shiftId: $shift->getKey(), actorId: $agent->getKey()));
        $shifts->recordMovement($shift, new ShiftMovementData('in-1', ShiftMovementType::CashIn, 1_000_00, 'Change from the safe.'), $agent->getKey());
        $shifts->recordMovement($shift, new ShiftMovementData('out-1', ShiftMovementType::CashOut, 300_00, 'Courier paid in cash.'), $agent->getKey());
        $payments->refund($booking, new BookingRefundData('refund-1', 2_000_00, 'KES', 'Customer reduced the party.', paymentId: $payment->getKey(), shiftId: $shift->getKey(), actorId: $agent->getKey()));

        $this->assertSame(2_000_00 + 10_000_00 + 1_000_00 - 300_00 - 2_000_00, $shifts->expectedCashMinor($shift), 'Mobile money never enters the drawer; cash refunds leave it.');
        $this->assertSame(1, $shift->movements()->where('movement_type', ShiftMovementType::Refund->value)->count());

        $closed = $shifts->close($shift, 10_000_00, 'Short by the courier receipt.', $agent->getKey());

        $this->assertSame(ShiftStatus::Closed, $closed->status);
        $this->assertSame(10_700_00, $closed->expected_cash_minor);
        $this->assertSame(-700_00, $closed->variance_minor);
        $this->assertNull($closed->register_open_guard);
        $this->assertTrue($shifts->exceedsVarianceThreshold($closed));

        $this->expectException(PointOfBookingException::class);
        $shifts->recordMovement($closed, new ShiftMovementData('in-2', ShiftMovementType::CashIn, 100, 'Too late.'), $agent->getKey());
    }

    /** An assisted sale places a desk booking, confirms the cash, writes one movement, and confirms the booking once the deposit is covered. */
    public function test_terminal_sale_books_settles_and_confirms_with_receipt(): void
    {
        [$tour, $departure] = $this->tourWithDeparture();
        $agent = $this->operator(TravelToursRole::BOOKING_AGENT);
        $register = BookingRegister::factory()->create(['automatic_receipt_print' => true, 'receipt_paper_width_mm' => 58]);
        $shift = app(BookingShiftService::class)->open($agent, $register, 1_000_00);

        $component = Livewire::actingAs($agent)
            ->test(Terminal::class)
            ->assertSee('Shift open on')
            ->set('form.tourId', (string) $tour->getKey())
            ->set('form.departureId', (string) $departure->getKey())
            ->set('form.adults', '2')
            ->set('form.children', '1')
            ->assertSee('Total')
            ->assertSee('KES 25,000.00')
            ->assertSee('KES 7,500.00')
            ->set('form.firstName', 'Grace')
            ->set('form.lastName', 'Achieng')
            ->set('form.phone', '0711000111')
            ->set('form.participants.1.first_name', 'Peter')
            ->set('form.participants.1.last_name', 'Achieng')
            ->set('form.participants.2.first_name', 'Zawadi')
            ->set('form.participants.2.last_name', 'Achieng')
            ->set('form.participants.2.date_of_birth', now()->subYears(8)->toDateString())
            ->set('form.paymentMethod', 'cash')
            ->set('form.amountReceived', '8000.00')
            ->set('form.paymentReference', 'CASH-1')
            ->call('sell')
            ->assertHasNoErrors()
            ->assertSee('recorded')
            ->assertSee('Print receipt (58 mm)');

        $booking = TourBooking::query()->with(['payments', 'participants'])->sole();
        $this->assertSame(BookingChannel::BookingDesk, $booking->channel);
        $this->assertSame($shift->getKey(), $booking->shift_id);
        $this->assertSame($register->getKey(), $booking->register_id);
        $this->assertSame($agent->getKey(), $booking->agent_id);
        $this->assertSame(BookingStatus::Confirmed, $booking->status, 'The deposit was covered, so the desk confirmed the booking.');
        $this->assertSame(PaymentStatus::Partial, $booking->payment_status);
        $this->assertSame(8_000_00, $booking->paid_minor);
        $this->assertSame(PaymentRecordStatus::Confirmed, $booking->payments->sole()->status);
        $this->assertSame(['Grace Achieng', 'Peter Achieng', 'Zawadi Achieng'], $booking->participants->sortBy('sequence')->map(fn ($p) => $p->first_name.' '.$p->last_name)->values()->all());
        $this->assertSame(1_000_00 + 8_000_00, app(BookingShiftService::class)->expectedCashMinor($shift));
        $this->assertSame(1, $shift->movements()->where('movement_type', ShiftMovementType::Payment->value)->count());

        $component->call('sell')->assertHasNoErrors();
        $this->assertSame(1, TourBooking::query()->count(), 'Submitting twice never records a second booking.');

        $this->withoutVite()->actingAs($agent)
            ->get(route('travel-tours.pob.receipt', ['payment' => $booking->payments->sole()->ulid, 'auto' => 1]))
            ->assertOk()
            ->assertSee('Booking '.$booking->booking_number)
            ->assertSee('KES 8,000.00')
            ->assertSee('Balance due')
            ->assertSee('--receipt-width: 58mm', false)
            ->assertSee('window.print()', false);
    }

    /** Without an open shift the terminal refuses to sell and explains why. */
    public function test_terminal_refuses_to_sell_without_an_open_shift(): void
    {
        [$tour, $departure] = $this->tourWithDeparture();
        $agent = $this->operator(TravelToursRole::BOOKING_AGENT);

        Livewire::actingAs($agent)
            ->test(Terminal::class)
            ->assertSee('No open shift')
            ->set('form.tourId', (string) $tour->getKey())
            ->set('form.departureId', (string) $departure->getKey())
            ->call('sell')
            ->assertHasErrors(['desk'])
            ->assertSee('Open a shift before taking a booking.');

        $this->assertSame(0, TourBooking::query()->count());

        Livewire::actingAs($this->operator(TravelToursRole::TOUR_EDITOR))
            ->test(Terminal::class)
            ->assertForbidden();
    }

    /** The terminal opens and closes shifts and declares cash movements through the dialogs. */
    public function test_terminal_shift_dialogs_open_move_and_close(): void
    {
        $register = BookingRegister::factory()->create(['name' => 'Front desk']);
        $agent = $this->operator(TravelToursRole::BOOKING_AGENT);

        $component = Livewire::actingAs($agent)
            ->test(Terminal::class)
            ->call('openShiftDialog')
            ->assertSet('dialog', 'open-shift')
            ->set('openingFloat', 'abc')
            ->call('openShift')
            ->assertHasErrors(['openingFloat'])
            ->set('openingFloat', '2500.00')
            ->call('openShift')
            ->assertHasNoErrors()
            ->assertSee('Shift opened.')
            ->assertSee('Front desk')
            ->assertSee('KES 2,500.00');

        $component->call('openMovementDialog')
            ->set('movementType', 'cash_out')
            ->set('movementAmount', '500.00')
            ->set('movementReason', 'Petty cash for stationery')
            ->call('recordMovement')
            ->assertHasNoErrors()
            ->assertSee('KES 2,000.00');

        $component->call('openCloseDialog')
            ->set('countedCash', '1900.00')
            ->call('closeShift')
            ->assertHasNoErrors()
            ->assertSee('variance of −KES 100.00')
            ->assertSee('No open shift');

        $shift = BookingShift::query()->sole();
        $this->assertSame(ShiftStatus::Closed, $shift->status);
        $this->assertSame(-100_00, $shift->variance_minor);
    }

    /** Managers create registers and reconcile closed shifts; agents cannot reach the workspace. */
    public function test_shift_manager_creates_registers_and_reconciles(): void
    {
        $manager = $this->operator(TravelToursRole::MANAGER);
        $agent = $this->operator(TravelToursRole::BOOKING_AGENT);

        Livewire::actingAs($agent)->test(ShiftManager::class)->call('openCreate')->assertForbidden();

        Livewire::actingAs($manager)
            ->test(ShiftManager::class)
            ->call('openCreate')
            ->set('code', 'desk 1')
            ->call('saveRegister')
            ->assertHasErrors(['code', 'name'])
            ->set('code', 'DESK-1')
            ->set('name', 'Westlands desk')
            ->set('paperWidth', '58')
            ->set('automaticPrint', true)
            ->call('saveRegister')
            ->assertHasNoErrors()
            ->assertSee('Register created.')
            ->assertSee('Westlands desk');

        $register = BookingRegister::query()->sole();
        $this->assertSame(58, $register->receipt_paper_width_mm);
        $this->assertTrue($register->automatic_receipt_print);

        $shifts = app(BookingShiftService::class);
        $shift = $shifts->open($agent, $register, 1_000_00);
        $shifts->close($shift, 1_000_00, null, $agent->getKey());

        Livewire::actingAs($manager)
            ->test(ShiftManager::class)
            ->call('openReconcile', $shift->getKey())
            ->set('notes', 'Counted with the operator.')
            ->call('reconcile')
            ->assertHasNoErrors()
            ->assertSee('Shift reconciled.');

        $this->assertSame(ShiftStatus::Reconciled, $shift->fresh()->status);
        $this->assertSame($manager->getKey(), $shift->fresh()->reconciled_by);
    }

    /**
     * Create a published tour (adult 10,000 / child 5,000 / infant 0; 30% deposit) with one open departure.
     *
     * @return array{Tour, TourDeparture}
     */
    private function tourWithDeparture(): array
    {
        $tour = Tour::factory()->create(['status' => PublicationStatus::Published, 'published_at' => now()->subDay(), 'policy_version' => '2026-09']);
        $plan = TourRatePlan::factory()->create(['tour_id' => $tour->getKey(), 'is_default' => true, 'currency' => 'KES', 'tax_rate_basis_points' => 0, 'deposit_type' => 'percentage', 'deposit_value' => 30_00, 'minimum_participants' => 1, 'maximum_participants' => 10]);
        foreach ([[ParticipantType::Adult, 18, null, 10_000_00], [ParticipantType::Child, 2, 17, 5_000_00], [ParticipantType::Infant, 0, 1, 0]] as [$type, $minimum, $maximum, $amount]) {
            ParticipantRate::factory()->create(['rate_plan_id' => $plan->getKey(), 'participant_type' => $type, 'minimum_age' => $minimum, 'maximum_age' => $maximum, 'amount_minor' => $amount]);
        }
        $departure = TourDeparture::factory()->create(['tour_id' => $tour->getKey(), 'rate_plan_id' => $plan->getKey(), 'capacity' => 10]);

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
