<?php

declare(strict_types=1);

namespace App\Modules\Commerce\PointOfSale\Livewire\Forms;

use App\Modules\Commerce\Support\ScaledDecimal;
use Livewire\Form;

/**
 * Validates register ownership and exact opening-float input.
 */
final class TillOpenForm extends Form
{
    public string $registerId = '';

    public string $cashierId = '';

    public string $openingFloat = '0.00';

    public string $note = '';

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'registerId' => ['required', 'integer', 'exists:registers,id'],
            'cashierId' => ['required', 'integer', 'exists:users,id'],
            'openingFloat' => ['required', 'decimal:0,'.self::decimals(), 'gte:0'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function openingFloatMinor(): int
    {
        return ScaledDecimal::parseUnsigned($this->openingFloat, self::decimals());
    }

    public function resetForOpen(): void
    {
        $this->reset();
        $this->openingFloat = ScaledDecimal::formatUnsigned(0, self::decimals());
        $this->resetValidation();
    }

    private static function decimals(): int
    {
        return max(0, min(6, (int) config('commerce.currency.decimal_places', 2)));
    }
}
