<?php

/**
 * Authorizes TravelTours tour content and publication actions.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Policies;

use App\Models\User;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Policies\TravelResourcePolicy;
use App\Modules\TravelTours\Support\TravelToursPermission;

/** Separate tour editing from the higher-impact publication decision. */
final class TourPolicy extends TravelResourcePolicy
{
    /** Return the capability required to inspect tours. */
    protected function viewPermission(): string
    {
        return TravelToursPermission::VIEW_CATALOG;
    }

    /** Return the capability required to maintain tour drafts. */
    protected function managePermission(): string
    {
        return TravelToursPermission::MANAGE_CATALOG;
    }

    /** Determine whether the user may make a tour publicly eligible. */
    public function publish(User $user, Tour $tour): bool
    {
        return $user->can(TravelToursPermission::PUBLISH_CATALOG);
    }

    /** Determine whether the user may remove a tour from publication. */
    public function unpublish(User $user, Tour $tour): bool
    {
        return $this->publish($user, $tour);
    }
}
