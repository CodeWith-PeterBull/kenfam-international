<?php

/** Carries one typed public experience item. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Data;

use App\Modules\TravelTours\Catalog\Enums\ContentItemType;

/** Immutable highlight, inclusion, exclusion, requirement or packing note. */
final readonly class TourContentItemData
{
    /** Capture a typed public content item without persistence metadata. */
    public function __construct(
        public ContentItemType $type,
        public string $content,
        public ?string $title = null,
    ) {}
}
