<?php

/** Carries a priced, optional tour add-on in integer minor units. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Data;

/** Immutable add-on identity, monetary value and selection limits. */
final readonly class TourExtraData
{
    /** @param list<string> $participantTypes */
    public function __construct(
        public string $code,
        public string $name,
        public string $pricingUnit,
        public int $amountMinor,
        public string $currency,
        public ?string $description = null,
        public array $participantTypes = [],
        public int $minimumQuantity = 0,
        public ?int $maximumQuantity = null,
        public bool $isActive = true,
        public bool $isPublic = true,
    ) {}
}
