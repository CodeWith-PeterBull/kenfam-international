<?php

namespace App\Services;

use App\Contracts\ResolvesInstitutionProfile;
use App\Data\InstitutionProfileData;
use App\Models\InstitutionDetail;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;

final class InstitutionProfileResolver implements ResolvesInstitutionProfile
{
    private ?InstitutionProfileData $resolved = null;

    public function current(): InstitutionProfileData
    {
        return $this->resolved ??= $this->resolve();
    }

    public function forget(): void
    {
        $this->resolved = null;
    }

    private function resolve(): InstitutionProfileData
    {
        $defaults = config('institution.defaults');
        $detail = $this->findDetail();
        $mediaAvailable = $detail !== null && $this->hasTable('media');
        $mainLogo = $mediaAvailable ? $detail->mainLogo() : null;
        $logoIcon = $mediaAvailable ? $detail->logoIcon() : null;

        return new InstitutionProfileData(
            id: $detail?->id,
            name: $detail?->name ?: (string) $defaults['name'],
            shortName: $detail?->short_name ?: (string) $defaults['short_name'],
            descriptor: $detail?->descriptor ?: $defaults['descriptor'],
            primaryEmail: $detail?->primary_email ?: $defaults['primary_email'],
            secondaryEmail: $detail?->secondary_email ?: $defaults['secondary_email'],
            primaryPhone: $detail?->primary_phone ?: $defaults['primary_phone'],
            secondaryPhone: $detail?->secondary_phone ?: $defaults['secondary_phone'],
            website: $detail?->website ?: $defaults['website'],
            physicalAddress: $detail?->physical_address ?: $defaults['physical_address'],
            city: $detail?->city ?: $defaults['city'],
            county: $detail?->county ?: $defaults['county'],
            postalCode: $detail?->postal_code ?: $defaults['postal_code'],
            postalAddress: $detail?->postal_address ?: $defaults['postal_address'],
            postalCity: $detail?->postal_city ?: $defaults['postal_city'],
            socialMedia: $detail?->social_media ?: $defaults['social_media'],
            mainLogoUrl: $mainLogo ? URL::to($mainLogo->getUrl()) : URL::to((string) config('institution.assets.main_logo_url')),
            mainLogoPath: $mainLogo?->getPath() ?: config('institution.assets.main_logo_path'),
            hasCustomMainLogo: $mainLogo !== null,
            logoIconUrl: $logoIcon ? URL::to($logoIcon->getUrl()) : URL::to((string) config('institution.assets.logo_icon_url')),
            logoIconPath: $logoIcon?->getPath() ?: config('institution.assets.logo_icon_path'),
            hasCustomLogoIcon: $logoIcon !== null,
        );
    }

    private function findDetail(): ?InstitutionDetail
    {
        try {
            if (! $this->hasTable('institution_details')) {
                return null;
            }

            return InstitutionDetail::query()->find(InstitutionDetail::PRIMARY_ID);
        } catch (QueryException) {
            return null;
        }
    }

    private function hasTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (QueryException) {
            return false;
        }
    }
}
