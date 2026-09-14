@extends('travel-tours::layouts.storefront')

@php
    $profile = app(\App\Modules\TravelTours\Storefront\Services\TravelStorefrontProfileResolver::class)->current();
    $cover = $tour->getFirstMediaUrl('tour_cover', 'hero') ?: $profile->heroImageUrl;
    $ratePlan = $tour->ratePlans->firstWhere('is_default', true) ?: $tour->ratePlans->first();
    $adultRate = $ratePlan?->participantRates?->first(
        static fn ($rate): bool => $rate->participant_type === \App\Modules\TravelTours\Bookings\Enums\ParticipantType::Adult
    );
    $contentGroups = $tour->contentItems->groupBy(static fn ($item): string => $item->type->value);
    $destinationNames = $tour->destinations->pluck('name')->join(', ');
    $tourSchema = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'TouristTrip',
        'name' => $tour->name,
        'description' => $tour->short_description,
        'url' => route('travel-tours.storefront.tours.show', $tour->slug),
        'image' => $cover,
        'touristType' => $tour->type->label(),
        'itinerary' => $destinationNames,
        'provider' => ['@type' => 'TravelAgency', 'name' => $profile->name, 'url' => route('home')],
    ], static fn (mixed $value): bool => $value !== null && $value !== '');
@endphp

@section('title', $tour->meta_title ?: $tour->name)
@section('meta_description', $tour->meta_description ?: $tour->short_description)
@section('canonical', route('travel-tours.storefront.tours.show', $tour->slug))
@section('og_type', 'product')
@section('social_image', $cover)
@section('page', 'tour-detail')

