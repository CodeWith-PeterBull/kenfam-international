<?php

/**
 * Defines immutable input for a TravelTours tour write.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Data;

use App\Modules\TravelTours\Bookings\Enums\BookingMode;
use App\Modules\TravelTours\Catalog\Enums\PublicationStatus;
use App\Modules\TravelTours\Catalog\Enums\TourDifficulty;
use App\Modules\TravelTours\Catalog\Enums\TourType;
use Carbon\CarbonImmutable;

/** Carry validated tour identity, content, policy, and publication values. */
final readonly class TourData
{
    /**
     * Initialize one normalized tour write request.
     *
     * @param  list<string>  $languages
     */
    public function __construct(
        public string $code,
        public string $name,
        public TourType $type,
        public int $durationDays,
        public ?string $slug = null,
        public PublicationStatus $status = PublicationStatus::Draft,
        public ?string $tagline = null,
        public ?string $shortDescription = null,
        public ?string $description = null,
        public int $durationNights = 0,
        public int $minimumAge = 0,
        public TourDifficulty $difficulty = TourDifficulty::Easy,
        public int $minimumParticipants = 1,
        public ?int $maximumParticipants = null,
        public array $languages = [],
        public BookingMode $bookingMode = BookingMode::Approval,
        public ?string $meetingPointName = null,
        public ?string $meetingPointDetails = null,
        public ?string $meetingLatitude = null,
        public ?string $meetingLongitude = null,
        public ?string $endPointName = null,
        public ?string $endPointDetails = null,
        public ?string $endLatitude = null,
        public ?string $endLongitude = null,
        public ?string $terms = null,
        public ?string $cancellationSummary = null,
        public ?string $policyVersion = null,
        public bool $isFeatured = false,
        public int $sortOrder = 0,
        public ?string $metaTitle = null,
        public ?string $metaDescription = null,
        public ?CarbonImmutable $publishedAt = null,
    ) {}
}
