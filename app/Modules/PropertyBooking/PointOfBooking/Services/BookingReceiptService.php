<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\PointOfBooking\Services;

use App\Contracts\RendersPdfReports;
use App\Contracts\ResolvesInstitutionProfile;
use App\Data\ReportContext;
use App\Enums\ReportOrientation;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\PointOfBooking\Data\Documents\BookingReceiptData;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/** Adapts paid POB bookings to the institutional portrait PDF engine. */
final readonly class BookingReceiptService
{
    /** Create the PDF adapter with shared institutional collaborators. */
    public function __construct(
        private RendersPdfReports $reports,
        private ResolvesInstitutionProfile $profiles,
        private BookingReceiptDataFactory $documents,
    ) {}

    /** Stream a canonical portrait booking receipt. */
    public function stream(
        Booking $booking,
        ?string $generatedBy = null,
        ReportOrientation $orientation = ReportOrientation::Portrait,
    ): Response {
        $this->assertPortrait($orientation);
        $receipt = $this->documents->fromBooking($booking);

        return $this->reports->stream(
            view: 'property-booking::reports.booking-receipt',
            data: ['receipt' => $receipt],
            context: $this->context($receipt, $generatedBy),
        );
    }

    /** Download a canonical portrait booking receipt. */
    public function download(
        Booking $booking,
        ?string $generatedBy = null,
        ReportOrientation $orientation = ReportOrientation::Portrait,
    ): Response {
        $this->assertPortrait($orientation);
        $receipt = $this->documents->fromBooking($booking);

        return $this->reports->download(
            view: 'property-booking::reports.booking-receipt',
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
            subtitle: "{$receipt->propertyName} | {$receipt->placedAt->format('d M Y, H:i')}",
            filename: ReportContext::sanitizeFilename("{$receipt->bookingNumber}-receipt.pdf"),
            orientation: ReportOrientation::Portrait,
            generatedAt: CarbonImmutable::now(),
            generatedBy: filled($generatedBy) ? trim((string) $generatedBy) : "{$profile->shortName} reception",
            filters: [
                "Receptionist: {$receipt->receptionistName}",
                "Tenders: {$receipt->tenderCount()}",
            ],
        );
    }

    /** Thermal receipt PDFs deliberately remain portrait-only. */
    private function assertPortrait(ReportOrientation $orientation): void
    {
        if ($orientation !== ReportOrientation::Portrait) {
            throw new InvalidArgumentException('Point of Booking receipt PDFs support portrait orientation only.');
        }
    }
}
