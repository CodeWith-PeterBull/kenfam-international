<?php

/**
 * Coordinates an HTTP request with TravelTours services and presentation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\Contracts\RendersBookingDocuments;
use App\Modules\TravelTours\Storefront\Services\BookingAccessUrlService;
use Symfony\Component\HttpFoundation\Response;

/** Serves private signed booking status and document resources. */
final class BookingAccessController extends Controller
{
    /** @var array<string, string> */
    private const PRIVATE_HEADERS = [
        'Cache-Control' => 'private, no-store, max-age=0',
        'Pragma' => 'no-cache',
        'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        'X-Content-Type-Options' => 'nosniff',
    ];

    /** Render the signed booking confirmation for its intended recipient. */
    public function confirmation(TourBooking $booking, BookingAccessUrlService $urls): Response
    {
        return $this->status($booking, $urls, true);
    }

    /** Render the signed booking tracking view for its intended recipient. */
    public function track(TourBooking $booking, BookingAccessUrlService $urls): Response
    {
        return $this->status($booking, $urls, false);
    }

    /** Stream the authorized booking document through the host report adapter. */
    public function document(TourBooking $booking, string $orientation, RendersBookingDocuments $documents): Response
    {
        $pdf = $documents->summary($booking, $orientation);

        return response($pdf->contents, 200, [
            ...self::PRIVATE_HEADERS,
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$pdf->filename.'"',
        ]);
    }

    /** Render the shared status page with fresh signed links for its follow-up actions. */
    private function status(TourBooking $booking, BookingAccessUrlService $urls, bool $isConfirmation): Response
    {
        $booking->load(['departure.tour', 'participants', 'payments' => fn ($query) => $query->orderBy('paid_at')->orderBy('id')]);

        return response()->view('travel-tours::storefront.bookings.show', [
            'booking' => $booking,
            'isConfirmation' => $isConfirmation,
            'trackingUrl' => $urls->tracking($booking),
            'documentUrl' => $urls->document($booking),
        ], 200, self::PRIVATE_HEADERS);
    }
}
