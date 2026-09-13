<?php

declare(strict_types=1);

namespace Tests\Feature\PropertyBooking;

use App\Enums\UserType;
use App\Models\User;
use App\Modules\PropertyBooking\Bookings\Livewire\Admin\BookingManager;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Catalog\Enums\UnitOperationalStatus;
use App\Modules\PropertyBooking\Catalog\Models\AccommodationUnit;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Catalog\Models\UnitType;
use App\Modules\PropertyBooking\Guests\Livewire\Admin\GuestManager;
use App\Modules\PropertyBooking\Guests\Models\Guest;
use App\Modules\PropertyBooking\Operations\Livewire\Admin\UnitReadinessManager;
use App\Modules\PropertyBooking\Support\PropertyBookingPermission;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** Verifies Phase 5 routes, Livewire authorization, privacy, and readiness writes. */
final class PropertyBookingOperationsInterfaceTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->administrator = User::factory()->create([
            'user_type' => UserType::SystemAdministrator,
            'is_active' => true,
        ]);
        $this->administrator->assignRole(UserType::SystemAdministrator->value);
    }

    /** Ensure every operations route is authenticated, capability guarded, and module rendered. */
    public function test_operations_routes_enforce_capabilities_and_render_livewire_managers(): void
    {
        $routes = [
            'property-booking.admin.bookings.index' => 'property-booking.admin.booking-manager',
            'property-booking.admin.guests.index' => 'property-booking.admin.guest-manager',
            'property-booking.admin.readiness.index' => 'property-booking.admin.unit-readiness-manager',
        ];

        foreach (array_keys($routes) as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }

        $plainUser = User::factory()->create(['is_active' => true]);
        foreach (array_keys($routes) as $route) {
            $this->actingAs($plainUser)->get(route($route))->assertForbidden();
        }

        foreach ($routes as $route => $component) {
            $this->actingAs($this->administrator)
                ->get(route($route))
                ->assertOk()
                ->assertSeeLivewire($component);
        }
    }

    /** Persist a complete guest profile without exposing its identity number in plaintext. */
    public function test_guest_manager_creates_an_encrypted_profile_and_archives_it(): void
    {
        $component = Livewire::actingAs($this->administrator)
            ->test(GuestManager::class)
            ->call('openCreate')
            ->set('form.firstName', 'Amina')
            ->set('form.middleName', 'Wanjiru')
            ->set('form.lastName', 'Kamau')
            ->set('form.email', 'amina@example.test')
            ->set('form.phone', '+254700111222')
            ->set('form.identityType', 'passport')
            ->set('form.identityNumber', 'P-998877')
            ->set('form.identityCountryCode', 'KE')
            ->set('form.city', 'Nairobi')
            ->set('form.countryCode', 'KE')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Guest profile created.');

        $guest = Guest::query()->sole();
        $this->assertNotNull($guest->identity_number_ciphertext);
        $this->assertNotSame('P-998877', $guest->identity_number_ciphertext);
        $this->assertNotNull($guest->identity_number_hash);

        $component->call('archive', $guest->id)->assertHasNoErrors();
        $this->assertSoftDeleted('property_booking_guests', ['id' => $guest->id]);
        $this->assertDatabaseHas('system_activities', ['activity_type' => 'property-booking.guest.archived']);
    }

    /** Keep booking rows inside explicit property scope and protect mutations from viewers. */
    public function test_booking_manager_scopes_rows_and_reauthorizes_mutations(): void
    {
        $property = Property::factory()->create();
        $otherProperty = Property::factory()->create();
        $visible = Booking::factory()->forProperty($property)->pending()->create();
        $hidden = Booking::factory()->forProperty($otherProperty)->pending()->create();
        $viewer = $this->assignedUser($property, PropertyBookingPermission::VIEW_BOOKINGS);

        Livewire::actingAs($viewer)
            ->test(BookingManager::class)
            ->assertSee($visible->booking_number)
            ->assertDontSee($hidden->booking_number)
            ->call('openCancellation', $visible->id)
            ->assertForbidden();
    }

    /** Allow a scoped housekeeper to advance only assigned-property readiness. */
    public function test_readiness_manager_uses_the_canonical_transition_service_and_property_scope(): void
    {
        $property = Property::factory()->create();
        $unitType = UnitType::factory()->for($property)->create();
        $dirtyUnit = AccommodationUnit::factory()->forUnitType($unitType)->create([
            'operational_status' => UnitOperationalStatus::Dirty,
        ]);
        $otherUnit = AccommodationUnit::factory()->create([
            'operational_status' => UnitOperationalStatus::Dirty,
        ]);
        $housekeeper = $this->assignedUser($property, PropertyBookingPermission::MANAGE_READINESS);

        Livewire::actingAs($housekeeper)
            ->test(UnitReadinessManager::class)
            ->assertSee($dirtyUnit->code)
            ->assertDontSee($otherUnit->code)
            ->call('changeReadiness', $dirtyUnit->id, UnitOperationalStatus::Cleaning->value)
            ->assertHasNoErrors()
            ->assertSee("{$dirtyUnit->code} moved to Cleaning.");

        $this->assertSame(UnitOperationalStatus::Cleaning, $dirtyUnit->fresh()->operational_status);
        $this->assertDatabaseHas('system_activities', ['activity_type' => 'property-booking.unit.readiness-changed']);

        try {
            Livewire::actingAs($housekeeper)
                ->test(UnitReadinessManager::class)
                ->call('changeReadiness', $otherUnit->id, UnitOperationalStatus::Cleaning->value);
            $this->fail('A housekeeper changed readiness outside the assigned property.');
        } catch (ModelNotFoundException) {
            $this->assertSame(UnitOperationalStatus::Dirty, $otherUnit->fresh()->operational_status);
        }
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
