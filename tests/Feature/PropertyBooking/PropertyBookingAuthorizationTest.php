<?php

declare(strict_types=1);

namespace Tests\Feature\PropertyBooking;

use App\Enums\UserType;
use App\Models\User;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Catalog\Models\UnitType;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionRegister;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionShift;
use App\Modules\PropertyBooking\Support\PropertyBookingPermission;
use App\Support\CmsPermission;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Verifies permission catalogue, system override, and explicit property scope. */
final class PropertyBookingAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_permissions_join_the_shared_catalogue_and_system_admin_role(): void
    {
        $catalogue = array_keys(array_filter(
            CmsPermission::catalogue(),
            static fn (array $definition): bool => $definition['group'] === 'Property booking',
        ));
        $this->assertEqualsCanonicalizing(PropertyBookingPermission::all(), $catalogue);

        foreach (PropertyBookingPermission::all() as $permission) {
            $this->assertTrue(Permission::findByName($permission)->exists);
            $this->assertTrue(Role::findByName(UserType::SystemAdministrator->value)->hasPermissionTo($permission));
        }
    }

    public function test_assigned_operator_cannot_cross_property_boundaries(): void
    {
        $assigned = Property::factory()->create();
        $other = Property::factory()->create();
        $assignedType = UnitType::factory()->for($assigned)->create();
        $otherType = UnitType::factory()->for($other)->create();
        $user = User::factory()->create(['is_active' => true]);
        $user->givePermissionTo(PropertyBookingPermission::VIEW_PROPERTIES);
        DB::table('property_booking_property_user')->insert([
            'property_id' => $assigned->id,
            'user_id' => $user->id,
            'assigned_by' => null,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue(Gate::forUser($user)->allows('view', $assigned));
        $this->assertTrue(Gate::forUser($user)->allows('view', $assignedType));
        $this->assertFalse(Gate::forUser($user)->allows('view', $other));
        $this->assertFalse(Gate::forUser($user)->allows('view', $otherType));

        $user->givePermissionTo(PropertyBookingPermission::MANAGE_PROPERTIES);
        $this->assertTrue(Gate::forUser($user)->allows('view', $other));
        $this->assertTrue(Gate::forUser($user)->allows('view', $otherType));
    }

    public function test_receptionist_may_operate_only_their_assigned_shift_and_inactive_admin_is_denied(): void
    {
        $property = Property::factory()->create();
        $register = ReceptionRegister::factory()->for($property)->create();
        $receptionist = User::factory()->create(['is_active' => true]);
        $other = User::factory()->create(['is_active' => true]);
        $receptionist->givePermissionTo(PropertyBookingPermission::ACCESS_POB);
        $other->givePermissionTo(PropertyBookingPermission::ACCESS_POB);
        foreach ([$receptionist, $other] as $operator) {
            DB::table('property_booking_property_user')->insert([
                'property_id' => $property->id, 'user_id' => $operator->id, 'assigned_by' => null,
                'is_default' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $shift = ReceptionShift::factory()->for($register, 'register')->create([
            'property_id' => $property->id,
            'receptionist_id' => $receptionist->id,
            'receptionist_open_guard' => $receptionist->id,
            'opened_by' => $receptionist->id,
        ]);

        $this->assertTrue(Gate::forUser($receptionist)->allows('operate', $shift));
        $this->assertFalse(Gate::forUser($other)->allows('operate', $shift));

        $admin = User::factory()->create(['user_type' => UserType::SystemAdministrator, 'is_active' => false]);
        $admin->syncRoles([UserType::SystemAdministrator->value]);
        $this->assertFalse(Gate::forUser($admin)->allows('view', $property));

        $admin->forceFill(['is_active' => true])->save();
        $this->assertTrue(Gate::forUser($admin->fresh())->allows('view', $property));
    }
}
