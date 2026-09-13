<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Database\Factories;

use App\Modules\Commerce\PointOfSale\Models\Register;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Register>
 */
final class RegisterFactory extends Factory
{
    protected $model = Register::class;

    /**
     * Define an active POS register.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Register '.fake()->unique()->numberBetween(1, 9999),
            'code' => 'REG-'.fake()->unique()->numerify('####'),
            'location_label' => 'Main counter',
            'description' => null,
            'receipt_print_driver' => 'browser',
            'receipt_print_mode' => 'manual',
            'receipt_paper_width' => 80,
            'receipt_printer_name' => null,
            'is_active' => true,
        ];
    }
}
