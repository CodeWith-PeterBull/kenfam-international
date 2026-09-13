<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Orders\Services;

use App\Modules\Commerce\Orders\Data\Documents\OrderDocumentData;
use App\Modules\Commerce\Orders\Data\Documents\OrderDocumentLineData;
use App\Modules\Commerce\Orders\Models\Order;
use Carbon\CarbonImmutable;

/**
 * Projects an order aggregate into the deliberately narrow report boundary.
 */
final readonly class OrderDocumentDataFactory
{
    public function fromOrder(Order $order): OrderDocumentData
    {
        $order->loadMissing('items');

        return new OrderDocumentData(
            orderNumber: (string) $order->order_number,
            channelLabel: $order->channel->label(),
            statusLabel: $order->status->label(),
            paymentStatusLabel: $order->payment_status->label(),
            fulfillmentLabel: $order->fulfillment_type->label(),
            customerName: $this->text($order->customer_display_name, 'Walk-in customer'),
            placedAt: $order->placed_at === null ? null : CarbonImmutable::instance($order->placed_at),
            subtotalMinor: $order->subtotal_minor,
            discountMinor: $order->discount_minor,
            deliveryFeeMinor: $order->delivery_fee_minor,
            taxMinor: $order->tax_minor,
            totalMinor: $order->total_minor,
            taxInclusive: $order->tax_inclusive,
            lines: $order->items->map(fn ($item): OrderDocumentLineData => new OrderDocumentLineData(
                productName: $this->text($item->product_name, 'Product'),
                sku: $this->text($item->sku, 'Not available'),
                quantity: $item->quantity,
                unitPriceMinor: $item->unit_price_minor,
                taxMinor: $item->tax_minor,
                lineTotalMinor: $item->line_total_minor,
            ))->values()->all(),
        );
    }

    /**
     * Collapse control characters before content reaches HTML or PDF output.
     */
    private function text(mixed $value, string $fallback): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', trim((string) $value));
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? $fallback : $value;
    }
}
