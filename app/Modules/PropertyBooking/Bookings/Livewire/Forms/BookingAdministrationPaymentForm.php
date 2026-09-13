<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Livewire\Forms;

use App\Modules\PropertyBooking\Bookings\Data\AdministrationPaymentData;
use App\Modules\PropertyBooking\Bookings\Enums\BookingPaymentMethod;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Support\ScaledDecimal;
use Illuminate\Validation\Rule;
use Livewire\Form;

/** Validates one shift-independent non-cash booking payment. */
final class BookingAdministrationPaymentForm extends Form
{
    public string $method = 'mobile_money';

    public string $amount = '';

    public string $reference = '';

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'method' => ['required', Rule::in(array_map(
                static fn (BookingPaymentMethod $method): string => $method->value,
                self::methods(),
            ))],
            'amount' => ['required', 'decimal:0,'.self::decimals(), 'gt:0'],
            'reference' => ['required', 'string', 'max:160'],
        ];
    }

    /** @return list<BookingPaymentMethod> */
    public static function methods(): array
    {
        return array_values(array_filter(
            BookingPaymentMethod::cases(),
            static fn (BookingPaymentMethod $method): bool => $method !== BookingPaymentMethod::Cash,
        ));
    }

    /** Fill the outstanding booking balance and preferred non-cash method. */
    public function fillFromBooking(Booking $booking): void
    {
        $preferred = $booking->preferred_payment_method;
        $this->method = $preferred !== null && $preferred !== BookingPaymentMethod::Cash
            ? $preferred->value
            : BookingPaymentMethod::MobileMoney->value;
        $this->amount = ScaledDecimal::formatUnsigned($booking->balance_minor, self::decimals());
        $this->reference = '';
        $this->resetValidation();
    }

    /** Build the exact minor-unit payment request. */
    public function data(): AdministrationPaymentData
    {
        return new AdministrationPaymentData(
            BookingPaymentMethod::from($this->method),
            ScaledDecimal::parseUnsigned($this->amount, self::decimals()),
            trim($this->reference),
        );
    }

    /** Return the configured currency decimal scale. */
    private static function decimals(): int
    {
        return max(0, min(6, (int) config('property-booking.defaults.currency_decimals', 2)));
    }
}
