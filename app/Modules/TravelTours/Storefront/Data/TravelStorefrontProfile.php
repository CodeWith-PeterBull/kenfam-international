<?php

/**
 * Defines the client-neutral institution projection consumed by travel views.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Storefront\Data;

/** Carry presentation-safe host identity and travel storefront asset URLs. */
final readonly class TravelStorefrontProfile
{
    /** Initialize the immutable, presentation-safe storefront profile. */
    public function __construct(
        public string $name,
        public string $shortName,
        public ?string $descriptor,
        public ?string $email,
        public ?string $phone,
        public ?string $phoneLink,
        public ?string $whatsappNumber,
        public ?string $address,
        public ?int $foundedYear,
        public string $logoUrl,
        public string $lightLogoUrl,
        public string $iconUrl,
        public string $socialImageUrl,
        public string $heroImageUrl,
        public string $metaDescription,
    ) {}
}
