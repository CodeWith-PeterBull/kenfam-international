<?php

/**
 * Reconciles deterministic TravelTours access and demonstration identities.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Database\Seeders;

use App\Modules\TravelTours\Support\TravelToursPermission;
use App\Modules\TravelTours\Support\TravelToursRole;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Reconciles TravelTours permissions and roles without creating people. */
final class TravelToursAccessSeeder extends Seeder
{
    /** Reconcile the module capability catalogue and its three operator roles. */
    public function run(): void
    {
        if (! config('travel-tours.enabled', false)) {
            return;
        }
        $this->call(RoleSeeder::class);

        Role::findOrCreate(TravelToursRole::MANAGER, 'web')->syncPermissions(TravelToursPermission::all());
        Role::findOrCreate(TravelToursRole::BOOKING_AGENT, 'web')->syncPermissions([
            TravelToursPermission::VIEW_DASHBOARD, TravelToursPermission::VIEW_CATALOG,
            TravelToursPermission::VIEW_DEPARTURES, TravelToursPermission::VIEW_PRICING,
            TravelToursPermission::VIEW_BOOKINGS, TravelToursPermission::MANAGE_BOOKINGS,
            TravelToursPermission::MANAGE_CUSTOMERS, TravelToursPermission::MANAGE_PAYMENTS,
            TravelToursPermission::VIEW_INQUIRIES, TravelToursPermission::MANAGE_INQUIRIES,
            TravelToursPermission::ACCESS_POB,
        ]);
        Role::findOrCreate(TravelToursRole::TOUR_EDITOR, 'web')->syncPermissions([
            TravelToursPermission::VIEW_DASHBOARD, TravelToursPermission::VIEW_CATALOG,
            TravelToursPermission::MANAGE_CATALOG, TravelToursPermission::VIEW_DEPARTURES,
            TravelToursPermission::VIEW_PRICING,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
