<?php

/**
 * Derives storefront contact and social identity from the institution profile.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Support;

use App\Data\InstitutionProfileData;
use App\Modules\TravelTours\Storefront\Data\SocialLink;

/**
 * Resolve WhatsApp, X, and social profile links from Institution Details.
 *
 * The institution social list is free-form (platform/handle/url), so this
 * helper recognises platforms from their recorded name or their URL host,
 * resolves the WhatsApp booking number, the X handle for Twitter card
 * metadata, and the sameAs list for TravelAgency structured data.
 */
final readonly class InstitutionContact
{
    /** Platform key => host names that identify it when the recorded name does not. */
    private const HOSTS = [
        'facebook' => ['facebook.com', 'fb.com', 'fb.me'],
        'instagram' => ['instagram.com'],
        'tiktok' => ['tiktok.com'],
        'x' => ['x.com', 'twitter.com'],
        'youtube' => ['youtube.com', 'youtu.be'],
        'linkedin' => ['linkedin.com'],
        'whatsapp' => ['wa.me', 'whatsapp.com'],
    ];

    /** Wrap the resolved host institution profile. */
    public function __construct(private InstitutionProfileData $profile) {}

    /** Build a contact helper for a resolved profile. */
    public static function from(InstitutionProfileData $profile): self
    {
        return new self($profile);
    }

    /**
     * International WhatsApp number (digits only) or null when none resolves.
     *
     * Prefers a social entry recorded as WhatsApp (parsing the url or handle),
     * then falls back to the institution primary phone. Numbers should be
     * stored in international format; a national "0…" number is left as-is
     * because its country code cannot be inferred safely.
     */
    public function whatsappNumber(): ?string
    {
        $social = $this->findSocial('whatsapp');
        $raw = $social === null
            ? null
            : (trim((string) ($social['url'] ?? '')) ?: trim((string) ($social['handle'] ?? '')));

        if (! filled($raw)) {
            $raw = $this->profile->primaryPhone;
        }

        return $this->normalizeMsisdn($raw);
    }

    /** Build a prefilled wa.me booking link for a tour, or null when no number resolves. */
    public function whatsappBookingUrl(string $tourName, string $tourUrl, ?string $priceLabel = null): ?string
    {
        $number = $this->whatsappNumber();
        if ($number === null) {
            return null;
        }

        $lines = ["Hello {$this->profile->shortName}, I would like to book:", $tourName];
        if (filled($priceLabel)) {
            $lines[] = "From: {$priceLabel}";
        }
        $lines[] = $tourUrl;

        return 'https://wa.me/'.$number.'?text='.rawurlencode(implode("\n", $lines));
    }

    /**
     * Public social profiles for the footer, in the order they were recorded.
     *
     * WhatsApp entries are messaging channels rather than profiles and are
     * served by the WhatsApp call to action instead; entries without a valid
     * http(s) URL are skipped.
     *
     * @return list<SocialLink>
     */
    public function socialLinks(): array
    {
        $links = [];
        foreach ($this->profile->socialMedia as $social) {
            $url = trim((string) ($social['url'] ?? ''));
            if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
                continue;
            }

            $key = $this->platformKey($social);
            if ($key === 'whatsapp') {
                continue;
            }

            $recognised = array_key_exists($key, SocialLink::PLATFORMS);
            $links[] = new SocialLink(
                key: $recognised ? $key : 'link',
                label: $recognised ? SocialLink::PLATFORMS[$key] : $this->fallbackLabel($social, $url),
                url: $url,
            );
        }

        return $links;
    }

    /**
     * All recorded social profile URLs for schema.org sameAs.
     *
     * @return list<string>
     */
    public function sameAs(): array
    {
        return array_values(array_filter(array_map(
            static fn (array $social): ?string => trim((string) ($social['url'] ?? '')) ?: null,
            $this->profile->socialMedia,
        )));
    }

    /** The institution X (Twitter) handle in @handle form for twitter:site, or null. */
    public function xHandle(): ?string
    {
        $social = $this->findSocial('x');
        if ($social === null) {
            return null;
        }

        $handle = trim((string) ($social['handle'] ?? ''));
        if ($handle === '') {
            $path = parse_url(trim((string) ($social['url'] ?? '')), PHP_URL_PATH);
            $handle = is_string($path) ? trim(basename($path)) : '';
        }

        return $handle === '' ? null : '@'.ltrim($handle, '@');
    }

    /**
     * Resolve a platform key from the recorded name first, then the URL host.
     *
     * @param  array{platform?: string, handle?: string, url?: string}  $social
     */
    private function platformKey(array $social): string
    {
        $platform = strtolower(trim((string) ($social['platform'] ?? '')));
        $byName = match (true) {
            $platform === 'fb', str_contains($platform, 'facebook') => 'facebook',
            $platform === 'ig', str_contains($platform, 'instagram') => 'instagram',
            str_contains(str_replace(' ', '', $platform), 'tiktok') => 'tiktok',
            $platform === 'x', str_contains($platform, 'twitter'), str_starts_with($platform, 'x ') => 'x',
            str_contains($platform, 'youtube') => 'youtube',
            str_contains($platform, 'linkedin') => 'linkedin',
            str_contains($platform, 'whatsapp') => 'whatsapp',
            default => null,
        };
        if ($byName !== null) {
            return $byName;
        }

        $host = strtolower((string) parse_url(trim((string) ($social['url'] ?? '')), PHP_URL_HOST));
        $host = preg_replace('/^(www|m|web)\./', '', $host) ?? $host;
        foreach (self::HOSTS as $key => $hosts) {
            if (in_array($host, $hosts, true)) {
                return $key;
            }
        }

        return 'link';
    }

    /**
     * Label for a platform without a brand mark: the recorded name, else the host.
     *
     * @param  array{platform?: string, handle?: string, url?: string}  $social
     */
    private function fallbackLabel(array $social, string $url): string
    {
        $platform = trim((string) ($social['platform'] ?? ''));

        return $platform !== '' ? $platform : (string) parse_url($url, PHP_URL_HOST);
    }

    /**
     * @return array{platform?: string, handle?: string, url?: string}|null
     */
    private function findSocial(string $key): ?array
    {
        foreach ($this->profile->socialMedia as $social) {
            if ($this->platformKey($social) === $key) {
                return $social;
            }
        }

        return null;
    }

    /** Reduce a recorded number or wa.me link to international digits. */
    private function normalizeMsisdn(?string $raw): ?string
    {
        if (! filled($raw)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        return strlen($digits) >= 7 ? $digits : null;
    }
}
