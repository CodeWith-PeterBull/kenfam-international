<?php

declare(strict_types=1);

namespace Tests\Feature\Commerce;

use App\Data\InstitutionProfileData;
use App\Modules\Commerce\Support\InstitutionContact;
use Tests\TestCase;

/**
 * Resolves the WhatsApp number, X handle, and sameAs list from institution socials.
 */
final class InstitutionContactTest extends TestCase
{
    /**
     * @param  list<array{platform: string, handle: string, url: string}>  $socials
     */
    private function contact(array $socials, ?string $phone = null): InstitutionContact
    {
        return InstitutionContact::from(new InstitutionProfileData(
            id: 1, name: 'Aureon Group', shortName: 'Aureon', descriptor: null,
            primaryEmail: 'shop@aureon.test', secondaryEmail: null, primaryPhone: $phone, secondaryPhone: null,
            website: 'https://aureon.test', physicalAddress: null, city: null, county: null, postalCode: null,
            postalAddress: null, postalCity: null, socialMedia: $socials,
            mainLogoUrl: null, lightLogoUrl: null, mainLogoPath: null, hasCustomMainLogo: false,
            logoIconUrl: null, logoIconPath: null, hasCustomLogoIcon: false,
        ));
    }

    public function test_whatsapp_number_resolves_from_a_social_entry(): void
    {
        $this->assertSame('254712345678', $this->contact([
            ['platform' => 'WhatsApp', 'handle' => '', 'url' => 'https://wa.me/254712345678'],
        ])->whatsappNumber());

        $this->assertSame('254712345678', $this->contact([
            ['platform' => 'WhatsApp Business', 'handle' => '+254 712 345 678', 'url' => ''],
        ])->whatsappNumber());

        $this->assertSame('254712345678', $this->contact([
            ['platform' => 'whatsapp', 'handle' => '00254712345678', 'url' => ''],
        ])->whatsappNumber());
    }

    public function test_whatsapp_number_falls_back_to_primary_phone_then_null(): void
    {
        $this->assertSame('254700000000', $this->contact([
            ['platform' => 'Facebook', 'handle' => 'aureon', 'url' => 'https://facebook.com/aureon'],
        ], '+254 700 000 000')->whatsappNumber());

        $this->assertNull($this->contact([
            ['platform' => 'Facebook', 'handle' => 'aureon', 'url' => 'https://facebook.com/aureon'],
        ])->whatsappNumber());
    }

    public function test_whatsapp_order_url_is_built_or_null(): void
    {
        $contact = $this->contact([['platform' => 'WhatsApp', 'handle' => '', 'url' => 'https://wa.me/254712345678']]);
        $url = $contact->whatsappOrderUrl('Arc ANC Headphones', 'https://aureon.test/shop/products/arc', 'KSh 15,900.00');

        $this->assertStringStartsWith('https://wa.me/254712345678?text=', (string) $url);
        $this->assertStringContainsString(rawurlencode('Arc ANC Headphones'), (string) $url);

        $this->assertNull($this->contact([])->whatsappOrderUrl('Widget', 'https://aureon.test/x'));
    }

    public function test_same_as_and_x_handle(): void
    {
        $contact = $this->contact([
            ['platform' => 'Facebook', 'handle' => '', 'url' => 'https://facebook.com/aureon'],
            ['platform' => 'X', 'handle' => '@aureon', 'url' => 'https://x.com/aureon'],
            ['platform' => 'Instagram', 'handle' => 'aureon', 'url' => ''],
        ]);

        $this->assertSame(['https://facebook.com/aureon', 'https://x.com/aureon'], $contact->sameAs());
        $this->assertSame('@aureon', $contact->xHandle());
        $this->assertSame('@aureonhq', $this->contact([['platform' => 'Twitter', 'handle' => '', 'url' => 'https://twitter.com/aureonhq']])->xHandle());
        $this->assertNull($this->contact([['platform' => 'Facebook', 'handle' => 'x', 'url' => 'https://facebook.com/x']])->xHandle());
    }
}
