<?php

declare(strict_types=1);

namespace App\Modules\Commerce\PointOfSale\Livewire\Forms;

use App\Modules\Commerce\PointOfSale\Models\TillSession;
use App\Modules\Commerce\Support\ScaledDecimal;
use Livewire\Form;

/**
 * Validates the physical cash count used for till reconciliation.
 */
final class TillCloseForm extends Form
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

    public function fillFromTill(TillSession $session): void
    {
        $this->countedCash = ScaledDecimal::formatUnsigned($session->expected_cash_minor, self::decimals());
        $this->note = '';
        $this->resetValidation();
    }

    public function countedCashMinor(): int
    {
        return ScaledDecimal::parseUnsigned($this->countedCash, self::decimals());
    }

    public function resetForClose(): void
    {
        $this->reset();
        $this->resetValidation();
    }

    private static function decimals(): int
    {
        return max(0, min(6, (int) config('commerce.currency.decimal_places', 2)));
    }
}
