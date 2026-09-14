<?php

/**
 * Idempotent ownership input for reserving a quoted departure allocation.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Scheduling\Data;

use App\Modules\TravelTours\Pricing\Data\TourQuote;
use InvalidArgumentException;

/** Bind a quote to one authenticated customer or purpose-scoped anonymous owner. */
final readonly class AvailabilityHoldRequest
{
    /** Validate retry identity and ownership evidence before any transaction starts. */
    public function __construct(
        public string $operationKey,
        public TourQuote $quote,
        public ?string $ownerToken = null,
        public ?int $customerId = null,
        public ?string $email = null,
    ) {
        if (trim($this->operationKey) === '' || mb_strlen($this->operationKey) > 64) {
            throw new InvalidArgumentException('Hold operation key must contain at most 64 characters.');
        }

        if ($this->ownerToken === null && $this->customerId === null) {
            throw new InvalidArgumentException('A hold requires an authenticated customer or anonymous owner token.');
        }
    }
}
