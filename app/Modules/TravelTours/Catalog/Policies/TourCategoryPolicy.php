<?php

/**
 * Authorizes TravelTours category discovery and hierarchy maintenance.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Policies;

use App\Modules\TravelTours\Policies\TravelResourcePolicy;
use App\Modules\TravelTours\Support\TravelToursPermission;

/** Guard category reads and writes through the catalog capability boundary. */
final class TourCategoryPolicy extends TravelResourcePolicy
{
    /** Return the capability required to inspect the category hierarchy. */
    protected function viewPermission(): string
    {
        return TravelToursPermission::VIEW_CATALOG;
    }

    /** Return the capability required to maintain category records. */
    protected function managePermission(): string
    {
        return TravelToursPermission::MANAGE_CATALOG;
    }
}
