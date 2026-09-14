<?php

/**
 * Defines controlled vocabulary for a persisted TravelTours domain state.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Inquiries\Enums;

use App\Modules\TravelTours\Support\Concerns\HasEnumLabel;

/** Enumerates the controlled inquiry type vocabulary used by TravelTours. */
enum InquiryType: string
{
    use HasEnumLabel;
    case General = 'general';
    case Tour = 'tour';
    case PrivateTour = 'private_tour';
    case CustomTour = 'custom_tour';
}
