<?php

namespace Tests\Feature\SystemActivity;

use App\Livewire\Admin\SystemActivityIndex;
use App\Models\User;
use App\Support\CmsPermission;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SystemActivityAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_guests_are_redirected_from_the_activity_page(): void
    {
        $this->get(route('admin.system-activity.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_users_without_permission_are_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.system-activity.index'))
            ->assertForbidden();

        Livewire::actingAs($user)
            ->test(SystemActivityIndex::class)
            ->assertForbidden();
    }

    public function test_authorized_administrators_can_view_the_activity_page_and_navigation(): void
    {
        $administrator = User::factory()->create();
        $administrator->assignRole('system-admin');

        $this->actingAs($administrator)
            ->get(route('admin.system-activity.index'))
            ->assertOk()
            ->assertSeeLivewire(SystemActivityIndex::class)
            ->assertSee('System activity')
            ->assertSee('Application logs');
    }

    public function test_activity_permission_does_not_implicitly_expose_application_logs(): void
    {
        $auditor = User::factory()->create();
        $auditor->givePermissionTo(CmsPermission::VIEW_SYSTEM_ACTIVITIES);

        $this->actingAs($auditor)
            ->get(route('admin.system-activity.index'))
            ->assertOk()
            ->assertDontSee('Application logs');
    }
}
