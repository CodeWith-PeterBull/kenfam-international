<?php

/** Validates manual payment evidence entered by the travel desk. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Bookings\Livewire\Forms;

use App\Modules\TravelTours\Bookings\Data\BookingPaymentData;
use App\Modules\TravelTours\Bookings\Enums\PaymentMethod;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\Support\ScaledDecimal;
use Livewire\Form;

/** Convert operator input into the typed payment DTO without trusting client money maths. */
final class PaymentRecordForm extends Form
{
    public string $method = 'mobile_money';

    public string $amount = '';

    public string $reference = '';

    public string $provider = '';

    public string $transactionIdentifier = '';

    public string $note = '';

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'method' => ['required', 'in:'.implode(',', array_map(static fn (PaymentMethod $method): string => $method->value, PaymentMethod::cases()))],
            'amount' => ['required', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            'reference' => ['nullable', 'string', 'max:120'],
            'provider' => ['nullable', 'string', 'max:80'],
            'transactionIdentifier' => ['nullable', 'string', 'max:160'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'amount.required' => 'Enter the amount received.',
            'amount.regex' => 'Enter the amount as a plain decimal, for example 12500.00.',
        ];
    }

    /** Reset the form for a new payment, defaulting to the customer's stated preference. */
    public function start(TourBooking $booking): void
    {
        $this->reset();
        $this->method = $booking->preferred_payment_method->value;
    }

    /** Return the typed, idempotent payment input for one booking. */
    public function toData(TourBooking $booking, string $operationKey, ?int $actorId): BookingPaymentData
    {
        return new BookingPaymentData(
            operationKey: $operationKey,
            method: PaymentMethod::from($this->method),
            amountMinor: ScaledDecimal::toMinor($this->amount, (int) $booking->currency_exponent),
            currency: $booking->currency,
            reference: $this->optional($this->reference),
            provider: $this->optional($this->provider),
            transactionIdentifier: $this->optional($this->transactionIdentifier),
            actorId: $actorId,
            safeMetadata: array_filter(['note' => $this->optional($this->note)], static fn (?string $value): bool => $value !== null),
        );
    }

    /** Trim optional text and treat blanks as absent. */
    private function optional(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