@push('head')
    <script type="application/ld+json">{!! json_encode($tourSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endpush

@section('content')
<section class="travel-detail-hero" aria-labelledby="travel-tour-title">
    <img src="{{ $cover }}" alt="{{ $tour->name }}" width="1920" height="1080" fetchpriority="high">
    <div class="container-xxl">
        <p class="travel-eyebrow">{{ $tour->type->label() }} journey</p>
        <h1 id="travel-tour-title">{{ $tour->name }}</h1>
        <p class="travel-hero__lead">{{ $tour->tagline ?: $tour->short_description }}</p>
        <div class="travel-detail-hero__meta">
            <span><i data-lucide="clock-3" aria-hidden="true"></i>{{ $tour->duration_days }} days / {{ $tour->duration_nights }} nights</span>
            @if ($destinationNames)
                <span><i data-lucide="map-pin" aria-hidden="true"></i>{{ $destinationNames }}</span>
            @endif
            <span><i data-lucide="activity" aria-hidden="true"></i>{{ $tour->difficulty->label() }} pace</span>
            @if ($adultRate)
                <span><i data-lucide="badge-dollar-sign" aria-hidden="true"></i>From {{ \App\Modules\TravelTours\Support\MoneyFormatter::format($adultRate->amount_minor, $ratePlan->currency) }} per adult</span>
            @endif
        </div>
    </div>
</section>

<section class="travel-section">
    <div class="container-xxl travel-detail-grid">
        <div class="travel-detail-content">
            <section aria-labelledby="tour-overview-title">
                <p class="travel-eyebrow">The experience</p>
                <h2 id="tour-overview-title">Journey overview</h2>
                <div class="travel-copy">{!! nl2br(e($tour->description ?: $tour->short_description)) !!}</div>
            </section>

            @if ($tour->departures->isNotEmpty())
                <section aria-labelledby="tour-departures-title">
                    <p class="travel-eyebrow">Plan ahead</p>
                    <h2 id="tour-departures-title">Available departures</h2>
                    <div class="travel-departure-list">
                        @foreach ($tour->departures as $departure)
                            <article class="travel-departure-card">
                                <div>
                                    <h3>{{ $departure->starts_at->timezone($departure->timezone)->format('d M Y') }} to {{ $departure->ends_at->timezone($departure->timezone)->format('d M Y') }}</h3>
                                    <p>{{ $departure->code }} <span aria-hidden="true">&middot;</span> {{ $departure->status->label() }} <span aria-hidden="true">&middot;</span> {{ $departure->availableSeats() }} places currently available</p>
                                </div>
                                @if ($adultRate)
                                    <strong>{{ \App\Modules\TravelTours\Support\MoneyFormatter::format($adultRate->amount_minor, $ratePlan->currency) }}</strong>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($contentGroups->isNotEmpty())
                <section aria-labelledby="tour-details-title">
                    <p class="travel-eyebrow">Know before you go</p>
                    <h2 id="tour-details-title">What the journey includes</h2>
                    <div class="travel-content-columns">
                        @foreach ($contentGroups as $type => $items)
                            <article class="travel-content-list">
                                <h3>{{ Str::headline(Str::plural($type)) }}</h3>
                                <ul>
                                    @foreach ($items as $item)
                                        <li>{{ $item->content }}</li>
                                    @endforeach
                                </ul>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($tour->itineraryDays->isNotEmpty())
                <section aria-labelledby="tour-itinerary-title">
                    <p class="travel-eyebrow">Day by day</p>
                    <h2 id="tour-itinerary-title">Itinerary</h2>
                    <div class="travel-itinerary">
                        @foreach ($tour->itineraryDays as $day)
                            <details {{ $loop->first ? 'open' : '' }}>
                                <summary>
                                    <strong>Day {{ $day->day_number }}</strong>
                                    <h3>{{ $day->title }}</h3>
                                    <i data-lucide="chevron-down" aria-hidden="true"></i>
                                </summary>
                                <div class="travel-itinerary__body">
                                    <p>{{ $day->description }}</p>
                                    <div class="travel-itinerary__facts">
                                        @if ($day->meals)
                                            <span><strong>Meals:</strong> {{ implode(', ', $day->meals) }}</span>
                                        @endif
                                        @if ($day->accommodation)
                                            <span><strong>Stay:</strong> {{ $day->accommodation }}</span>
                                        @endif
                                    </div>
                                    @if ($day->activities->isNotEmpty())
                                        <ul class="mt-3 mb-0">
                                            @foreach ($day->activities as $activity)
                                                <li>
                                                    {{ $activity->title }}
                                                    @if ($activity->description)
                                                        : {{ $activity->description }}
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            </details>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($tour->faqs->where('is_active', true)->isNotEmpty())
                <section aria-labelledby="tour-faq-title">
                    <p class="travel-eyebrow">Useful answers</p>
                    <h2 id="tour-faq-title">Frequently asked questions</h2>
                    <div class="travel-faqs">
                        @foreach ($tour->faqs->where('is_active', true) as $faq)
                            <details><summary>{{ $faq->question }}</summary><p>{{ $faq->answer }}</p></details>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>

        <aside class="travel-inquiry" aria-labelledby="tour-inquiry-title">
            <p class="travel-eyebrow">Plan this journey</p>
            <h2 id="tour-inquiry-title">Talk to a travel specialist</h2>
            @if (session('inquiry_submitted'))
                <div class="alert alert-success" role="status">{{ session('inquiry_submitted') }}</div>
            @endif
            <form method="post" action="{{ route('travel-tours.storefront.inquiries.store') }}">
                @csrf
                @honeypot
                <input type="hidden" name="operation_key" value="{{ old('operation_key', (string) Str::ulid()) }}">
                <input type="hidden" name="tour_id" value="{{ $tour->id }}">
                <input type="hidden" name="inquiry_type" value="tour">
                <input type="hidden" name="consent" value="1">
                <label><span>Full name</span><input name="contact_name" value="{{ old('contact_name') }}" autocomplete="name" required></label>
                <label><span>Email</span><input type="email" name="contact_email" value="{{ old('contact_email') }}" autocomplete="email"></label>
                <label><span>Phone or WhatsApp</span><input name="contact_phone" value="{{ old('contact_phone') }}" autocomplete="tel" required></label>
                @if ($tour->departures->isNotEmpty())
                    <label><span>Preferred departure</span><select name="departure_id"><option value="">I am flexible</option>@foreach ($tour->departures as $departure)<option value="{{ $departure->id }}" @selected((string) old('departure_id') === (string) $departure->id)>{{ $departure->starts_at->timezone($departure->timezone)->format('d M Y') }}</option>@endforeach</select></label>
                @endif
                <div class="row g-2">
                    <div class="col-6"><label><span>Adults</span><input type="number" name="adult_count" min="1" max="50" value="{{ old('adult_count', 1) }}"></label></div>
                    <div class="col-6"><label><span>Children</span><input type="number" name="child_count" min="0" max="50" value="{{ old('child_count', 0) }}"></label></div>
                </div>
                <label><span>Dates or questions</span><textarea name="message" required>{{ old('message') }}</textarea></label>
                @if ($errors->any())
                    <div class="alert alert-danger" role="alert"><strong>Please review your details.</strong><ul class="mb-0 mt-2">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                @endif
                <button class="travel-button" type="submit">Send inquiry <i data-lucide="arrow-up-right" aria-hidden="true"></i></button>
                @if ($profile->whatsappNumber)
                    <a class="travel-button travel-button--quiet" href="https://wa.me/{{ $profile->whatsappNumber }}?text={{ rawurlencode('Hello '.$profile->shortName.', I am interested in '.$tour->name) }}" target="_blank" rel="noopener noreferrer">Ask on WhatsApp</a>
                @endif
                <p class="travel-inquiry__assurance"><i data-lucide="shield-check" aria-hidden="true"></i><span>Your details are used only to respond to this travel inquiry.</span></p>
            </form>
        </aside>
    </div>
</section>
@endsection
