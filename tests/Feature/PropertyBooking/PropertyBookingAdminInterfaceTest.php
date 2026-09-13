<?php

declare(strict_types=1);

namespace Tests\Feature\PropertyBooking;

use App\Enums\UserType;
use App\Models\User;
use App\Modules\PropertyBooking\Availability\Livewire\Admin\AvailabilityManager;
use App\Modules\PropertyBooking\Availability\Models\AvailabilityBlock;
use App\Modules\PropertyBooking\Catalog\Livewire\Admin\AccommodationUnitManager;
use App\Modules\PropertyBooking\Catalog\Livewire\Admin\AmenityManager;
use App\Modules\PropertyBooking\Catalog\Livewire\Admin\PropertyCategoryManager;
use App\Modules\PropertyBooking\Catalog\Livewire\Admin\PropertyManager;
use App\Modules\PropertyBooking\Catalog\Livewire\Admin\UnitTypeManager;
use App\Modules\PropertyBooking\Catalog\Models\AccommodationUnit;
use App\Modules\PropertyBooking\Catalog\Models\Amenity;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Catalog\Models\PropertyCategory;
use App\Modules\PropertyBooking\Catalog\Models\UnitType;
use App\Modules\PropertyBooking\Pricing\Livewire\Admin\RateOverrideManager;
use App\Modules\PropertyBooking\Pricing\Livewire\Admin\RatePlanManager;
use App\Modules\PropertyBooking\Pricing\Models\RateOverride;
use App\Modules\PropertyBooking\Pricing\Models\RatePlan;
use App\Modules\PropertyBooking\Support\PropertyBookingPermission;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Verifies the complete authorized Phase 2 administration surface. */
final class PropertyBookingAdminInterfaceTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    /** Seed the shared permission catalogue and an active system administrator. */
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

    /** Confirm every module route enforces its own read capability and renders its managers. */
    public function test_admin_routes_are_guarded_and_render_module_owned_livewire_components(): void
    {
        $routes = [
            'property-booking.admin.properties.index' => ['property-booking.admin.property-manager', 'property-booking.admin.property-category-manager'],
            'property-booking.admin.amenities.index' => ['property-booking.admin.amenity-manager'],
            'property-booking.admin.units.index' => ['property-booking.admin.unit-type-manager', 'property-booking.admin.accommodation-unit-manager'],
            'property-booking.admin.rates.index' => ['property-booking.admin.rate-plan-manager', 'property-booking.admin.rate-override-manager'],
            'property-booking.admin.availability.index' => ['property-booking.admin.availability-manager'],
        ];

        foreach (array_keys($routes) as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }

        $viewer = User::factory()->create(['is_active' => true]);
        foreach (array_keys($routes) as $route) {
            $this->actingAs($viewer)->get(route($route))->assertForbidden();
        }

        $viewer->givePermissionTo(
            PropertyBookingPermission::VIEW_PROPERTIES,
            PropertyBookingPermission::VIEW_RATES,
            PropertyBookingPermission::VIEW_AVAILABILITY,
        );

        foreach ($routes as $route => $components) {
            $response = $this->actingAs($viewer)->get(route($route))->assertOk();
            foreach ($components as $component) {
                $response->assertSeeLivewire($component);
            }
        }
    }

    /** Confirm read-only operators cannot open any mutation flow. */
    public function test_view_only_operator_cannot_mutate_catalog_rates_or_availability(): void
    {
        $viewer = User::factory()->create(['is_active' => true]);
        $viewer->givePermissionTo(
            PropertyBookingPermission::VIEW_PROPERTIES,
            PropertyBookingPermission::VIEW_RATES,
            PropertyBookingPermission::VIEW_AVAILABILITY,
        );

        Livewire::actingAs($viewer)->test(PropertyManager::class)->call('openCreate')->assertForbidden();
        Livewire::actingAs($viewer)->test(UnitTypeManager::class)->call('openCreate')->assertForbidden();
        Livewire::actingAs($viewer)->test(RatePlanManager::class)->call('openCreate')->assertForbidden();
        Livewire::actingAs($viewer)->test(AvailabilityManager::class)->call('openBlock')->assertForbidden();
    }

    /** Exercise category, amenity, and property creation through Livewire forms. */
    public function test_administrator_can_create_the_property_catalog_through_livewire(): void
    {
        Livewire::actingAs($this->administrator)
            ->test(PropertyCategoryManager::class)
            ->call('openCreate')
            ->set('form.name', 'Serviced apartments')
            ->set('form.description', 'Professionally operated short-stay apartments.')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Property category created.');

        Livewire::actingAs($this->administrator)
            ->test(AmenityManager::class)
            ->call('openCreate')
            ->set('form.name', 'High-speed Wi-Fi')
            ->set('form.iconKey', 'wifi')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Amenity created.');

        $category = PropertyCategory::query()->sole();
        $amenity = Amenity::query()->sole();

        Livewire::actingAs($this->administrator)
            ->test(PropertyManager::class)
            ->call('openCreate')
            ->set('form.categoryId', (string) $category->id)
            ->set('form.code', 'NBO-RES-01')
            ->set('form.name', 'Aureon Riverside Residence')
            ->set('form.city', 'Nairobi')
            ->set('form.shortDescription', 'A quiet corporate residence near the city centre.')
            ->set('form.amenityIds', [$amenity->id])
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Property created as a draft.');

        $property = Property::query()->with('amenities')->sole();
        $this->assertSame('NBO-RES-01', $property->code);
        $this->assertSame('aureon-riverside-residence', $property->slug);
        $this->assertTrue($property->amenities->contains($amenity));
        $this->assertDatabaseHas('system_activities', ['activity_type' => 'property-booking.property.created']);
    }

    /** Exercise sellable type, concrete inventory, rate, override, and block creation. */
    public function test_administrator_can_build_a_rateable_and_blockable_inventory_chain(): void
    {
        $property = Property::factory()->create([
            'name' => 'Aureon City Suites',
            'code' => 'CITY-01',
            'timezone' => 'Africa/Nairobi',
        ]);

        Livewire::actingAs($this->administrator)
            ->test(UnitTypeManager::class)
            ->call('openCreate')
            ->set('form.propertyId', (string) $property->id)
            ->set('form.code', 'DELUXE-KING')
            ->set('form.name', 'Deluxe King Room')
            ->set('form.maximumGuests', 2)
            ->set('form.maximumAdults', 2)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Unit type created as a draft.');

        $unitType = UnitType::query()->sole();

        Livewire::actingAs($this->administrator)
            ->test(AccommodationUnitManager::class)
            ->call('openCreate')
            ->set('form.propertyId', (string) $property->id)
            ->set('form.unitTypeId', (string) $unitType->id)
            ->set('form.code', 'ROOM-401')
            ->set('form.displayName', 'Room 401')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Accommodation unit created.');

        Livewire::actingAs($this->administrator)
            ->test(RatePlanManager::class)
            ->call('openCreate')
            ->set('form.unitTypeId', (string) $unitType->id)
            ->set('form.code', 'FLEX-NIGHT')
            ->set('form.name', 'Flexible nightly rate')
            ->set('form.baseRate', '12500.00')
            ->set('form.includedAdults', 2)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Rate plan created as a draft.');

        $ratePlan = RatePlan::query()->sole();
        $this->assertSame(1_250_000, $ratePlan->base_rate_minor);

        Livewire::actingAs($this->administrator)
            ->test(RateOverrideManager::class)
            ->call('openCreate')
            ->set('targetRatePlanId', (string) $ratePlan->id)
            ->set('form.startsOn', now($property->timezone)->addDays(10)->toDateString())
            ->set('form.endsOn', now($property->timezone)->addDays(12)->toDateString())
            ->set('form.rate', '15000.00')
            ->set('form.reason', 'Conference demand')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Rate override created.');

        $unit = AccommodationUnit::query()->sole();
        $startsAt = now($property->timezone)->addDays(20)->setTime(10, 0);

        Livewire::actingAs($this->administrator)
            ->test(AvailabilityManager::class)
            ->call('openBlock')
            ->set('blockForm.propertyId', (string) $property->id)
            ->set('blockForm.unitId', (string) $unit->id)
            ->set('blockForm.startsAt', $startsAt->format('Y-m-d\TH:i'))
            ->set('blockForm.endsAt', $startsAt->copy()->addHours(4)->format('Y-m-d\TH:i'))
            ->set('blockForm.reason', 'Preventive maintenance')
            ->call('saveBlock')
            ->assertHasNoErrors()
            ->assertSee('Availability block created.');

        $this->assertDatabaseCount('property_booking_unit_types', 1);
        $this->assertDatabaseCount('property_booking_units', 1);
        $this->assertDatabaseCount('property_booking_rate_plans', 1);
        $this->assertDatabaseCount('property_booking_rate_overrides', 1);
        $this->assertDatabaseCount('property_booking_availability_blocks', 1);
        $this->assertSame('Conference demand', RateOverride::query()->sole()->reason);
        $this->assertSame('Preventive maintenance', AvailabilityBlock::query()->sole()->reason);
    }
}
