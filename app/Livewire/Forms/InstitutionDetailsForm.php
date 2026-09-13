<?php

namespace App\Livewire\Forms;

use App\Data\InstitutionProfileData;
use Livewire\Attributes\Validate;
use Livewire\Form;

class InstitutionDetailsForm extends Form
{
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|string|max:80')]
    public string $shortName = '';

    #[Validate('nullable|string|max:255')]
    public ?string $descriptor = null;

    #[Validate('nullable|email:rfc|max:255')]
    public ?string $primaryEmail = null;

    #[Validate('nullable|email:rfc|max:255')]
    public ?string $secondaryEmail = null;

    #[Validate('nullable|string|max:40')]
    public ?string $primaryPhone = null;

    #[Validate('nullable|string|max:40')]
    public ?string $secondaryPhone = null;

    #[Validate('nullable|url:http,https|max:255')]
    public ?string $website = null;

    #[Validate('nullable|string|max:255')]
    public ?string $physicalAddress = null;

    #[Validate('nullable|string|max:120')]
    public ?string $city = null;

    #[Validate('nullable|string|max:120')]
    public ?string $county = null;

    #[Validate('nullable|string|max:30')]
    public ?string $postalCode = null;

    #[Validate('nullable|string|max:255')]
    public ?string $postalAddress = null;

    #[Validate('nullable|string|max:120')]
    public ?string $postalCity = null;

    /** @var list<array{platform: string, handle: string, url: string}> */
    #[Validate([
        'socialMedia' => 'array|max:12',
        'socialMedia.*.platform' => 'nullable|string|max:60',
        'socialMedia.*.handle' => 'nullable|string|max:120',
        'socialMedia.*.url' => 'nullable|url:http,https|max:255',
    ])]
    public array $socialMedia = [];

    public function fillFromProfile(InstitutionProfileData $profile): void
    {
        $this->name = $profile->name;
        $this->shortName = $profile->shortName;
        $this->descriptor = $profile->descriptor;
        $this->primaryEmail = $profile->primaryEmail;
        $this->secondaryEmail = $profile->secondaryEmail;
        $this->primaryPhone = $profile->primaryPhone;
        $this->secondaryPhone = $profile->secondaryPhone;
        $this->website = $profile->website;
        $this->physicalAddress = $profile->physicalAddress;
        $this->city = $profile->city;
        $this->county = $profile->county;
        $this->postalCode = $profile->postalCode;
        $this->postalAddress = $profile->postalAddress;
        $this->postalCity = $profile->postalCity;
        $this->socialMedia = $profile->socialMedia;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'name' => trim($this->name),
            'short_name' => trim($this->shortName),
            'descriptor' => $this->nullable($this->descriptor),
            'primary_email' => $this->nullable($this->primaryEmail),
            'secondary_email' => $this->nullable($this->secondaryEmail),
            'primary_phone' => $this->nullable($this->primaryPhone),
            'secondary_phone' => $this->nullable($this->secondaryPhone),
            'website' => $this->nullable($this->website),
            'physical_address' => $this->nullable($this->physicalAddress),
            'city' => $this->nullable($this->city),
            'county' => $this->nullable($this->county),
            'postal_code' => $this->nullable($this->postalCode),
            'postal_address' => $this->nullable($this->postalAddress),
            'postal_city' => $this->nullable($this->postalCity),
            'social_media' => $this->normalizedSocialMedia(),
        ];
    }

    private function nullable(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** @return list<array{platform: string, handle: string, url: string}>|null */
    private function normalizedSocialMedia(): ?array
    {
        $rows = array_values(array_filter(array_map(
            fn (array $row): array => [
                'platform' => trim((string) ($row['platform'] ?? '')),
                'handle' => trim((string) ($row['handle'] ?? '')),
                'url' => trim((string) ($row['url'] ?? '')),
            ],
            $this->socialMedia,
        ), static fn (array $row): bool => implode('', $row) !== ''));

        return $rows === [] ? null : $rows;
    }
}
