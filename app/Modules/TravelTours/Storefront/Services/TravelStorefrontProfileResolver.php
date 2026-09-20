<?php

/**
 * Projects host institution details into a client-neutral travel view profile.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Storefront\Services;

use App\Contracts\ResolvesInstitutionProfile;
use App\Modules\TravelTours\Storefront\Data\TravelStorefrontProfile;
use App\Modules\TravelTours\Support\InstitutionContact;
use Illuminate\Support\Facades\URL;

/** Resolve storefront identity without importing a client-specific config file. */
final readonly class TravelStorefrontProfileResolver
{
    /** Inject the host institution resolver used across Aureon documents and mail. */
    public function __construct(private ResolvesInstitutionProfile $institutions) {}

    /** Build a presentation-safe profile from institutional and module settings. */
    public function current(): TravelStorefrontProfile
    {
        $institution = $this->institutions->current();
        $contact = InstitutionContact::from($institution);
        $phoneLink = $this->telephoneLink($institution->primaryPhone);
        $logo = $institution->mainLogoUrl ?: $this->assetUrl(config('travel-tours.storefront.fallback_logo'));

        $lightLogo = $this->assetUrl(config('travel-tours.storefront.light_logo'))
            ?: $this->assetUrl(config('institution.assets.light_logo_url'))
            ?: $logo;
        $socialImage = $this->assetUrl(config('travel-tours.storefront.social_image'))
            ?: $this->assetUrl(config('institution.assets.social_image_url'))
            ?: $logo;
        $heroImage = $this->assetUrl(config('travel-tours.storefront.hero_image'))
            ?: $this->assetUrl(config('institution.assets.travel_hero_url'))
            ?: $socialImage;
        $configuredYear = config('travel-tours.storefront.founded_year')
            ?? config('institution.defaults.founded_year');

        return new TravelStorefrontProfile(
            name: $institution->name,
            shortName: $institution->shortName,
            descriptor: $institution->descriptor,
            email: $institution->primaryEmail,
            phone: $institution->primaryPhone,
            phoneLink: $phoneLink,
            whatsappNumber: $contact->whatsappNumber(),
            address: $institution->address(),
            foundedYear: $configuredYear === null ? null : (int) $configuredYear,
            logoUrl: $logo,
            lightLogoUrl: $lightLogo,
            iconUrl: $institution->logoIconUrl ?: $this->assetUrl(config('travel-tours.storefront.fallback_icon')),
            socialImageUrl: $socialImage,
            heroImageUrl: $heroImage,
            metaDescription: (string) config('travel-tours.storefront.meta_description'),
            socialLinks: $contact->socialLinks(),
            xHandle: $contact->xHandle(),
            sameAs: $contact->sameAs(),
        );
    }

    /** Convert a configured relative asset path into an absolute public URL. */
    private function assetUrl(mixed $path): string
    {
        $value = trim(is_string($path) ? $path : '');
        if ($value === '') {
            return '';
        }

        return filter_var($value, FILTER_VALIDATE_URL) ? $value : URL::to(ltrim($value, '/'));
    }

    /** Normalize a display telephone number for a tel link without inventing it. */
    private function telephoneLink(?string $phone): ?string
    {
        $normalized = preg_replace('/(?!^)\D+/', '', trim((string) $phone));

        return $normalized === null || $normalized === '' ? null : $normalized;
    }
}
