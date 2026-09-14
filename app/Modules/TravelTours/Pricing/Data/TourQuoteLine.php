<?php

/**
 * Defines immutable typed input or output data for a TravelTours operation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Pricing\Data;

/** Immutable and document-ready component of a tour quote. */
final readonly class TourQuoteLine
{
    /** @param array<string, scalar|null> $metadata */
    public function __construct(
        public string $type,
        public string $description,
        public int $quantity,
        public int $unitAmountMinor,
        public int $totalMinor,
        public array $metadata = [],
    ) {}
}
