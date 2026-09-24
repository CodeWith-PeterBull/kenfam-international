@php
    $ratePlan = $tour->ratePlans->firstWhere('is_default', true) ?: $tour->ratePlans->first();
    $adultRate = $ratePlan?->participantRates?->first(
        static fn ($rate): bool => $rate->participant_type === \App\Modules\TravelTours\Bookings\Enums\ParticipantType::Adult
    );
    $nextDeparture = $tour->departures->first();
    $cover = $tour->getFirstMediaUrl('tour_cover', 'hero') ?: $profile->heroImageUrl;
    $destinations = $tour->destinations->pluck('name')->take(2)->join(', ');
    $tourUrl = route('travel-tours.storefront.tours.show', $tour->slug);
@endphp

<article class="swiper-slide travel-hero__slide travel-hero__slide--tour" data-travel-hero-tour>
    <img class="travel-hero__image" src="{{ $cover }}" alt="" width="1920" height="1080" loading="lazy">
    <div class="container-xxl travel-hero__content">
        <p class="travel-eyebrow">{{ $tour->is_featured ? 'Featured journey' : 'Selected journey' }} <span aria-hidden="true">&middot;</span> {{ $tour->type->label() }}</p>
        <h2>{{ $tour->name }}</h2>
        <p class="travel-hero__lead">{{ $tour->tagline ?: $tour->short_description }}</p>
        <div class="travel-hero__meta" aria-label="Journey summary">
            <span><i data-lucide="clock-3" aria-hidden="true"></i>{{ $tour->duration_days }} days{{ $tour->duration_nights ? ' / '.$tour->duration_nights.' nights' : '' }}</span>
            @if ($destinations !== '')
                <span><i data-lucide="map-pin" aria-hidden="true"></i>{{ $destinations }}</span>
            @endif
            @if ($adultRate)
                <span><i data-lucide="wallet-cards" aria-hidden="true"></i>From {{ \App\Modules\TravelTours\Support\MoneyFormatter::format($adultRate->amount_minor, $ratePlan->currency) }}</span>
            @endif
            @if ($nextDeparture)
                <span><i data-lucide="calendar-days" aria-hidden="true"></i>{{ $nextDeparture->starts_at->timezone($nextDeparture->timezone)->format('d M Y') }}</span>
            @endif
        </div>
        <div class="travel-hero__actions">
            <a class="travel-button" href="{{ $tourUrl }}">View journey <i data-lucide="arrow-up-right" aria-hidden="true"></i></a>
            @if ($nextDeparture)
                <a class="travel-button travel-button--quiet" href="{{ $tourUrl }}#travel-departures">See departures</a>
            @endif
        </div>
    </div>
</article>
