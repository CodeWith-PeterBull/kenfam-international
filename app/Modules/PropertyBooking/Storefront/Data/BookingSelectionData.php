<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Storefront\Data;

use App\Modules\PropertyBooking\Pricing\Data\BookingQuote;
use Carbon\CarbonImmutable;

/** Minimal server-session selection; monetary and availability values are excluded. */
final readonly class BookingSelectionData
{
    /** Create one public booking selection identity. */
    public function __construct(
        public string $ratePlanUlid,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public int $adults,
        public int $children,
        public int $infants,
    ) {}

    /** Create a selection from an authoritative quote. */
    public static function fromQuote(BookingQuote $quote): self
    {
        return new self(
            ratePlanUlid: $quote->ratePlanUlid,
            startsAt: $quote->startsAt,
            endsAt: $quote->endsAt,
            adults: $quote->adults,
            children: $quote->children,
            infants: $quote->infants,
        );
    }

    /** Serialize only safe scalar values into the session. */
    public function toSession(): array
    {
        return [
            'rate_plan_ulid' => $this->ratePlanUlid,
            'starts_at' => $this->startsAt->utc()->toIso8601String(),
            'ends_at' => $this->endsAt->utc()->toIso8601String(),
            'adults' => $this->adults,
            'children' => $this->children,
            'infants' => $this->infants,
        ];
    }

    /** Rehydrate a validated scalar session payload. */
    public static function fromSession(array $payload): ?self
    {
        try {
            $ratePlanUlid = trim((string) ($payload['rate_plan_ulid'] ?? ''));
            $startsAt = CarbonImmutable::parse((string) ($payload['starts_at'] ?? ''), 'UTC')->utc();
            $endsAt = CarbonImmutable::parse((string) ($payload['ends_at'] ?? ''), 'UTC')->utc();
            $adults = filter_var($payload['adults'] ?? null, FILTER_VALIDATE_INT);
            $children = filter_var($payload['children'] ?? null, FILTER_VALIDATE_INT);
            $infants = filter_var($payload['infants'] ?? null, FILTER_VALIDATE_INT);
        } catch (\Throwable) {
            return null;
        }

        if ($ratePlanUlid === '' || $adults === false || $children === false || $infants === false) {
            return null;
        }

        if ($adults < 1 || $adults > 20 || $children < 0 || $children > 20 || $infants < 0 || $infants > 10 || $endsAt->lessThanOrEqualTo($startsAt)) {
            return null;
        }

        return new self($ratePlanUlid, $startsAt, $endsAt, $adults, $children, $infants);
    }
}
