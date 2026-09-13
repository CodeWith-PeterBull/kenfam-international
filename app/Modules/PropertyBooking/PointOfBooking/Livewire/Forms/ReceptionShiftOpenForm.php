<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\PointOfBooking\Livewire\Forms;

use App\Modules\PropertyBooking\Support\ScaledDecimal;
use Livewire\Form;

/** Validates reception register ownership and exact opening-float input. */
final class ReceptionShiftOpenForm extends Form
{
    public string $registerId = '';

    public string $receptionistId = '';

    public string $openingFloat = '0.00';

    public string $note = '';

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'registerId' => ['required', 'integer', 'exists:property_booking_registers,id'],
            'receptionistId' => ['required', 'integer', 'exists:users,id'],
            'openingFloat' => ['required', 'decimal:0,'.self::decimals(), 'gte:0'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** Convert the validated decimal input to exact minor units. */
    public function openingFloatMinor(): int
    {
        return ScaledDecimal::parseUnsigned($this->openingFloat, self::decimals());
    }

    /** Reset the opening form to a zero float. */
    public function resetForOpen(): void
    {
        $this->reset();
        $this->openingFloat = ScaledDecimal::formatUnsigned(0, self::decimals());
        $this->resetValidation();
    }

    /** Return the bounded property-booking currency precision. */
    private static function decimals(): int
    {
        return max(0, min(6, (int) config('property-booking.defaults.currency_decimals', 2)));
    }
}
