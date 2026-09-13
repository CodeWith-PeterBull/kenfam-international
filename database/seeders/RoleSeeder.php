<?php

namespace Database\Seeders;

use App\Enums\UserType;
use App\Support\CmsPermission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect(CmsPermission::foundational())
            ->map(static fn (string $permission): Permission => Permission::findOrCreate($permission, 'web'));

        foreach (UserType::values() as $role) {
            Role::findOrCreate($role, 'web');
        }

        Role::findByName('system-admin', 'web')
            ->syncPermissions($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
