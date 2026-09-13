<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Database\Factories;

use App\Modules\Commerce\Customers\Models\Customer;
use App\Modules\Commerce\Orders\Enums\FulfillmentType;
use App\Modules\Commerce\Orders\Enums\OrderChannel;
use App\Modules\Commerce\Orders\Enums\OrderPaymentStatus;
use App\Modules\Commerce\Orders\Enums\OrderStatus;
use App\Modules\Commerce\Orders\Enums\PaymentMethod;
use App\Modules\Commerce\Orders\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
final class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * Define a placed web order with immutable customer and total snapshots.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_number' => 'WEB-'.fake()->unique()->numerify('######'),
            'channel' => OrderChannel::Web,
            'status' => OrderStatus::Pending,
            'payment_status' => OrderPaymentStatus::Unpaid,
            'fulfillment_type' => FulfillmentType::Pickup,
            'preferred_payment_method' => PaymentMethod::CashOnDelivery,
            'customer_id' => Customer::factory(),
            'user_id' => null,
            'register_id' => null,
            'till_session_id' => null,
            'cashier_id' => null,
            'created_by' => null,
            'currency' => (string) config('commerce.currency.code', 'KES'),
            'subtotal_minor' => 10_000,
            'discount_minor' => 0,
            'discount_reason' => null,
            'delivery_fee_minor' => 0,
            'tax_minor' => 1_379,
            'total_minor' => 10_000,
            'paid_minor' => 0,
            'tax_inclusive' => true,
            'customer_first_name' => fake()->firstName(),
            'customer_last_name' => fake()->lastName(),
            'customer_company' => null,
            'customer_tax_identifier' => null,
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => fake()->e164PhoneNumber(),
            'address_line_1' => null,
            'address_line_2' => null,
            'city' => null,
            'region' => null,
            'postal_code' => null,
            'country_code' => (string) config('commerce.checkout.country_code', 'KE'),
            'customer_note' => null,
            'internal_note' => null,
            'cancellation_reason' => null,
            'stock_committed_at' => now(),
            'stock_released_at' => null,
            'placed_at' => now(),
            'completed_at' => null,
            'cancelled_at' => null,
        ];
    }

    /**
     * Define a held POS cart that has not consumed stock or a business number.
     */
    public function held(): static
    {
        return $this->state(fn (): array => [
            'order_number' => null,
            'channel' => OrderChannel::PointOfSale,
            'status' => OrderStatus::Held,
            'fulfillment_type' => FulfillmentType::Counter,
            'preferred_payment_method' => null,
            'subtotal_minor' => 0,
            'tax_minor' => 0,
            'total_minor' => 0,
            'stock_committed_at' => null,
            'placed_at' => null,
        ]);
    }
}
