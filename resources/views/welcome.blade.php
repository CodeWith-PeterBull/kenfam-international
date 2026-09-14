@extends('travel-tours::layouts.storefront')

@php($profile = app(\App\Modules\TravelTours\Storefront\Services\TravelStorefrontProfileResolver::class)->current())

@section('title', 'Escorted tours and considered journeys')
@section('meta_description', $profile->metaDescription)
@section('canonical', route('home'))
@section('page', 'home')

@section('content')
<section class="travel-hero" aria-labelledby="travel-home-title">
    <img class="travel-hero__image" src="{{ $profile->heroImageUrl }}" alt="Travelers overlooking an East African landscape at sunrise" width="1920" height="1080" fetchpriority="high">
    <div class="container-xxl travel-hero__content">
        <p class="travel-eyebrow">
            Escorted travel expertise
            @if ($profile->foundedYear)
                since {{ $profile->foundedYear }}
            @endif
        </p>
        <h1 id="travel-home-title">The world, thoughtfully within reach.</h1>
        <p class="travel-hero__lead">Considered itineraries, clear coordination, and attentive human service for leisure, educational, group, and custom journeys.</p>
        <div class="travel-hero__actions">
            <a class="travel-button" href="{{ route('travel-tours.storefront.catalog.index') }}">Explore tours <i data-lucide="arrow-up-right" aria-hidden="true"></i></a>
            @if ($profile->whatsappNumber)
                <a class="travel-button travel-button--quiet" href="https://wa.me/{{ $profile->whatsappNumber }}?text={{ rawurlencode('Hello '.$profile->shortName.', I would like help planning a journey.') }}" target="_blank" rel="noopener noreferrer">Plan on WhatsApp</a>
            @endif
        </div>
    </div>
</section>

<section class="travel-trustbar" aria-label="Travel service strengths">
    <div class="container-xxl">
        <div><i data-lucide="calendar-check" aria-hidden="true"></i><span>Clear departures and booking support</span></div>
        <div><i data-lucide="route" aria-hidden="true"></i><span>Escorted and custom itineraries</span></div>
        <div><i data-lucide="headphones" aria-hidden="true"></i><span>Human support from Nairobi</span></div>
    </div>
</section>

@if ($featuredDestinations->isNotEmpty())
    <section class="travel-section" aria-labelledby="featured-destinations-title">
        <div class="container-xxl">
            <header class="travel-heading">
                <div><p class="travel-eyebrow">Where next</p><h2 id="featured-destinations-title">Distinct places, thoughtfully connected.</h2></div>
                <p>Start with a destination, then discover an itinerary shaped around time, pace, and the experiences worth carrying home.</p>
            </header>
            <div class="travel-destination-grid">
                @foreach ($featuredDestinations as $destination)
                    @php($destinationCover = $destination->getFirstMediaUrl('destination_cover', 'card') ?: $profile->heroImageUrl)
                    <a class="travel-destination-card" href="{{ route('travel-tours.storefront.catalog.index', ['destination' => $destination->slug]) }}">
                        <img src="{{ $destinationCover }}" alt="{{ $destination->name }}" width="960" height="640" loading="lazy">
                        <span class="travel-destination-card__content"><span class="travel-eyebrow">{{ $destination->type->label() }}</span><h3>{{ $destination->name }}</h3><p>{{ $destination->short_description }}</p></span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endif

<section class="travel-section travel-section--soft" id="travel-styles" aria-labelledby="travel-styles-title">
    <div class="container-xxl">
        <header class="travel-heading">
            <div><p class="travel-eyebrow">Ways to travel</p><h2 id="travel-styles-title">One capable team, different kinds of journey.</h2></div>
            <p>Choose an established departure or begin with a blank page. Planning stays clear from the first conversation through your return.</p>
        </header>
        <div class="travel-style-grid">
            <article class="travel-style-card"><span class="travel-style-card__icon"><i data-lucide="users" aria-hidden="true"></i></span><h3>Escorted departures</h3><p>Shared journeys with considered pacing, known dates, and coordinated group support.</p></article>
            <article class="travel-style-card"><span class="travel-style-card__icon"><i data-lucide="sparkles" aria-hidden="true"></i></span><h3>Private and custom travel</h3><p>Flexible routes shaped around your party, interests, dates, and preferred level of support.</p></article>
            <article class="travel-style-card"><span class="travel-style-card__icon"><i data-lucide="graduation-cap" aria-hidden="true"></i></span><h3>Educational and group tours</h3><p>Structured travel for institutions, faith communities, families, and special-interest groups.</p></article>
        </div>
    </div>
