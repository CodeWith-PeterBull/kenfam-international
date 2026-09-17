<?php

/** Presents a bounded, authorized TravelTours catalog index. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Livewire\Admin;

use App\Models\User;
use App\Modules\TravelTours\Catalog\Enums\PublicationStatus;
use App\Modules\TravelTours\Catalog\Enums\TourType;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Catalog\Services\CatalogQueryService;
use App\Modules\TravelTours\Catalog\Services\TourReadinessService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Query permitted tours with URL-backed filters and Bootstrap pagination. */
final class TourCatalog extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $statusFilter = '';

    #[Url(as: 'type')]
    public string $typeFilter = '';

    #[Url(as: 'category')]
    public string $categoryFilter = '';

    #[Url(as: 'destination')]
    public string $destinationFilter = '';

    public int $perPage = 12;

    /** Require catalog read authority on every Livewire request. */
    public function boot(): void
    {
        Gate::authorize('viewAny', Tour::class);
    }

    /** Reset the current page after any filter change. */
    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'statusFilter', 'typeFilter', 'categoryFilter', 'destinationFilter', 'perPage'], true)) {
            $this->resetPage('tourPage');
        }
    }

    /** Clear all search filters without changing the actor's access. */
    public function clearFilters(): void
    {
        $this->reset('search', 'statusFilter', 'typeFilter', 'categoryFilter', 'destinationFilter');
        $this->resetPage('tourPage');
    }

    /** Render a bounded tour page and valid filter options. */
    public function render(CatalogQueryService $catalog, TourReadinessService $readiness): View
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);
        $query = $catalog->tours($actor)->with(['categories', 'destinations', 'media', 'ratePlans' => fn ($plans) => $plans->publiclyAvailable()->with('participantRates')]);
        $search = trim($this->search);
        if ($search !== '') {
            $escaped = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_substr($search, 0, 100)).'%';
            $query->where(fn (Builder $match) => $match
                ->whereRaw("name LIKE ? ESCAPE '!'", [$escaped])
                ->orWhereRaw("code LIKE ? ESCAPE '!'", [$escaped]));
        }
        if ($status = PublicationStatus::tryFrom($this->statusFilter)) {
            $query->where('status', $status->value);
        }
        if ($type = TourType::tryFrom($this->typeFilter)) {
            $query->where('type', $type->value);
        }
        if (ctype_digit($this->categoryFilter)) {
            $query->whereHas('categories', fn (Builder $related) => $related->whereKey((int) $this->categoryFilter));
        }
        if (ctype_digit($this->destinationFilter)) {
            $query->whereHas('destinations', fn (Builder $related) => $related->whereKey((int) $this->destinationFilter));
        }

        $tours = $query->orderBy('sort_order')->orderByDesc('id')->paginate(in_array($this->perPage, [12, 24, 48], true) ? $this->perPage : 12, pageName: 'tourPage');

        return view('travel-tours::livewire.admin.catalog.tour-catalog', [
            'tours' => $tours,
            'readiness' => $tours->getCollection()->mapWithKeys(fn (Tour $tour): array => [$tour->id => $readiness->reasons($tour)]),
            'statusCounts' => $catalog->tours($actor)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status'),
            'categories' => $catalog->categories($actor)->active()->orderBy('name')->get(),
            'destinations' => $catalog->destinations($actor)->where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
