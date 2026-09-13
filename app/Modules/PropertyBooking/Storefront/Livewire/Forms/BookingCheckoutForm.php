<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Storefront\Livewire\Forms;

use App\Models\User;
use App\Modules\PropertyBooking\Bookings\Enums\BookingPaymentMethod;
use App\Modules\PropertyBooking\Guests\Data\GuestProfileData;
use Illuminate\Validation\Rule;
use Livewire\Form;

/** Validates public-safe guest, preference, request, and consent input. */
final class BookingCheckoutForm extends Form
{
    public string $firstName = '';

    public string $middleName = '';

    public string $lastName = '';

    public string $email = '';

    public string $phone = '';

    public string $addressLine1 = '';

    public string $addressLine2 = '';

    public string $city = '';

    public string $region = '';

    public string $postalCode = '';

    public string $countryCode = 'KE';

    public string $identityType = '';

    public string $identityNumber = '';

    public string $paymentMethod = 'mobile_money';

    public string $specialRequests = '';

    public bool $acceptedTerms = false;

    /** Return the public checkout validation contract. */
    protected function rules(): array
    {
        $identityPresence = (bool) config('property-booking.storefront.guest_identity_required', false)
            ? 'required'
            : 'nullable';

        return [
            'firstName' => ['required', 'string', 'max:100'],
            'middleName' => ['nullable', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'addressLine1' => ['nullable', 'string', 'max:180'],
            'addressLine2' => ['nullable', 'string', 'max:180'],
            'city' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'postalCode' => ['nullable', 'string', 'max:30'],
            'countryCode' => ['required', 'alpha', 'size:2'],
            'identityType' => [$identityPresence, 'string', Rule::in($this->allowedIdentityTypes()), 'required_with:identityNumber'],
            'identityNumber' => [$identityPresence, 'string', 'max:180', 'required_with:identityType'],
            'paymentMethod' => ['required', Rule::in($this->allowedPaymentMethods())],
            'specialRequests' => ['nullable', 'string', 'max:1000'],
            'acceptedTerms' => ['accepted'],
        ];
    }

    /** Return readable guest-facing validation labels. */
    protected function validationAttributes(): array
    {
        return [
            'firstName' => 'first name',
            'middleName' => 'middle name',
            'lastName' => 'last name',
            'addressLine1' => 'address line 1',
            'addressLine2' => 'address line 2',
            'postalCode' => 'postal code',
            'countryCode' => 'country code',
            'identityType' => 'identity document type',
            'identityNumber' => 'ID or passport number',
            'paymentMethod' => 'payment preference',
            'specialRequests' => 'special requests',
            'acceptedTerms' => 'booking policy consent',
        ];
    }

    /** Prefill safe account profile values without requiring authentication. */
    public function fillFromAccount(?User $user): void
    {
        $this->countryCode = strtoupper((string) config('property-booking.defaults.country_code', 'KE'));
        $this->paymentMethod = $this->allowedPaymentMethods()[0] ?? BookingPaymentMethod::MobileMoney->value;
        if (! $user instanceof User) {
            return;
        }

        $user->loadMissing('profile');
        $profile = $user->profile;
        $parts = preg_split('/\s+/', trim($user->display_name)) ?: [];
        $this->firstName = (string) ($profile?->first_name ?: array_shift($parts) ?: '');
        $this->middleName = (string) $profile?->middle_name;
        $this->lastName = (string) ($profile?->last_name ?: array_pop($parts) ?: '');
        $this->email = $user->email;
        $this->phone = (string) $profile?->phone;
    }

    /** Convert validated form values into a public-safe guest DTO. */
    public function guestData(): GuestProfileData
    {
        return new GuestProfileData(
            firstName: trim($this->firstName),
            middleName: $this->nullable($this->middleName),
            lastName: trim($this->lastName),
            email: strtolower(trim($this->email)),
            phone: trim($this->phone),
            addressLine1: $this->nullable($this->addressLine1),
            addressLine2: $this->nullable($this->addressLine2),
            city: $this->nullable($this->city),
            region: $this->nullable($this->region),
            postalCode: $this->nullable($this->postalCode),
            countryCode: strtoupper(trim($this->countryCode)),
            identityType: $this->nullable($this->identityType),
            identityNumber: $this->nullable($this->identityNumber),
        );
    }

    /** Return the validated payment preference enum. */
    public function payment(): BookingPaymentMethod
    {
        return BookingPaymentMethod::from($this->paymentMethod);
    }

    /** Return configured public payment method values backed by known enums. */
    private function allowedPaymentMethods(): array
    {
        return collect(config('property-booking.storefront.payment_methods', []))
            ->filter(static fn (mixed $method): bool => is_string($method) && BookingPaymentMethod::tryFrom($method) !== null)
            ->values()
            ->all();
    }

    /** Return configured identity document values accepted at public checkout. */
    private function allowedIdentityTypes(): array
    {
        return collect(config('property-booking.identity.types', []))
            ->filter(static fn (mixed $label, mixed $value): bool => is_string($value) && $value !== '' && is_string($label) && $label !== '')
            ->keys()
            ->values()
            ->all();
    }

    /** Normalize optional public text. */
    private function nullable(string $value): ?string
    {
        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}
