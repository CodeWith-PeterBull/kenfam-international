<?php

/**
 * Implements a focused TravelTours domain or application service.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Storefront\Services;

use App\Enums\ReportOrientation;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use Illuminate\Support\Facades\URL;

/** Generates purpose-specific expiring signed booking URLs. */
final class BookingAccessUrlService
{
    /** Create a temporary signed confirmation URL bound to the booking access version. */
    public function confirmation(TourBooking $booking): string
    {
        return URL::temporarySignedRoute('travel-tours.storefront.bookings.confirmation', now()->addMinutes((int) config('travel-tours.booking.confirmation_link_minutes', 120)), ['booking' => $booking]);
    }

    /** Create a temporary signed tracking URL bound to the booking access version. */
    public function tracking(TourBooking $booking): string
    {
        return URL::temporarySignedRoute('travel-tours.storefront.bookings.track', now()->addDays((int) config('travel-tours.booking.tracking_link_days', 180)), ['booking' => $booking]);
    }

    /** Create a temporary signed document URL bound to the booking access version. */
    public function document(TourBooking $booking, ReportOrientation $orientation = ReportOrientation::Portrait): string
    {
        return URL::temporarySignedRoute('travel-tours.storefront.bookings.document', now()->addDays((int) config('travel-tours.booking.document_link_days', 30)), ['booking' => $booking, 'orientation' => $orientation->value]);
    }
}
