<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Orders\Services;

use App\Contracts\RendersPdfReports;
use App\Contracts\ResolvesInstitutionProfile;
use App\Data\ReportContext;
use App\Enums\ReportOrientation;
use App\Modules\Commerce\Orders\Data\Documents\OrderDocumentData;
use App\Modules\Commerce\Orders\Models\Order;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adapts Commerce order snapshots to the shared orientation-aware PDF engine.
 */
final readonly class OrderDocumentService
{
    public function __construct(
        private RendersPdfReports $reports,
        private ResolvesInstitutionProfile $profiles,
        private OrderDocumentDataFactory $documents,
    ) {}

    /**
     * Stream a printable order summary inline.
     */
    public function stream(
        Order $order,
        ReportOrientation $orientation = ReportOrientation::Portrait,
        ?string $generatedBy = null,
    ): Response {
        $document = $this->documents->fromOrder($order);

        return $this->reports->stream(
            view: 'commerce::reports.order-summary',
            data: ['document' => $document],
            context: $this->context($document, $orientation, $generatedBy),
        );
    }

    /**
     * Download a printable order summary as an attachment.
     */
    public function download(
        Order $order,
        ReportOrientation $orientation = ReportOrientation::Portrait,
        ?string $generatedBy = null,
    ): Response {
        $document = $this->documents->fromOrder($order);

        return $this->reports->download(
            view: 'commerce::reports.order-summary',
            data: ['document' => $document],
            context: $this->context($document, $orientation, $generatedBy),
        );
    }

    private function context(OrderDocumentData $document, ReportOrientation $orientation, ?string $generatedBy): ReportContext
    {
        $profile = $this->profiles->current();

        return new ReportContext(
            title: "Order {$document->orderNumber}",
            subtitle: "{$document->fulfillmentLabel} | {$document->statusLabel}",
            filename: ReportContext::sanitizeFilename("{$document->orderNumber}-{$orientation->value}.pdf"),
            orientation: $orientation,
            generatedAt: CarbonImmutable::now(),
            generatedBy: filled($generatedBy) ? trim((string) $generatedBy) : "{$profile->shortName} Commerce",
            filters: [
                "Order channel: {$document->channelLabel}",
                "Payment status: {$document->paymentStatusLabel}",
            ],
        );
    }
}
