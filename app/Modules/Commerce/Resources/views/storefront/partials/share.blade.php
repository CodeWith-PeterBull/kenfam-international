{{--
    Product share group. Requires $url (absolute product URL) and $title.

    Facebook / X / WhatsApp use real web share intents. TikTok, Instagram, and
    Copy write the link to the clipboard (they have no web URL-share intent). A
    native Share button (Web Share API) is revealed by storefront.js where the
    browser supports it. Brand marks are inline monochrome SVGs so they inherit
    the theme colour and add no icon-font dependency.
--}}
@php
    $sharing = config('commerce.storefront.sharing', []);
@endphp
@if (($sharing['enabled'] ?? true))
    @php
        $allowed = collect($sharing['platforms'] ?? []);
        $show = fn (string $key): bool => $allowed->isEmpty() || $allowed->contains($key);
        $encodedUrl = rawurlencode($url);
        $encodedTitle = rawurlencode($title);
        $whatsappText = rawurlencode($title.' '.$url);
    @endphp
    <div class="commerce-share" role="group" aria-label="Share this product">
        <span class="commerce-share__title">Share</span>
        <div class="commerce-share__buttons">
            @if ($show('facebook'))
                <a class="commerce-share__btn commerce-share__btn--facebook" href="https://www.facebook.com/sharer/sharer.php?u={{ $encodedUrl }}" target="_blank" rel="noopener noreferrer" aria-label="Share on Facebook" data-share-window>
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.69 4.53-4.69 1.31 0 2.68.24 2.68.24v2.97h-1.5c-1.49 0-1.96.93-1.96 1.89v2.25h3.33l-.53 3.49h-2.8V24C19.61 23.1 24 18.1 24 12.07"/></svg>
                    <span class="commerce-share__label">Facebook</span>
                </a>
            @endif
            @if ($show('x'))
                <a class="commerce-share__btn commerce-share__btn--x" href="https://twitter.com/intent/tweet?url={{ $encodedUrl }}&text={{ $encodedTitle }}" target="_blank" rel="noopener noreferrer" aria-label="Share on X" data-share-window>
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.9 1.15h3.68l-8.04 9.19L24 22.85h-7.41l-5.8-7.58-6.64 7.58H.47l8.6-9.83L0 1.15h7.59l5.24 6.93zM17.6 20.64h2.04L6.49 3.24H4.3z"/></svg>
                    <span class="commerce-share__label">X</span>
                </a>
            @endif
            @if ($show('whatsapp'))
                <a class="commerce-share__btn commerce-share__btn--whatsapp" href="https://wa.me/?text={{ $whatsappText }}" target="_blank" rel="noopener noreferrer" aria-label="Share on WhatsApp" data-share-window>
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.07-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48s1.06 2.88 1.21 3.08c.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.69.63.71.23 1.36.2 1.87.12.57-.09 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.42-.07-.13-.27-.2-.57-.35M12.04 21.5h-.01a9.4 9.4 0 0 1-4.79-1.31l-.34-.2-3.56.93.95-3.47-.22-.36a9.38 9.38 0 0 1-1.44-5.01c0-5.18 4.22-9.4 9.42-9.4a9.36 9.36 0 0 1 9.4 9.41c0 5.18-4.22 9.4-9.4 9.4M20.52 3.49A11.8 11.8 0 0 0 12.04 0C5.46 0 .1 5.36.09 11.94c0 2.1.55 4.16 1.6 5.97L0 24l6.24-1.64a11.9 11.9 0 0 0 5.8 1.48h.01c6.58 0 11.94-5.36 11.95-11.95a11.9 11.9 0 0 0-3.48-8.4"/></svg>
                    <span class="commerce-share__label">WhatsApp</span>
                </a>
            @endif
            @if ($show('tiktok'))
                <button type="button" class="commerce-share__btn commerce-share__btn--tiktok" aria-label="Copy link to share on TikTok" data-share-copy="{{ $url }}" data-share-hint="TikTok">
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12.53.02C13.84 0 15.14.01 16.44 0c.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg>
                    <span class="commerce-share__label">TikTok</span>
                </button>
            @endif
            @if ($show('instagram'))
                <button type="button" class="commerce-share__btn commerce-share__btn--instagram" aria-label="Copy link to share on Instagram" data-share-copy="{{ $url }}" data-share-hint="Instagram">
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.16c3.2 0 3.58.01 4.85.07 1.17.05 1.8.25 2.23.41.56.22.96.48 1.38.9.42.42.68.82.9 1.38.16.42.36 1.06.41 2.23.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.05 1.17-.25 1.8-.41 2.23-.22.56-.48.96-.9 1.38-.42.42-.82.68-1.38.9-.42.16-1.06.36-2.23.41-1.27.06-1.65.07-4.85.07s-3.58-.01-4.85-.07c-1.17-.05-1.8-.25-2.23-.41a3.7 3.7 0 0 1-1.38-.9 3.7 3.7 0 0 1-.9-1.38c-.16-.42-.36-1.06-.41-2.23C2.17 15.58 2.16 15.2 2.16 12s.01-3.58.07-4.85c.05-1.17.25-1.8.41-2.23.22-.56.48-.96.9-1.38.42-.42.82-.68 1.38-.9.42-.16 1.06-.36 2.23-.41C8.42 2.17 8.8 2.16 12 2.16M12 0C8.74 0 8.33.01 7.05.07c-1.28.06-2.15.26-2.91.56-.79.31-1.46.72-2.13 1.38A5.9 5.9 0 0 0 .63 4.14c-.3.76-.5 1.63-.56 2.91C.01 8.33 0 8.74 0 12s.01 3.67.07 4.95c.06 1.28.26 2.15.56 2.91.31.79.72 1.46 1.38 2.13a5.9 5.9 0 0 0 2.13 1.38c.76.3 1.63.5 2.91.56C8.33 23.99 8.74 24 12 24s3.67-.01 4.95-.07c1.28-.06 2.15-.26 2.91-.56a5.9 5.9 0 0 0 2.13-1.38 5.9 5.9 0 0 0 1.38-2.13c.3-.76.5-1.63.56-2.91.06-1.28.07-1.69.07-4.95s-.01-3.67-.07-4.95c-.06-1.28-.26-2.15-.56-2.91a5.9 5.9 0 0 0-1.38-2.13A5.9 5.9 0 0 0 19.86.63c-.76-.3-1.63-.5-2.91-.56C15.67.01 15.26 0 12 0m0 5.84A6.16 6.16 0 1 0 18.16 12 6.16 6.16 0 0 0 12 5.84M12 16a4 4 0 1 1 4-4 4 4 0 0 1-4 4m6.41-10.85a1.44 1.44 0 1 0 1.44 1.44 1.44 1.44 0 0 0-1.44-1.44"/></svg>
                    <span class="commerce-share__label">Instagram</span>
                </button>
            @endif
            @if ($show('copy'))
                <button type="button" class="commerce-share__btn commerce-share__btn--copy" aria-label="Copy product link" data-share-copy="{{ $url }}">
                    <i data-lucide="link" aria-hidden="true"></i>
                    <span class="commerce-share__label">Copy link</span>
                </button>
            @endif
            <button type="button" class="commerce-share__btn commerce-share__btn--native" aria-label="Share this product" data-share-native data-share-url="{{ $url }}" data-share-title="{{ $title }}" hidden>
                <i data-lucide="share-2" aria-hidden="true"></i>
                <span class="commerce-share__label">Share</span>
            </button>
        </div>
    </div>
@endif
