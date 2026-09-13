<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Guests\Livewire\Forms;

use App\Modules\PropertyBooking\Guests\Models\Guest;
use Livewire\Form;

/** Validates reusable guest profile and optional protected identity input. */
final class GuestForm extends Form
{
    public string $title = '';

    public string $firstName = '';

    public string $middleName = '';

    public string $lastName = '';

    public string $email = '';

    public string $phone = '';

    public string $alternatePhone = '';

    public string $dateOfBirth = '';

    public string $nationalityCountryCode = '';

    public string $identityType = '';

    public string $identityNumber = '';

    public string $identityCountryCode = '';

    public string $addressLine1 = '';

    public string $addressLine2 = '';

    public string $city = '';

    public string $region = '';

    public string $postalCode = '';

    public string $countryCode = '';

    public string $emergencyContactName = '';

    public string $emergencyContactPhone = '';

    public string $note = '';

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:30'],
            'firstName' => ['required', 'string', 'max:100'],
            'middleName' => ['nullable', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'alternatePhone' => ['nullable', 'string', 'max:40'],
            'dateOfBirth' => ['nullable', 'date', 'before_or_equal:today'],
            'nationalityCountryCode' => ['nullable', 'alpha', 'size:2'],
            'identityType' => ['nullable', 'string', 'max:40', 'required_with:identityNumber'],
            'identityNumber' => ['nullable', 'string', 'max:180'],
            'identityCountryCode' => ['nullable', 'alpha', 'size:2'],
            'addressLine1' => ['nullable', 'string', 'max:180'],
            'addressLine2' => ['nullable', 'string', 'max:180'],
            'city' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'postalCode' => ['nullable', 'string', 'max:30'],
            'countryCode' => ['nullable', 'alpha', 'size:2'],
            'emergencyContactName' => ['nullable', 'string', 'max:160'],
            'emergencyContactPhone' => ['nullable', 'string', 'max:40'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'firstName' => 'first name', 'middleName' => 'middle name', 'lastName' => 'last name',
            'alternatePhone' => 'alternate phone', 'dateOfBirth' => 'date of birth',
            'nationalityCountryCode' => 'nationality code', 'identityType' => 'identity type',
            'identityNumber' => 'identity number', 'identityCountryCode' => 'issuing country code',
            'addressLine1' => 'address line 1', 'addressLine2' => 'address line 2',
            'postalCode' => 'postal code', 'countryCode' => 'residence country code',
            'emergencyContactName' => 'emergency contact name',
            'emergencyContactPhone' => 'emergency contact phone',
        ];
    }

    /** Populate editable values without decrypting protected identity data. */
    public function fillFromGuest(Guest $guest): void
    {
        $this->title = (string) $guest->title;
        $this->firstName = $guest->first_name;
        $this->middleName = (string) $guest->middle_name;
        $this->lastName = $guest->last_name;
        $this->email = (string) $guest->email;
        $this->phone = (string) $guest->phone;
        $this->alternatePhone = (string) $guest->alternate_phone;
        $this->dateOfBirth = $guest->date_of_birth?->format('Y-m-d') ?? '';
        $this->nationalityCountryCode = (string) $guest->nationality_country_code;
        $this->identityType = (string) $guest->identity_type;
        $this->identityNumber = '';
        $this->identityCountryCode = (string) $guest->identity_country_code;
        $this->addressLine1 = (string) $guest->address_line_1;
        $this->addressLine2 = (string) $guest->address_line_2;
        $this->city = (string) $guest->city;
        $this->region = (string) $guest->region;
        $this->postalCode = (string) $guest->postal_code;
        $this->countryCode = (string) $guest->country_code;
        $this->emergencyContactName = (string) $guest->emergency_contact_name;
        $this->emergencyContactPhone = (string) $guest->emergency_contact_phone;
        $this->note = (string) $guest->note;
        $this->resetValidation();
    }

    /** Reset every field for a new guest profile. */
    public function resetForCreate(): void
    {
        $this->reset();
        $this->resetValidation();
    }

    /** @return array<string, mixed> */
    public function payload(bool $includeBlankIdentity = true): array
    {
        $payload = [
            'title' => $this->nullable($this->title),
            'first_name' => trim($this->firstName),
            'middle_name' => $this->nullable($this->middleName),
            'last_name' => trim($this->lastName),
            'email' => $this->nullable($this->email),
            'phone' => $this->nullable($this->phone),
            'alternate_phone' => $this->nullable($this->alternatePhone),
            'date_of_birth' => $this->nullable($this->dateOfBirth),
            'nationality_country_code' => $this->nullableUpper($this->nationalityCountryCode),
            'identity_type' => $this->nullable($this->identityType),
            'identity_country_code' => $this->nullableUpper($this->identityCountryCode),
            'address_line_1' => $this->nullable($this->addressLine1),
            'address_line_2' => $this->nullable($this->addressLine2),
            'city' => $this->nullable($this->city),
            'region' => $this->nullable($this->region),
            'postal_code' => $this->nullable($this->postalCode),
            'country_code' => $this->nullableUpper($this->countryCode),
            'emergency_contact_name' => $this->nullable($this->emergencyContactName),
            'emergency_contact_phone' => $this->nullable($this->emergencyContactPhone),
            'note' => $this->nullable($this->note),
        ];
        if ($includeBlankIdentity || trim($this->identityNumber) !== '') {
            $payload['identity_number'] = $this->nullable($this->identityNumber);
        }

        return $payload;
    }

    /** Normalize optional text. */
    private function nullable(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /** Normalize optional two-letter country codes. */
    private function nullableUpper(string $value): ?string
    {
        $value = $this->nullable($value);

        return $value === null ? null : strtoupper($value);
    }
}
