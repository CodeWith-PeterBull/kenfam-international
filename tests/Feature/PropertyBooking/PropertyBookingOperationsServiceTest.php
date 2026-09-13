<?php

declare(strict_types=1);

namespace Tests\Feature\PropertyBooking;

use App\Enums\UserType;
use App\Models\User;
use App\Modules\PropertyBooking\Bookings\Data\AdministrationPaymentData;
use App\Modules\PropertyBooking\Bookings\Data\BookingModificationData;
use App\Modules\PropertyBooking\Bookings\Enums\BookingPaymentMethod;
use App\Modules\PropertyBooking\Bookings\Enums\BookingStatus;
use App\Modules\PropertyBooking\Bookings\Enums\StayStatus;
use App\Modules\PropertyBooking\Bookings\Enums\UnitAssignmentStatus;
use App\Modules\PropertyBooking\Bookings\Exceptions\BookingOperationException;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Bookings\Services\BookingAdministrationPaymentService;
use App\Modules\PropertyBooking\Bookings\Services\BookingLifecycleService;
use App\Modules\PropertyBooking\Bookings\Services\BookingModificationService;
use App\Modules\PropertyBooking\Bookings\Services\BookingService;
use App\Modules\PropertyBooking\Catalog\Enums\UnitOperationalStatus;
use App\Modules\PropertyBooking\Catalog\Exceptions\CatalogException;
use App\Modules\PropertyBooking\Catalog\Models\AccommodationUnit;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Catalog\Models\UnitType;
use App\Modules\PropertyBooking\Catalog\Services\AccommodationUnitService;
use App\Modules\PropertyBooking\Guests\Models\Guest;
use App\Modules\PropertyBooking\Pricing\Models\RatePlan;
use App\Modules\PropertyBooking\Pricing\Services\BookingQuoteService;
use App\Modules\PropertyBooking\Support\PropertyBookingPermission;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Verifies locked booking lifecycle, modification, settlement, and scope invariants. */
final class PropertyBookingOperationsServiceTest extends TestCase
{
    use RefreshDatabase;

    /** Seed capabilities used by the service-scope fixtures. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    /** Restore the shared test clock after lifecycle timing assertions. */
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    /** Require the configured deposit before explicitly confirming a pending booking. */
    public function test_confirmation_requires_deposit_and_preserves_active_allocation(): void
    {
        [$property, , $ratePlan] = $this->inventory();
        $booking = $this->pendingBooking($ratePlan);
        $manager = $this->manager();
        $lifecycle = app(BookingLifecycleService::class);

        try {
            $lifecycle->confirm($booking, $manager);
            $this->fail('An unpaid deposit-backed booking was confirmed.');
        } catch (BookingOperationException $exception) {
            $this->assertStringContainsString('payment requirement', $exception->getMessage());
        }

        app(BookingAdministrationPaymentService::class)->recordCompleted(
            $booking,
            new AdministrationPaymentData(
                BookingPaymentMethod::MobileMoney,
                (int) $booking->required_deposit_minor,
                'OPS-CONFIRM-DEPOSIT-001',
            ),
            $manager,
        );
        $confirmed = $lifecycle->confirm($booking, $manager);

        $this->assertSame(BookingStatus::Confirmed, $confirmed->status);
        $this->assertNotNull($confirmed->confirmed_at);
        $this->assertNull($confirmed->pending_expires_at);
        $this->assertSame(UnitAssignmentStatus::Active, $confirmed->unitAssignments()->sole()->status);
        $this->assertDatabaseHas('system_activities', ['activity_type' => 'property-booking.booking.confirmed']);
    }

