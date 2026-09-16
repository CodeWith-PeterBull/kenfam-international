<?php

/**
 * Defines immutable input for a TravelTours category write.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Data;

/** Carry validated category hierarchy, presentation, and SEO values. */
final readonly class TourCategoryData
{
    /** Initialize one normalized category write request. */
    public function __construct(
        public string $name,
        public ?string $slug = null,
        public ?int $parentId = null,
        public ?string $description = null,
        public ?string $iconKey = null,
        public int $sortOrder = 0,
        public bool $isActive = true,
        public ?string $metaTitle = null,
        public ?string $metaDescription = null,
    ) {}
}
