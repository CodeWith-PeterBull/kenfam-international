<?php

/** Validates one assisted sale: selection, customer, travellers, and money received. */

declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Livewire\Forms;

use App\Modules\TravelTours\Bookings\Data\BookingParticipantData;
use App\Modules\TravelTours\Bookings\Enums\ParticipantType;
use App\Modules\TravelTours\Bookings\Enums\PaymentMethod;
use App\Modules\TravelTours\Customers\Data\TravelCustomerData;
use App\Modules\TravelTours\PointOfBooking\Data\DeskSaleData;
use App\Modules\TravelTours\Pricing\Data\ParticipantMix;
use App\Modules\TravelTours\Support\ScaledDecimal;
use Carbon\CarbonImmutable;
use Livewire\Form;

/**
 * The operator types what the customer says; nothing priced comes from this
 * form. Participant rows follow the counts and are validated like the public
 * checkout so a desk booking carries the same identity snapshots.
 */
final class DeskSaleForm extends Form
{
    public string $tourId = '';

    public string $departureId = '';

    public string $adults = '1';

    public string $children = '0';

    public string $infants = '0';

    public string $promotionCode = '';

    public string $firstName = '';

    public string $lastName = '';

    public string $email = '';

    public string $phone = '';

    public bool $leadTravels = true;

    /** @var list<array{type: string, first_name: string, last_name: string, date_of_birth: string}> */
    public array $participants = [];

    public string $paymentMethod = 'cash';

    public string $amountReceived = '';

    public string $paymentReference = '';

    public string $specialRequests = '';

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        $maximum = max(1, (int) config('travel-tours.booking.maximum_participants', 20));
        $rules = [
            'tourId' => ['required', 'integer'],
            'departureId' => ['required', 'integer'],
            'adults' => ['required', 'integer', 'min:1', 'max:'.$maximum],
            'children' => ['required', 'integer', 'min:0', 'max:'.$maximum],
            'infants' => ['required', 'integer', 'min:0', 'max:'.$maximum],
            'promotionCode' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9-]+$/'],
            'firstName' => ['required', 'string', 'max:80'],
            'lastName' => ['required', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['required', 'string', 'max:40', 'regex:/^\+?[0-9 ().-]{7,30}$/'],
            'leadTravels' => ['boolean'],
            'participants' => ['required', 'array'],
            'paymentMethod' => ['required', 'in:'.implode(',', array_map(static fn (PaymentMethod $method): string => $method->value, PaymentMethod::cases()))],
            'amountReceived' => ['nullable', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            'paymentReference' => ['nullable', 'string', 'max:120'],
            'specialRequests' => ['nullable', 'string', 'max:1000'],
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
            'tourId.required' => 'Choose a tour.',
            'departureId.required' => 'Choose a departure.',
            'adults.min' => 'At least one adult travels on every booking.',
            'phone.required' => 'A phone number is needed to reach the customer.',
            'phone.regex' => 'Enter a phone number using digits, spaces, and an optional country code.',
            'amountReceived.regex' => 'Enter the amount as a plain decimal, for example 12500.00.',
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

    /** Reset every field for the next customer. */
    public function start(): void
    {
        $this->reset();
        $this->syncParticipants();
    }

    /** Return the participant counts as the shared pricing input. */
    public function mix(): ParticipantMix
    {
        return new ParticipantMix((int) $this->adults, (int) $this->children, (int) $this->infants);
    }

    /** Keep one traveller row per counted seat, preserving what was already typed. */
    public function syncParticipants(): void
    {
        $wanted = [];
        foreach ([[ParticipantType::Adult, (int) $this->adults], [ParticipantType::Child, (int) $this->children], [ParticipantType::Infant, (int) $this->infants]] as [$type, $count]) {
            for ($i = 0; $i < max(0, $count); $i++) {
                $wanted[] = $type->value;
            }
        }
        $existing = $this->participants;
        $rows = [];
        foreach ($wanted as $type) {
            $match = null;
            foreach ($existing as $key => $row) {
                if (($row['type'] ?? null) === $type) {
                    $match = $row;
                    unset($existing[$key]);
                    break;
                }
            }
            $rows[] = $match ?? ['type' => $type, 'first_name' => '', 'last_name' => '', 'date_of_birth' => ''];
        }
        $this->participants = $rows;
    }

    /** Build the typed sale; the service prices and re-verifies everything. */
    public function toData(string $operationKey, int $currencyExponent): DeskSaleData
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
        $amount = trim($this->amountReceived) === '' ? 0 : ScaledDecimal::toMinor($this->amountReceived, $currencyExponent);
        $code = strtoupper(trim($this->promotionCode));

        return new DeskSaleData(
            operationKey: $operationKey,
            departureId: (int) $this->departureId,
            mix: $this->mix(),
            customer: new TravelCustomerData(firstName: trim($this->firstName), lastName: trim($this->lastName), email: $this->normalizedEmail(), phone: trim($this->phone)),
            participants: $participants,
            preferredPaymentMethod: PaymentMethod::from($this->paymentMethod),
            amountReceivedMinor: $amount,
            paymentMethod: $amount > 0 ? PaymentMethod::from($this->paymentMethod) : null,
            paymentReference: trim($this->paymentReference) === '' ? null : trim($this->paymentReference),
            promotionCode: $code === '' ? null : $code,
            specialRequests: trim($this->specialRequests) === '' ? null : trim($this->specialRequests),
        );
    }

    /** Lower-case and trim the email, or null when the customer gave none. */
    private function normalizedEmail(): ?string
    {
        $email = mb_strtolower(trim($this->email));

        return $email === '' ? null : $email;
    }
}