    /** Check in, protect occupied readiness, then check out and dirty the released unit. */
    public function test_check_in_and_checkout_update_occupants_assignments_and_readiness(): void
    {
        [, , $ratePlan] = $this->inventory();
        $booking = $this->pendingBooking($ratePlan);
        $manager = $this->manager();
        $payments = app(BookingAdministrationPaymentService::class);
        $payments->recordCompleted(
            $booking,
            new AdministrationPaymentData(BookingPaymentMethod::Card, (int) $booking->total_minor, 'OPS-FULL-001'),
            $manager,
        );
        $booking = app(BookingLifecycleService::class)->confirm($booking, $manager);
        $unit = $booking->unitAssignments()->active()->with('unit')->sole()->unit;

        CarbonImmutable::setTestNow(CarbonImmutable::instance($booking->starts_at));
        $checkedIn = app(BookingLifecycleService::class)->checkIn($booking, $manager);
        $this->assertSame(StayStatus::CheckedIn, $checkedIn->stay_status);
        $this->assertDatabaseHas('property_booking_guest_assignments', [
            'booking_id' => $booking->getKey(),
            'guest_id' => $booking->primary_guest_id,
        ]);
        $this->assertNotNull(DB::table('property_booking_guest_assignments')
            ->where('booking_id', $booking->getKey())
            ->value('checked_in_at'));

        try {
            app(AccommodationUnitService::class)->transitionReadiness($unit, UnitOperationalStatus::Dirty, $manager);
            $this->fail('An occupied unit was moved out of ready state.');
        } catch (CatalogException $exception) {
            $this->assertStringContainsString('checked-in booking', $exception->getMessage());
        }

        CarbonImmutable::setTestNow(CarbonImmutable::instance($booking->ends_at));
        $checkedOut = app(BookingLifecycleService::class)->checkOut($booking->fresh(), $manager);
        $this->assertSame(BookingStatus::Completed, $checkedOut->status);
        $this->assertSame(StayStatus::CheckedOut, $checkedOut->stay_status);
        $this->assertSame(UnitAssignmentStatus::Released, $checkedOut->unitAssignments()->sole()->status);
        $this->assertSame(UnitOperationalStatus::Dirty, $unit->fresh()->operational_status);
        $this->assertNotNull(DB::table('property_booking_guest_assignments')
            ->where('booking_id', $booking->getKey())
            ->value('checked_out_at'));
    }

    /** Release concrete availability for both cancellation and reviewed no-show transitions. */
    public function test_cancellation_and_no_show_release_assignments_and_reject_premature_review(): void
    {
        config()->set('property-booking.operations.confirmation_payment_policy', 'none');
        [$property, , $ratePlan] = $this->inventory(2);
        $manager = $this->manager();
        $lifecycle = app(BookingLifecycleService::class);
        $cancelledBooking = $lifecycle->confirm($this->pendingBooking($ratePlan), $manager);
        $cancelled = $lifecycle->cancel($cancelledBooking, 'Guest requested cancellation', $manager);

        $this->assertSame(BookingStatus::Cancelled, $cancelled->status);
        $this->assertSame(UnitAssignmentStatus::Released, $cancelled->unitAssignments()->sole()->status);

        $noShowBooking = $lifecycle->confirm($this->pendingBooking($ratePlan, 4), $manager);
        try {
            $lifecycle->markNoShow($noShowBooking, 'Guest did not arrive', $manager);
            $this->fail('A future arrival was marked no-show.');
        } catch (BookingOperationException $exception) {
            $this->assertStringContainsString('threshold', $exception->getMessage());
        }

        CarbonImmutable::setTestNow(
            CarbonImmutable::instance($noShowBooking->starts_at)
                ->addMinutes((int) config('property-booking.operations.no_show_after_minutes')),
        );
        $noShow = $lifecycle->markNoShow($noShowBooking, 'Guest did not arrive', $manager);
        $this->assertSame(BookingStatus::NoShow, $noShow->status);
        $this->assertSame(StayStatus::NoShow, $noShow->stay_status);
        $this->assertSame(UnitAssignmentStatus::Released, $noShow->unitAssignments()->sole()->status);
        $this->assertDatabaseHas('system_activities', ['activity_type' => 'property-booking.booking.no-show']);
        $this->assertSame($property->id, $noShow->property_id);
    }

    /** Reprice one interval under lock and preserve the exact unit through a historical reassignment. */
    public function test_interval_modification_reprices_and_reallocates_the_same_unit(): void
    {
        config()->set('property-booking.operations.confirmation_payment_policy', 'none');
        [, , $ratePlan] = $this->inventory();
        $manager = $this->manager();
        $booking = app(BookingLifecycleService::class)->confirm($this->pendingBooking($ratePlan), $manager);
        $originalAssignment = $booking->unitAssignments()->active()->with('unit')->sole();
        $originalTotal = (int) $booking->total_minor;
        $newEnd = CarbonImmutable::instance($booking->ends_at)->addDay();

        $modified = app(BookingModificationService::class)->modifyInterval(
            $booking,
            new BookingModificationData(
                CarbonImmutable::instance($booking->starts_at),
                $newEnd,
                'Guest extended the stay by one night',
            ),
            $manager,
        );

        $this->assertTrue($modified->ends_at->equalTo($newEnd));
        $this->assertGreaterThan($originalTotal, $modified->total_minor);
        $this->assertSame(UnitAssignmentStatus::Released, $originalAssignment->fresh()->status);
        $active = $modified->unitAssignments()->active()->sole();
        $this->assertSame($originalAssignment->unit_id, $active->unit_id);
        $this->assertSame(2, $modified->unitAssignments()->count());
        $this->assertDatabaseHas('system_activities', ['activity_type' => 'property-booking.booking.modified']);
    }

