<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Support;

use App\Models\User;

/**
 * Stable module role identifiers and operator-facing responsibility labels.
 */
final class PropertyBookingRole
{
    public const MANAGER = 'property-booking-manager';

    public const RECEPTIONIST = 'booking-receptionist';

    public const AGENT = 'booking-agent';

    public const HOUSEKEEPING = 'property-housekeeping';

    /**
     * Resolve the most relevant accommodation responsibility for a user.
     */
    public static function operatorLabel(User $user): string
    {
        if ($user->isSystemAdministrator()) {
            return 'System administrator';
        }

        return match (true) {
            $user->can(PropertyBookingPermission::MANAGE_PROPERTIES) => 'Property booking manager',
            $user->can(PropertyBookingPermission::MANAGE_SHIFTS) => 'Reception supervisor',
            $user->can(PropertyBookingPermission::ACCESS_POB) => 'Booking receptionist',
            $user->can(PropertyBookingPermission::MANAGE_READINESS) => 'Property housekeeping',
            default => $user->user_type->label(),
        };
    }

    /** Prevent direct utility instantiation. */
    private function __construct() {}
}
