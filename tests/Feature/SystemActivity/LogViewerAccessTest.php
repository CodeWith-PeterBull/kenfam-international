<?php

namespace Tests\Feature\SystemActivity;

use App\Models\User;
use App\Support\CmsPermission;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogViewerAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_log_viewer_is_published_at_the_canonical_route(): void
    {
        $this->assertSame('logs', config('log-viewer.route_path'));
        $this->assertTrue(config('log-viewer.enabled'));
        $this->assertNotNull(route('log-viewer.index'));
    }

    public function test_guests_and_users_without_permission_cannot_view_logs(): void
    {
        $this->get('/logs')->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->get('/logs')
            ->assertForbidden();
    }

    public function test_authorized_administrators_can_view_the_log_viewer_and_api(): void
    {
        $administrator = User::factory()->create();
        $administrator->assignRole('system-admin');

        $this->actingAs($administrator)
            ->get('/logs')
            ->assertOk()
            ->assertSee('Log Viewer');

        $this->actingAs($administrator)
            ->get('/logs/api/folders')
            ->assertOk();
    }

    public function test_log_view_and_management_permissions_are_independent(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(CmsPermission::VIEW_APPLICATION_LOGS);

        $this->assertTrue($viewer->can(CmsPermission::VIEW_APPLICATION_LOGS));
        $this->assertFalse($viewer->can(CmsPermission::MANAGE_APPLICATION_LOGS));

        $this->actingAs($viewer)
            ->get('/logs')
            ->assertOk();
    }
}
