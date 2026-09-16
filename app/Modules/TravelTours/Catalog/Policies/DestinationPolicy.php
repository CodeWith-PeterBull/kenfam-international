<?php

/**
 * Authorizes TravelTours destination content and publication actions.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Policies;

use App\Models\User;
use App\Modules\TravelTours\Catalog\Models\Destination;
use App\Modules\TravelTours\Policies\TravelResourcePolicy;
use App\Modules\TravelTours\Support\TravelToursPermission;

/** Separate editorial maintenance from public destination publication. */
final class DestinationPolicy extends TravelResourcePolicy
{
    /** Return the capability required to inspect destinations. */
    protected function viewPermission(): string
    {
        return TravelToursPermission::VIEW_CATALOG;
    }

    /** Return the capability required to maintain destination drafts. */
    protected function managePermission(): string
    {
        return TravelToursPermission::MANAGE_CATALOG;
    }

    /** Determine whether the user may make a destination publicly eligible. */
    public function publish(User $user, Destination $destination): bool
    {
        return $user->can(TravelToursPermission::PUBLISH_CATALOG);
    }

    /** Determine whether the user may remove a destination from publication. */
    public function unpublish(User $user, Destination $destination): bool
    {
        return $this->publish($user, $destination);
    }
}
