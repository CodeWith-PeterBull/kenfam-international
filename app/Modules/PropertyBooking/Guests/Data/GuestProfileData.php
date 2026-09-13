<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Guests\Data;

/** Normalized guest input whose optional identity value exists only at the write boundary. */
final readonly class GuestProfileData
{
    /** Create guest contact, address, and transient protected-identity input. */
    public function __construct(
        public string $firstName,
        public ?string $middleName,
        public string $lastName,
        public string $email,
        public string $phone,
        public ?string $addressLine1,
        public ?string $addressLine2,
        public ?string $city,
        public ?string $region,
        public ?string $postalCode,
        public string $countryCode,
        public ?string $identityType = null,
        public ?string $identityNumber = null,
    ) {}

    /** Convert the DTO to the existing guest service's normalized field names. */
    public function toAttributes(?int $userId = null): array
    {
        $attributes = [
            'user_id' => $userId,
            'first_name' => $this->firstName,
            'middle_name' => $this->middleName,
            'last_name' => $this->lastName,
            'email' => $this->email,
            'phone' => $this->phone,
            'address_line_1' => $this->addressLine1,
            'address_line_2' => $this->addressLine2,
            'city' => $this->city,
            'region' => $this->region,
            'postal_code' => $this->postalCode,
            'country_code' => $this->countryCode,
        ];

        if ($this->identityType !== null || $this->identityNumber !== null) {
            $attributes['identity_type'] = $this->identityType;
            $attributes['identity_number'] = $this->identityNumber;
        }

        return $attributes;
    }
}
