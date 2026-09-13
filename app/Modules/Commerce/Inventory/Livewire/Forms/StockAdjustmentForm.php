<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Inventory\Livewire\Forms;

use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * Captures a deliberate positive or negative manual stock adjustment.
 */
final class StockAdjustmentForm extends Form
{
    public string $direction = 'increase';

    public int $quantity = 1;

    public string $note = '';

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'direction' => ['required', Rule::in(['increase', 'decrease'])],
            'quantity' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'note' => ['required', 'string', 'max:255'],
        ];
    }

    public function resetForAdjustment(): void
    {
        $this->reset();
        $this->direction = 'increase';
        $this->quantity = 1;
        $this->resetValidation();
    }

    public function signedQuantity(): int
    {
        return $this->direction === 'decrease' ? -$this->quantity : $this->quantity;
    }
}
