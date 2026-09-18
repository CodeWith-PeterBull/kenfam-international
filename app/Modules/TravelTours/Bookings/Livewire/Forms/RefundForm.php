<?php

/** Validates a manual refund entered by an authorized operator. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Bookings\Livewire\Forms;

use App\Modules\TravelTours\Bookings\Data\BookingRefundData;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\Support\ScaledDecimal;
use Livewire\Form;

/** Convert operator input into the typed refund DTO; ceilings are enforced by the service. */
final class RefundForm extends Form
{
    public string $paymentId = '';

    public string $amount = '';

    public string $reason = '';

    public string $processor = '';

    public string $transactionIdentifier = '';

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'paymentId' => ['nullable', 'integer'],
            'amount' => ['required', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'processor' => ['nullable', 'string', 'max:80'],
            'transactionIdentifier' => ['nullable', 'string', 'max:160'],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'amount.required' => 'Enter the amount refunded.',
            'amount.regex' => 'Enter the amount as a plain decimal, for example 12500.00.',
            'reason.required' => 'Explain why this refund was made.',
            'reason.min' => 'Give a fuller reason for the refund.',
        ];
    }

    /** Reset the form for a new refund. */
    public function start(): void
    {
        $this->reset();
    }

    /** Return the typed, idempotent refund input for one booking. */
    public function toData(TourBooking $booking, string $operationKey, ?int $actorId): BookingRefundData
    {
        return new BookingRefundData(
            operationKey: $operationKey,
            amountMinor: ScaledDecimal::toMinor($this->amount, (int) $booking->currency_exponent),
            currency: $booking->currency,
            reason: trim($this->reason),
            paymentId: $this->paymentId === '' ? null : (int) $this->paymentId,
            processor: trim($this->processor) === '' ? null : trim($this->processor),
            transactionIdentifier: trim($this->transactionIdentifier) === '' ? null : trim($this->transactionIdentifier),
            actorId: $actorId,
        );
    }
}
