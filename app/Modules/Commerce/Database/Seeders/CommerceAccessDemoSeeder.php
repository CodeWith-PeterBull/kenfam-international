<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Database\Seeders;

use App\Enums\UserType;
use App\Models\User;
use App\Modules\Commerce\Support\CommercePermission;
use App\Modules\Commerce\Support\CommerceRole;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Reconciles Commerce permissions and creates a least-privileged demo cashier.
 */
final class CommerceAccessDemoSeeder extends Seeder
{
    public const CASHIER_EMAIL = 'cashier@aureon.test';

    /**
     * Seed the POS role and its deterministic local demonstration account.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $cashierRole = Role::findOrCreate(CommerceRole::POS_CASHIER, 'web');
        $cashierRole->syncPermissions([CommercePermission::ACCESS_POS]);

        $cashier = User::query()->firstOrCreate(
            ['email' => self::CASHIER_EMAIL],
            [
                'name' => 'aureon.cashier',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $cashier->forceFill([
            'user_type' => UserType::Viewer,
            'is_active' => true,
            'email_verified_at' => $cashier->email_verified_at ?? now(),
        ])->save();
        $cashier->syncRoles([
            UserType::Viewer->value,
            CommerceRole::POS_CASHIER,
        ]);
        $cashier->profile()->firstOrCreate([], [
            'first_name' => 'Aureon',
            'last_name' => 'Cashier',
            'job_title' => 'Point-of-sale cashier',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
