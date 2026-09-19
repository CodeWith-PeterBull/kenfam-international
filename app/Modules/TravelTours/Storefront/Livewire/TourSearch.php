<?php

/** Live public tour search: every filter applies as it changes, without a page load. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Storefront\Livewire;

use App\Modules\TravelTours\Catalog\Enums\TourType;
use App\Modules\TravelTours\Catalog\Models\Destination;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Catalog\Models\TourCategory;
use App\Modules\TravelTours\Contracts\SearchesTours;
use App\Modules\TravelTours\Storefront\Services\TravelStorefrontProfileResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The filters are the component's state and live in the query string, so a
 * shared or refreshed URL reproduces the same results and the first paint is
 * rendered on the server. Typing and selecting update the state through
 * `wire:model.live`; each change validates its own field, drops back to the
 * first page, and re-runs the search through the same SearchesTours boundary
 * the classic request used. Values that fail validation are shown inline and
 * never reach the query.
 */
final class TourSearch extends Component
{
    use WithPagination;

    /** Typed fields debounce for this long before the search runs. */
    public const DEBOUNCE_MS = 400;

    #[Url(as: 'keyword', except: '')]
    public string $keyword = '';

    #[Url(as: 'destination', except: '')]
    public string $destination = '';

    #[Url(as: 'category', except: '')]
    public string $category = '';

    #[Url(as: 'type', except: '')]
    public string $type = '';

    #[Url(as: 'maximum_duration_days', except: '')]
    public string $maximumDurationDays = '';

    #[Url(as: 'departure_date', except: '')]
    public string $departureDate = '';

    /** The filter properties in the order the form shows them. */
    private const FILTERS = ['keyword', 'destination', 'category', 'type', 'maximumDurationDays', 'departureDate'];

    /** Validate the changed filter and drop back to the first page of results. */
    public function updated(string $property): void
    {
        if (! in_array($property, self::FILTERS, true)) {
            return;
        }
        $this->validateOnly($property, $this->rules(), $this->messages());
        $this->resetPage();
    }

    /** Apply the form explicitly (Enter, or the button before a debounce has fired); the state is already live. */
    public function search(): void
    {
        $this->validate($this->rules(), $this->messages());
        $this->resetPage();
    }

    /** Drop every filter and show the whole catalogue again. */
    public function clearFilters(): void
    {
        $this->reset(self::FILTERS);
        $this->resetValidation();
        $this->resetPage();
    }

    /** Whether any filter narrows the catalogue. */
    public function hasFilters(): bool
    {
        return $this->filters() !== [];
    }

    /**
     * The current page of matching tours.
     *
     * @return LengthAwarePaginator<int, Tour>
     */
    #[Computed]
    public function tours(): LengthAwarePaginator
    {
        return app(SearchesTours::class)->search($this->filters(), (int) config('travel-tours.storefront.page_size', 12));
    }

    /** @return Collection<int, Destination> */
    #[Computed]
    public function destinations(): Collection
    {
        return Destination::query()->published()->orderBy('sort_order')->orderBy('name')->limit(100)->get(['id', 'slug', 'name']);
    }

    /** @return Collection<int, TourCategory> */
    #[Computed]
    public function categories(): Collection
    {
        return TourCategory::query()->active()->orderBy('sort_order')->orderBy('name')->get(['id', 'slug', 'name']);
    }

    /** @return list<TourType> */
    #[Computed]
    public function types(): array
    {
        return TourType::cases();
    }

    /** Render the search with the storefront profile the cards need for their fallback imagery. */
    public function render(): View
    {
        return view('travel-tours::livewire.storefront.tour-search', [
            'profile' => app(TravelStorefrontProfileResolver::class)->current(),
        ]);
    }

    /**
     * The filters the search service receives: only values that pass validation, under the service's documented keys.
     *
     * @return array<string, string>
     */
    private function filters(): array
    {
        $valid = Validator::make($this->raw(), $this->rules())->valid();

        return array_filter([
            'keyword' => trim((string) ($valid['keyword'] ?? '')),
            'destination' => (string) ($valid['destination'] ?? ''),
            'category' => (string) ($valid['category'] ?? ''),
            'type' => (string) ($valid['type'] ?? ''),
            'maximum_duration_days' => (string) ($valid['maximumDurationDays'] ?? ''),
            'departure_date' => (string) ($valid['departureDate'] ?? ''),
        ], static fn (string $value): bool => $value !== '');
    }

    /** @return array<string, string> */
    private function raw(): array
    {
        return [
            'keyword' => $this->keyword,
            'destination' => $this->destination,
            'category' => $this->category,
            'type' => $this->type,
            'maximumDurationDays' => $this->maximumDurationDays,
            'departureDate' => $this->departureDate,
        ];
    }

    /** The same contract the classic request enforced, per field. */
    private function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:120'],
            'destination' => ['nullable', 'string', 'max:180'],
            'category' => ['nullable', 'string', 'max:180'],
            'type' => ['nullable', 'string', Rule::in(array_map(static fn (TourType $type): string => $type->value, TourType::cases()))],
            'maximumDurationDays' => ['nullable', 'integer', 'min:1', 'max:365'],
            'departureDate' => ['nullable', 'date'],
        ];
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'keyword.max' => 'Keep the search under 120 characters.',
            'type.in' => 'Choose one of the listed tour types.',
            'maximumDurationDays.integer' => 'Enter the maximum days as a whole number.',
            'maximumDurationDays.min' => 'Enter at least one day.',
            'maximumDurationDays.max' => 'Tours run for at most 365 days.',
            'departureDate.date' => 'Enter a valid travel date.',
        ];
    }
}
