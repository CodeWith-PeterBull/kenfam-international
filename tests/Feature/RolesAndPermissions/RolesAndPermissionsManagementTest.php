<?php

namespace Tests\Feature\RolesAndPermissions;

use App\Enums\UserType;
use App\Livewire\Admin\RolesAndPermissionsManager;
use App\Models\User;
use App\Support\CmsPermission;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesAndPermissionsManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->administrator = User::factory()->create(['user_type' => UserType::SystemAdministrator]);
        $this->administrator->assignRole(UserType::SystemAdministrator->value);
    }

    public function test_role_manager_requires_the_view_permission(): void
    {
        $this->get(route('admin.roles-and-permissions.index'))->assertRedirect(route('login'));

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('admin.roles-and-permissions.index'))->assertForbidden();
        Livewire::actingAs($user)->test(RolesAndPermissionsManager::class)->assertForbidden();

        $user->givePermissionTo(CmsPermission::VIEW_ROLES_AND_PERMISSIONS);
        $this->actingAs($user)->get(route('admin.roles-and-permissions.index'))->assertOk();
        Livewire::actingAs($user)->test(RolesAndPermissionsManager::class)->call('openCreate')->assertForbidden();
    }

    public function test_administrator_can_create_and_update_a_custom_role(): void
    {
        Livewire::actingAs($this->administrator)
            ->test(RolesAndPermissionsManager::class)
            ->call('openCreate')
            ->set('form.name', 'communications-reviewer')
            ->set('form.permissions', [CmsPermission::VIEW_USERS, CmsPermission::VIEW_SYSTEM_ACTIVITIES])
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Access role created successfully.');

        $role = Role::findByName('communications-reviewer', 'web');
        $this->assertTrue($role->hasAllPermissions([CmsPermission::VIEW_USERS, CmsPermission::VIEW_SYSTEM_ACTIVITIES]));

        Livewire::actingAs($this->administrator)
            ->test(RolesAndPermissionsManager::class)
            ->call('openEdit', $role->id)
            ->set('form.name', 'communications-manager')
            ->set('form.permissions', [CmsPermission::VIEW_USERS, CmsPermission::MANAGE_USERS])
            ->call('save')
            ->assertHasNoErrors();

        $role->refresh();
        $this->assertSame('communications-manager', $role->name);
        $this->assertTrue($role->hasAllPermissions([CmsPermission::VIEW_USERS, CmsPermission::MANAGE_USERS]));
        $this->assertFalse($role->hasPermissionTo(CmsPermission::VIEW_SYSTEM_ACTIVITIES));
        $this->assertDatabaseHas('system_activities', ['activity_type' => 'role.created']);
        $this->assertDatabaseHas('system_activities', ['activity_type' => 'role.updated']);
    }

    public function test_base_role_names_are_fixed_and_system_administrator_is_locked(): void
    {
        $viewer = Role::findByName(UserType::Viewer->value, 'web');

        Livewire::actingAs($this->administrator)
            ->test(RolesAndPermissionsManager::class)
            ->call('openEdit', $viewer->id)
            ->set('form.name', 'renamed-viewer')
            ->call('save')
            ->assertHasErrors('management');

        $systemAdministrator = Role::findByName(UserType::SystemAdministrator->value, 'web');
        Livewire::actingAs($this->administrator)
            ->test(RolesAndPermissionsManager::class)
            ->call('openEdit', $systemAdministrator->id)
            ->assertForbidden();

        $this->assertDatabaseHas('roles', ['name' => UserType::Viewer->value]);
    }

    public function test_assigned_custom_role_cannot_be_deleted_until_users_are_reassigned(): void
    {
        $role = Role::create(['name' => 'assigned-role', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        Livewire::actingAs($this->administrator)
            ->test(RolesAndPermissionsManager::class)
            ->call('confirmDelete', $role->id)
            ->call('delete')
            ->assertHasErrors('management');

        $this->assertNotNull($role->fresh());
    }

    public function test_unassigned_custom_role_can_be_deleted_but_base_roles_cannot(): void
    {
        $custom = Role::create(['name' => 'temporary-role', 'guard_name' => 'web']);

        Livewire::actingAs($this->administrator)
            ->test(RolesAndPermissionsManager::class)
            ->call('confirmDelete', $custom->id)
            ->call('delete')
            ->assertHasNoErrors();

        $this->assertNull($custom->fresh());
        $this->assertDatabaseHas('system_activities', ['activity_type' => 'role.deleted']);

        $viewer = Role::findByName(UserType::Viewer->value, 'web');
        Livewire::actingAs($this->administrator)
            ->test(RolesAndPermissionsManager::class)
            ->call('confirmDelete', $viewer->id)
            ->assertForbidden();
    }

    public function test_invalid_permission_identifiers_are_rejected(): void
    {
        Livewire::actingAs($this->administrator)
            ->test(RolesAndPermissionsManager::class)
            ->call('openCreate')
            ->set('form.name', 'unsafe-role')
            ->set('form.permissions', ['invented_permission'])
            ->call('save')
            ->assertHasErrors('form.permissions.0');

        $this->assertDatabaseMissing('roles', ['name' => 'unsafe-role']);
    }
}
