<?php

/**
 * Implements a focused TravelTours domain or application service.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Services;

use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Contracts\SearchesTours;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/** Builds bounded public catalog queries from a documented filter contract. */
final class TourSearchService implements SearchesTours
{
    /** Search publicly eligible tours through bounded filters and pagination. */
    public function search(array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        $perPage = max(1, min($perPage, (int) config('travel-tours.storefront.maximum_page_size', 48)));
        $keyword = trim((string) ($filters['keyword'] ?? ''));
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_substr($keyword, 0, 120)).'%';

        return Tour::query()
            ->published()
            ->with([
                'categories',
                'destinations',
                'ratePlans' => fn ($query) => $query->publiclyAvailable()->with('participantRates'),
                'departures' => fn ($query) => $query->bookable()->limit(1),
            ])
            ->when($keyword !== '', fn (Builder $query): Builder => $query->where(function (Builder $search) use ($pattern): void {
                $search->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("short_description LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("code LIKE ? ESCAPE '!'", [$pattern]);
            }))
            ->when(filled($filters['destination'] ?? null), fn (Builder $query): Builder => $query->whereHas(
                'destinations', fn (Builder $destination): Builder => $destination->where('slug', (string) $filters['destination'])
            ))
            ->when(filled($filters['category'] ?? null), fn (Builder $query): Builder => $query->whereHas(
                'categories', fn (Builder $category): Builder => $category->where('slug', (string) $filters['category'])
            ))
            ->when(filled($filters['type'] ?? null), fn (Builder $query): Builder => $query->where('type', (string) $filters['type']))
            ->when(filled($filters['maximum_duration_days'] ?? null), fn (Builder $query): Builder => $query->where('duration_days', '<=', (int) $filters['maximum_duration_days']))
            ->when(filled($filters['departure_date'] ?? null), fn (Builder $query): Builder => $query->whereHas(
                'departures', fn (Builder $departure): Builder => $departure->bookable()
                    ->whereDate('starts_at', '>=', (string) $filters['departure_date'])
            ))
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }
}
