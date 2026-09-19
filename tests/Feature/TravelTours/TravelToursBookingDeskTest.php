<?php

/**
 * Verifies register and shift management, the cash ledger, the three-pane terminal, holds, split tenders, and receipts.
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
use App\Modules\TravelTours\Catalog\Services\CatalogMediaService;
use App\Modules\TravelTours\Customers\Models\TravelCustomer;
use App\Modules\TravelTours\Database\Seeders\TravelToursAccessSeeder;
use App\Modules\TravelTours\PointOfBooking\Data\ShiftMovementData;
use App\Modules\TravelTours\PointOfBooking\Enums\ShiftMovementType;
use App\Modules\TravelTours\PointOfBooking\Enums\ShiftStatus;
use App\Modules\TravelTours\PointOfBooking\Exceptions\PointOfBookingException;
use App\Modules\TravelTours\PointOfBooking\Livewire\Admin\RegisterManager;
use App\Modules\TravelTours\PointOfBooking\Livewire\Admin\ShiftManager;
use App\Modules\TravelTours\PointOfBooking\Livewire\Terminal;
use App\Modules\TravelTours\PointOfBooking\Models\BookingRegister;
use App\Modules\TravelTours\PointOfBooking\Models\BookingShift;
use App\Modules\TravelTours\PointOfBooking\Notifications\ShiftVarianceNotification;
use App\Modules\TravelTours\PointOfBooking\Services\BookingShiftService;
use App\Modules\TravelTours\Pricing\Models\ParticipantRate;
use App\Modules\TravelTours\Pricing\Models\TourRatePlan;
use App\Modules\TravelTours\Scheduling\Models\TourDeparture;
use App\Modules\TravelTours\Scheduling\Services\DepartureAvailabilityService;
use App\Modules\TravelTours\Support\TravelToursRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Prove that a manager opens and closes shifts for operators, that expected
 * cash is always the signed movement ledger, that the terminal books through
 * the storefront services with desk attribution, and that every desk action
 * is bounded by the shift and the operator's grants.
 */
final class TravelToursBookingDeskTest extends TestCase
{
    use RefreshDatabase;

