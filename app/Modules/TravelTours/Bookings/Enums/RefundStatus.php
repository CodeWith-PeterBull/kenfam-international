<?php

/**
 * Defines controlled vocabulary for a persisted TravelTours domain state.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Bookings\Enums;

use App\Modules\TravelTours\Support\Concerns\HasEnumLabel;

/** Enumerates the controlled refund status vocabulary used by TravelTours. */
enum RefundStatus: string
{
    use HasEnumLabel;
    case Pending = 'pending';
    case Processed = 'processed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
