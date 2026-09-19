<?php

/** Validates the physical cash count used to close a shift. */

declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Livewire\Forms;

use App\Modules\TravelTours\PointOfBooking\Models\BookingShift;
use App\Modules\TravelTours\Support\ScaledDecimal;
use Livewire\Form;

/** The counted drawer and the closing note explaining any variance. */
final class ShiftCloseForm extends Form
{
    public string $countedCash = '';

    public string $note = '';

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'countedCash' => ['required', 'regex:/^\d{1,12}(\.\d{1,'.self::decimals().'})?$/'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'countedCash.required' => 'Count the cash in the drawer and enter it.',
            'countedCash.regex' => 'Enter the counted cash as a plain decimal, for example 12000.00.',
        ];
    }

    /** Prefill the count with the ledger's expected cash so the operator confirms or corrects it. */
    public function fillFromShift(BookingShift $shift, int $expectedCashMinor): void
    {
        $this->countedCash = ScaledDecimal::formatUnsigned(max($expectedCashMinor, 0), (int) $shift->currency_exponent);
        $this->note = '';
        $this->resetValidation();
    }

    /** Convert the validated decimal input to exact minor units. */
    public function countedCashMinor(int $exponent): int
    {
        return ScaledDecimal::toMinor($this->countedCash, $exponent);
    }

    /** Clear the reconciliation form. */
    public function resetForClose(): void
    {
        $this->reset();
        $this->resetValidation();
    }

    /** Return the bounded module currency precision. */
    private static function decimals(): int
    {
        return max(0, min(6, (int) config('travel-tours.defaults.currency_decimals', 2)));
    }
}