</section>

<section class="travel-section" id="tours" aria-labelledby="featured-tours-title">
    <div class="container-xxl">
        <header class="travel-heading">
            <div><p class="travel-eyebrow">Selected journeys</p><h2 id="featured-tours-title">A clear way into your next destination.</h2></div>
            <p>Each journey brings together practical planning, meaningful experiences, transparent inclusions, and a team ready to help.</p>
        </header>
        @if ($featuredTours->isNotEmpty())
            <div class="travel-tour-grid">
                @foreach ($featuredTours as $tour)
                    @include('travel-tours::storefront.catalog.partials.tour-card', ['tour' => $tour, 'profile' => $profile])
                @endforeach
            </div>
        @else
            <div class="travel-empty">
                <h3>Our next collection is being prepared.</h3>
                <p>Speak with our team now for a private, group, educational, or custom itinerary.</p>
                @if ($profile->whatsappNumber)
                    <a class="travel-button" href="https://wa.me/{{ $profile->whatsappNumber }}" target="_blank" rel="noopener noreferrer">Start a conversation</a>
                @endif
            </div>
        @endif
    </div>
</section>

<section class="travel-section travel-section--alt" id="about" aria-labelledby="about-travel-title">
    <div class="container-xxl travel-about">
        <div class="travel-about__metric">
            <strong>{{ $profile->foundedYear ? now()->year - $profile->foundedYear : 30 }}+</strong>
            <span>years coordinating journeys</span>
        </div>
        <div>
            <p class="travel-eyebrow">About {{ $profile->name }}</p>
            <h2 id="about-travel-title">Experience matters most when it makes travel feel effortless.</h2>
            <p>{{ $profile->name }} began operating in 1994 and has since hosted hundreds of travelers across cities, attractions, and resorts around the world. Our work is grounded in responsible planning, dependable service, and journeys that leave travelers richer in perspective.</p>
            <p>From our Nairobi office, we coordinate escorted leisure and educational travel across African, Middle Eastern, European, Asian, Australian, and other international destinations.</p>
            @if ($profile->email)
                <a class="travel-button travel-button--quiet" href="mailto:{{ $profile->email }}">Talk to our team</a>
            @endif
        </div>
    </div>
</section>

<section class="travel-section" aria-labelledby="travel-process-title">
    <div class="container-xxl">
        <header class="travel-heading">
            <div><p class="travel-eyebrow">A considered process</p><h2 id="travel-process-title">Simple steps, properly coordinated.</h2></div>
            <p>From choosing a departure to the final travel notes, every stage has a clear owner and a useful next action.</p>
        </header>
        <div class="travel-process-grid">
            <article class="travel-process-card"><span class="travel-process-card__number">01</span><h3>Discover</h3><p>Browse available journeys or tell us the destination and travel style you have in mind.</p></article>
            <article class="travel-process-card"><span class="travel-process-card__number">02</span><h3>Shape the plan</h3><p>Confirm dates, participants, inclusions, and any practical or accessibility requirements.</p></article>
            <article class="travel-process-card"><span class="travel-process-card__number">03</span><h3>Travel prepared</h3><p>Receive clear confirmation, payment guidance, documents, and support before departure.</p></article>
        </div>
    </div>
</section>

<section class="travel-cta">
    <div class="container-xxl travel-cta__inner">
        <div><p class="travel-eyebrow">Custom and group travel</p><h2>Your journey does not have to start from a fixed package.</h2><p>Share your destination, dates, group size, and priorities. We will help shape the right route.</p></div>
        @if ($profile->whatsappNumber)
            <a class="travel-button travel-button--light" href="https://wa.me/{{ $profile->whatsappNumber }}?text={{ rawurlencode('Hello '.$profile->shortName.', I would like help planning a trip.') }}" target="_blank" rel="noopener noreferrer">Plan with us <i data-lucide="message-circle" aria-hidden="true"></i></a>
        @endif
    </div>
</section>
@endsection
