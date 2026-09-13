<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Customers\Livewire\Forms;

use App\Modules\Commerce\Customers\Models\Customer;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * Validates and normalizes reusable customer administration input.
 */
final class CustomerForm extends Form
{
    public ?Customer $customer = null;

    public string $userId = '';

    public string $firstName = '';

    public string $lastName = '';

    public string $company = '';

    public string $taxIdentifier = '';

    public string $email = '';

    public string $phone = '';

    public string $addressLine1 = '';

    public string $addressLine2 = '';

    public string $city = '';

    public string $region = '';

    public string $postalCode = '';

    public string $countryCode = 'KE';

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'userId' => ['nullable', 'integer', Rule::exists('users', 'id'), Rule::unique('customers', 'user_id')->ignore($this->customer)],
            'firstName' => ['required', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],
            'company' => ['nullable', 'string', 'max:160'],
            'taxIdentifier' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'addressLine1' => ['nullable', 'string', 'max:180'],
            'addressLine2' => ['nullable', 'string', 'max:180'],
            'city' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'postalCode' => ['nullable', 'string', 'max:30'],
            'countryCode' => ['required', 'alpha', 'size:2'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'userId' => 'linked account',
            'firstName' => 'first name',
            'lastName' => 'last name',
            'taxIdentifier' => 'tax identifier',
            'addressLine1' => 'address line 1',
            'addressLine2' => 'address line 2',
            'postalCode' => 'postal code',
            'countryCode' => 'country code',
        ];
    }

    public function fillFromCustomer(Customer $customer): void
    {
        $this->customer = $customer;
        $this->userId = $customer->user_id === null ? '' : (string) $customer->user_id;
        $this->firstName = $customer->first_name;
        $this->lastName = $customer->last_name;
        $this->company = (string) $customer->company;
        $this->taxIdentifier = (string) $customer->tax_identifier;
        $this->email = (string) $customer->email;
        $this->phone = (string) $customer->phone;
        $this->addressLine1 = (string) $customer->address_line_1;
        $this->addressLine2 = (string) $customer->address_line_2;
        $this->city = (string) $customer->city;
        $this->region = (string) $customer->region;
        $this->postalCode = (string) $customer->postal_code;
        $this->countryCode = $customer->country_code;
    }

    public function resetForCreate(): void
    {
        $this->reset();
        $this->customer = null;
        $this->countryCode = strtoupper((string) config('commerce.checkout.country_code', 'KE'));
        $this->resetValidation();
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'user_id' => $this->userId === '' ? null : (int) $this->userId,
            'first_name' => trim($this->firstName),
            'last_name' => trim($this->lastName),
            'company' => self::nullable($this->company),
            'tax_identifier' => self::nullable($this->taxIdentifier),
            'email' => self::nullable($this->email),
            'phone' => self::nullable($this->phone),
            'address_line_1' => self::nullable($this->addressLine1),
            'address_line_2' => self::nullable($this->addressLine2),
            'city' => self::nullable($this->city),
            'region' => self::nullable($this->region),
            'postal_code' => self::nullable($this->postalCode),
            'country_code' => strtoupper(trim($this->countryCode)),
        ];
    }

    private static function nullable(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
