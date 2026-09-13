<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Database\Seeders;

use Illuminate\Database\Seeder;

/** Seeds the complete optional foundation demonstration graph. */
final class PropertyBookingDemoSeeder extends Seeder
{
    /** Seed the module-owned demonstration records. */
    public function run(): void
    {
        if (! config('property-booking.enabled', true)) {
            return;
        }

        $this->call([
            PropertyBookingAccessDemoSeeder::class,
            PropertyBookingCatalogDemoSeeder::class,
            PropertyBookingOperationsDemoSeeder::class,
        ]);
    }
}
