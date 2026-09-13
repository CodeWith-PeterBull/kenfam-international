<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Operations\Data;

/** Bounded operational queue item linked to a stable booking filter. */
final readonly class BookingActionSummary
{
    /** @param array<string, string> $filter */
    public function __construct(
        public string $key,
        public string $label,
        public string $description,
        public int $count,
        public string $icon,
        public string $tone,
        public array $filter,
    ) {}
}
