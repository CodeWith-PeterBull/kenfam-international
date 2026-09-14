<?php

/**
 * Defines validated, immutable input for one public TravelTours inquiry.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Inquiries\Data;

use App\Modules\TravelTours\Inquiries\Enums\InquiryType;
use Carbon\CarbonImmutable;

/** Carry only customer-controlled inquiry fields accepted by the domain service. */
final readonly class TourInquiryData
{
    /**
     * @param  list<string>  $requestedDestinations
     */
    public function __construct(
        public string $operationKey,
        public InquiryType $type,
        public string $contactName,
        public ?string $contactEmail,
        public ?string $contactPhone,
        public bool $whatsappPreferred,
        public array $requestedDestinations,
        public ?CarbonImmutable $preferredStartDate,
        public ?CarbonImmutable $preferredEndDate,
        public int $adultCount,
        public int $childCount,
        public int $infantCount,
        public ?string $budgetCurrency,
        public ?int $budgetMinor,
        public string $message,
        public ?int $tourId,
        public ?int $departureId,
        public ?string $consentIp,
        public string $consentPurpose,
        public string $consentVersion,
        public string $source = 'website',
    ) {}
}
