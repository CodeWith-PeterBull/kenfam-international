<?php

/**
 * Verify the Kenfam client homepage and disabled reference-module boundary.
 */
declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Exercise public branding, discovery links, SEO, and loader bootstrap. */
final class HomePageTest extends TestCase
{
    /** The root page presents Kenfam rather than the inherited Aureon showcase. */
    public function test_homepage_presents_kenfam_travel_identity_and_seo(): void
    {
        $this->withoutVite();

        config([
            'institution.defaults.founded_year' => 1994,
            'travel-tours.storefront.founded_year' => null,
        ]);

        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertSee('<title>Escorted tours and considered journeys | Kenfam International</title>', false)
            ->assertSee('<link rel="canonical" href="'.route('home').'">', false)
            ->assertSee('name="robots" content="index, follow, max-image-preview:large"', false)
            ->assertSee('type="application/ld+json"', false)
            ->assertSee('"@type":"TravelAgency"', false)
            ->assertSee('Travel farther. Return richer.')
            ->assertSee('Tours and Travel experts')
            ->assertSee('since 1994')
            ->assertSee('About Kenfam International')
            ->assertSee('href="'.route('travel-tours.storefront.catalog.index').'"', false)
            ->assertSee('class="travel-header__desktop d-none d-xl-block"', false)
            ->assertSee('class="travel-header__tablet d-none d-md-grid d-xl-none"', false)
            ->assertSee('class="travel-header__mobile d-grid d-md-none"', false)
            ->assertSee('href="'.route('login').'"', false)
            ->assertSee('href="'.route('register').'"', false)
            ->assertSee('id="themeController"', false)
            ->assertSee('https://wa.me/'.config('kenfam.whatsapp'), false)
            ->assertSee(asset(config('kenfam.brand.hero')), false)
            ->assertDontSee('Aureon CMS | Modular Business Operations Platform')
            ->assertDontSee('Property Management');
    }

    /** Critical loader rules precede markup and remain available without Vite. */
    public function test_homepage_emits_critical_loader_styles_before_loader_markup(): void
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

    /** Client brand assets are local and reference-module routes remain absent. */
    public function test_client_assets_exist_and_reference_modules_are_disabled(): void
    {
        foreach (config('kenfam.brand') as $asset) {
            $this->assertFileExists(public_path($asset));
        }

        $this->assertFalse((bool) config('commerce.enabled'));
        $this->assertFalse((bool) config('property-booking.enabled'));
        $this->assertFalse(Route::has('commerce.storefront.catalog.index'));
        $this->assertFalse(Route::has('property-booking.storefront.catalog.index'));
        $this->assertTrue(Route::has('travel-tours.storefront.catalog.index'));
    }
}
