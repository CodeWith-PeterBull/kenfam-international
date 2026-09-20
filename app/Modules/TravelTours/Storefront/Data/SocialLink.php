<?php

/**
 * Describes one institutional social profile as the storefront renders it.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Storefront\Data;

/** Carry a recognised platform key, its display label, and the public profile URL. */
final readonly class SocialLink
{
    /** Platform keys with a dedicated brand mark in the storefront; anything else renders a generic link mark. */
    public const PLATFORMS = [
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'tiktok' => 'TikTok',
        'x' => 'X',
        'youtube' => 'YouTube',
        'linkedin' => 'LinkedIn',
    ];

    /** Initialize the immutable link; the key is one of PLATFORMS or "link". */
    public function __construct(
        public string $key,
        public string $label,
        public string $url,
    ) {}
}
