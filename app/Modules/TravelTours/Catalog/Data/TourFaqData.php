<?php

/** Carries a traveler-facing tour question and answer. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Data;

/** Immutable FAQ content and visibility choice. */
final readonly class TourFaqData
{
    /** Capture the FAQ's traveler-facing text and visibility state. */
    public function __construct(
        public string $question,
        public string $answer,
        public bool $isActive = true,
    ) {}
}
