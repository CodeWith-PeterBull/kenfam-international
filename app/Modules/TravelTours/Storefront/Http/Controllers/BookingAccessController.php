<?php

/**
 * Coordinates an HTTP request with TravelTours services and presentation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\Contracts\RendersBookingDocuments;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\Response;

/** Serves private signed booking status and document resources. */
final class BookingAccessController extends Controller
{
    /** Render the signed booking confirmation for its intended recipient. */
    public function confirmation(TourBooking $booking): View
    {
        return view('travel-tours::storefront.bookings.show', ['booking' => $booking->load(['departure.tour', 'participants', 'payments']), 'isConfirmation' => true]);
    }

    /** Render the signed booking tracking view for its intended recipient. */
    public function track(TourBooking $booking): View
    {
        return view('travel-tours::storefront.bookings.show', ['booking' => $booking->load(['departure.tour', 'participants', 'payments']), 'isConfirmation' => false]);
    }

    /** Stream the authorized booking document through the host report adapter. */
    public function document(TourBooking $booking, string $orientation, RendersBookingDocuments $documents): Response
    {
        $pdf = $documents->summary($booking, $orientation);

        return response($pdf->contents, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$pdf->filename.'"', 'X-Content-Type-Options' => 'nosniff']);
    }
}
