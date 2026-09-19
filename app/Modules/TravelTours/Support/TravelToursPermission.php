<?php

/**
 * Provides a documented component of the independent TravelTours module.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Support;

/** Developer-owned capability catalogue for travel operations. */
final class TravelToursPermission
{
    public const VIEW_DASHBOARD = 'view-travel-dashboard';

    public const VIEW_CATALOG = 'view-travel-catalog';

    public const MANAGE_CATALOG = 'manage-travel-catalog';

    public const PUBLISH_CATALOG = 'publish-travel-catalog';

    public const VIEW_DEPARTURES = 'view-tour-departures';

    public const MANAGE_DEPARTURES = 'manage-tour-departures';

    public const VIEW_PRICING = 'view-tour-pricing';

    public const MANAGE_PRICING = 'manage-tour-pricing';

    public const VIEW_BOOKINGS = 'view-tour-bookings';

    public const MANAGE_BOOKINGS = 'manage-tour-bookings';

    public const MANAGE_CUSTOMERS = 'manage-travel-customers';

    public const MANAGE_PAYMENTS = 'manage-tour-payments';

    public const CONFIRM_PAYMENTS = 'confirm-tour-payments';

    public const REFUND_PAYMENTS = 'refund-tour-payments';

    public const VIEW_INQUIRIES = 'view-tour-inquiries';

    public const MANAGE_INQUIRIES = 'manage-tour-inquiries';

    public const ACCESS_POB = 'access-travel-booking-desk';

    public const MANAGE_SHIFTS = 'manage-travel-booking-shifts';

    public const VIEW_REPORTS = 'view-travel-reports';

    public const MANAGE_SETTINGS = 'manage-travel-settings';

    /** @return array<string, array{label: string, group: string, description: string}> */
    public static function catalogue(): array
    {
        $definitions = [
            self::VIEW_DASHBOARD => ['View travel dashboard', 'View booking, departure, capacity, and revenue summaries.'],
            self::VIEW_CATALOG => ['View travel catalog', 'Browse tours, destinations, itineraries, content, FAQs, and extras.'],
            self::MANAGE_CATALOG => ['Manage travel catalog', 'Create, edit, organize, and submit travel catalog records for publication.'],
            self::PUBLISH_CATALOG => ['Publish travel catalog', 'Publish, unpublish, and archive public travel catalog records.'],
            self::VIEW_DEPARTURES => ['View tour departures', 'Inspect schedules, capacity, holds, and assigned staff.'],
            self::MANAGE_DEPARTURES => ['Manage tour departures', 'Create schedules and control departure availability.'],
            self::VIEW_PRICING => ['View tour pricing', 'Inspect rate plans, participant rates, rules, and promotions.'],
            self::MANAGE_PRICING => ['Manage tour pricing', 'Create and change rates, pricing rules, and promotions.'],
            self::VIEW_BOOKINGS => ['View tour bookings', 'Inspect bookings, travelers, totals, history, and documents.'],
            self::MANAGE_BOOKINGS => ['Manage tour bookings', 'Place, confirm, amend, cancel, expire, and complete bookings.'],
            self::MANAGE_CUSTOMERS => ['Manage travel customers', 'Maintain protected customer and traveler records.'],
            self::MANAGE_PAYMENTS => ['Manage tour payments', 'Record payment evidence awaiting confirmation.'],
            self::CONFIRM_PAYMENTS => ['Confirm tour payments', 'Confirm or reject recorded payments and settle bookings.'],
            self::REFUND_PAYMENTS => ['Refund tour payments', 'Record processed refunds against confirmed payments.'],
            self::VIEW_INQUIRIES => ['View tour inquiries', 'Inspect general, private, and custom tour enquiries.'],
            self::MANAGE_INQUIRIES => ['Manage tour inquiries', 'Assign, follow up, close, and convert enquiries.'],
            self::ACCESS_POB => ['Access booking desk', 'Operate the point-of-booking terminal on a shift opened for you.'],
            self::MANAGE_SHIFTS => ['Manage booking shifts', 'Configure registers and open, close, and sign off operator shifts.'],
            self::VIEW_REPORTS => ['View travel reports', 'View and export authorized operational and financial reports.'],
            self::MANAGE_SETTINGS => ['Manage travel settings', 'Change authorized module operating defaults.'],
        ];

        return array_map(
            static fn (array $entry): array => ['label' => $entry[0], 'group' => 'Travel & Tours', 'description' => $entry[1]],
            $definitions,
        );
    }

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(self::catalogue());
    }

    /** Initialize the TravelToursPermission with its required dependencies or immutable state. */
    private function __construct() {}
}
