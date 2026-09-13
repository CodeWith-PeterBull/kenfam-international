<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Database\Factories;

use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\PointOfBooking\Enums\ReceiptPaperWidth;
use App\Modules\PropertyBooking\PointOfBooking\Enums\ReceiptPrintMode;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionRegister;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ReceptionRegister> */
final class ReceptionRegisterFactory extends Factory
{
    protected $model = ReceptionRegister::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'created_by' => null,
            'updated_by' => null,
            'code' => 'DESK-'.strtoupper(fake()->unique()->bothify('??###')),
            'name' => 'Reception desk '.fake()->unique()->numberBetween(1, 999),
            'location_label' => 'Main lobby',
            'description' => 'Primary demonstration reception endpoint.',
            'receipt_print_driver' => config('property-booking.pob.receipt_printing.default_driver', 'browser'),
            'receipt_print_mode' => ReceiptPrintMode::Manual,
            'receipt_paper_width' => ReceiptPaperWidth::Roll80,
            'receipt_printer_name' => null,
            'is_active' => true,
        ];
    }
}
