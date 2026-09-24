<?php

namespace App\Data;

final readonly class InstitutionProfileData
{
    /**
     * @param  list<array{platform: string, handle: string, url: string}>  $socialMedia
     */
    public function __construct(
        public ?int $id,
        public string $name,
        public string $shortName,
        public ?string $descriptor,
        public ?string $primaryEmail,
        public ?string $secondaryEmail,
        public ?string $primaryPhone,
        public ?string $secondaryPhone,
        public ?string $website,
        public ?string $physicalAddress,
        public ?string $city,
        public ?string $county,
        public ?string $postalCode,
        public ?string $postalAddress,
        public ?string $postalCity,
        public array $socialMedia,
        public ?string $mainLogoUrl,
        public ?string $lightLogoUrl,
        public ?string $mainLogoPath,
        public bool $hasCustomMainLogo,
        public ?string $logoIconUrl,
        public ?string $logoIconPath,
        public bool $hasCustomLogoIcon,
    ) {}

    public function address(): ?string
    {
        $physical = implode(', ', array_filter([
            $this->physicalAddress,
            $this->city,
            $this->county,
        ]));

        return $physical !== '' ? $physical : $this->postalAddress;
    }

    public function socialLine(): ?string
    {
        $parts = array_values(array_filter(array_map(
            static fn (array $social): ?string => trim($social['platform'].' '.$social['handle']) ?: null,
            $this->socialMedia,
        )));

        return $parts === [] ? null : implode(' | ', $parts);
    }

    public function logoDataUri(): ?string
    {
        if (! $this->mainLogoPath || ! is_file($this->mainLogoPath)) {
            return null;
        }

        $mime = mime_content_type($this->mainLogoPath);
        if (! in_array($mime, ['image/png', 'image/jpeg'], true)) {
            return null;
        }

        $contents = file_get_contents($this->mainLogoPath);

        return $contents === false ? null : sprintf('data:%s;base64,%s', $mime, base64_encode($contents));
    }
}
