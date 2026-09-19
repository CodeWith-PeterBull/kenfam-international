<?php

/**
 * Adapts desk bookings to the institutional portrait PDF engine.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Services;

use App\Contracts\RendersPdfReports;
use App\Contracts\ResolvesInstitutionProfile;
use App\Data\ReportContext;
use App\Enums\ReportOrientation;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\PointOfBooking\Data\Documents\BookingReceiptData;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpFoundation\Response;

/** Streams the A4 counterpart of the thermal receipt through the shared report layout. */
final readonly class BookingReceiptService
{
    /** Create the PDF adapter with the shared institutional collaborators. */
    public function __construct(
        private RendersPdfReports $reports,
        private ResolvesInstitutionProfile $profiles,
        private BookingReceiptDataFactory $documents,
    ) {}

    /** Stream the portrait booking receipt; thermal receipts have no landscape form. */
    public function stream(TourBooking $booking, ?string $generatedBy = null): Response
    {
        $receipt = $this->documents->fromBooking($booking);

        return $this->reports->stream(
            view: 'travel-tours::reports.booking-receipt',
            data: ['receipt' => $receipt],
            context: $this->context($receipt, $generatedBy),
        );
    }

    /** Build the shared institutional report context. */
    private function context(BookingReceiptData $receipt, ?string $generatedBy): ReportContext
    {
        $profile = $this->profiles->current();

        return new ReportContext(
            title: "Booking receipt {$receipt->bookingNumber}",
            subtitle: "{$receipt->tourName} | {$receipt->placedAt->format('d M Y, H:i')}",
            filename: ReportContext::sanitizeFilename("{$receipt->bookingNumber}-receipt.pdf"),
            orientation: ReportOrientation::Portrait,
            generatedAt: CarbonImmutable::now(),
            generatedBy: filled($generatedBy) ? trim((string) $generatedBy) : "{$profile->shortName} booking desk",
            filters: [
                "Operator: {$receipt->operatorName}",
                "Tenders: {$receipt->tenderCount()}",
            ],
        );
    }
}
