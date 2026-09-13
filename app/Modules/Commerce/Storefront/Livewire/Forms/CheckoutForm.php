<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Storefront\Livewire\Forms;

use App\Models\User;
use App\Modules\Commerce\Orders\Data\CustomerSnapshotData;
use App\Modules\Commerce\Orders\Enums\FulfillmentType;
use App\Modules\Commerce\Orders\Enums\PaymentMethod;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * Validates customer, fulfillment, and manual payment-preference input.
 */
final class CheckoutForm extends Form
{
    public string $firstName = '';

    public string $lastName = '';

    public string $company = '';

    public string $taxIdentifier = '';

    public string $email = '';

    public string $phone = '';

    public string $fulfillmentType = 'pickup';

    public string $paymentMethod = 'mobile_money';

    public string $addressLine1 = '';

    public string $addressLine2 = '';

    public string $city = '';

    public string $region = '';

    public string $postalCode = '';

    public string $countryCode = 'KE';

    public string $customerNote = '';

    public bool $acceptedTerms = false;

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'firstName' => ['required', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],
            'company' => ['nullable', 'string', 'max:160'],
            'taxIdentifier' => ['nullable', 'string', 'max:80'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'fulfillmentType' => ['required', Rule::in([FulfillmentType::Pickup->value, FulfillmentType::Delivery->value])],
            'paymentMethod' => ['required', Rule::in($this->allowedPaymentMethods())],
            'addressLine1' => ['nullable', 'required_if:fulfillmentType,delivery', 'string', 'max:180'],
            'addressLine2' => ['nullable', 'string', 'max:180'],
            'city' => ['nullable', 'required_if:fulfillmentType,delivery', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'postalCode' => ['nullable', 'string', 'max:30'],
            'countryCode' => ['required', 'alpha', 'size:2'],
            'customerNote' => ['nullable', 'string', 'max:1000'],
            'acceptedTerms' => ['accepted'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'firstName' => 'first name',
            'lastName' => 'last name',
            'taxIdentifier' => 'tax identifier',
            'fulfillmentType' => 'fulfillment option',
            'paymentMethod' => 'payment preference',
            'addressLine1' => 'address line 1',
            'addressLine2' => 'address line 2',
            'postalCode' => 'postal code',
            'countryCode' => 'country code',
            'customerNote' => 'order note',
            'acceptedTerms' => 'order confirmation',
        ];
    }

    /**
     * Prefill safe account profile fields without making an account mandatory.
     */
    public function fillFromAccount(?User $user): void
    {
        $this->countryCode = strtoupper((string) config('commerce.checkout.country_code', 'KE'));
        if (! $user instanceof User) {
            return;
        }

        $user->loadMissing('profile');
        $profile = $user->profile;
        $parts = preg_split('/\s+/', trim($user->display_name)) ?: [];
        $this->firstName = (string) ($profile?->first_name ?: array_shift($parts) ?: '');
        $this->lastName = (string) ($profile?->last_name ?: array_pop($parts) ?: '');
        $this->email = $user->email;
        $this->phone = (string) $profile?->phone;
    }

    public function customerSnapshot(): CustomerSnapshotData
    {
        return new CustomerSnapshotData(
            firstName: $this->firstName,
            lastName: $this->lastName,
            company: self::nullable($this->company),
            taxIdentifier: self::nullable($this->taxIdentifier),
            email: $this->email,
            phone: $this->phone,
            addressLine1: self::nullable($this->addressLine1),
            addressLine2: self::nullable($this->addressLine2),
            city: self::nullable($this->city),
            region: self::nullable($this->region),
            postalCode: self::nullable($this->postalCode),
            countryCode: $this->countryCode,
        );
    }

    public function fulfillment(): FulfillmentType
    {
        return FulfillmentType::from($this->fulfillmentType);
    }

    public function payment(): PaymentMethod
    {
        return PaymentMethod::from($this->paymentMethod);
    }

    /** @return list<string> */
    private function allowedPaymentMethods(): array
    {
        return collect(config('commerce.checkout.payment_methods', []))
            ->filter(static fn (mixed $method): bool => is_string($method) && PaymentMethod::tryFrom($method) !== null)
            ->values()
            ->all();
    }

    private static function nullable(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
