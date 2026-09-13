<?php

declare(strict_types=1);

namespace Tests\Feature\PropertyBooking;

use App\Enums\UserType;
use App\Models\User;
use App\Modules\PropertyBooking\Bookings\Enums\BookingPaymentStatus;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Catalog\Models\AccommodationUnit;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Catalog\Models\UnitType;
use App\Modules\PropertyBooking\Guests\Models\Guest;
use App\Modules\PropertyBooking\Guests\Services\GuestService;
use App\Modules\PropertyBooking\PointOfBooking\Enums\ReceptionShiftStatus;
use App\Modules\PropertyBooking\PointOfBooking\Livewire\Admin\ReceptionRegisterManager;
use App\Modules\PropertyBooking\PointOfBooking\Livewire\Admin\ReceptionShiftManager;
use App\Modules\PropertyBooking\PointOfBooking\Livewire\Terminal;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionRegister;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionShift;
use App\Modules\PropertyBooking\PointOfBooking\Services\ReceptionShiftService;
use App\Modules\PropertyBooking\Pricing\Models\RatePlan;
use App\Modules\PropertyBooking\Support\PropertyBookingPermission;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** Verifies POB routes, administration workspaces, and the reception terminal. */
final class PropertyBookingPobInterfaceTest extends TestCase
{
    use RefreshDatabase;

