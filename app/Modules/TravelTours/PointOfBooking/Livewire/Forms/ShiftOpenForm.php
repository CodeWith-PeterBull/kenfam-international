<?php

/** Validates register ownership and the exact opening float for a new shift. */

declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Livewire\Forms;

use App\Modules\TravelTours\Support\ScaledDecimal;
use Livewire\Form;

/** A manager's choice of register, operator, counted float, and opening note. */
final class ShiftOpenForm extends Form
{
    public string $registerId = '';

    public string $operatorId = '';

    public string $openingFloat = '0.00';

    public string $note = '';

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'registerId' => ['required', 'integer', 'exists:travel_booking_registers,id'],
            'operatorId' => ['required', 'integer', 'exists:users,id'],
            'openingFloat' => ['required', 'regex:/^\d{1,12}(\.\d{1,'.self::decimals().'})?$/'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'registerId.required' => 'Choose an available register.',
            'operatorId.required' => 'Choose an available operator.',
            'openingFloat.regex' => 'Enter the float as a plain decimal, for example 2500.00.',
        ];
    }

    /** Convert the validated decimal input to exact minor units. */
    public function openingFloatMinor(): int
    {
        return ScaledDecimal::toMinor($this->openingFloat, self::decimals());
    }

    /** Reset the opening form to a zero float. */
    public function resetForOpen(): void
    {
        $this->reset();
        $this->openingFloat = ScaledDecimal::formatUnsigned(0, self::decimals());
        $this->resetValidation();
    }

    /** Return the bounded module currency precision. */
    private static function decimals(): int
    {
        return max(0, min(6, (int) config('travel-tours.defaults.currency_decimals', 2)));
    }
}
