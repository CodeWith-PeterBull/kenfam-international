<?php

declare(strict_types=1);

namespace Tests\Feature\PropertyBooking;

use App\Enums\UserType;
use App\Models\User;
use App\Modules\PropertyBooking\Bookings\Enums\BookingPaymentMethod;
use App\Modules\PropertyBooking\Bookings\Enums\BookingPaymentStatus;
use App\Modules\PropertyBooking\Bookings\Enums\BookingStatus;
use App\Modules\PropertyBooking\Bookings\Enums\UnitAssignmentStatus;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Bookings\Models\BookingPayment;
use App\Modules\PropertyBooking\Bookings\Models\UnitAssignment;
use App\Modules\PropertyBooking\Catalog\Models\AccommodationUnit;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Catalog\Models\UnitType;
use App\Modules\PropertyBooking\Guests\Models\Guest;
use App\Modules\PropertyBooking\Guests\Services\GuestService;
use App\Modules\PropertyBooking\PointOfBooking\Data\PobTenderData;
use App\Modules\PropertyBooking\PointOfBooking\Events\ReceptionShiftVarianceDetected;
use App\Modules\PropertyBooking\PointOfBooking\Exceptions\PointOfBookingException;
use App\Modules\PropertyBooking\PointOfBooking\Exceptions\ReceptionShiftException;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionRegister;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionShift;
use App\Modules\PropertyBooking\PointOfBooking\Services\PobCheckoutService;
use App\Modules\PropertyBooking\PointOfBooking\Services\ReceptionShiftService;
use App\Modules\PropertyBooking\Pricing\Data\BookingQuote;
use App\Modules\PropertyBooking\Pricing\Models\RatePlan;
use App\Modules\PropertyBooking\Pricing\Services\BookingQuoteService;
use App\Modules\PropertyBooking\Support\PropertyBookingPermission;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Verifies POB shift ownership, exact settlement, hold, and rollback invariants. */
final class PropertyBookingPobTransactionTest extends TestCase
{
    use RefreshDatabase;

    /** Seed the shared capabilities required by direct permission assignment. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    /** Enforce one open shift per register and receptionist and preserve reconciliation snapshots. */
    public function test_shift_service_enforces_open_guards_and_reconciles_physical_cash(): void
    {
        $property = Property::factory()->create();
        $manager = $this->manager();
        $receptionist = $this->operator($property, PropertyBookingPermission::ACCESS_POB);
        $otherReceptionist = $this->operator($property, PropertyBookingPermission::ACCESS_POB);
        $register = ReceptionRegister::factory()->for($property)->create();
        $otherRegister = ReceptionRegister::factory()->for($property)->create();
        $service = app(ReceptionShiftService::class);

        $shift = $service->open($register, $receptionist, $manager, 10_000, 'Morning reception shift');
        $this->assertTrue($shift->isOpen());
        $this->assertSame(10_000, $shift->expected_cash_minor);

        foreach ([[$register, $otherReceptionist], [$otherRegister, $receptionist]] as [$blockedRegister, $blockedReceptionist]) {
            try {
                $service->open($blockedRegister, $blockedReceptionist, $manager, 0);
                $this->fail('A conflicting reception shift was opened.');
            } catch (ReceptionShiftException $exception) {
                $this->assertStringContainsString('already has an open shift', $exception->getMessage());
            }
        }

        $closed = $service->close($shift, $manager, 10_250, 'Counted at handover');
        $this->assertFalse($closed->isOpen());
        $this->assertSame(10_000, $closed->expected_cash_minor);
        $this->assertSame(10_250, $closed->counted_cash_minor);
        $this->assertSame(250, $closed->variance_minor);
        $this->assertNull($closed->register_open_guard);
        $this->assertNull($closed->receptionist_open_guard);

        $event = new ReceptionShiftVarianceDetected($closed->ulid, 250, 100);
        $this->assertInstanceOf(ShouldDispatchAfterCommit::class, $event);
        $this->assertDatabaseHas('system_activities', ['activity_type' => 'property-booking.shift.closed']);
    }

