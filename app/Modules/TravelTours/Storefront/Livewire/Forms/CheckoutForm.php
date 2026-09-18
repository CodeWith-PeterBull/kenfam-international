<?php

/** Validates customer, traveller, payment preference, and terms input for one held quote. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Storefront\Livewire\Forms;

use App\Modules\TravelTours\Bookings\Data\BookingParticipantData;
use App\Modules\TravelTours\Bookings\Data\BookingPlacementData;
use App\Modules\TravelTours\Bookings\Enums\BookingChannel;
use App\Modules\TravelTours\Bookings\Enums\ParticipantType;
use App\Modules\TravelTours\Bookings\Enums\PaymentMethod;
use App\Modules\TravelTours\Customers\Data\TravelCustomerData;
use App\Modules\TravelTours\Scheduling\Models\AvailabilityHold;
use Carbon\CarbonImmutable;
use Livewire\Form;

/**
 * The traveller list is fixed by the hold's participant mix, so the form only
 * collects identities for the seats already reserved; the placement service
 * re-verifies the mix against the hold before anything is written.
 */
final class CheckoutForm extends Form
{
    public string $firstName = '';

    public string $lastName = '';

    public string $email = '';

    public string $phone = '';

    /** Whether traveller 1 is the customer, whose name is then reused. */
    public bool $leadTravels = true;

    /** @var list<array{type: string, first_name: string, last_name: string, date_of_birth: string}> */
    public array $participants = [];

    public string $preferredMethod = '';

    public string $specialRequests = '';

    public bool $acceptTerms = false;

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        $rules = [
            'firstName' => ['required', 'string', 'max:80'],
            'lastName' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:160'],
            'phone' => ['required', 'string', 'max:40', 'regex:/^\+?[0-9 ().-]{7,30}$/'],
            'leadTravels' => ['boolean'],
            'participants' => ['required', 'array'],
            'preferredMethod' => ['required', 'in:'.implode(',', array_map(static fn (PaymentMethod $method): string => $method->value, self::allowedMethods()))],
            'specialRequests' => ['nullable', 'string', 'max:1000'],
            'acceptTerms' => ['accepted'],
        ];

        foreach ($this->participants as $index => $participant) {
            $named = ! ($index === 0 && $this->leadTravels);
            $minor = in_array($participant['type'] ?? '', [ParticipantType::Child->value, ParticipantType::Infant->value], true);
            $rules["participants.{$index}.first_name"] = $named ? ['required', 'string', 'max:80'] : ['nullable', 'string', 'max:80'];
            $rules["participants.{$index}.last_name"] = $named ? ['required', 'string', 'max:80'] : ['nullable', 'string', 'max:80'];
            $rules["participants.{$index}.date_of_birth"] = [$minor ? 'required' : 'nullable', 'date', 'before:today'];
        }

        return $rules;
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        $messages = [
            'phone.regex' => 'Enter a phone number using digits, spaces, and an optional country code.',
            'preferredMethod.required' => 'Choose how you would like to pay.',
            'preferredMethod.in' => 'Choose one of the listed payment options.',
            'acceptTerms.accepted' => 'Please accept the booking terms to continue.',
        ];
        foreach (array_keys($this->participants) as $index) {
            $number = $index + 1;
            $messages["participants.{$index}.first_name.required"] = "Enter traveller {$number}'s first name.";
            $messages["participants.{$index}.last_name.required"] = "Enter traveller {$number}'s last name.";
            $messages["participants.{$index}.date_of_birth.required"] = "Enter traveller {$number}'s date of birth so the fare can be confirmed.";
            $messages["participants.{$index}.date_of_birth.before"] = "Traveller {$number}'s date of birth must be in the past.";
        }

        return $messages;
    }

    /** Seed one traveller row per held seat, adults first, and the first allowed payment method. */
    public function start(AvailabilityHold $hold): void
    {
        $this->participants = [];
        foreach ([[ParticipantType::Adult, $hold->adult_count], [ParticipantType::Child, $hold->child_count], [ParticipantType::Infant, $hold->infant_count]] as [$type, $count]) {
            for ($i = 0; $i < (int) $count; $i++) {
                $this->participants[] = ['type' => $type->value, 'first_name' => '', 'last_name' => '', 'date_of_birth' => ''];
            }
        }
        $this->preferredMethod = self::allowedMethods()[0]->value ?? PaymentMethod::Other->value;
    }

    /** Build the typed placement input; the service re-checks everything against the hold. */
    public function toPlacement(AvailabilityHold $hold, string $ownerToken, string $termsVersion): BookingPlacementData
    {
        $participants = [];
        foreach ($this->participants as $index => $participant) {
            $usesCustomer = $index === 0 && $this->leadTravels;
            $participants[] = new BookingParticipantData(
                type: ParticipantType::from($participant['type']),
                firstName: trim($usesCustomer ? $this->firstName : $participant['first_name']),
                lastName: trim($usesCustomer ? $this->lastName : $participant['last_name']),
                dateOfBirth: filled($participant['date_of_birth']) ? CarbonImmutable::parse($participant['date_of_birth']) : null,
                email: $usesCustomer ? $this->normalizedEmail() : null,
                phone: $usesCustomer ? trim($this->phone) : null,
                isLead: $index === 0,
            );
        }

        return new BookingPlacementData(
            operationKey: 'checkout:'.$hold->ulid,
            holdUlid: $hold->ulid,
            holdOwnerToken: $ownerToken,
            customer: new TravelCustomerData(
                firstName: trim($this->firstName),
                lastName: trim($this->lastName),
                email: $this->normalizedEmail(),
                phone: trim($this->phone),
            ),
            participants: $participants,
            channel: BookingChannel::Web,
            preferredPaymentMethod: PaymentMethod::from($this->preferredMethod),
            termsVersion: $termsVersion,
            specialRequests: trim($this->specialRequests) === '' ? null : trim($this->specialRequests),
            attribution: ['source' => 'storefront'],
        );
    }

    /**
     * Payment methods the storefront may offer, in configured order.
     *
     * @return list<PaymentMethod>
     */
    public static function allowedMethods(): array
    {
        $configured = (array) config('travel-tours.storefront.payment_methods', []);
        $methods = [];
        foreach ($configured as $value) {
            $method = PaymentMethod::tryFrom((string) $value);
            if ($method instanceof PaymentMethod && ! in_array($method, $methods, true)) {
                $methods[] = $method;
            }
        }

        return $methods === [] ? [PaymentMethod::Other] : $methods;
    }

    /** Lower-case and trim the email once so customer matching stays deterministic. */
    private function normalizedEmail(): string
    {
        return mb_strtolower(trim($this->email));
    }
}
