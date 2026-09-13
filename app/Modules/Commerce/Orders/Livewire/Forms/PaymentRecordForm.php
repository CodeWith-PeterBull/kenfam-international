<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Orders\Livewire\Forms;

use App\Modules\Commerce\Orders\Data\PaymentData;
use App\Modules\Commerce\Orders\Enums\PaymentMethod;
use App\Modules\Commerce\Orders\Models\Order;
use App\Modules\Commerce\Support\ScaledDecimal;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * Validates an administrative manual-payment confirmation.
 */
final class PaymentRecordForm extends Form
{
    public string $method = 'mobile_money';

    public string $amount = '';

    public string $reference = '';

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        $decimals = self::decimals();

        return [
            'method' => ['required', Rule::in(array_map(static fn (PaymentMethod $method): string => $method->value, PaymentMethod::cases()))],
            'amount' => ['required', 'decimal:0,'.$decimals, 'gt:0'],
            'reference' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function fillForOrder(Order $order): void
    {
        $this->reset();
        $this->method = $order->preferred_payment_method?->value ?? PaymentMethod::MobileMoney->value;
        $this->amount = ScaledDecimal::formatUnsigned($order->balance_due_minor, self::decimals());
        $this->reference = '';
        $this->resetValidation();
    }

    public function paymentData(): PaymentData
    {
        $method = PaymentMethod::from($this->method);
        $amount = ScaledDecimal::parseUnsigned($this->amount, self::decimals());

        return new PaymentData(
            method: $method,
            amountMinor: $amount,
            tenderedMinor: $amount,
            reference: trim($this->reference) ?: null,
        );
    }

    private static function decimals(): int
    {
        return max(0, min(6, (int) config('commerce.currency.decimal_places', 2)));
    }
}
