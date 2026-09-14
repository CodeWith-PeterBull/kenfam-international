<?php

/**
 * Provides a documented component of the independent TravelTours module.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Support;

use App\Models\User;

/** Stable role identifiers for travel operations. */
final class TravelToursRole
{
    public const MANAGER = 'travel-manager';

    public const BOOKING_AGENT = 'travel-booking-agent';

    public const TOUR_EDITOR = 'tour-editor';

    /** Return the human-readable label for a module operator role. */
    public static function operatorLabel(User $user): string
    {
        if ($user->isSystemAdministrator()) {
            return 'System administrator';
        }

        return match (true) {
            $user->can(TravelToursPermission::MANAGE_SETTINGS) => 'Travel manager',
            $user->can(TravelToursPermission::ACCESS_POB) => 'Booking agent',
            $user->can(TravelToursPermission::MANAGE_CATALOG) => 'Tour editor',
            default => $user->user_type->label(),
        };
    }

    /** Initialize the TravelToursRole with its required dependencies or immutable state. */
    private function __construct() {}
}
