<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Support;

/**
 * Developer-owned capability catalogue for accommodation operations.
 */
final class PropertyBookingPermission
{
    public const VIEW_DASHBOARD = 'view-property-booking-dashboard';

    public const VIEW_PROPERTIES = 'view-booking-properties';

    public const MANAGE_PROPERTIES = 'manage-booking-properties';

    public const VIEW_RATES = 'view-booking-rates';

    public const MANAGE_RATES = 'manage-booking-rates';

    public const VIEW_AVAILABILITY = 'view-booking-availability';

    public const MANAGE_AVAILABILITY = 'manage-booking-availability';

    public const VIEW_BOOKINGS = 'view-property-bookings';

    public const MANAGE_BOOKINGS = 'manage-property-bookings';

    public const MANAGE_GUESTS = 'manage-booking-guests';

    public const MANAGE_PAYMENTS = 'manage-booking-payments';

    public const ACCESS_POB = 'access-point-of-booking';

    public const MANAGE_SHIFTS = 'manage-reception-shifts';

    public const CHECK_IN = 'check-in-booking-guests';

    public const CHECK_OUT = 'check-out-booking-guests';

    public const MANAGE_READINESS = 'manage-unit-readiness';

    /** @return array<string, array{label: string, group: string, description: string}> */
    public static function catalogue(): array
    {
        return [
            self::VIEW_DASHBOARD => self::entry('View booking dashboard', 'View property, booking, occupancy, arrivals, and revenue summaries.'),
            self::VIEW_PROPERTIES => self::entry('View properties', 'Browse assigned properties, unit types, concrete units, and amenities.'),
            self::MANAGE_PROPERTIES => self::entry('Manage properties', 'Create and maintain all properties, units, amenities, and operator assignments.'),
            self::VIEW_RATES => self::entry('View booking rates', 'Inspect rate plans, restrictions, and date overrides for assigned properties.'),
            self::MANAGE_RATES => self::entry('Manage booking rates', 'Create and maintain rate plans and bounded date overrides.'),
            self::VIEW_AVAILABILITY => self::entry('View booking availability', 'Inspect unit calendars, assignments, and operational blocks.'),
            self::MANAGE_AVAILABILITY => self::entry('Manage booking availability', 'Create and release operational availability blocks.'),
            self::VIEW_BOOKINGS => self::entry('View property bookings', 'Inspect bookings, stays, guests, charges, payments, and lifecycle history.'),
            self::MANAGE_BOOKINGS => self::entry('Manage property bookings', 'Place, confirm, amend, cancel, and complete eligible bookings.'),
            self::MANAGE_GUESTS => self::entry('Manage booking guests', 'Create and maintain protected guest identity and contact records.'),
            self::MANAGE_PAYMENTS => self::entry('Manage booking payments', 'Record and refund eligible booking payments.'),
            self::ACCESS_POB => self::entry('Access point of booking', 'Use the reception booking terminal during an assigned open shift.'),
            self::MANAGE_SHIFTS => self::entry('Manage reception shifts', 'Configure reception registers and open or reconcile shifts.'),
            self::CHECK_IN => self::entry('Check in booking guests', 'Complete authorized guest and unit check-in operations.'),
            self::CHECK_OUT => self::entry('Check out booking guests', 'Complete authorized guest check-out operations.'),
            self::MANAGE_READINESS => self::entry('Manage unit readiness', 'Update cleaning, maintenance, and room readiness states.'),
        ];
    }

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(self::catalogue());
    }

    /** @return array{label: string, group: string, description: string} */
    private static function entry(string $label, string $description): array
    {
        return ['label' => $label, 'group' => 'Property booking', 'description' => $description];
    }

    /** Prevent direct utility instantiation. */
    private function __construct() {}
}
