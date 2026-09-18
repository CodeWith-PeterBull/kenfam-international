<?php

/**
 * Defines immutable typed input or output data for a TravelTours operation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Data;

/** What the terminal must do to put a receipt in the operator's hands. */
final readonly class ReceiptPrintInstructionData
{
    /** Carry the driver's answer; the browser driver hands back a page to open. */
    public function __construct(
        public string $driver,
        public string $url,
        public bool $automatic,
        public int $paperWidthMm,
    ) {}
}
