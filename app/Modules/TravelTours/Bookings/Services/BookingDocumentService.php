<?php

/**
 * Implements a focused TravelTours domain or application service.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Bookings\Services;

use App\Contracts\RendersPdfReports;
use App\Contracts\ResolvesInstitutionProfile;
use App\Data\RenderedPdf;
use App\Data\ReportContext;
use App\Enums\ReportOrientation;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\Contracts\RendersBookingDocuments;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/** Adapts immutable booking snapshots to shared institutional PDF layouts. */
final readonly class BookingDocumentService implements RendersBookingDocuments
{
    /** Initialize the BookingDocumentService with its required dependencies or immutable state. */
    public function __construct(private RendersPdfReports $reports, private ResolvesInstitutionProfile $profiles) {}

    /** Render an authorized booking summary through the host document adapter. */
    public function summary(TourBooking $booking, string $orientation = 'portrait'): RenderedPdf
    {
        $reportOrientation = ReportOrientation::tryFrom($orientation) ?? throw new InvalidArgumentException('Unsupported report orientation.');
        $booking->loadMissing(['participants', 'priceLines', 'payments', 'departure.tour', 'customer']);
        $profile = $this->profiles->current();

        return $this->reports->render('travel-tours::reports.booking-summary', ['booking' => $booking], new ReportContext(
            title: "Tour booking {$booking->booking_number}", subtitle: $booking->tour_name_snapshot,
            filename: ReportContext::sanitizeFilename("{$booking->booking_number}-summary.pdf"), orientation: $reportOrientation,
            generatedAt: CarbonImmutable::now(), generatedBy: "{$profile->shortName} travel operations",
            filters: ["Status: {$booking->status->label()}", "Payment: {$booking->payment_status->label()}"],
        ));
    }
}
