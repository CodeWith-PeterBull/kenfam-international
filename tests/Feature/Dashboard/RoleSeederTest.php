<?php

namespace Tests\Feature\Dashboard;

use App\Support\CmsPermission;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_base_roles_are_seeded_idempotently(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->assertSame(4, Role::query()->count());
        $this->assertEqualsCanonicalizing(
            ['system-admin', 'content-manager', 'editor', 'viewer'],
            Role::query()->pluck('name')->all(),
        );
        $this->assertEqualsCanonicalizing(
            CmsPermission::foundational(),
            Permission::query()->pluck('name')->all(),
        );

        $systemAdministrator = Role::findByName('system-admin');

        foreach (CmsPermission::foundational() as $permission) {
            $this->assertTrue($systemAdministrator->hasPermissionTo($permission));
        }

        $this->assertCount(0, Role::findByName('content-manager')->permissions);
    }
}
