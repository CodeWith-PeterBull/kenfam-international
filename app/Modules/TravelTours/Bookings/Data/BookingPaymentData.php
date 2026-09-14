<?php

/**
 * Validated, idempotent payment input accepted by the payment service.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Bookings\Data;

use App\Modules\TravelTours\Bookings\Enums\PaymentMethod;
use InvalidArgumentException;

/** Carry payment evidence without allowing client-controlled aggregate state. */
final readonly class BookingPaymentData
{
    /** @param array<string, scalar|null> $safeMetadata */
    public function __construct(
        public string $operationKey,
        public PaymentMethod $method,
        public int $amountMinor,
        public string $currency,
        public ?string $reference = null,
        public ?string $provider = null,
        public ?string $transactionIdentifier = null,
        public ?int $paymentScheduleId = null,
        public ?int $shiftId = null,
        public ?int $actorId = null,
        public array $safeMetadata = [],
    ) {
        if (trim($this->operationKey) === '' || mb_strlen($this->operationKey) > 64) {
            throw new InvalidArgumentException('Payment operation key must contain at most 64 characters.');
        }

        if ($this->amountMinor <= 0) {
            throw new InvalidArgumentException('Payment amount must be greater than zero.');
        }

        if (preg_match('/^[A-Za-z]{3}$/', $this->currency) !== 1) {
            throw new InvalidArgumentException('Payment currency must use ISO 4217 alpha-3 format.');
        }
    }
}
