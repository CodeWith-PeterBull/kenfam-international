@php
    $tours = $this->tours;
    $debounce = \App\Modules\TravelTours\Storefront\Livewire\TourSearch::DEBOUNCE_MS;
    $targets = 'keyword, destination, category, type, maximumDurationDays, departureDate, search, clearFilters, gotoPage, nextPage, previousPage';
@endphp
<div class="travel-search" wire:offline.class="is-offline">
    <section class="travel-catalog-hero" aria-labelledby="travel-catalog-title">
        <div class="container-xxl">
            <p class="travel-eyebrow">Find your next journey</p>
            <h1 id="travel-catalog-title">Travel experiences shaped around what matters.</h1>
            <p>Search available departures or browse every published itinerary. Results update as you type and choose; our travel team can also shape a private journey around your dates.</p>

            {{-- A plain GET form underneath: without JavaScript it still submits and the query string restores the same filters. --}}
            <form class="travel-filter" method="get" action="{{ route('travel-tours.storefront.catalog.index') }}" wire:submit="search" aria-label="Filter tours" novalidate>
                <label class="travel-field" for="tour-search-keyword">
                    <span>Keywords</span>
                    <input
                        id="tour-search-keyword"
                        type="search"
                        name="keyword"
                        wire:model.live.debounce.{{ $debounce }}ms="keyword"
                        placeholder="Tour name or experience"
                        autocomplete="off"
                        maxlength="120"
                        @if ($errors->has('keyword')) aria-invalid="true" aria-describedby="tour-search-keyword-error" @endif
                    >
                    @error('keyword')
                        <small class="travel-field__error" id="tour-search-keyword-error" role="alert">{{ $message }}</small>
                    @enderror
                </label>
                <label class="travel-field" for="tour-search-destination">
                    <span>Destination</span>
                    <select id="tour-search-destination" name="destination" wire:model.live="destination">
                        <option value="">All destinations</option>
                        @foreach ($this->destinations as $destination)
                            <option value="{{ $destination->slug }}">{{ $destination->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="travel-field" for="tour-search-category">
                    <span>Category</span>
                    <select id="tour-search-category" name="category" wire:model.live="category">
                        <option value="">All categories</option>
                        @foreach ($this->categories as $category)
                            <option value="{{ $category->slug }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="travel-field" for="tour-search-type">
                    <span>Tour type</span>
                    <select id="tour-search-type" name="type" wire:model.live="type" @if ($errors->has('type')) aria-invalid="true" aria-describedby="tour-search-type-error" @endif>
                        <option value="">All types</option>
                        @foreach ($this->types as $type)
                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    @error('type')
                        <small class="travel-field__error" id="tour-search-type-error" role="alert">{{ $message }}</small>
                    @enderror
                </label>
                <label class="travel-field" for="tour-search-days">
                    <span>Maximum days</span>
                    <input
                        id="tour-search-days"
                        type="number"
                        name="maximum_duration_days"
                        min="1"
                        max="365"
                        inputmode="numeric"
                        wire:model.live.debounce.{{ $debounce }}ms="maximumDurationDays"
                        placeholder="Any duration"
                        @if ($errors->has('maximumDurationDays')) aria-invalid="true" aria-describedby="tour-search-days-error" @endif
                    >
                    @error('maximumDurationDays')
                        <small class="travel-field__error" id="tour-search-days-error" role="alert">{{ $message }}</small>
                    @enderror
                </label>
                <label class="travel-field" for="tour-search-date">
                    <span>Travel from</span>
                    <input
                        id="tour-search-date"
                        type="date"
                        name="departure_date"
                        wire:model.live="departureDate"
                        @if ($errors->has('departureDate')) aria-invalid="true" aria-describedby="tour-search-date-error" @endif
                    >
                    @error('departureDate')
                        <small class="travel-field__error" id="tour-search-date-error" role="alert">{{ $message }}</small>
                    @enderror
                </label>
                <button class="travel-button" type="submit" wire:loading.attr="disabled" wire:target="{{ $targets }}">
                    <span wire:loading.remove wire:target="{{ $targets }}"><i data-lucide="search" aria-hidden="true"></i>Search tours</span>
                    <span wire:loading wire:target="{{ $targets }}"><span class="travel-spinner" aria-hidden="true"></span>Searching&hellip;</span>
                </button>
            </form>
            <p class="travel-search__offline" wire:offline role="status">
                <i data-lucide="wifi-off" aria-hidden="true"></i>You are offline. Filters will apply again once the connection returns.
            </p>
        </div>
    </section>

    <section class="travel-section" id="travel-results">
        <div class="container-xxl">
            <header class="travel-result-summary">
                <div role="status" aria-live="polite" aria-atomic="true">
                    <h2>{{ $tours->total() }} {{ Str::plural('journey', $tours->total()) }}</h2>
                    <p>{{ $this->hasFilters() ? 'Matching the filters above.' : 'Published itineraries with current planning and departure information.' }}</p>
                </div>
                @if ($this->hasFilters())
                    <button class="travel-button travel-button--quiet" type="button" wire:click="clearFilters" wire:loading.attr="disabled" wire:target="clearFilters">
                        <i data-lucide="rotate-ccw" aria-hidden="true"></i>Clear filters
                    </button>
                @endif
            </header>

            <div class="travel-search__results" wire:loading.class="is-loading" wire:loading.attr="aria-busy" wire:target="{{ $targets }}">
                <div class="travel-search__progress" wire:loading.flex wire:target="{{ $targets }}" aria-hidden="true">
                    <span class="travel-spinner"></span>Updating results&hellip;
                </div>
                @if ($tours->isEmpty())
                    <div class="travel-empty">
                        <h2>No tours match those filters.</h2>
                        <p>Clear the search or contact our team for a custom itinerary.</p>
                        <button class="travel-button" type="button" wire:click="clearFilters">View all tours</button>
                    </div>
                @else
                    <div class="travel-tour-grid">
                        @foreach ($tours as $tour)
                            @include('travel-tours::storefront.catalog.partials.tour-card', ['tour' => $tour, 'profile' => $profile, 'wireKey' => 'tour-'.$tour->getKey()])
                        @endforeach
                    </div>
                    <nav class="mt-4" aria-label="Tour catalog pages">{{ $tours->links(data: ['scrollTo' => '#travel-results']) }}</nav>
                @endif
            </div>
        </div>
    </section>
</div>
