<?php

/**
 * Defines controlled vocabulary for a persisted TravelTours domain state.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Inquiries\Enums;

use App\Modules\TravelTours\Support\Concerns\HasEnumLabel;

/** Enumerates the controlled inquiry status vocabulary used by TravelTours. */
enum InquiryStatus: string
{
    use HasEnumLabel;
    case New = 'new';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case AwaitingCustomer = 'awaiting_customer';
    case Converted = 'converted';
    case Closed = 'closed';
    case Spam = 'spam';

    /** Whether the inquiry has left the follow-up queue. */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Converted, self::Closed, self::Spam], true);
    }
}
