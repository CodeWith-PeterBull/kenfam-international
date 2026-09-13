<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Orders\Data;

use App\Modules\Commerce\Exceptions\InvalidCartException;

/**
 * Immutable customer and fulfillment snapshot supplied to order placement.
 */
final readonly class CustomerSnapshotData
{
    public string $firstName;

    public string $lastName;

    public ?string $company;

    public ?string $taxIdentifier;

    public ?string $email;

    public ?string $phone;

    public ?string $addressLine1;

    public ?string $addressLine2;

    public ?string $city;

    public ?string $region;

    public ?string $postalCode;

    public string $countryCode;

    public function __construct(
        string $firstName,
        string $lastName,
        ?string $company = null,
        ?string $taxIdentifier = null,
        ?string $email = null,
        ?string $phone = null,
        ?string $addressLine1 = null,
        ?string $addressLine2 = null,
        ?string $city = null,
        ?string $region = null,
        ?string $postalCode = null,
        string $countryCode = 'KE',
    ) {
        $this->firstName = $this->required($firstName, 'A customer first name is required.');
        $this->lastName = $this->required($lastName, 'A customer last name is required.');
        $this->company = $this->optional($company);
        $this->taxIdentifier = $this->optional($taxIdentifier);
        $this->email = $this->optional($email === null ? null : strtolower($email));
        $this->phone = $this->optional($phone);
        $this->addressLine1 = $this->optional($addressLine1);
        $this->addressLine2 = $this->optional($addressLine2);
        $this->city = $this->optional($city);
        $this->region = $this->optional($region);
        $this->postalCode = $this->optional($postalCode);
        $this->countryCode = strtoupper(trim($countryCode));

        if (strlen($this->countryCode) !== 2) {
            throw new InvalidCartException('Customer country codes must contain two letters.');
        }
    }

    private function required(string $value, string $message): string
    {
        $value = trim($value);

        if ($value === '') {
            throw new InvalidCartException($message);
        }

        return $value;
    }

    private function optional(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
