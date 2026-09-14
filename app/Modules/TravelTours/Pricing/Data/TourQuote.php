<?php

/**
 * Defines immutable typed input or output data for a TravelTours operation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Pricing\Data;

/** Authoritative deterministic tour quote represented entirely in minor units. */
final readonly class TourQuote
{
    /** @param list<TourQuoteLine> $lines */
    public function __construct(
        public int $departureId,
        public int $ratePlanId,
        public ParticipantMix $participants,
        public string $currency,
        public array $lines,
        public int $subtotalMinor,
        public int $discountMinor,
        public int $taxMinor,
        public int $totalMinor,
        public int $depositMinor,
        public ?int $promotionId,
        public string $fingerprint,
    ) {}

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        return [
            'departure_id' => $this->departureId, 'rate_plan_id' => $this->ratePlanId,
            'participants' => $this->participants->byType(), 'currency' => $this->currency,
            'lines' => array_map(static fn (TourQuoteLine $line): array => (array) $line, $this->lines),
            'subtotal_minor' => $this->subtotalMinor, 'discount_minor' => $this->discountMinor,
            'tax_minor' => $this->taxMinor, 'total_minor' => $this->totalMinor, 'deposit_minor' => $this->depositMinor,
            'promotion_id' => $this->promotionId, 'fingerprint' => $this->fingerprint,
        ];
    }
}
