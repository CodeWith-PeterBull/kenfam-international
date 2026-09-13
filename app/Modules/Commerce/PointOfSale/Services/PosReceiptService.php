<?php

declare(strict_types=1);

namespace App\Modules\Commerce\PointOfSale\Services;

use App\Contracts\RendersPdfReports;
use App\Contracts\ResolvesInstitutionProfile;
use App\Data\ReportContext;
use App\Enums\ReportOrientation;
use App\Modules\Commerce\Orders\Models\Order;
use App\Modules\Commerce\PointOfSale\Data\Documents\PosReceiptData;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adapts completed POS aggregates to the shared portrait PDF engine.
 */
final readonly class PosReceiptService
{
    public function __construct(
        private RendersPdfReports $reports,
        private ResolvesInstitutionProfile $profiles,
        private PosReceiptDataFactory $documents,
    ) {}

    public function stream(
        Order $order,
        ?string $generatedBy = null,
        ReportOrientation $orientation = ReportOrientation::Portrait,
    ): Response {
        $this->assertPortrait($orientation);
        $receipt = $this->documents->fromOrder($order);

        return $this->reports->stream(
            view: 'commerce::reports.pos-receipt',
            data: ['receipt' => $receipt],
            context: $this->context($receipt, $generatedBy),
        );
    }

    public function download(
        Order $order,
        ?string $generatedBy = null,
        ReportOrientation $orientation = ReportOrientation::Portrait,
    ): Response {
        $this->assertPortrait($orientation);
        $receipt = $this->documents->fromOrder($order);

        return $this->reports->download(
            view: 'commerce::reports.pos-receipt',
            data: ['receipt' => $receipt],
            context: $this->context($receipt, $generatedBy),
        );
    }

    private function context(PosReceiptData $receipt, ?string $generatedBy): ReportContext
    {
        $profile = $this->profiles->current();

        return new ReportContext(
            title: "Receipt {$receipt->orderNumber}",
            subtitle: "{$receipt->registerName} | {$receipt->placedAt?->format('d M Y, H:i')}",
            filename: ReportContext::sanitizeFilename("{$receipt->orderNumber}-receipt.pdf"),
            orientation: ReportOrientation::Portrait,
            generatedAt: CarbonImmutable::now(),
            generatedBy: filled($generatedBy) ? trim((string) $generatedBy) : "{$profile->shortName} POS",
            filters: [
                "Cashier: {$receipt->cashierName}",
                "Tenders: {$receipt->tenderCount()}",
            ],
        );
    }

    private function assertPortrait(ReportOrientation $orientation): void
    {
        if ($orientation !== ReportOrientation::Portrait) {
            throw new InvalidArgumentException('POS receipt PDFs support portrait orientation only.');
        }
    }
}
