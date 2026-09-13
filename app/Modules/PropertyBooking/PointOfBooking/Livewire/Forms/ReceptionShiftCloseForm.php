<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\PointOfBooking\Livewire\Forms;

use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionShift;
use App\Modules\PropertyBooking\Support\ScaledDecimal;
use Livewire\Form;

/** Validates physical cash used for reception-shift reconciliation. */
final class ReceptionShiftCloseForm extends Form
{
    public string $countedCash = '';

    public string $note = '';

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'countedCash' => ['required', 'decimal:0,'.self::decimals(), 'gte:0'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** Hydrate the physical count with the latest advisory expected cash. */
    public function fillFromShift(ReceptionShift $shift): void
    {
        $this->countedCash = ScaledDecimal::formatUnsigned($shift->expected_cash_minor, self::decimals());
        $this->note = '';
        $this->resetValidation();
    }

    /** Convert the validated decimal input to exact minor units. */
    public function countedCashMinor(): int
    {
        return ScaledDecimal::parseUnsigned($this->countedCash, self::decimals());
    }

    /** Clear the reconciliation form. */
    public function resetForClose(): void
    {
        $this->reset();
        $this->resetValidation();
    }

    /** Return the bounded property-booking currency precision. */
    private static function decimals(): int
    {
        return max(0, min(6, (int) config('property-booking.defaults.currency_decimals', 2)));
    }
}
