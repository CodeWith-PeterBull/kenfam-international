<?php

/**
 * Defines immutable input for a TravelTours destination write.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Data;

use App\Modules\TravelTours\Catalog\Enums\DestinationType;
use App\Modules\TravelTours\Catalog\Enums\PublicationStatus;
use Carbon\CarbonImmutable;

/** Carry validated destination hierarchy, geography, publication, and SEO data. */
final readonly class DestinationData
{
    /** Initialize one normalized destination write request. */
    public function __construct(
        public DestinationType $type,
        public string $name,
        public ?string $slug = null,
        public ?int $parentId = null,
        public ?string $countryCode = null,
        public ?string $code = null,
        public ?string $shortDescription = null,
        public ?string $description = null,
        public ?string $latitude = null,
        public ?string $longitude = null,
        public ?string $timezone = null,
        public bool $isFeatured = false,
        public bool $isActive = true,
        public PublicationStatus $status = PublicationStatus::Draft,
        public int $sortOrder = 0,
        public ?string $metaTitle = null,
        public ?string $metaDescription = null,
        public ?CarbonImmutable $publishedAt = null,
    ) {}
}
