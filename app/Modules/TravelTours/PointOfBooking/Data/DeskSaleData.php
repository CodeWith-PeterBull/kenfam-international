<?php

/**
 * Defines immutable typed input or output data for a TravelTours operation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Data;

use App\Modules\TravelTours\Bookings\Data\BookingParticipantData;
use App\Modules\TravelTours\Customers\Data\TravelCustomerData;
use App\Modules\TravelTours\Pricing\Data\ParticipantMix;
use InvalidArgumentException;

/** Everything an operator captured for one desk booking; the desk service prices and writes it. */
final readonly class DeskSaleData
{
    /** @param list<BookingParticipantData> $participants */
    public function __construct(
        public string $operationKey,
        public int $departureId,
        public ParticipantMix $mix,
        public TravelCustomerData $customer,
        public array $participants,
        public ?string $promotionCode = null,
        public ?string $specialRequests = null,
    ) {
        if (trim($this->operationKey) === '' || mb_strlen($this->operationKey) > 48) {
            throw new InvalidArgumentException('Desk sale operation key must contain at most 48 characters.');
        }
        if ($this->participants === [] || array_filter($this->participants, fn (mixed $participant): bool => ! $participant instanceof BookingParticipantData) !== []) {
            throw new InvalidArgumentException('Desk sale participants must be typed participant data objects.');
        }
    }
}