    /** Seed capabilities before exercising route and Livewire authorization. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    /** Guard each POB surface with its dedicated terminal or shift capability. */
    public function test_pob_routes_enforce_terminal_and_shift_management_permissions(): void
    {
        $plainUser = User::factory()->create(['is_active' => true]);
        $receptionist = $this->userWith(PropertyBookingPermission::ACCESS_POB);
        $manager = $this->userWith(PropertyBookingPermission::MANAGE_SHIFTS);

        $this->get(route('property-booking.pob.terminal'))->assertRedirect(route('login'));
        $this->actingAs($plainUser)->get(route('property-booking.pob.terminal'))->assertForbidden();
        $this->actingAs($receptionist)->get(route('property-booking.pob.terminal'))
            ->assertOk()
            ->assertSee('No open shift assigned');
        $this->actingAs($receptionist)->get(route('property-booking.pob.admin.registers.index'))->assertForbidden();
        $this->actingAs($receptionist)->get(route('property-booking.pob.admin.shifts.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('property-booking.pob.terminal'))->assertForbidden();
        $this->actingAs($manager)->get(route('property-booking.pob.admin.registers.index'))
            ->assertOk()
            ->assertSeeLivewire('property-booking.pob.admin.reception-register-manager')
            ->assertSee('aria-label="Register statistics"', false);
        $this->actingAs($manager)->get(route('property-booking.pob.admin.shifts.index'))
            ->assertOk()
            ->assertSeeLivewire('property-booking.pob.admin.reception-shift-manager')
            ->assertSee('aria-label="Reception shift statistics"', false);
    }

    /** Configure a register and complete shift opening and reconciliation through Livewire forms. */
    public function test_register_and_shift_managers_complete_operational_setup(): void
    {
        $property = Property::factory()->create();
        $manager = $this->administrator();
        $receptionist = $this->assignedUser(
            $property,
            PropertyBookingPermission::ACCESS_POB,
            PropertyBookingPermission::MANAGE_GUESTS,
        );

        Livewire::actingAs($manager)
            ->test(ReceptionRegisterManager::class)
            ->call('openCreate')
            ->set('form.propertyId', (string) $property->id)
            ->set('form.name', 'Main Lobby Desk')
            ->set('form.code', 'lobby-01')
            ->set('form.locationLabel', 'Ground floor')
            ->set('form.receiptPrintMode', 'auto_prompt')
            ->set('form.receiptPaperWidth', '58')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('dialog', '');

        $register = ReceptionRegister::query()->sole();
        $this->assertSame('LOBBY-01', $register->code);
        $this->assertSame('auto_prompt', $register->receipt_print_mode->value);
        $this->assertSame(58, $register->receipt_paper_width->value);

        Livewire::actingAs($manager)
            ->test(ReceptionShiftManager::class)
            ->call('openShiftDialog')
            ->set('openForm.registerId', (string) $register->id)
            ->set('openForm.receptionistId', (string) $receptionist->id)
            ->set('openForm.openingFloat', '2500.00')
            ->call('openShift')
            ->assertHasNoErrors()
            ->assertSet('dialog', '');

        $shift = ReceptionShift::query()->sole();
        $this->assertSame($receptionist->id, $shift->receptionist_id);
        $this->assertSame($manager->id, $shift->opened_by);
        $this->assertSame(250_000, $shift->opening_float_minor);

        Livewire::actingAs($manager)
            ->test(ReceptionRegisterManager::class)
            ->call('toggleActive', $register->id)
            ->assertHasErrors('management');

        Livewire::actingAs($manager)
            ->test(ReceptionShiftManager::class)
            ->call('openCloseDialog', $shift->id)
            ->set('closeForm.countedCash', '2500.00')
            ->call('closeShift')
            ->assertHasNoErrors();

        $this->assertSame(ReceptionShiftStatus::Closed, $shift->fresh()->status);
    }

    /** Create a guest and complete an exact cash booking through the full-width terminal. */
    public function test_receptionist_completes_a_booking_through_the_livewire_terminal(): void
    {
        [$property, $ratePlan] = $this->inventory();
        $manager = $this->administrator();
        $receptionist = $this->assignedUser(
            $property,
            PropertyBookingPermission::ACCESS_POB,
            PropertyBookingPermission::MANAGE_GUESTS,
        );
        $register = ReceptionRegister::factory()->for($property)->create();
        $shift = app(ReceptionShiftService::class)->open($register, $receptionist, $manager, 10_000);

        $component = Livewire::actingAs($receptionist)
            ->test(Terminal::class)
            ->assertSet('shiftUlid', $shift->ulid)
            ->call('toggleGuestForm')
            ->set('guestFirstName', 'Amina')
            ->set('guestLastName', 'Otieno')
            ->set('guestEmail', 'amina.pob@example.test')
            ->set('guestCountryCode', 'KE')
            ->set('guestIdentityType', 'national_id')
            ->set('guestIdentityNumber', 'ID-9988-1100')
            ->call('createGuest')
            ->assertHasNoErrors()
            ->call('selectRate', $ratePlan->id)
            ->call('applyTotal', 0)
            ->call('completeBooking')
            ->assertHasNoErrors();

        $booking = Booking::query()->with(['payments', 'primaryGuest'])->sole();
        $this->assertSame(BookingPaymentStatus::Paid, $booking->payment_status);
        $this->assertSame($receptionist->id, $booking->receptionist_id);
        $this->assertSame('amina.pob@example.test', $booking->primaryGuest?->email);
        $this->assertSame(1, $booking->payments->count());
        $this->assertSame(1, Guest::query()->count());
        $this->assertNotNull($booking->primaryGuest?->identity_number_hash);
        $component->assertRedirect(route('property-booking.pob.receipts.show', [
            'booking' => $booking->ulid,
            'print' => 'checkout',
        ]));
    }

    /** Permit adopters to make POB identity optional without changing terminal code. */
    public function test_pob_identity_requirement_can_be_disabled_by_configuration(): void
    {
        config()->set('property-booking.pob.guest_identity_required', false);
        [$property, $ratePlan] = $this->inventory();
        $manager = $this->administrator();
        $receptionist = $this->assignedUser(
            $property,
            PropertyBookingPermission::ACCESS_POB,
            PropertyBookingPermission::MANAGE_GUESTS,
        );
        $register = ReceptionRegister::factory()->for($property)->create();
        app(ReceptionShiftService::class)->open($register, $receptionist, $manager, 0);

        Livewire::actingAs($receptionist)
            ->test(Terminal::class)
            ->call('toggleGuestForm')
            ->set('guestFirstName', 'Optional')
            ->set('guestLastName', 'Identity')
            ->set('guestEmail', 'optional.identity@example.test')
            ->call('createGuest')
            ->assertHasNoErrors()
            ->call('selectRate', $ratePlan->id)
            ->call('applyTotal', 0)
            ->call('completeBooking')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertNull(Guest::query()->sole()->identity_number_hash);
        $this->assertDatabaseCount((new Booking)->getTable(), 1);
    }

    /** Prefill a same-minute walk-in and retain next-day property checkout defaults. */
    public function test_now_action_returns_walk_in_availability_despite_public_advance_notice(): void
    {
        $now = CarbonImmutable::parse('2026-09-01 10:37:42', 'Africa/Nairobi');
        CarbonImmutable::setTestNow($now);

        try {
            [$property] = $this->inventory(60);
            $manager = $this->administrator();
            $receptionist = $this->assignedUser(
                $property,
                PropertyBookingPermission::ACCESS_POB,
                PropertyBookingPermission::MANAGE_GUESTS,
            );
            $register = ReceptionRegister::factory()->for($property)->create();
            app(ReceptionShiftService::class)->open($register, $receptionist, $manager, 0);

            Livewire::actingAs($receptionist)
                ->test(Terminal::class)
                ->call('useCurrentArrival')
                ->assertSet('arrivalDate', '2026-09-01')
                ->assertSet('arrivalTime', '10:37')
                ->assertSet('departureDate', '2026-09-02')
                ->assertSet('departureTime', '10:00')
                ->assertSee('1 matches');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /** Upgrade a selected pre-policy guest without decrypting or exposing identity values. */
    public function test_receptionist_can_record_identity_for_a_selected_legacy_guest(): void
    {
        [$property] = $this->inventory();
        $manager = $this->administrator();
        $receptionist = $this->administrator();
        $register = ReceptionRegister::factory()->for($property)->create();
        app(ReceptionShiftService::class)->open($register, $receptionist, $manager, 0);
        $guest = Guest::factory()->create([
            'first_name' => 'Legacy',
            'last_name' => 'Guest',
            'email' => 'legacy.guest@example.test',
        ]);

        Livewire::actingAs($receptionist)
            ->test(Terminal::class)
            ->set('guestSearch', 'legacy.guest')
            ->call('selectGuest', $guest->id)
            ->assertSee('ID or passport required')
            ->set('guestIdentityType', 'passport')
            ->set('guestIdentityNumber', 'PASS-LEGACY-001')
            ->call('recordSelectedGuestIdentity')
            ->assertHasNoErrors()
            ->assertDontSee('ID or passport required');

        $this->assertNotNull($guest->fresh()->identity_number_hash);
        $this->assertSame('PASS-LEGACY-001', app(GuestService::class)->revealIdentity($guest->fresh()));
    }

    /** @return array{Property, RatePlan} */
    private function inventory(int $minimumAdvanceMinutes = 0): array
    {
        $property = Property::factory()->published()->create([
            'timezone' => 'Africa/Nairobi',
            'currency' => 'KES',
            'minimum_notice_minutes' => $minimumAdvanceMinutes,
            'maximum_advance_days' => 365,
            'turnover_minutes' => 0,
            'check_in_from' => '14:00',
            'check_out_until' => '10:00',
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
            'minimum_advance_minutes' => $minimumAdvanceMinutes,
        ]);
        AccommodationUnit::factory()->forUnitType($unitType)->create();

        return [$property, $ratePlan];
    }

    /** Create a globally authorized active system administrator. */
    private function administrator(): User
    {
        $administrator = User::factory()->create([
            'user_type' => UserType::SystemAdministrator,
            'is_active' => true,
        ]);
        $administrator->assignRole(UserType::SystemAdministrator->value);

        return $administrator;
    }

    /** Create one active user with direct capabilities. */
    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->givePermissionTo($permissions);

        return $user;
    }

    /** Create an active user with capabilities and explicit property scope. */
    private function assignedUser(Property $property, string ...$permissions): User
    {
        $user = $this->userWith(...$permissions);
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
