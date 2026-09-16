<?php

/**
 * Builds authorization-scoped TravelTours catalog queries for staff surfaces.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Services;

use App\Models\User;
use App\Modules\TravelTours\Catalog\Models\Destination;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Catalog\Models\TourCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/** Centralize collection authorization without inventing unsupported tenancy. */
final class CatalogQueryService
{
    /** @return Builder<Tour> */
    public function tours(User $actor): Builder
    {
        Gate::forUser($actor)->authorize('viewAny', Tour::class);

        return Tour::query();
    }

    /** @return Builder<TourCategory> */
    public function categories(User $actor): Builder
    {
        Gate::forUser($actor)->authorize('viewAny', TourCategory::class);

        return TourCategory::query();
    }

    /** @return Builder<Destination> */
    public function destinations(User $actor): Builder
    {
        Gate::forUser($actor)->authorize('viewAny', Destination::class);

        return Destination::query();
    }
}
