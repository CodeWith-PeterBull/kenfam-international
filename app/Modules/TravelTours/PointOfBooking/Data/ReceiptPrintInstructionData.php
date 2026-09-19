<?php

/**
 * Defines immutable typed input or output data for a TravelTours operation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Data;

use App\Modules\TravelTours\PointOfBooking\Enums\ReceiptPaperWidth;
use App\Modules\TravelTours\PointOfBooking\Enums\ReceiptPrintMode;

/** Browser-safe instruction returned by the configured desk print adapter. */
final readonly class ReceiptPrintInstructionData
{
    /** Create a complete printer instruction; nothing here can roll back the sale that produced it. */
    public function __construct(
        public string $driver,
        public string $strategy,
        public ReceiptPrintMode $mode,
        public ReceiptPaperWidth $paperWidth,
        public ?string $printerName,
        public bool $autoPrompt,
        public bool $supportsSilentPrinting,
        public string $receiptKey,
    ) {}

    /** @return array<string, bool|int|string|null> */
    public function toBrowserArray(): array
    {
        return [
            'driver' => $this->driver,
            'strategy' => $this->strategy,
            'mode' => $this->mode->value,
            'paperWidthMillimeters' => $this->paperWidth->value,
            'printableWidthMillimeters' => $this->paperWidth->printableWidth(),
            'printerName' => $this->printerName,
            'autoPrompt' => $this->autoPrompt,
            'supportsSilentPrinting' => $this->supportsSilentPrinting,
            'receiptKey' => $this->receiptKey,
        ];
    }
}
