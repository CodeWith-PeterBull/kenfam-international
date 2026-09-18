<?php

/**
 * Validated, idempotent refund input accepted by the payment service.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Bookings\Data;

use InvalidArgumentException;

/** Carry refund evidence without allowing client-controlled aggregate state. */
final readonly class BookingRefundData
{
    /** @param array<string, scalar|null> $safeMetadata */
    public function __construct(
        public string $operationKey,
        public int $amountMinor,
        public string $currency,
        public string $reason,
        public ?int $paymentId = null,
        public ?string $processor = null,
        public ?string $transactionIdentifier = null,
        public ?int $shiftId = null,
        public ?int $actorId = null,
        public array $safeMetadata = [],
    ) {
        if (trim($this->operationKey) === '' || mb_strlen($this->operationKey) > 64) {
            throw new InvalidArgumentException('Refund operation key must contain at most 64 characters.');
        }

        if ($this->amountMinor <= 0) {
            throw new InvalidArgumentException('Refund amount must be greater than zero.');
        }

        if (preg_match('/^[A-Za-z]{3}$/', $this->currency) !== 1) {
            throw new InvalidArgumentException('Refund currency must use ISO 4217 alpha-3 format.');
        }

        if (trim($this->reason) === '') {
            throw new InvalidArgumentException('A refund requires a reason.');
        }
    }
}
