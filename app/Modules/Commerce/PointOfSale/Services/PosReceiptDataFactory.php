<?php

declare(strict_types=1);

namespace App\Modules\Commerce\PointOfSale\Services;

use App\Modules\Commerce\Orders\Models\Order;
use App\Modules\Commerce\PointOfSale\Data\Documents\PosReceiptData;
use App\Modules\Commerce\PointOfSale\Data\Documents\PosReceiptLineData;
use App\Modules\Commerce\PointOfSale\Data\Documents\PosReceiptPaymentData;
use Carbon\CarbonImmutable;

/**
 * Projects completed POS aggregates into one privacy-safe receipt contract.
 */
final readonly class PosReceiptDataFactory
{
    public function fromOrder(Order $order): PosReceiptData
    {
        $order->loadMissing(['items', 'payments', 'register', 'cashier']);

        return new PosReceiptData(
            orderNumber: (string) $order->order_number,
            placedAt: $order->placed_at === null ? null : CarbonImmutable::instance($order->placed_at),
            registerName: $this->text($order->register?->name, 'Unassigned register'),
            registerCode: $this->text($order->register?->code, 'Not available'),
            cashierName: $this->text($order->cashier?->display_name, 'Unassigned cashier'),
            customerName: $this->text($order->customer_display_name, 'Walk-in customer'),
            subtotalMinor: $order->subtotal_minor,
            discountMinor: $order->discount_minor,
            taxMinor: $order->tax_minor,
            totalMinor: $order->total_minor,
            taxInclusive: $order->tax_inclusive,
            lines: $order->items->map(fn ($item): PosReceiptLineData => new PosReceiptLineData(
                productName: $this->text($item->product_name, 'Product'),
                sku: $this->text($item->sku, 'Not available'),
                quantity: $item->quantity,
                unitPriceMinor: $item->unit_price_minor,
                lineTotalMinor: $item->line_total_minor,
            ))->values()->all(),
            payments: $order->payments->map(fn ($payment): PosReceiptPaymentData => new PosReceiptPaymentData(
                methodLabel: $payment->method->label(),
                reference: $this->nullableText($payment->reference),
                amountMinor: $payment->amount_minor,
                changeMinor: $payment->change_minor,
            ))->values()->all(),
        );
    }

    private function text(mixed $value, string $fallback): string
    {
        return $this->nullableText($value) ?? $fallback;
    }

    /**
     * Remove browser/PDF control characters from operator-entered snapshots.
     */
    private function nullableText(mixed $value): ?string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', trim((string) $value));
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }
}
