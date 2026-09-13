<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Storefront\Services;

use App\Enums\ReportOrientation;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use Illuminate\Support\Facades\URL;

/** Generates independent expiring signatures for private public booking views. */
final class BookingAccessUrlService
{
    /** Generate the short-lived post-placement confirmation URL. */
    public function confirmation(Booking $booking): string
    {
        $minutes = max(5, min(1_440, (int) config('property-booking.storefront.confirmation_link_minutes', 120)));

        return URL::temporarySignedRoute(
            'property-booking.storefront.bookings.confirmation',
            now()->addMinutes($minutes),
            ['booking' => $booking->getRouteKey()],
        );
    }

    /** Generate the longer-lived guest tracking URL. */
    public function tracking(Booking $booking): string
    {
        $days = max(1, min(365, (int) config('property-booking.storefront.tracking_link_days', 90)));

        return URL::temporarySignedRoute(
            'property-booking.storefront.bookings.track',
            now()->addDays($days),
            ['booking' => $booking->getRouteKey()],
        );
    }

    /** Generate an orientation-specific printable booking summary URL. */
    public function document(Booking $booking, ReportOrientation $orientation = ReportOrientation::Portrait): string
    {
        $days = max(1, min(365, (int) config('property-booking.storefront.document_link_days', 30)));

        return URL::temporarySignedRoute(
            'property-booking.storefront.bookings.document',
            now()->addDays($days),
            ['booking' => $booking->getRouteKey(), 'orientation' => $orientation->value],
        );
    }
}
