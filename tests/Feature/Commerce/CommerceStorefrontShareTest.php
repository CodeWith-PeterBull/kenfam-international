<?php

declare(strict_types=1);

namespace Tests\Feature\Commerce;

use App\Contracts\ResolvesInstitutionProfile;
use App\Models\InstitutionDetail;
use App\Modules\Commerce\Catalog\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Product-page social sharing, WhatsApp ordering, and SEO structured data.
 */
final class CommerceStorefrontShareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function product(): Product
    {
        return Product::factory()->published()->withStock(12)->create([
            'name' => 'Arc ANC Headphones',
            'slug' => 'arc-anc-headphones',
            'sku' => 'ARC-ANC',
            'category_id' => null,
            'price_minor' => 18_500_00,
            'sale_price_minor' => null,
        ]);
    }

    /**
     * @param  list<array{platform: string, handle: string, url: string}>  $socials
     */
    private function withInstitution(array $socials = [], ?string $phone = null): void
    {
        InstitutionDetail::query()->updateOrCreate(
            ['id' => InstitutionDetail::PRIMARY_ID],
            ['name' => 'Aureon Group', 'short_name' => 'Aureon', 'primary_phone' => $phone, 'social_media' => $socials],
        );
        app(ResolvesInstitutionProfile::class)->forget();
    }

    private function show(Product $product): TestResponse
    {
        return $this->get(route('commerce.storefront.products.show', ['product' => $product->slug]));
    }

    public function test_product_page_shows_share_group_and_whatsapp_button(): void
    {
        $this->withInstitution([['platform' => 'WhatsApp', 'handle' => '', 'url' => 'https://wa.me/254712345678']]);

        $this->show($this->product())
            ->assertOk()
            ->assertSee('commerce-share__buttons', false)
            ->assertSee('Share on Facebook', false)
            ->assertSee('Share on X', false)
            ->assertSee('Copy product link', false)
            ->assertSee('Buy via WhatsApp')
            ->assertSee('https://wa.me/254712345678?text=', false);
    }

    public function test_whatsapp_button_falls_back_to_primary_phone(): void
    {
        $this->withInstitution([], '+254 700 000 000');

        $this->show($this->product())
            ->assertOk()
            ->assertSee('https://wa.me/254700000000?text=', false);
    }

    public function test_share_group_and_whatsapp_button_respect_their_config_flags(): void
    {
        config(['commerce.storefront.sharing.enabled' => false, 'commerce.storefront.whatsapp_order.enabled' => false]);
        $this->withInstitution([['platform' => 'WhatsApp', 'handle' => '', 'url' => 'https://wa.me/254712345678']]);

        $this->show($this->product())
            ->assertOk()
            ->assertDontSee('commerce-share__buttons', false)
            ->assertDontSee('Buy via WhatsApp');
    }

    public function test_whatsapp_button_hidden_when_no_number_resolves(): void
    {
        $this->withInstitution([['platform' => 'Facebook', 'handle' => 'aureon', 'url' => 'https://facebook.com/aureon']]);

        $this->show($this->product())
            ->assertOk()
            ->assertDontSee('Buy via WhatsApp')
            ->assertSee('commerce-share__buttons', false);
    }

    public function test_product_page_emits_product_json_ld_and_seo_meta(): void
    {
        $this->withInstitution([['platform' => 'X', 'handle' => '@aureonhq', 'url' => 'https://x.com/aureonhq']]);

        $this->show($this->product())
            ->assertOk()
            ->assertSee('<meta property="og:type" content="product">', false)
            ->assertSee('name="twitter:card" content="summary_large_image"', false)
            ->assertSee('name="twitter:site" content="@aureonhq"', false)
            ->assertSee('"@type":"Product"', false)
            ->assertSee('"priceCurrency":"KES"', false)
            ->assertSee('"price":"18500.00"', false)
            ->assertSee('https://schema.org/InStock', false)
            ->assertSee('"@type":"Organization"', false);
    }
}
