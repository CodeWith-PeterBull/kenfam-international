<?php

declare(strict_types=1);

namespace Tests\Feature\PropertyBooking;

use App\Enums\UserType;
use App\Models\User;
use App\Modules\PropertyBooking\Bookings\Enums\BookingChannel;
use App\Modules\PropertyBooking\Bookings\Enums\BookingStatus;
use App\Modules\PropertyBooking\Bookings\Enums\StayStatus;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Bookings\Models\BookingPayment;
use App\Modules\PropertyBooking\Bookings\Models\BookingStay;
use App\Modules\PropertyBooking\Bookings\Models\UnitAssignment;
use App\Modules\PropertyBooking\Catalog\Enums\UnitOperationalStatus;
use App\Modules\PropertyBooking\Catalog\Models\AccommodationUnit;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Catalog\Models\UnitType;
use App\Modules\PropertyBooking\Operations\Enums\OperationsDashboardRange;
use App\Modules\PropertyBooking\Operations\Services\PropertyBookingDashboardService;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionRegister;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionShift;
use App\Modules\PropertyBooking\Pricing\Models\RatePlan;
use App\Modules\PropertyBooking\Support\PropertyBookingPermission;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Verifies the property-scoped accommodation dashboard read model and page. */
final class PropertyBookingDashboardTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);
        $this->now = CarbonImmutable::parse('2026-09-01 12:00:00', 'UTC');
        CarbonImmutable::setTestNow($this->now);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    /** Enforce the dedicated capability and retain the system administrator override. */
    public function test_dashboard_route_requires_permission_and_renders_a_stable_empty_state(): void
    {
        $plain = User::factory()->create(['is_active' => true]);
        $operator = User::factory()->create(['is_active' => true]);
        $operator->givePermissionTo(PropertyBookingPermission::VIEW_DASHBOARD);

        $this->get(route('property-booking.admin.dashboard'))->assertRedirect(route('login'));
        $this->actingAs($plain)->get(route('property-booking.admin.dashboard'))->assertForbidden();
        $response = $this->actingAs($operator)
            ->get(route('property-booking.admin.dashboard'))
            ->assertOk()
            ->assertSee('data-property-booking-dashboard', false)
            ->assertSee(asset('build/plugins/chartjs/chart.min.js'), false)
            ->assertSee('property-booking-dashboard-chart-data', false)
            ->assertSee(route('property-booking.storefront.catalog.index'), false)
            ->assertSee('Stay storefront')
            ->assertSee('Public stays')
            ->assertSee('No bookings have been placed.')
            ->assertSee('No reception shift is currently open.');
        $this->assertSame(8, substr_count($response->getContent(), 'data-property-booking-metric'));

        $role = Role::findByName(UserType::SystemAdministrator->value, 'web');
        $role->revokePermissionTo(PropertyBookingPermission::VIEW_DASHBOARD);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $administrator = User::factory()->create([
            'user_type' => UserType::SystemAdministrator,
            'is_active' => true,
        ]);
        $administrator->assignRole($role);

        $this->actingAs($administrator)
            ->get(route('property-booking.admin.dashboard'))
            ->assertOk()
            ->assertSee('Accommodation operations');
    }

    /** Prove scope, currency, lifecycle, local-calendar, readiness, and shift semantics. */
    public function test_snapshot_uses_exact_scoped_operational_semantics(): void
    {
        $property = Property::factory()->create([
            'timezone' => 'Africa/Nairobi',
            'currency' => 'KES',
        ]);
        $otherProperty = Property::factory()->create(['currency' => 'KES']);
        $operator = $this->assignedUser(
            $property,
            PropertyBookingPermission::VIEW_DASHBOARD,
            PropertyBookingPermission::VIEW_BOOKINGS,
            PropertyBookingPermission::MANAGE_READINESS,
            PropertyBookingPermission::MANAGE_SHIFTS,
        );

        $pending = $this->booking($property, 100_000, BookingStatus::Pending, StayStatus::Expected, BookingChannel::Web);
        $arrival = $this->booking($property, 200_000, BookingStatus::Confirmed, StayStatus::Expected, BookingChannel::Admin, [
            'starts_at' => $this->now->setTime(10, 0),
            'ends_at' => $this->now->addDay()->setTime(8, 0),
        ]);
        $inHouse = $this->booking($property, 300_000, BookingStatus::Confirmed, StayStatus::CheckedIn, BookingChannel::PointOfBooking, [
            'starts_at' => $this->now->subDay(),
            'ends_at' => $this->now->setTime(8, 0),
            'checked_in_at' => $this->now->subDay(),
        ]);
        $this->booking($property, 400_000, BookingStatus::Cancelled, StayStatus::Expected, BookingChannel::Web, [
            'cancelled_at' => $this->now,
        ]);
        $this->booking($property, 500_000, BookingStatus::Pending, StayStatus::Expected, BookingChannel::Web, [
            'currency' => 'USD',
        ]);
        $this->booking($otherProperty, 900_000, BookingStatus::Confirmed, StayStatus::Expected, BookingChannel::Web);

        BookingPayment::factory()->for($arrival, 'booking')->create([
            'recorded_by' => $operator->id,
            'amount_minor' => 70_000,
            'paid_at' => $this->now,
        ]);

        $unitType = UnitType::factory()->for($property)->create();
        $ratePlan = RatePlan::factory()->forUnitType($unitType)->active()->create();
        $occupied = AccommodationUnit::factory()->forUnitType($unitType)->create();
        AccommodationUnit::factory()->forUnitType($unitType)->create(['operational_status' => UnitOperationalStatus::Dirty]);
        AccommodationUnit::factory()->forUnitType($unitType)->create(['operational_status' => UnitOperationalStatus::Cleaning]);
        $stay = BookingStay::factory()->create([
            'booking_id' => $inHouse->id,
            'rate_plan_id' => $ratePlan->id,
            'unit_type_id' => $unitType->id,
        ]);
        UnitAssignment::factory()->create([
            'booking_stay_id' => $stay->id,
            'booking_id' => $inHouse->id,
            'property_id' => $property->id,
            'unit_id' => $occupied->id,
        ]);

        $register = ReceptionRegister::factory()->for($property)->create();
        ReceptionShift::factory()->create([
            'register_id' => $register->id,
            'property_id' => $property->id,
            'receptionist_id' => $operator->id,
            'opened_by' => $operator->id,
            'expected_cash_minor' => 25_000,
        ]);

        $snapshot = app(PropertyBookingDashboardService::class)->snapshot(
            $operator,
            OperationsDashboardRange::ThirtyDays,
            $this->now,
        );

        $this->assertSame(600_000, $snapshot->bookedValueMinor);
        $this->assertSame(70_000, $snapshot->paymentsCollectedMinor);
        $this->assertSame(5, $snapshot->bookingsReceived);
        $this->assertSame(200_000, $snapshot->averageBookingValueMinor);
        $this->assertSame(1, $snapshot->mixedCurrencyBookingsExcluded);
        $this->assertSame(1, $snapshot->arrivalsToday);
        $this->assertSame(1, $snapshot->departuresToday);
        $this->assertSame(1, $snapshot->inHouseStays);
        $this->assertSame(4, $snapshot->activeBookings);
        $this->assertSame(1, $snapshot->occupiedUnits);
        $this->assertSame(3, $snapshot->activeUnits);
        $this->assertSame(33, $snapshot->occupancyPercentage());
        $this->assertSame(1, $snapshot->openShiftCount);
        $this->assertSame(1, $snapshot->readinessCounts[UnitOperationalStatus::Ready->value]);
        $this->assertSame(1, $snapshot->readinessCounts[UnitOperationalStatus::Dirty->value]);
        $this->assertSame(1, $snapshot->readinessCounts[UnitOperationalStatus::Cleaning->value]);
        $this->assertCount(30, $snapshot->bookingTrend);
        $this->assertSame(600_000, $snapshot->bookingTrend[29]->totalMinor());
        $this->assertCount(4, $snapshot->actionQueue);
        $this->assertCount(5, $snapshot->recentBookings);
        $this->assertCount(1, $snapshot->activeShifts);
        $this->assertContains(
            $pending->booking_number,
            array_map(static fn ($booking): string => $booking->bookingNumber, $snapshot->recentBookings),
        );

        $this->actingAs($operator)
            ->get(route('property-booking.admin.dashboard', ['range' => 30]))
            ->assertOk()
            ->assertSee(route('property-booking.admin.bookings.index'), false)
            ->assertSee(route('property-booking.admin.readiness.index'), false)
            ->assertSee(route('property-booking.pob.admin.shifts.index'), false)
            ->assertDontSee('900000');
    }

    /** Reject unsupported ranges rather than silently changing reporting semantics. */
    public function test_dashboard_rejects_unsupported_ranges(): void
    {
        $operator = User::factory()->create(['is_active' => true]);
        $operator->givePermissionTo(PropertyBookingPermission::VIEW_DASHBOARD);

        $this->actingAs($operator)
            ->from(route('property-booking.admin.dashboard'))
            ->get(route('property-booking.admin.dashboard', ['range' => 13]))
            ->assertRedirect(route('property-booking.admin.dashboard'))
            ->assertSessionHasErrors('range');
    }

    /** Create one numbered booking inside the active period. */
    private function booking(
        Property $property,
        int $totalMinor,
        BookingStatus $status,
        StayStatus $stayStatus,
        BookingChannel $channel,
        array $extra = [],
    ): Booking {
        return Booking::factory()->forProperty($property)->create([
            'booking_number' => strtoupper($channel->value).'-'.$property->id.'-'.fake()->unique()->numerify('######'),
            'channel' => $channel,
            'status' => $status,
            'stay_status' => $stayStatus,
            'total_minor' => $totalMinor,
            'accommodation_subtotal_minor' => $totalMinor,
            'required_deposit_minor' => 0,
            'paid_minor' => 0,
            'placed_at' => $this->now,
            'confirmed_at' => $status === BookingStatus::Confirmed ? $this->now : null,
            'hold_expires_at' => null,
            ...$extra,
        ]);
    }

    /** Create an active operator assigned to exactly one property. */
    private function assignedUser(Property $property, string ...$permissions): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->givePermissionTo($permissions);
        DB::table('property_booking_property_user')->insert([
            'property_id' => $property->id,
            'user_id' => $user->id,
            'assigned_by' => null,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }
}
