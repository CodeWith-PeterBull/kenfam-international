<?php

/**
 * Defines an injectable TravelTours application-service boundary.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Contracts;

use App\Data\RenderedPdf;
use App\Modules\TravelTours\Bookings\Models\TourBooking;

/** Institution-aware booking document boundary. */
interface RendersBookingDocuments
{
    /** Render an authorized booking summary through the host document adapter. */
    public function summary(TourBooking $booking, string $orientation = 'portrait'): RenderedPdf;
}
