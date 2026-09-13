<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class HomePageTest extends TestCase
{
    public function test_homepage_presents_ready_modules_with_public_links_and_seo(): void
    {
        $this->withoutVite();
        $favicon = asset('aureon/assets/brand/favicon.png').'?v='.rawurlencode((string) config('aureon-home.asset_version'));

        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertSee('<title>Aureon CMS | Modular Business Operations Platform</title>', false)
            ->assertSee('<link rel="canonical" href="'.route('home').'">', false)
            ->assertSee('name="robots" content="index, follow, max-image-preview:large"', false)
            ->assertSee('rel="icon" href="'.$favicon.'" type="image/png" sizes="64x64"', false)
            ->assertSee('rel="shortcut icon" href="'.$favicon.'" type="image/png"', false)
            ->assertSee('type="application/ld+json"', false)
            ->assertSee('Aureon CMS')
            ->assertSee('Commerce')
            ->assertSee('Property Management')
            ->assertSee('Ready and available')
            ->assertSee('href="'.route('commerce.storefront.catalog.index').'"', false)
            ->assertSee('href="'.route('property-booking.storefront.catalog.index').'"', false)
            ->assertSee('aureon/assets/images/home/modules/commerce-storefront.webp', false)
            ->assertSee('aureon/assets/images/home/modules/commerce-dashboard.webp', false)
            ->assertSee('aureon/assets/images/home/modules/commerce-cashier-sales.webp', false)
            ->assertSee('aureon/assets/images/home/modules/property-booking-storefront.webp', false)
            ->assertSee('aureon/assets/images/home/modules/property-booking-dashboard.webp', false)
            ->assertSee('aureon/assets/images/home/modules/property-booking-pob.webp', false)
            ->assertSee('data-gallery-previous', false)
            ->assertSee('data-gallery-next', false)
            ->assertSee('data-gallery-expand', false)
            ->assertSee('data-gallery-lightbox', false)
            ->assertSee('data-lightbox-image', false)
            ->assertSee('data-lightbox-position', false)
            ->assertSee('data-aureon-contact-card', false)
            ->assertSee('data-aureon-contact-trigger', false)
            ->assertDontSee('Laravel has an incredibly rich ecosystem');
    }

    public function test_homepage_emits_critical_loader_styles_before_the_loader_markup(): void
    {
        $this->withoutVite();

        $content = $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-aureon-loader-critical', false)
            ->assertSee('div#global-loader.aureon-loader', false)
            ->assertSee('html[data-theme="dark"] div#global-loader.aureon-loader', false)
            ->assertSee('data-page-loader', false)
            ->getContent();

        $this->assertLessThan(
            strpos($content, 'data-page-loader'),
            strpos($content, 'data-aureon-loader-critical'),
        );
    }

    public function test_each_module_exposes_a_role_labelled_ten_view_showcase(): void
    {
        $this->withoutVite();

        $modules = collect(config('aureon-home.modules'));
        $this->assertNotEmpty($modules);

        $modules->each(function (array $module): void {
            $this->assertGreaterThanOrEqual(10, count($module['screenshots']));

            foreach ($module['screenshots'] as $screenshot) {
                $this->assertNotEmpty($screenshot['src']);
                $this->assertNotEmpty($screenshot['alt']);
                $this->assertNotEmpty($screenshot['label']);
                $this->assertNotEmpty($screenshot['role']);

                $asset = resource_path($screenshot['src']);
                $this->assertFileExists($asset);
                $this->assertLessThanOrEqual(150 * 1024, filesize($asset));

                $dimensions = getimagesize($asset);
                $this->assertSame([1280, 800], [$dimensions[0], $dimensions[1]]);
                $this->assertSame('image/webp', $dimensions['mime']);
            }
        });

        $response = $this->get(route('home'))->assertOk();
        $response
            ->assertSee('Public experience')
            ->assertSee('Administrator')
            ->assertSee('Cashier')
            ->assertSee('Property manager')
            ->assertSee('Receptionist');
    }

    public function test_homepage_contact_card_exposes_all_provider_channels(): void
    {
        $this->withoutVite();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('META SOFTWARE DEVELOPERS')
            ->assertSee('Your bridge between software and utility.')
            ->assertSee('mailto:info@metasoftdevs.com?subject=Aureon%20CMS%20implementation%20quote', false)
            ->assertSee('mailto:support@metasoftdevs.com?subject=Aureon%20CMS%20implementation%20quote', false)
            ->assertSee('tel:+254722809376', false)
            ->assertSee('tel:+254794035976', false)
            ->assertSee('https://wa.me/254722809376', false)
            ->assertSee('https://wa.me/254794035976', false)
            ->assertSee('https://www.metasoftdevs.com', false)
            ->assertSee('Nairobi, Kenya');
    }

    public function test_disabled_module_remains_described_without_an_active_public_link(): void
    {
        $this->withoutVite();
        config()->set('aureon-home.modules.0.enabled', false);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Commerce')
            ->assertSee('Available for adoption')
            ->assertDontSee('href="'.route('commerce.storefront.catalog.index').'"', false)
            ->assertSee('href="'.route('property-booking.storefront.catalog.index').'"', false);
    }
}
