<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Reporting\Data;

/**
 * One zero-filled calendar point in the cross-channel collected-sales series.
 */
final readonly class SalesSeriesPoint
{
    public function __construct(
        public string $date,
        public string $label,
        public int $webMinor,
        public int $pointOfSaleMinor,
    ) {}

    /**
     * Return the combined collected amount for this calendar day.
     */
    public function totalMinor(): int
    {
        return $this->webMinor + $this->pointOfSaleMinor;
    }
}