    /** Atomically allocate accommodation, record split tender, cash change, and live shift cash. */
    public function test_pob_checkout_records_exact_split_tender_and_live_cash_projection(): void
    {
        [$property, , $ratePlan] = $this->inventory(2);
        [$receptionist, $shift] = $this->openShift($property, 10_000);
        $guest = $this->verifiedGuest();
        $quote = $this->quote($ratePlan);
        $cash = 40_000;

        $booking = app(PobCheckoutService::class)->checkout(
            quote: $quote,
            ratePlan: $ratePlan,
            guest: $guest,
            tenders: [
                new PobTenderData(BookingPaymentMethod::Cash, $cash, $cash + 1_000),
                new PobTenderData(
                    BookingPaymentMethod::MobileMoney,
                    $quote->calculation->totalMinor - $cash,
                    reference: 'MPESA-POB-0001',
                ),
            ],
            shift: $shift,
            receptionist: $receptionist,
        );

        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame(BookingPaymentStatus::Paid, $booking->payment_status);
        $this->assertSame($booking->total_minor, $booking->paid_minor);
        $this->assertSame($shift->id, $booking->reception_shift_id);
        $this->assertSame($receptionist->id, $booking->receptionist_id);
        $this->assertCount(2, $booking->payments);
        $this->assertSame(1_000, $booking->payments->firstWhere('method', BookingPaymentMethod::Cash)?->change_minor);
        $this->assertSame(10_000 + $cash, $shift->fresh()->expected_cash_minor);
        $this->assertSame(UnitAssignmentStatus::Active, $booking->unitAssignments->sole()->status);
        $this->assertDatabaseHas('system_activities', ['activity_type' => 'property-booking.pob-booking.completed']);
    }

    /** Reject direct POB service calls that bypass the required guest identity workflow. */
    public function test_pob_service_rejects_a_guest_without_required_identity(): void
    {
        [$property, , $ratePlan] = $this->inventory(1);
        [$receptionist, $shift] = $this->openShift($property);
        $guest = Guest::factory()->create();
        $quote = $this->quote($ratePlan);

        try {
            app(PobCheckoutService::class)->checkout(
                $quote,
                $ratePlan,
                $guest,
                [new PobTenderData(BookingPaymentMethod::Cash, $quote->calculation->totalMinor, $quote->calculation->totalMinor)],
                $shift,
                $receptionist,
            );
            $this->fail('A POB booking was created without the configured guest identity.');
        } catch (PointOfBookingException $exception) {
            $this->assertStringContainsString('ID or passport', $exception->getMessage());
        }

        $this->assertDatabaseCount((new Booking)->getTable(), 0);
        $this->assertDatabaseCount((new UnitAssignment)->getTable(), 0);
    }

    /** Recalculate pricing under lock and roll back every write when browser tender is stale. */
    public function test_stale_pob_quote_cannot_commit_a_stale_price_or_partial_aggregate(): void
    {
        [$property, , $ratePlan] = $this->inventory(1);
        [$receptionist, $shift] = $this->openShift($property, 5_000);
        $guest = $this->verifiedGuest();
        $quote = $this->quote($ratePlan);
        $staleTotal = $quote->calculation->totalMinor;
        $ratePlan->forceFill(['base_rate_minor' => $ratePlan->base_rate_minor + 25_000])->save();

        try {
            app(PobCheckoutService::class)->checkout(
                $quote,
                $ratePlan,
                $guest,
                [new PobTenderData(BookingPaymentMethod::Cash, $staleTotal, $staleTotal)],
                $shift,
                $receptionist,
            );
            $this->fail('A stale-price POB booking was committed.');
        } catch (PointOfBookingException $exception) {
            $this->assertStringContainsString('settle the booking total exactly', $exception->getMessage());
        }

        $this->assertDatabaseCount((new Booking)->getTable(), 0);
        $this->assertDatabaseCount((new BookingPayment)->getTable(), 0);
        $this->assertDatabaseCount((new UnitAssignment)->getTable(), 0);
        $this->assertSame(5_000, $shift->fresh()->expected_cash_minor);
        $this->assertDatabaseMissing('system_activities', ['activity_type' => 'property-booking.pob-booking.placed']);
    }

    /** Reprice a held booking without changing identity before exact settlement. */
    public function test_owned_hold_is_repriced_and_completed_without_changing_identity(): void
    {
        [$property, , $ratePlan] = $this->inventory(2);
        [$receptionist, $shift] = $this->openShift($property);
        $quote = $this->quote($ratePlan);
        $checkout = app(PobCheckoutService::class);
        $held = $checkout->hold($quote, $ratePlan, $this->verifiedGuest(), $shift, $receptionist);

        $this->assertSame(BookingStatus::Held, $held->status);
        $this->assertNotNull($held->hold_expires_at);
        $ratePlan->forceFill(['base_rate_minor' => $ratePlan->base_rate_minor + 20_000])->save();
        $newTotal = app(BookingQuoteService::class)->quote(
            $ratePlan->fresh(),
            $quote->startsAt,
            $quote->endsAt,
            $quote->adults,
            $quote->children,
            $quote->infants,
        )->calculation->totalMinor;

        $completed = $checkout->checkoutHeld(
            $held,
            [new PobTenderData(BookingPaymentMethod::Card, $newTotal, reference: 'CARD-POB-0001')],
            $shift,
            $receptionist,
        );

        $this->assertSame($held->id, $completed->id);
        $this->assertSame($held->ulid, $completed->ulid);
        $this->assertSame($newTotal, $completed->total_minor);
        $this->assertSame(BookingStatus::Confirmed, $completed->status);
        $this->assertSame(BookingPaymentStatus::Paid, $completed->payment_status);
        $this->assertNull($completed->hold_expires_at);
    }

