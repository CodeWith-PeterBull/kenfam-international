@extends('travel-tours::layouts.storefront')

@php($profile = app(\App\Modules\TravelTours\Storefront\Services\TravelStorefrontProfileResolver::class)->current())

@section('title', 'Explore tours')
@section('meta_description', 'Search published tours by destination, travel date, tour type, and duration.')
@section('canonical', route('travel-tours.storefront.catalog.index'))
@section('page', 'catalog')

@section('content')
<section class="travel-catalog-hero" aria-labelledby="travel-catalog-title">
    <div class="container-xxl">
        <p class="travel-eyebrow">Find your next journey</p>
        <h1 id="travel-catalog-title">Travel experiences shaped around what matters.</h1>
        <p>Search available departures or browse every published itinerary. Our travel team can also shape a private journey around your dates.</p>
        <form class="travel-filter" method="get" action="{{ route('travel-tours.storefront.catalog.index') }}" aria-label="Filter tours">
            <label class="travel-field">
                <span>Keywords</span>
                <input type="search" name="keyword" value="{{ $filters['keyword'] ?? '' }}" placeholder="Tour name or experience">
            </label>
            <label class="travel-field">
                <span>Destination</span>
                <select name="destination">
                    <option value="">All destinations</option>
                    @foreach ($destinations as $destination)
                        <option value="{{ $destination->slug }}" @selected(($filters['destination'] ?? '') === $destination->slug)>{{ $destination->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="travel-field"><span>Category</span><select name="category"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category->slug }}" @selected(($filters['category'] ?? '') === $category->slug)>{{ $category->name }}</option>@endforeach</select></label>
            <label class="travel-field"><span>Tour type</span><select name="type"><option value="">All types</option>@foreach(\App\Modules\TravelTours\Catalog\Enums\TourType::cases() as $type)<option value="{{ $type->value }}" @selected(($filters['type'] ?? '') === $type->value)>{{ $type->label() }}</option>@endforeach</select></label>
            <label class="travel-field"><span>Maximum days</span><input type="number" name="maximum_duration_days" min="1" max="365" value="{{ $filters['maximum_duration_days'] ?? '' }}" placeholder="Any duration"></label>
            <label class="travel-field">
                <span>Travel from</span>
                <input type="date" name="departure_date" value="{{ $filters['departure_date'] ?? '' }}">
            </label>
            <button class="travel-button" type="submit"><i data-lucide="search" aria-hidden="true"></i>Search tours</button>
        </form>
    </div>
</section>

<section class="travel-section">
    <div class="container-xxl">
        <header class="travel-result-summary">
            <div>
                <h2>{{ $tours->total() }} {{ Str::plural('journey', $tours->total()) }}</h2>
                <p>Published itineraries with current planning and departure information.</p>
            </div>
            @if (collect($filters)->filter()->isNotEmpty())
                <a class="travel-button travel-button--quiet" href="{{ route('travel-tours.storefront.catalog.index') }}"><i data-lucide="rotate-ccw" aria-hidden="true"></i>Clear filters</a>
            @endif
        </header>

        @if ($tours->isEmpty())
            <div class="travel-empty">
                <h2>No tours match those filters.</h2>
                <p>Clear the search or contact our team for a custom itinerary.</p>
                <a class="travel-button" href="{{ route('travel-tours.storefront.catalog.index') }}">View all tours</a>
            </div>
        @else
            <div class="travel-tour-grid">
                @foreach ($tours as $tour)
                    @include('travel-tours::storefront.catalog.partials.tour-card', ['tour' => $tour, 'profile' => $profile])
                @endforeach
            </div>
            <nav class="mt-4" aria-label="Tour catalog pages">{{ $tours->links() }}</nav>
        @endif
    </div>
</section>
@endsection