    /** Move an in-house stay atomically and mark only the vacated unit dirty. */
    public function test_in_house_unit_move_records_history_and_dirties_the_vacated_unit(): void
    {
        config()->set('property-booking.operations.confirmation_payment_policy', 'none');
        config()->set('property-booking.operations.check_in_payment_policy', 'none');
        [, , $ratePlan, $units] = $this->inventory(2);
        $manager = $this->manager();
        $booking = app(BookingLifecycleService::class)->confirm($this->pendingBooking($ratePlan), $manager);
        $oldAssignment = $booking->unitAssignments()->active()->sole();
        $target = collect($units)->firstWhere('id', '!=', $oldAssignment->unit_id);
        $this->assertInstanceOf(AccommodationUnit::class, $target);
        CarbonImmutable::setTestNow(CarbonImmutable::instance($booking->starts_at));
        $booking = app(BookingLifecycleService::class)->checkIn($booking, $manager);

        $moved = app(BookingModificationService::class)->moveUnit(
            $booking,
            $target,
            'Guest requested a quieter room',
            $manager,
        );

        $this->assertSame(UnitAssignmentStatus::Released, $oldAssignment->fresh()->status);
        $this->assertSame(UnitOperationalStatus::Dirty, $oldAssignment->unit->fresh()->operational_status);
        $this->assertSame($target->id, $moved->unitAssignments()->active()->sole()->unit_id);
        $this->assertSame(UnitOperationalStatus::Ready, $target->fresh()->operational_status);
        $this->assertDatabaseHas('system_activities', ['activity_type' => 'property-booking.booking.unit-moved']);
    }

    /** Reject cash, duplicate references, overpayment, and cross-property administration. */
    public function test_administration_payment_is_bounded_unique_and_property_scoped(): void
    {
        [, , $ratePlan] = $this->inventory();
        $booking = $this->pendingBooking($ratePlan);
        $manager = $this->manager();
        $service = app(BookingAdministrationPaymentService::class);
        $service->recordCompleted(
            $booking,
            new AdministrationPaymentData(BookingPaymentMethod::BankTransfer, 10_000, 'OPS-UNIQUE-001'),
            $manager,
        );

        foreach ([
            new AdministrationPaymentData(BookingPaymentMethod::BankTransfer, 10_000, 'OPS-UNIQUE-001'),
            new AdministrationPaymentData(BookingPaymentMethod::Cash, 10_000, 'CASH-NOT-ALLOWED'),
            new AdministrationPaymentData(BookingPaymentMethod::Card, (int) $booking->total_minor + 1, 'OPS-OVERPAY-001'),
        ] as $invalid) {
            try {
                $service->recordCompleted($booking->fresh(), $invalid, $manager);
                $this->fail('An invalid administration payment was recorded.');
            } catch (BookingOperationException) {
                $this->assertTrue(true);
            }
        }

        $otherProperty = Property::factory()->create();
        $operator = $this->operator($otherProperty, PropertyBookingPermission::MANAGE_PAYMENTS);
        $this->expectException(AuthorizationException::class);
        $service->recordCompleted(
            $booking->fresh(),
            new AdministrationPaymentData(BookingPaymentMethod::Card, 10_000, 'OPS-SCOPE-001'),
            $operator,
        );
    }

    /** @return array{Property, UnitType, RatePlan, list<AccommodationUnit>} */
    private function inventory(int $unitCount = 1): array
    {
        $property = Property::factory()->published()->create([
            'timezone' => 'Africa/Nairobi',
            'currency' => 'KES',
            'check_in_from' => null,
            'check_in_until' => null,
            'check_out_from' => null,
            'check_out_until' => null,
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

    /** Place one exact-unit pending web booking through the canonical services. */
    private function pendingBooking(RatePlan $ratePlan, int $arrivalDays = 3): Booking
    {
        $startsAt = CarbonImmutable::now($ratePlan->property->timezone)
            ->addDays($arrivalDays)
            ->setTime(14, 0)
            ->utc();
        $quote = app(BookingQuoteService::class)->quote(
            $ratePlan,
            $startsAt,
            $startsAt->addDay()->subHours(3),
            2,
            0,
        );

        return app(BookingService::class)->placeWeb(
            $quote,
            $ratePlan,
            Guest::factory()->create(),
            BookingPaymentMethod::MobileMoney,
        );
    }

    /** Create one global active administrator. */
    private function manager(): User
    {
        $manager = User::factory()->create(['user_type' => UserType::SystemAdministrator, 'is_active' => true]);
        $manager->assignRole(UserType::SystemAdministrator->value);

        return $manager;
    }

    /** Create one explicitly property-scoped operator. */
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