    /** Enable the module with the real role grants and fake delivery. */
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('d', 32)), 'travel-tours.enabled' => true, 'travel-tours.pob.variance_threshold_minor' => 500_00]);
        $this->seed(TravelToursAccessSeeder::class);
        Notification::fake();
    }

    /** One open shift per operator and per register; the float opens the ledger; only desk operators qualify. */
    public function test_shifts_open_once_per_operator_and_register(): void
    {
        $service = app(BookingShiftService::class);
        $register = BookingRegister::factory()->create();
        $manager = $this->operator(TravelToursRole::MANAGER);
        $agent = $this->operator(TravelToursRole::BOOKING_AGENT);
        $other = $this->operator(TravelToursRole::BOOKING_AGENT);

        $shift = $service->open($register, $agent, $manager, 5_000_00, 'Counted with the agent.');

        $this->assertSame(ShiftStatus::Open, $shift->status);
        $this->assertSame(5_000_00, $service->expectedCashMinor($shift));
        $this->assertSame('Counted with the agent.', $shift->movements()->where('movement_type', ShiftMovementType::OpeningFloat->value)->sole()->reason);
        $this->assertTrue($service->currentFor($agent)->is($shift));

        try {
            $service->open(BookingRegister::factory()->create(), $agent, $manager, 0);
            $this->fail('An operator cannot hold two open shifts.');
        } catch (PointOfBookingException $exception) {
            $this->assertStringContainsString('already has an open shift', $exception->getMessage());
        }
        try {
            $service->open($register, $other, $manager, 0);
            $this->fail('A register cannot host two open shifts.');
        } catch (PointOfBookingException $exception) {
            $this->assertStringContainsString('Another operator', $exception->getMessage());
        }
        try {
            $service->open(BookingRegister::factory()->create(), $this->operator(TravelToursRole::TOUR_EDITOR), $manager, 0);
            $this->fail('Only desk operators can be given a shift.');
        } catch (PointOfBookingException $exception) {
            $this->assertStringContainsString('not an active booking-desk operator', $exception->getMessage());
        }

        $this->expectException(PointOfBookingException::class);
        $service->open(BookingRegister::factory()->create(['is_active' => false]), $other, $manager, 0);
    }

    /** Expected cash is float + cash payments + cash in − cash refunds − cash out; closing records the variance and alerts managers past the threshold. */
    public function test_expected_cash_follows_the_movement_ledger_and_closing_records_variance(): void
    {
        $shifts = app(BookingShiftService::class);
        $payments = app(BookingPaymentService::class);
        $manager = $this->operator(TravelToursRole::MANAGER);
        $agent = $this->operator(TravelToursRole::BOOKING_AGENT);
        $shift = $shifts->open(BookingRegister::factory()->create(), $agent, $manager, 2_000_00);
        $booking = TourBooking::factory()->create(['status' => BookingStatus::Confirmed, 'total_minor' => 50_000_00, 'subtotal_minor' => 50_000_00, 'shift_id' => $shift->getKey(), 'register_id' => $shift->register_id, 'currency' => 'KES']);

        $payment = $payments->record($booking, new BookingPaymentData('cash-1', PaymentMethod::Cash, 10_000_00, 'KES', shiftId: $shift->getKey(), actorId: $agent->getKey()));
        $payments->record($booking, new BookingPaymentData('mobile-1', PaymentMethod::MobileMoney, 5_000_00, 'KES', shiftId: $shift->getKey(), actorId: $agent->getKey()));
        $shifts->recordMovement($shift, new ShiftMovementData('in-1', ShiftMovementType::CashIn, 1_000_00, 'Change from the safe.'), $agent->getKey());
        $shifts->recordMovement($shift, new ShiftMovementData('out-1', ShiftMovementType::CashOut, 300_00, 'Courier paid in cash.'), $agent->getKey());
        $payments->refund($booking, new BookingRefundData('refund-1', 2_000_00, 'KES', 'Customer reduced the party.', paymentId: $payment->getKey(), shiftId: $shift->getKey(), actorId: $agent->getKey()));

        $this->assertSame(2_000_00 + 10_000_00 + 1_000_00 - 300_00 - 2_000_00, $shifts->expectedCashMinor($shift), 'Mobile money never enters the drawer; cash refunds leave it.');
        $this->assertSame(1, $shift->movements()->where('movement_type', ShiftMovementType::Refund->value)->count());

        $closed = $shifts->close($shift, $manager, 10_000_00, 'Short by the courier receipt.');

        $this->assertSame(ShiftStatus::Closed, $closed->status);
        $this->assertSame(10_700_00, $closed->expected_cash_minor);
        $this->assertSame(-700_00, $closed->variance_minor);
        $this->assertSame('Short by the courier receipt.', $closed->closing_notes);
        $this->assertNull($closed->register_open_guard);
        $this->assertTrue($shifts->exceedsVarianceThreshold($closed));
        Notification::assertSentTo($manager, ShiftVarianceNotification::class, fn (ShiftVarianceNotification $notification): bool => $notification->varianceMinor === -700_00 && $notification->shiftUlid === $closed->ulid);
        Notification::assertNotSentTo($agent, ShiftVarianceNotification::class);

        $this->expectException(PointOfBookingException::class);
        $shifts->recordMovement($closed, new ShiftMovementData('in-2', ShiftMovementType::CashIn, 100, 'Too late.'), $agent->getKey());
    }

    /** The terminal lists priced departures, takes the customer and travellers, settles with split tenders, and hands off to the receipt. */
    public function test_terminal_completes_a_booking_with_split_tenders_and_prints_a_receipt(): void
    {
        [$tour, $departure] = $this->tourWithDeparture();
        $manager = $this->operator(TravelToursRole::MANAGER);
        $agent = $this->operator(TravelToursRole::BOOKING_AGENT);
        $shift = app(BookingShiftService::class)->open(BookingRegister::factory()->create(['automatic_receipt_print' => true]), $agent, $manager, 2_000_00);

        $component = Livewire::actingAs($agent)->test(Terminal::class)
            ->assertSet('shiftUlid', $shift->ulid)
            ->assertSee($tour->name)
            ->set('adults', 2)->set('children', 1)
            ->call('selectDeparture', $departure->getKey())
            ->assertSee('KES 25,000.00')
            ->assertSee('Deposit due now')
            ->call('toggleCustomerForm')
            ->set('customerFirstName', 'Grace')->set('customerLastName', 'Achieng')->set('customerPhone', '0711000111')
            ->call('createCustomer')->assertHasNoErrors()
            ->assertSee('Customer profile created and selected.')
            ->set('travellers.1.first_name', 'Peter')->set('travellers.1.last_name', 'Achieng')
            ->set('travellers.2.first_name', 'Zawadi')->set('travellers.2.last_name', 'Achieng')
            ->set('travellers.2.date_of_birth', now()->subYears(8)->toDateString())
            ->call('applyDeposit', 0)
            ->assertSet('tenders.0.amount', '7500.00')
            ->assertSet('tenders.0.tendered', '7500.00')
            ->call('addTender')
            ->set('tenders.0.tendered', '8000.00')
            ->set('tenders.1.method', 'mobile_money')->set('tenders.1.amount', '2500.00')->set('tenders.1.reference', 'MPESA-77')
            ->call('completeBooking')->assertHasNoErrors();
        $booking = TourBooking::query()->sole();
        $component->assertRedirect(route('travel-tours.pob.receipts.show', ['booking' => $booking->ulid, 'print' => 'checkout']));

        $this->assertSame(BookingChannel::BookingDesk, $booking->channel);
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame(PaymentStatus::Partial, $booking->payment_status);
        $this->assertSame(10_000_00, $booking->paid_minor);
        $this->assertSame($shift->getKey(), $booking->shift_id);
        $this->assertSame($agent->getKey(), $booking->agent_id);
        $this->assertSame(1, TravelCustomer::query()->count(), 'The desk customer profile is reused at placement.');
        $this->assertSame(['Grace', 'Peter', 'Zawadi'], $booking->participants->sortBy('sequence')->pluck('first_name')->values()->all());
        $this->assertSame(7, app(DepartureAvailabilityService::class)->check($departure)->availableSeats);

        $cash = $booking->payments->firstWhere('method', PaymentMethod::Cash);
        $this->assertSame(PaymentRecordStatus::Confirmed, $cash->status);
        $this->assertSame(['tendered_minor' => 8_000_00, 'change_minor' => 500_00], $cash->safe_metadata);
        $this->assertSame(2_000_00 + 7_500_00, app(BookingShiftService::class)->expectedCashMinor($shift), 'Only the cash tender enters the drawer.');

        $this->withoutVite()->actingAs($agent)->get(route('travel-tours.pob.receipts.show', ['booking' => $booking->ulid, 'print' => 'checkout']))
            ->assertOk()
            ->assertSee('Booking receipt')
            ->assertSee('MPESA-77')
            ->assertSee('Cash change')
            ->assertSee('KES 500.00')
            ->assertSee('"autoPrompt":true', false);
        $this->actingAs($agent)->get(route('travel-tours.pob.receipts.pdf', $booking))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($this->operator(TravelToursRole::TOUR_EDITOR))->get(route('travel-tours.pob.receipts.show', $booking))->assertForbidden();
    }

    /** A hold parks a pending booking on the shift; it can be settled later or discarded, and a shift with holds cannot close. */
    public function test_terminal_holds_settles_and_discards_bookings_on_the_shift(): void
    {
        [$tour, $departure] = $this->tourWithDeparture();
        $manager = $this->operator(TravelToursRole::MANAGER);
        $agent = $this->operator(TravelToursRole::BOOKING_AGENT);
        $shifts = app(BookingShiftService::class);
        $shift = $shifts->open(BookingRegister::factory()->create(), $agent, $manager, 0);
        $customer = TravelCustomer::factory()->create(['first_name' => 'Amina', 'last_name' => 'Wanjiru', 'phone' => '+254700000001']);

        $component = Livewire::actingAs($agent)->test(Terminal::class)
            ->call('selectDeparture', $departure->getKey())
            ->set('customerSearch', 'Amina')
            ->call('selectCustomer', $customer->getKey())
            ->call('holdBooking')->assertHasNoErrors()
            ->assertSee('held on this shift')
            ->assertSet('selectedDepartureId', null);
        $held = TourBooking::query()->sole();
        $this->assertSame(BookingStatus::Pending, $held->status);
        $this->assertSame(9, app(DepartureAvailabilityService::class)->check($departure)->availableSeats);

        try {
            $shifts->close($shift, $manager, 0);
            $this->fail('A shift with holds cannot close.');
        } catch (PointOfBookingException $exception) {
            $this->assertStringContainsString('held bookings', $exception->getMessage());
        }

        $component->call('resumeHold', $held->getKey())
            ->assertSet('resumingBookingId', $held->getKey())
            ->assertSee('Resuming hold')
            ->set('tenders.0.amount', '1000.00')
            ->call('completeBooking')
            ->assertHasErrors(['terminal'])
            ->assertSee('Take at least the deposit');
        $component->call('applyTotal', 0)->assertSet('tenders.0.amount', '10000.00')->call('completeBooking')->assertHasNoErrors();

        $held->refresh();
        $this->assertSame(BookingStatus::Confirmed, $held->status);
        $this->assertSame(PaymentStatus::Paid, $held->payment_status);

        $component->call('selectDeparture', $departure->getKey())->call('selectCustomer', $customer->getKey());
        Livewire::actingAs($agent)->test(Terminal::class)
            ->call('selectDeparture', $departure->getKey())
            ->set('customerSearch', 'Wanj')
            ->call('selectCustomer', $customer->getKey())
            ->call('holdBooking')->assertHasNoErrors();
        $second = TourBooking::query()->where('status', BookingStatus::Pending->value)->sole();
        Livewire::actingAs($agent)->test(Terminal::class)->call('discardHold', $second->getKey())->assertHasNoErrors()->assertSee('discarded');

        $this->assertSame(BookingStatus::Cancelled, $second->fresh()->status);
        $this->assertSame(9, app(DepartureAvailabilityService::class)->check($departure)->availableSeats);
        $this->assertSame(ShiftStatus::Closed, $shifts->close($shift, $manager, 10_000_00)->status);
    }

    /** Departure cards show the tour cover from its thumbnail when generated and from the original while conversions are still queued. */
    public function test_terminal_departure_cards_show_the_cover_before_conversions_exist(): void
    {
        Storage::fake('public');
        [$tour, $departure] = $this->tourWithDeparture();
        $manager = $this->operator(TravelToursRole::MANAGER);
        $agent = $this->operator(TravelToursRole::BOOKING_AGENT);
        app(BookingShiftService::class)->open(BookingRegister::factory()->create(), $agent, $manager, 0);
        $tour = app(CatalogMediaService::class)->replaceTourCover($tour, UploadedFile::fake()->image('cover.jpg', 640, 480), 'Cover', null);
        $cover = $tour->getFirstMedia('tour_cover');

        Livewire::actingAs($agent)->test(Terminal::class)->assertSeeHtml('src="'.$cover->getUrl('thumb').'"');

        $cover->forceFill(['generated_conversions' => []])->save();

        Livewire::actingAs($agent)->test(Terminal::class)
            ->assertSeeHtml('src="'.$cover->getUrl().'"')
            ->assertDontSeeHtml('-thumb.');
    }

    /** Without a shift the terminal shows the gate; a manager can operate any open shift and an operator only their own. */
    public function test_terminal_shift_gate_and_scope(): void
    {
        $manager = $this->operator(TravelToursRole::MANAGER);
        $agent = $this->operator(TravelToursRole::BOOKING_AGENT);
        $other = $this->operator(TravelToursRole::BOOKING_AGENT);

        Livewire::actingAs($agent)->test(Terminal::class)->assertSee('No open shift assigned')->assertDontSee('Open a booking shift');
        Livewire::actingAs($manager)->test(Terminal::class)->assertSee('No open shift assigned')->assertSee('Open a booking shift');
        Livewire::actingAs($this->operator(TravelToursRole::TOUR_EDITOR))->test(Terminal::class)->assertForbidden();

        $shift = app(BookingShiftService::class)->open(BookingRegister::factory()->create(), $other, $manager, 0);

        Livewire::actingAs($agent)->test(Terminal::class)->assertSet('shiftUlid', '')->assertSee('No open shift assigned');
        Livewire::actingAs($manager)->test(Terminal::class)->assertSet('shiftUlid', $shift->ulid)->assertSee($other->display_name);
    }

    /** The register manager creates, edits, and retires registers through the service. */
    public function test_register_manager_creates_edits_and_retires_registers(): void
    {
        $manager = $this->operator(TravelToursRole::MANAGER);
        $agent = $this->operator(TravelToursRole::BOOKING_AGENT);

        Livewire::actingAs($agent)->test(RegisterManager::class)->assertForbidden();

        $component = Livewire::actingAs($manager)->test(RegisterManager::class)
            ->call('openCreate')
            ->set('form.code', 'desk 1')
            ->call('save')
            ->assertHasErrors(['form.name', 'form.code'])
            ->set('form.code', 'desk-1')
            ->set('form.name', 'Westlands desk')
            ->set('form.receiptPrintMode', 'auto_prompt')
            ->set('form.receiptPaperWidth', '58')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Register created.');
        $register = BookingRegister::query()->where('code', 'DESK-1')->sole();
        $this->assertTrue($register->automatic_receipt_print);
        $this->assertSame(58, $register->receipt_paper_width_mm);
        $this->assertSame($manager->getKey(), $register->created_by);

        $component->call('openEdit', $register->getKey())
            ->assertSet('form.receiptPrintMode', 'auto_prompt')
            ->set('form.location', 'Head office')
            ->call('save')->assertHasNoErrors();
        $this->assertSame('Head office', $register->fresh()->location);

        app(BookingShiftService::class)->open($register, $agent, $manager, 0);
        $component->call('toggleActive', $register->getKey())->assertHasErrors(['management'])->assertSee('Close the open shift');
        $this->assertTrue($register->fresh()->is_active);
    }

    /** The shift manager opens a shift for a free operator, closes it against the count, and signs it off. */
    public function test_shift_manager_opens_closes_and_signs_off_shifts(): void
    {
        $manager = $this->operator(TravelToursRole::MANAGER);
        $agent = $this->operator(TravelToursRole::BOOKING_AGENT);
        $register = BookingRegister::factory()->create();

        Livewire::actingAs($agent)->test(ShiftManager::class)->assertForbidden();

        $component = Livewire::actingAs($manager)->test(ShiftManager::class)
            ->call('openShiftDialog')
            ->set('openForm.registerId', (string) $register->getKey())
            ->set('openForm.operatorId', (string) $agent->getKey())
            ->set('openForm.openingFloat', 'abc')
            ->call('openShift')
            ->assertHasErrors(['openForm.openingFloat'])
            ->set('openForm.openingFloat', '2500.00')
            ->call('openShift')
            ->assertHasNoErrors()
            ->assertSee('Shift opened.');
        $shift = BookingShift::query()->sole();
        $this->assertSame($agent->getKey(), $shift->operator_id);
        $this->assertSame(2_500_00, $shift->opening_float_minor);

        $component->call('openCloseDialog', $shift->getKey())
            ->assertSet('closeForm.countedCash', '2500.00')
            ->set('closeForm.countedCash', '2400.00')
            ->set('closeForm.note', 'Short by a taxi receipt.')
            ->call('closeShift')
            ->assertHasNoErrors()
            ->assertSee('variance of');
        $this->assertSame(ShiftStatus::Closed, $shift->fresh()->status);
        $this->assertSame(-100_00, $shift->fresh()->variance_minor);

        $component->set('state', 'closed')
            ->call('openSignOff', $shift->getKey())
            ->set('signOffNote', 'Taxi receipt filed.')
            ->call('signOff')
            ->assertHasNoErrors()
            ->assertSee('Shift signed off.');
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
