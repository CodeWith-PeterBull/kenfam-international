<?php

/**
 * Defines immutable typed input or output data for a TravelTours operation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Bookings\Data;

use App\Modules\TravelTours\Bookings\Enums\BookingChannel;
use App\Modules\TravelTours\Bookings\Enums\PaymentMethod;
use App\Modules\TravelTours\Customers\Data\TravelCustomerData;
use InvalidArgumentException;

/** Validated customer, participants, terms, and channel supplied to booking placement. */
final readonly class BookingPlacementData
{
    /**
     * @param  list<BookingParticipantData>  $participants
     * @param  array<string, scalar|null>  $attribution
     */
    public function __construct(
        public string $operationKey,
        public string $holdUlid,
        public ?string $holdOwnerToken,
        public TravelCustomerData $customer,
        public array $participants,
        public BookingChannel $channel,
        public PaymentMethod $preferredPaymentMethod,
        public string $termsVersion,
        public ?string $specialRequests = null,
        public array $attribution = [],
        public ?int $actorId = null,
        public ?int $registerId = null,
        public ?int $shiftId = null,
    ) {
        if (trim($this->operationKey) === '' || mb_strlen($this->operationKey) > 64) {
            throw new InvalidArgumentException('Booking operation key must contain at most 64 characters.');
        }

        if ($this->participants === [] || array_filter($this->participants, fn (mixed $participant): bool => ! $participant instanceof BookingParticipantData) !== []) {
            throw new InvalidArgumentException('Booking participants must be typed participant data objects.');
        }

        if (trim($this->termsVersion) === '') {
            throw new InvalidArgumentException('Accepted booking terms version is required.');
        }
    }
}
