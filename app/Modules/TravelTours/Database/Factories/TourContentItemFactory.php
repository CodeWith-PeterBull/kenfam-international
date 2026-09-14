<?php

/** Define one structured public content item for a tour. */
declare(strict_types=1);

namespace App\Modules\TravelTours\Database\Factories;

use App\Modules\TravelTours\Catalog\Enums\ContentItemType;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Catalog\Models\TourContentItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TourContentItem> */
final class TourContentItemFactory extends Factory
{
    protected $model = TourContentItem::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['tour_id' => Tour::factory(), 'type' => ContentItemType::Highlight, 'content' => fake()->sentence(), 'sort_order' => 1];
    }
}
