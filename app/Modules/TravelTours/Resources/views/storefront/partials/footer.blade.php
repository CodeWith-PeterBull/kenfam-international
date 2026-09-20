<footer class="travel-footer" id="contact">
    <div class="container-xxl travel-footer__lead">
        <div>
            <p class="travel-eyebrow">Begin with a conversation</p>
            <h2>Tell us where you would like to go.</h2>
        </div>
        @if ($travelProfile->socialLinks !== [])
            {{-- Profiles recorded in Institution Details (or the configured defaults), in the order they were entered. --}}
            <ul class="travel-footer__social" aria-label="{{ $travelProfile->shortName }} on social media">
                @foreach ($travelProfile->socialLinks as $social)
                    <li>
                        <a href="{{ $social->url }}" target="_blank" rel="me noopener noreferrer" aria-label="{{ $travelProfile->shortName }} on {{ $social->label }}" title="{{ $social->label }}">
                            @include('travel-tours::storefront.partials.brand-mark', ['platform' => $social->key])
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
        @if ($travelProfile->whatsappNumber)
            <a class="travel-button travel-button--light" href="https://wa.me/{{ $travelProfile->whatsappNumber }}?text={{ rawurlencode('Hello '.$travelProfile->shortName.', I would like help planning a journey.') }}" target="_blank" rel="noopener noreferrer">
                <i data-lucide="message-circle" aria-hidden="true"></i>
                Plan on WhatsApp
            </a>
        @endif
    </div>
    <div class="container-xxl travel-footer__grid">
        <div class="travel-footer__identity">
            <a class="travel-brand" href="{{ route('home') }}" aria-label="{{ $travelProfile->name }} home">
                <img src="{{ $travelProfile->lightLogoUrl }}" alt="{{ $travelProfile->name }}" width="199" height="70">
            </a>
            <p>{{ $travelProfile->descriptor ?: 'Considered journeys, capable coordination, and attentive service.' }}</p>
        </div>
        <nav aria-label="Explore">
            <h2>Explore</h2>
            <a href="{{ route('travel-tours.storefront.catalog.index') }}">All tours</a>
            <a href="{{ route('home') }}#about">Our story</a>
            <a href="{{ route('home') }}#travel-styles">Ways to travel</a>
        </nav>
        <nav aria-label="Account">
            <h2>Account</h2>
            <a href="{{ auth()->check() ? route('dashboard') : route('login') }}">{{ auth()->check() ? 'Open workspace' : 'Sign in' }}</a>
            @guest
                <a href="{{ route('register') }}">Create account</a>
            @endguest
            <a href="{{ route('travel-tours.storefront.catalog.index') }}">Find a journey</a>
        </nav>
        <address class="travel-footer__contact">
            <h2>Contact</h2>
            @if ($travelProfile->email)
                <a href="mailto:{{ $travelProfile->email }}">{{ $travelProfile->email }}</a>
            @endif
            @if ($travelProfile->phoneLink)
                <a href="tel:{{ $travelProfile->phoneLink }}">{{ $travelProfile->phone }}</a>
            @endif
            @if ($travelProfile->address)
                <p>{{ $travelProfile->address }}</p>
            @endif
        </address>
    </div>
    <div class="container-xxl travel-footer__legal">
        <span>&copy; <span data-current-year>{{ now()->year }}</span> {{ $travelProfile->name }}</span>
        <span>Travel operations powered by Aureon</span>
    </div>
</footer>
