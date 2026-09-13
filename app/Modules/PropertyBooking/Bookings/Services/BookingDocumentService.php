<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Services;

use App\Contracts\RendersPdfReports;
use App\Contracts\ResolvesInstitutionProfile;
use App\Data\ReportContext;
use App\Enums\ReportOrientation;
use App\Modules\PropertyBooking\Bookings\Data\Documents\BookingDocumentData;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpFoundation\Response;

/** Adapts booking snapshots to shared institutional portrait/landscape PDFs. */
final readonly class BookingDocumentService
{
    /** Create document rendering from shared host contracts. */
    public function __construct(
        private RendersPdfReports $reports,
        private ResolvesInstitutionProfile $profiles,
        private BookingDocumentDataFactory $documents,
    ) {}

    /** Stream a privacy-safe booking summary inline. */
    public function stream(Booking $booking, ReportOrientation $orientation = ReportOrientation::Portrait, ?string $generatedBy = null): Response
    {
        $document = $this->documents->fromBooking($booking);

        return $this->reports->stream(
            view: 'property-booking::reports.booking-summary',
            data: ['document' => $document],
            context: $this->context($document, $orientation, $generatedBy),
        );
    }

    /** Download a privacy-safe booking summary attachment. */
    public function download(Booking $booking, ReportOrientation $orientation = ReportOrientation::Portrait, ?string $generatedBy = null): Response
    {
        $document = $this->documents->fromBooking($booking);

        return $this->reports->download(
            view: 'property-booking::reports.booking-summary',
            data: ['document' => $document],
            context: $this->context($document, $orientation, $generatedBy),
        );
    }

    /** Build shared report metadata and a safe filename. */
    private function context(BookingDocumentData $document, ReportOrientation $orientation, ?string $generatedBy): ReportContext
    {
        $profile = $this->profiles->current();

        return new ReportContext(
            title: "Booking {$document->bookingNumber}",
            subtitle: "{$document->propertyName} | {$document->statusLabel}",
            filename: ReportContext::sanitizeFilename("{$document->bookingNumber}-{$orientation->value}.pdf"),
            orientation: $orientation,
            generatedAt: CarbonImmutable::now(),
            generatedBy: filled($generatedBy) ? trim((string) $generatedBy) : "{$profile->shortName} Accommodation",
            filters: [
                "Stay status: {$document->stayStatusLabel}",
                "Payment status: {$document->paymentStatusLabel}",
            ],
        );
    }
}