    /** Block shift close while holds exist and permit only the owning operator to discard them. */
    public function test_unresolved_hold_blocks_close_and_cross_operator_discard(): void
    {
        [$property, , $ratePlan] = $this->inventory(1);
        [$receptionist, $shift, , $manager] = $this->openShift($property, 7_500);
        $otherReceptionist = $this->operator($property, PropertyBookingPermission::ACCESS_POB);
        $checkout = app(PobCheckoutService::class);
        $held = $checkout->hold($this->quote($ratePlan), $ratePlan, $this->verifiedGuest(), $shift, $receptionist);

        try {
            app(ReceptionShiftService::class)->close($shift, $manager, 7_500);
            $this->fail('A shift with an unresolved hold was closed.');
        } catch (ReceptionShiftException $exception) {
            $this->assertStringContainsString('Resolve or discard held bookings', $exception->getMessage());
        }

        try {
            $checkout->discardHeld($held, $shift, $otherReceptionist);
            $this->fail('A different receptionist discarded the hold.');
        } catch (PointOfBookingException $exception) {
            $this->assertStringContainsString('owned open reception shift', $exception->getMessage());
        }

        $discarded = $checkout->discardHeld($held, $shift, $receptionist);
        $this->assertSame(BookingStatus::Expired, $discarded->status);
        $this->assertSame(UnitAssignmentStatus::Released, $discarded->unitAssignments()->sole()->status);
        $this->assertFalse(app(ReceptionShiftService::class)->close($shift, $manager, 7_500)->isOpen());
    }

    /** @return array{Property, UnitType, RatePlan, list<AccommodationUnit>} */
    private function inventory(int $unitCount): array
    {
        $property = Property::factory()->published()->create([
            'timezone' => 'Africa/Nairobi',
            'currency' => 'KES',
            'minimum_notice_minutes' => 0,
            'maximum_advance_days' => 365,
            'turnover_minutes' => 0,
        ]);
        $unitType = UnitType::factory()->for($property)->published()->create([
            'maximum_guests' => 4,
            'maximum_adults' => 2,
            'maximum_children' => 2,
        ]);
        $ratePlan = RatePlan::factory()->forUnitType($unitType)->active()->create([
            'base_rate_minor' => 120_000,
            'included_adults' => 2,
            'included_children' => 2,
            'extra_adult_minor' => 0,
            'extra_child_minor' => 0,
            'tax_rate_bps' => 0,
        ]);
        $units = AccommodationUnit::factory()->count($unitCount)->forUnitType($unitType)->create()->all();

        return [$property, $unitType, $ratePlan, $units];
    }

    /** Generate a deterministic two-night POB quote. */
    private function quote(RatePlan $ratePlan): BookingQuote
    {
        $startsAt = CarbonImmutable::now($ratePlan->property->timezone)->addDays(10)->setTime(15, 0)->utc();

        return app(BookingQuoteService::class)->quote($ratePlan, $startsAt, $startsAt->addDays(2)->subHours(5), 2, 0);
    }

    /** @return array{User, ReceptionShift, ReceptionRegister, User} */
    private function openShift(Property $property, int $openingFloatMinor = 0): array
    {
        $manager = $this->manager();
        $receptionist = $this->operator(
            $property,
            PropertyBookingPermission::ACCESS_POB,
            PropertyBookingPermission::MANAGE_GUESTS,
        );
        $register = ReceptionRegister::factory()->for($property)->create();
        $shift = app(ReceptionShiftService::class)->open(
            $register,
            $receptionist,
            $manager,
            $openingFloatMinor,
        );

        return [$receptionist, $shift, $register, $manager];
    }

    /** Create a guest that satisfies the default protected-identity POB policy. */
    private function verifiedGuest(): Guest
    {
        return app(GuestService::class)->create([
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->e164PhoneNumber(),
            'country_code' => 'KE',
            'identity_type' => 'national_id',
            'identity_number' => fake()->unique()->numerify('TEST-ID-########'),
            'identity_country_code' => 'KE',
        ]);
    }

    /** Create one globally authorized active booking manager. */
    private function manager(): User
    {
        $manager = User::factory()->create([
            'user_type' => UserType::SystemAdministrator,
            'is_active' => true,
        ]);
        $manager->assignRole(UserType::SystemAdministrator->value);

        return $manager;
    }

    /** Create and explicitly assign a scoped operator to one property. */
    private function operator(Property $property, string ...$permissions): User
    {
        $operator = User::factory()->create(['is_active' => true]);
        $operator->givePermissionTo($permissions);
        DB::table('property_booking_property_user')->insert([
            'property_id' => $property->getKey(),
            'user_id' => $operator->getKey(),
            'assigned_by' => null,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $operator;
    }
}
