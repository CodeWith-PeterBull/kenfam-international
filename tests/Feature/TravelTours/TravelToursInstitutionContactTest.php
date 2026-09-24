<?php

/**
 * Verifies how TravelTours reads social identity out of Institution Details.
 */

declare(strict_types=1);

namespace Tests\Feature\TravelTours;

use App\Data\InstitutionProfileData;
use App\Modules\TravelTours\Support\InstitutionContact;
use Tests\TestCase;

/** Resolve the WhatsApp number, the X handle, the footer links, and sameAs from free-form socials. */
final class TravelToursInstitutionContactTest extends TestCase
{
    /**
     * @param  list<array{platform: string, handle: string, url: string}>  $socials
     */
    private function contact(array $socials, ?string $phone = null): InstitutionContact
    {
        return InstitutionContact::from(new InstitutionProfileData(
            id: 1, name: 'Kenfam International', shortName: 'Kenfam', descriptor: null,
            primaryEmail: 'travel@kenfam.test', secondaryEmail: null, primaryPhone: $phone, secondaryPhone: null,
            website: 'https://kenfam.test', physicalAddress: null, city: null, county: null, postalCode: null,
            postalAddress: null, postalCity: null, socialMedia: $socials,
            mainLogoUrl: null, lightLogoUrl: null, mainLogoPath: null, hasCustomMainLogo: false,
            logoIconUrl: null, logoIconPath: null, hasCustomLogoIcon: false,
        ));
    }

    /** A WhatsApp social entry wins over the primary phone, then the phone, then nothing. */
    public function test_whatsapp_number_prefers_a_social_entry_then_the_primary_phone(): void
    {
        $this->assertSame('254712345678', $this->contact([
            ['platform' => 'WhatsApp', 'handle' => '', 'url' => 'https://wa.me/254712345678'],
        ], '+254 700 000 000')->whatsappNumber());

        $this->assertSame('254712345678', $this->contact([
            ['platform' => 'Chat', 'handle' => '00254712345678', 'url' => 'https://wa.me/254712345678'],
        ])->whatsappNumber());

        $this->assertSame('254700000000', $this->contact([], '+254 700 000 000')->whatsappNumber());
        $this->assertNull($this->contact([])->whatsappNumber());
    }

    /** The booking link carries the greeting, the tour, the from-price, and the public URL. */
    public function test_whatsapp_booking_url_is_prefilled_or_null(): void
    {
        $contact = $this->contact([['platform' => 'WhatsApp', 'handle' => '+254 712 345 678', 'url' => '']]);
        $url = $contact->whatsappBookingUrl('Cairo and the Nile', 'https://kenfam.test/tours/cairo', 'KES 385,000.00');

        $this->assertStringStartsWith('https://wa.me/254712345678?text=', (string) $url);
        $this->assertSame(
            "Hello Kenfam, I would like to book:\nCairo and the Nile\nFrom: KES 385,000.00\nhttps://kenfam.test/tours/cairo",
            rawurldecode(substr((string) $url, strlen('https://wa.me/254712345678?text='))),
        );
        $this->assertNull($this->contact([])->whatsappBookingUrl('Cairo and the Nile', 'https://kenfam.test/tours/cairo'));
    }

    /** Platforms are recognised by their recorded name or their URL host; WhatsApp and invalid URLs are skipped. */
    public function test_social_links_recognise_platforms_by_name_or_host(): void
    {
        $links = $this->contact([
            ['platform' => 'fb', 'handle' => 'kenfam', 'url' => 'https://www.facebook.com/kenfam'],
            ['platform' => 'IG', 'handle' => '@kenfam', 'url' => 'https://instagram.com/kenfam'],
            ['platform' => 'Tik Tok', 'handle' => '@kenfam', 'url' => 'https://www.tiktok.com/@kenfam'],
            ['platform' => 'Twitter', 'handle' => '@kenfam', 'url' => 'https://x.com/kenfam'],
            ['platform' => '', 'handle' => '', 'url' => 'https://youtu.be/kenfam'],
            ['platform' => 'Company page', 'handle' => '', 'url' => 'https://www.linkedin.com/company/kenfam'],
            ['platform' => 'Travel blog', 'handle' => '', 'url' => 'https://blog.kenfam.test'],
            ['platform' => 'WhatsApp', 'handle' => '', 'url' => 'https://wa.me/254712345678'],
            ['platform' => 'Pinterest', 'handle' => 'kenfam', 'url' => ''],
            ['platform' => 'Threads', 'handle' => 'kenfam', 'url' => 'not a url'],
        ])->socialLinks();

        $this->assertSame(
            ['facebook', 'instagram', 'tiktok', 'x', 'youtube', 'linkedin', 'link'],
            array_map(static fn ($link): string => $link->key, $links),
        );
        $this->assertSame(
            ['Facebook', 'Instagram', 'TikTok', 'X', 'YouTube', 'LinkedIn', 'Travel blog'],
            array_map(static fn ($link): string => $link->label, $links),
        );
        $this->assertSame('https://www.tiktok.com/@kenfam', $links[2]->url);
    }

    /** The X handle comes from the handle, else the URL path; sameAs lists every recorded URL. */
    public function test_x_handle_and_same_as(): void
    {
        $contact = $this->contact([
            ['platform' => 'Facebook', 'handle' => '', 'url' => 'https://facebook.com/kenfam'],
            ['platform' => 'X', 'handle' => '', 'url' => 'https://x.com/kenfamhq'],
            ['platform' => 'WhatsApp', 'handle' => '+254712345678', 'url' => ''],
        ]);

        $this->assertSame('@kenfamhq', $contact->xHandle());
        $this->assertSame(['https://facebook.com/kenfam', 'https://x.com/kenfamhq'], $contact->sameAs());
        $this->assertSame('@kenfam', $this->contact([['platform' => 'Twitter', 'handle' => 'kenfam', 'url' => '']])->xHandle());
        $this->assertNull($this->contact([])->xHandle());
    }
}
