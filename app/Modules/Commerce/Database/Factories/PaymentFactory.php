<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Database\Factories;

use App\Modules\Commerce\Orders\Enums\PaymentMethod;
use App\Modules\Commerce\Orders\Enums\PaymentStatus;
use App\Modules\Commerce\Orders\Models\Order;
use App\Modules\Commerce\Orders\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
final class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * Define a pending manual payment record.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'till_session_id' => null,
            'recorded_by' => null,
            'method' => PaymentMethod::MobileMoney,
            'status' => PaymentStatus::Pending,
            'amount_minor' => 10_000,
            'tendered_minor' => 10_000,
            'change_minor' => 0,
            'reference' => null,
            'metadata' => null,
            'paid_at' => null,
        ];
    }

    /**
     * Mark the generated payment as completed.
     */
    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::Completed,
            'reference' => strtoupper(fake()->unique()->bothify('PAY-########')),
            'paid_at' => now(),
        ]);
    }
}
