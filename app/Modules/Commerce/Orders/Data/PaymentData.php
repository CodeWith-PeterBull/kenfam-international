<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Orders\Data;

use App\Modules\Commerce\Exceptions\PaymentMismatchException;
use App\Modules\Commerce\Orders\Enums\PaymentMethod;

/**
 * One payment tender request with only sanitized non-credential context.
 */
final readonly class PaymentData
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public PaymentMethod $method,
        public int $amountMinor,
        public ?int $tenderedMinor = null,
        public ?string $reference = null,
        public array $metadata = [],
    ) {
        if ($amountMinor <= 0) {
            throw new PaymentMismatchException('Payment amounts must be greater than zero.');
        }

        if ($tenderedMinor !== null && $tenderedMinor < 0) {
            throw new PaymentMismatchException('Tendered amounts cannot be negative.');
        }

        if ($reference !== null && strlen(trim($reference)) > 120) {
            throw new PaymentMismatchException('Payment references cannot exceed 120 characters.');
        }
    }
}
