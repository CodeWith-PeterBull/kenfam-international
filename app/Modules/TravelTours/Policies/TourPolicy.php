<?php

/**
 * Defines authorization rules for protected TravelTours resources.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Policies;

use App\Modules\TravelTours\Support\TravelToursPermission;

/** Catalog authorization for tours, destinations, itinerary, content, and media. */
final class TourPolicy extends TravelResourcePolicy
{
    /** Return the permission required to inspect this resource family. */
    protected function viewPermission(): string
    {
        return TravelToursPermission::VIEW_CATALOG;
    }

    /** Return the permission required to mutate this resource family. */
    protected function managePermission(): string
    {
        return TravelToursPermission::MANAGE_CATALOG;
    }
}
