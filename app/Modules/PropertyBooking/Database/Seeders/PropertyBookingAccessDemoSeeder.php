<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Database\Seeders;

use App\Enums\UserType;
use App\Models\User;
use App\Modules\PropertyBooking\Support\PropertyBookingPermission;
use App\Modules\PropertyBooking\Support\PropertyBookingRole;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Reconciles module roles and creates least-privileged demonstration operators. */
final class PropertyBookingAccessDemoSeeder extends Seeder
{
    public const MANAGER_EMAIL = 'booking.manager@aureon.test';

    public const RECEPTIONIST_EMAIL = 'receptionist@aureon.test';

    /** Seed the module-owned demonstration records. */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        Role::findOrCreate(PropertyBookingRole::MANAGER, 'web')->syncPermissions(PropertyBookingPermission::all());
        Role::findOrCreate(PropertyBookingRole::RECEPTIONIST, 'web')->syncPermissions([
            PropertyBookingPermission::VIEW_DASHBOARD,
            PropertyBookingPermission::VIEW_PROPERTIES,
            PropertyBookingPermission::VIEW_RATES,
            PropertyBookingPermission::VIEW_AVAILABILITY,
            PropertyBookingPermission::VIEW_BOOKINGS,
            PropertyBookingPermission::MANAGE_BOOKINGS,
            PropertyBookingPermission::MANAGE_GUESTS,
            PropertyBookingPermission::MANAGE_PAYMENTS,
            PropertyBookingPermission::ACCESS_POB,
            PropertyBookingPermission::CHECK_IN,
            PropertyBookingPermission::CHECK_OUT,
        ]);
        Role::findOrCreate(PropertyBookingRole::AGENT, 'web')->syncPermissions([
            PropertyBookingPermission::VIEW_DASHBOARD,
            PropertyBookingPermission::VIEW_PROPERTIES,
            PropertyBookingPermission::VIEW_RATES,
            PropertyBookingPermission::VIEW_AVAILABILITY,
            PropertyBookingPermission::VIEW_BOOKINGS,
            PropertyBookingPermission::MANAGE_BOOKINGS,
            PropertyBookingPermission::MANAGE_GUESTS,
        ]);
        Role::findOrCreate(PropertyBookingRole::HOUSEKEEPING, 'web')->syncPermissions([
            PropertyBookingPermission::VIEW_PROPERTIES,
            PropertyBookingPermission::VIEW_AVAILABILITY,
            PropertyBookingPermission::MANAGE_READINESS,
        ]);

        $this->operator(self::MANAGER_EMAIL, 'aureon.booking.manager', 'Aureon', 'Booking Manager', PropertyBookingRole::MANAGER);
        $this->operator(self::RECEPTIONIST_EMAIL, 'aureon.receptionist', 'Aureon', 'Receptionist', PropertyBookingRole::RECEPTIONIST);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Build the deterministic operator fixture. */
    private function operator(string $email, string $username, string $firstName, string $lastName, string $role): User
    {
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            ['name' => $username, 'password' => Hash::make('password'), 'email_verified_at' => now()],
        );
        $user->forceFill([
            'user_type' => UserType::Viewer,
            'is_active' => true,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();
        $user->syncRoles([UserType::Viewer->value, $role]);
        $user->profile()->updateOrCreate([], [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'job_title' => $role === PropertyBookingRole::MANAGER ? 'Property booking manager' : 'Booking receptionist',
        ]);

        return $user;
    }
}
