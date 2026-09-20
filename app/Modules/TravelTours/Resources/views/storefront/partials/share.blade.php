{{--
    Tour share group. Requires $url (absolute public tour URL) and $title.

    Facebook / X / WhatsApp use real web share intents. TikTok, Instagram, and
    Copy write the link to the clipboard (they have no web URL-share intent). A
    native Share button (Web Share API) is revealed by storefront.js where the
    browser supports it. Platforms come from the allow-list in
    travel-tours.storefront.sharing; an empty list means all of them.
--}}
@php
    $sharing = config('travel-tours.storefront.sharing', []);
@endphp
@if (($sharing['enabled'] ?? true))
    @php
        $allowed = collect($sharing['platforms'] ?? []);
        $show = fn (string $key): bool => $allowed->isEmpty() || $allowed->contains($key);
        $encodedUrl = rawurlencode($url);
        $encodedTitle = rawurlencode($title);
        $whatsappText = rawurlencode($title.' '.$url);
    @endphp
    <div class="travel-share" role="group" aria-label="Share this tour">
        <span class="travel-share__title">Share</span>
        <div class="travel-share__buttons">
            @if ($show('facebook'))
                <a class="travel-share__btn" href="https://www.facebook.com/sharer/sharer.php?u={{ $encodedUrl }}" target="_blank" rel="noopener noreferrer" aria-label="Share on Facebook" data-share-window>
                    @include('travel-tours::storefront.partials.brand-mark', ['platform' => 'facebook'])
                    <span class="travel-share__label">Facebook</span>
                </a>
            @endif
            @if ($show('x'))
                <a class="travel-share__btn" href="https://twitter.com/intent/tweet?url={{ $encodedUrl }}&text={{ $encodedTitle }}" target="_blank" rel="noopener noreferrer" aria-label="Share on X" data-share-window>
                    @include('travel-tours::storefront.partials.brand-mark', ['platform' => 'x'])
                    <span class="travel-share__label">X</span>
                </a>
            @endif
            @if ($show('whatsapp'))
                <a class="travel-share__btn" href="https://wa.me/?text={{ $whatsappText }}" target="_blank" rel="noopener noreferrer" aria-label="Share on WhatsApp" data-share-window>
                    @include('travel-tours::storefront.partials.brand-mark', ['platform' => 'whatsapp'])
                    <span class="travel-share__label">WhatsApp</span>
                </a>
            @endif
            @if ($show('tiktok'))
                <button type="button" class="travel-share__btn" aria-label="Copy link to share on TikTok" data-share-copy="{{ $url }}" data-share-hint="TikTok">
                    @include('travel-tours::storefront.partials.brand-mark', ['platform' => 'tiktok'])
                    <span class="travel-share__label">TikTok</span>
                </button>
            @endif
            @if ($show('instagram'))
                <button type="button" class="travel-share__btn" aria-label="Copy link to share on Instagram" data-share-copy="{{ $url }}" data-share-hint="Instagram">
                    @include('travel-tours::storefront.partials.brand-mark', ['platform' => 'instagram'])
                    <span class="travel-share__label">Instagram</span>
                </button>
            @endif
            @if ($show('copy'))
                <button type="button" class="travel-share__btn" aria-label="Copy tour link" data-share-copy="{{ $url }}">
                    <i data-lucide="link" aria-hidden="true"></i>
                    <span class="travel-share__label">Copy link</span>
                </button>
            @endif
            <button type="button" class="travel-share__btn" aria-label="Share this tour" data-share-native data-share-url="{{ $url }}" data-share-title="{{ $title }}" hidden>
                <i data-lucide="share-2" aria-hidden="true"></i>
                <span class="travel-share__label">Share</span>
            </button>
        </div>
    </div>
@endif
