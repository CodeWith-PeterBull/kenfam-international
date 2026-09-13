<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Storefront\Http\Controllers;

use App\Enums\ReportOrientation;
use App\Http\Controllers\Controller;
use App\Modules\PropertyBooking\Bookings\Enums\BookingChannel;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Bookings\Services\BookingDocumentService;
use App\Modules\PropertyBooking\Storefront\Services\BookingAccessUrlService;
use App\Modules\PropertyBooking\Storefront\Services\StorefrontNavigation;
use Symfony\Component\HttpFoundation\Response;

/** Serves private-by-signature web booking pages and summary documents. */
final class BookingAccessController extends Controller
{
    /** Create the signed access controller with its public adapters. */
    public function __construct(
        private readonly StorefrontNavigation $navigation,
        private readonly BookingAccessUrlService $urls,
        private readonly BookingDocumentService $documents,
    ) {}

    /** Render the immediate post-placement confirmation. */
    public function confirmation(Booking $booking): Response
    {
        $booking = $this->publicAggregate($booking);

        return response()->view('property-booking::storefront.bookings.confirmation', [
            'booking' => $booking,
            'trackingUrl' => $this->urls->tracking($booking),
            'portraitDocumentUrl' => $this->urls->document($booking),
            'landscapeDocumentUrl' => $this->urls->document($booking, ReportOrientation::Landscape),
            'storefrontNavigationCategories' => $this->navigation->categories(),
        ])->withHeaders($this->privateHeaders());
    }

    /** Render the current guest-visible booking status. */
    public function track(Booking $booking): Response
    {
        $booking = $this->publicAggregate($booking);

        return response()->view('property-booking::storefront.bookings.track', [
            'booking' => $booking,
            'portraitDocumentUrl' => $this->urls->document($booking),
            'landscapeDocumentUrl' => $this->urls->document($booking, ReportOrientation::Landscape),
            'storefrontNavigationCategories' => $this->navigation->categories(),
        ])->withHeaders($this->privateHeaders());
    }

    /** Stream one signed orientation-aware booking summary. */
    public function document(Booking $booking, ReportOrientation $orientation): Response
    {
        $response = $this->documents->stream($this->publicAggregate($booking), $orientation);
        foreach ($this->privateHeaders() as $header => $value) {
            $response->headers->set($header, $value);
        }

        return $response;
    }

    /** Load only a placed web aggregate and guest-visible stay snapshots. */
    private function publicAggregate(Booking $booking): Booking
    {
        abort_unless($booking->channel === BookingChannel::Web && filled($booking->booking_number), 404);

        return $booking->loadMissing('stays');
    }

    /** Return cache and indexing protections for signed booking content. */
    private function privateHeaders(): array
    {
        return [
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ];
    }
}
