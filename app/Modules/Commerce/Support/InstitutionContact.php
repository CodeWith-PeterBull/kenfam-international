<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Support;

use App\Data\InstitutionProfileData;

/**
 * Derives storefront contact and social identity from the institution profile.
 *
 * The institution social list is free-form (platform/handle/url), so this
 * helper resolves the WhatsApp ordering number, the X handle for Twitter card
 * metadata, and the full sameAs list for Organization structured data from
 * whatever the administrator recorded in Institution Details.
 */
final readonly class InstitutionContact
{
    public function __construct(private InstitutionProfileData $profile) {}

    public static function from(InstitutionProfileData $profile): self
    {
        return new self($profile);
    }

    /**
     * International WhatsApp number (digits only) or null when none resolves.
     *
     * Prefers a social entry whose platform mentions "whatsapp" (parsing the
     * url or handle), then falls back to the institution primary phone. Numbers
     * should be stored in international format; a national "0…" number is left
     * as-is because its country code cannot be inferred safely.
     */
    public function whatsappNumber(): ?string
    {
        $social = $this->findSocial(static fn (string $platform): bool => str_contains($platform, 'whatsapp'));
        $raw = $social === null
            ? null
            : (trim((string) ($social['url'] ?? '')) ?: trim((string) ($social['handle'] ?? '')));

        if (! filled($raw)) {
            $raw = $this->profile->primaryPhone;
        }

        return $this->normalizeMsisdn($raw);
    }

    /**
     * Build a prefilled wa.me ordering link, or null when no number resolves.
     */
    public function whatsappOrderUrl(string $productName, string $productUrl, ?string $priceLabel = null): ?string
    {
        $number = $this->whatsappNumber();
        if ($number === null) {
            return null;
        }

        $lines = ["Hello {$this->profile->shortName}, I would like to order:", $productName];
        if (filled($priceLabel)) {
            $lines[] = "Price: {$priceLabel}";
        }
        $lines[] = $productUrl;

        return 'https://wa.me/'.$number.'?text='.rawurlencode(implode("\n", $lines));
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

    /**
     * The store X (Twitter) handle in @handle form for twitter:site, or null.
     */
    public function xHandle(): ?string
    {
        $social = $this->findSocial(static fn (string $platform): bool => $platform === 'x' || str_contains($platform, 'twitter'));
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
     * @param  callable(string): bool  $matches
     * @return array{platform: string, handle: string, url: string}|null
     */
    private function findSocial(callable $matches): ?array
    {
        foreach ($this->profile->socialMedia as $social) {
            if ($matches(strtolower(trim((string) ($social['platform'] ?? ''))))) {
                return $social;
            }
        }

        return null;
    }

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
