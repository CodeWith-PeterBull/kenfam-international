@php
    $ratePlan = $tour->ratePlans->firstWhere('is_default', true) ?: $tour->ratePlans->first();
    $adultRate = $ratePlan?->participantRates?->first(
        static fn ($rate): bool => $rate->participant_type === \App\Modules\TravelTours\Bookings\Enums\ParticipantType::Adult
    );
    $nextDeparture = $tour->relationLoaded('departures') ? $tour->departures->first() : null;
    $cover = $tour->getFirstMediaUrl('tour_cover', 'card') ?: $profile->heroImageUrl;
@endphp

<article class="travel-tour-card" @isset($wireKey) wire:key="{{ $wireKey }}" @endisset>
    <a class="travel-tour-card__media" href="{{ route('travel-tours.storefront.tours.show', $tour->slug) }}">
        <img src="{{ $cover }}" alt="{{ $tour->name }}" width="960" height="640" loading="lazy">
        @if ($adultRate)
            <span class="travel-tour-card__price">From {{ \App\Modules\TravelTours\Support\MoneyFormatter::format($adultRate->amount_minor, $ratePlan->currency) }}</span>
        @endif
        <span class="travel-tour-card__duration">{{ $tour->duration_days }} days</span>
    </a>
    <div class="travel-tour-card__body">
        <p class="travel-eyebrow">{{ $tour->type->label() }}</p>
        <h3><a href="{{ route('travel-tours.storefront.tours.show', $tour->slug) }}">{{ $tour->name }}</a></h3>
        <p>{{ $tour->short_description }}</p>
        <div class="travel-tour-card__meta">
            <span><i data-lucide="map-pin" aria-hidden="true"></i>{{ $tour->destinations->pluck('name')->take(2)->join(', ') ?: 'Multiple destinations' }}</span>
            @if ($nextDeparture)
                <span><i data-lucide="calendar-days" aria-hidden="true"></i>{{ $nextDeparture->starts_at->timezone($nextDeparture->timezone)->format('d M Y') }}</span>
            @else
                <span><i data-lucide="users" aria-hidden="true"></i>From {{ $tour->minimum_participants }} travelers</span>
            @endif
        </div>
    </div>
</article>
