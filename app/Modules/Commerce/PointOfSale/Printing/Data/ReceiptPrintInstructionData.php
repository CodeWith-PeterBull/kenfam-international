<?php

declare(strict_types=1);

namespace App\Modules\Commerce\PointOfSale\Printing\Data;

use App\Modules\Commerce\PointOfSale\Enums\ReceiptPaperWidth;
use App\Modules\Commerce\PointOfSale\Enums\ReceiptPrintMode;

/**
 * Browser-safe print instruction produced by a configured printer driver.
 */
final readonly class ReceiptPrintInstructionData
{
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

    /**
     * Serialize only the values required by the receipt print client.
     *
     * @return array<string, bool|int|string|null>
     */
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
