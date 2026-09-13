<?php

namespace Tests\Feature\Dashboard;

use App\Enums\UserType;
use App\Models\User;
use App\Support\CmsPermission;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_guests_are_redirected_from_dashboard_pages(): void
    {
        foreach (['dashboard', 'admin.dashboard', 'admin.blank', 'content-manager.dashboard', 'editor.dashboard', 'viewer.dashboard'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
    }

    #[DataProvider('roleDashboardProvider')]
    public function test_neutral_dashboard_redirects_each_account_to_its_base_workspace(
        UserType $type,
        string $route,
        string $heading,
        string $sidebarLabel,
    ): void {
        $user = $this->userFor($type);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route($route));

        $this->actingAs($user)
            ->get(route($route))
            ->assertOk()
            ->assertSee($heading)
            ->assertSee($sidebarLabel)
            ->assertSee('id="global-loader"', false)
            ->assertSee('id="dashboard-settings"', false);
    }

    /** @return array<string, array{UserType, string, string, string}> */
    public static function roleDashboardProvider(): array
    {
        return [
            'system administrator' => [UserType::SystemAdministrator, 'admin.dashboard', 'Corporate content and operations at a glance', 'Users and access'],
            'content manager' => [UserType::ContentManager, 'content-manager.dashboard', 'Content workspace', 'Editorial calendar'],
            'editor' => [UserType::Editor, 'editor.dashboard', 'Editor dashboard', 'Review queue'],
            'viewer' => [UserType::Viewer, 'viewer.dashboard', 'Your corporate workspace', 'Saved items'],
        ];
    }

    #[DataProvider('crossRoleProvider')]
    public function test_role_dashboards_reject_other_base_roles(UserType $type, string $forbiddenRoute): void
    {
        $this->actingAs($this->userFor($type))
            ->get(route($forbiddenRoute))
            ->assertForbidden();
    }

    /** @return array<string, array{UserType, string}> */
    public static function crossRoleProvider(): array
    {
        return [
            'content manager cannot administer' => [UserType::ContentManager, 'admin.dashboard'],
            'editor cannot manage content workspace' => [UserType::Editor, 'content-manager.dashboard'],
            'viewer cannot edit' => [UserType::Viewer, 'editor.dashboard'],
            'administrator does not use viewer dashboard' => [UserType::SystemAdministrator, 'viewer.dashboard'],
        ];
    }

    public function test_role_sample_workspaces_are_real_protected_pages(): void
    {
        $contentManager = $this->userFor(UserType::ContentManager);
        $editor = $this->userFor(UserType::Editor);
        $viewer = $this->userFor(UserType::Viewer);

        $this->actingAs($contentManager)->get(route('content-manager.queue'))->assertOk()->assertSee('Ready for review');
        $this->actingAs($contentManager)->get(route('content-manager.calendar'))->assertOk()->assertSee('Annual outlook');
        $this->actingAs($editor)->get(route('editor.drafts'))->assertOk()->assertSee('Advisory services overview');
        $this->actingAs($editor)->get(route('editor.reviews'))->assertOk()->assertSee('Annual report introduction');
        $this->actingAs($viewer)->get(route('viewer.library'))->assertOk()->assertSee('Annual corporate outlook');
        $this->actingAs($viewer)->get(route('viewer.saved'))->assertOk()->assertSee('Market outlook');
    }

    public function test_non_admin_shell_exposes_only_explicitly_assigned_cms_tools(): void
    {
        $contentManager = $this->userFor(UserType::ContentManager);

        $this->actingAs($contentManager)
            ->get(route('content-manager.dashboard'))
            ->assertOk()
            ->assertDontSee('Institution details');

        $contentManager->givePermissionTo(CmsPermission::VIEW_INSTITUTION_DETAILS);

        $this->actingAs($contentManager->fresh())
            ->get(route('content-manager.dashboard'))
            ->assertOk()
            ->assertSee('Assigned tools')
            ->assertSee('Institution details');
    }

    public function test_administrator_can_view_the_blank_module_workspace(): void
    {
        $this->actingAs($this->userFor(UserType::SystemAdministrator))
            ->get(route('admin.blank'))
            ->assertOk()
            ->assertSee('Module workspace');
    }

    public function test_development_seeder_provisions_every_base_dashboard_account(): void
    {
        $this->seed(DatabaseSeeder::class);

        $accounts = [
            UserType::SystemAdministrator->value => 'admin@aureon.test',
            UserType::ContentManager->value => 'content@aureon.test',
            UserType::Editor->value => 'editor@aureon.test',
            UserType::Viewer->value => 'viewer@aureon.test',
        ];

        foreach ($accounts as $role => $email) {
            $user = User::query()->where('email', $email)->firstOrFail();

            $this->assertSame($role, $user->user_type->value);
            $this->assertTrue($user->hasRole($role));
            $this->assertNotNull($user->profile);
        }
    }

    private function userFor(UserType $type): User
    {
        $user = User::factory()->create(['user_type' => $type]);
        $user->assignRole($type->value);

        return $user;
    }
}
