<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Storefront\Data;

use App\Modules\PropertyBooking\Catalog\Models\Property;
use Carbon\CarbonImmutable;

/** Available property and its guest-visible rate options for one interval. */
final readonly class PropertySearchResult
{
    /** @param list<AvailableStayOption> $options */
    public function __construct(
        public Property $property,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public array $options,
    ) {}

    /** Return the lowest current total across available options. */
    public function minimumTotalMinor(): int
    {
        return min(array_map(static fn (AvailableStayOption $option): int => $option->quote->calculation->totalMinor, $this->options));
    }

    /** Return the summed concrete-unit availability without exposing identities. */
    public function availableUnitCount(): int
    {
        return array_sum(array_map(static fn (AvailableStayOption $option): int => $option->quote->availableUnitCount, $this->options));
    }
}
