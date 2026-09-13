<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Database\Factories;

use App\Modules\PropertyBooking\Catalog\Enums\UnitTypeStatus;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Catalog\Models\UnitType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<UnitType> */
final class UnitTypeFactory extends Factory
{
    protected $model = UnitType::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->randomElement(['Deluxe King Room', 'Studio Apartment', 'Two Bedroom House', 'Garden Suite']).' '.fake()->unique()->numberBetween(1, 999999);

        return [
            'property_id' => Property::factory(),
            'created_by' => null,
            'updated_by' => null,
            'code' => 'TYPE-'.strtoupper(fake()->unique()->bothify('??###')),
            'slug' => Str::slug($name),
            'name' => $name,
            'status' => UnitTypeStatus::Draft,
            'short_description' => fake()->sentence(10),
            'description' => fake()->paragraph(),
            'size_square_metres' => fake()->randomFloat(2, 20, 160),
            'bedroom_count' => 1,
            'bathroom_count' => 1,
            'living_room_count' => 0,
            'bed_count' => 1,
            'bed_configuration' => [['type' => 'king', 'quantity' => 1]],
            'maximum_guests' => 2,
            'maximum_adults' => 2,
            'maximum_children' => 1,
            'maximum_infants' => 1,
            'allows_infants_on_top' => true,
            'is_entire_unit' => true,
            'smoking_allowed' => false,
            'is_featured' => false,
            'meta_title' => null,
            'meta_description' => null,
            'published_at' => null,
        ];
    }

    /** Configure the factory for the published state. */
    public function published(): static
    {
        return $this->state(fn (): array => ['status' => UnitTypeStatus::Published, 'published_at' => now()]);
    }
}
