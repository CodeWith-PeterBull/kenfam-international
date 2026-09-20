<?php

/**
 * Verifies footer social marks, tour-page sharing, WhatsApp booking, and pre-share metadata.
 */

declare(strict_types=1);

namespace Tests\Feature\TravelTours;

use App\Contracts\ResolvesInstitutionProfile;
use App\Models\InstitutionDetail;
use App\Models\User;
use App\Modules\TravelTours\Catalog\Enums\PublicationStatus;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Database\Seeders\TravelToursAccessSeeder;
use App\Modules\TravelTours\Pricing\Models\ParticipantRate;
use App\Modules\TravelTours\Pricing\Models\TourRatePlan;
use App\Modules\TravelTours\Scheduling\Models\TourDeparture;
use App\Modules\TravelTours\Support\TravelToursRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Exercise the social surfaces the storefront derives from Institution Details. */
final class TravelToursStorefrontShareTest extends TestCase
{
    use RefreshDatabase;

    /** Prepare isolated media and an enabled module. */
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        // The workstation .env may carry an institution phone; the tests decide what resolves.
        config(['travel-tours.enabled' => true, 'institution.defaults.primary_phone' => null, 'institution.defaults.social_media' => []]);
    }

    /**
     * @param  list<array{platform: string, handle: string, url: string}>  $socials
     */
    private function withInstitution(array $socials = [], ?string $phone = null): void
    {
        InstitutionDetail::query()->updateOrCreate(
            ['id' => InstitutionDetail::PRIMARY_ID],
            ['name' => 'Kenfam International', 'short_name' => 'Kenfam', 'primary_phone' => $phone, 'social_media' => $socials],
        );
        app(ResolvesInstitutionProfile::class)->forget();
    }

    /** A published tour with a public adult rate and one bookable departure. */
    private function tour(bool $withDeparture = true): Tour
    {
        $tour = Tour::factory()->create([
            'name' => 'Cairo and the Nile Heritage Journey',
            'slug' => 'cairo-and-the-nile',
            'status' => PublicationStatus::Published,
            'published_at' => now()->subDay(),
        ]);
        $plan = TourRatePlan::factory()->create(['tour_id' => $tour->getKey(), 'is_default' => true, 'currency' => 'KES']);
        ParticipantRate::factory()->create(['rate_plan_id' => $plan->getKey(), 'amount_minor' => 385_000_00]);
        if ($withDeparture) {
            TourDeparture::factory()->create(['tour_id' => $tour->getKey(), 'rate_plan_id' => $plan->getKey()]);
        }

        return $tour;
    }

    private function show(Tour $tour): TestResponse
    {
        return $this->get(route('travel-tours.storefront.tours.show', $tour->slug));
    }

    /** The footer renders one round mark per recorded profile, in order, and nothing when none are recorded. */
    public function test_footer_renders_social_marks_from_institution_details(): void
    {
        $this->withInstitution([
            ['platform' => 'Facebook', 'handle' => 'kenfam', 'url' => 'https://facebook.com/kenfam'],
            ['platform' => 'Instagram', 'handle' => '@kenfam', 'url' => 'https://instagram.com/kenfam'],
            ['platform' => 'TikTok', 'handle' => '@kenfam', 'url' => 'https://tiktok.com/@kenfam'],
            ['platform' => 'X', 'handle' => '@kenfam', 'url' => 'https://x.com/kenfam'],
            ['platform' => 'WhatsApp', 'handle' => '', 'url' => 'https://wa.me/254712345678'],
        ]);

        $this->get(route('travel-tours.storefront.catalog.index'))
            ->assertOk()
            ->assertSee('class="travel-footer__social"', false)
            ->assertSeeInOrder([
                'aria-label="Kenfam on Facebook"',
                'aria-label="Kenfam on Instagram"',
                'aria-label="Kenfam on TikTok"',
                'aria-label="Kenfam on X"',
            ], false)
            ->assertSee('href="https://tiktok.com/@kenfam" target="_blank" rel="me noopener noreferrer"', false)
            ->assertDontSee('aria-label="Kenfam on WhatsApp"', false)
            ->assertSee('name="twitter:site" content="@kenfam"', false)
            ->assertSee('"sameAs":["https://facebook.com/kenfam"', false);

        $this->withInstitution([]);

        $this->get(route('travel-tours.storefront.catalog.index'))
            ->assertOk()
            ->assertDontSee('travel-footer__social', false)
            ->assertDontSee('twitter:site', false);
    }

    /** The tour page carries the share group, the WhatsApp booking link, and the pre-share metadata. */
    public function test_tour_page_shows_share_group_whatsapp_booking_and_seo_context(): void
    {
        $this->withInstitution([
            ['platform' => 'WhatsApp', 'handle' => '', 'url' => 'https://wa.me/254712345678'],
            ['platform' => 'X', 'handle' => '@kenfam', 'url' => 'https://x.com/kenfam'],
        ]);
        $tour = $this->tour();
        $tourUrl = route('travel-tours.storefront.tours.show', $tour->slug);

        $this->show($tour)
            ->assertOk()
            ->assertSee('class="travel-share"', false)
            ->assertSee('Share on Facebook', false)
            ->assertSee('Share on X', false)
            ->assertSee('Share on WhatsApp', false)
            ->assertSee('Copy link to share on TikTok', false)
            ->assertSee('Copy link to share on Instagram', false)
            ->assertSee('Copy tour link', false)
            ->assertSee('href="https://www.facebook.com/sharer/sharer.php?u='.rawurlencode($tourUrl).'"', false)
            ->assertSee('Book via WhatsApp')
            ->assertSee('Check departures')
            ->assertSee('href="https://wa.me/254712345678?text='.rawurlencode("Hello Kenfam, I would like to book:\n{$tour->name}\nFrom: KES 385,000.00\n{$tourUrl}").'"', false)
            ->assertSee('<meta property="og:type" content="product">', false)
            ->assertSee('<meta property="product:price:amount" content="385000.00">', false)
            ->assertSee('<meta property="product:price:currency" content="KES">', false)
            ->assertSee('<meta property="og:availability" content="instock">', false)
            ->assertSee('name="twitter:site" content="@kenfam"', false)
            ->assertSee('"@type":"TouristTrip"', false)
            ->assertSee('"sameAs":["https://wa.me/254712345678","https://x.com/kenfam"]', false)
            ->assertSee('"offers":{"@type":"Offer","url":"'.$tourUrl.'","priceCurrency":"KES","price":"385000.00","availability":"https://schema.org/InStock"', false);
    }

    /** Without a bookable departure the hero leads to the inquiry form and no availability is claimed. */
    public function test_tour_page_without_departures_leads_to_the_inquiry_form(): void
    {
        $this->withInstitution([], '+254 700 000 000');

        $this->show($this->tour(withDeparture: false))
            ->assertOk()
            ->assertSee('href="#travel-inquiry"', false)
            ->assertSee('Plan this journey')
            ->assertDontSee('og:availability', false)
            ->assertDontSee('schema.org/InStock', false)
            ->assertSee('https://wa.me/254700000000?text=', false);
    }

    /** Both surfaces honour their configuration flags, and the platform allow-list prunes the group. */
    public function test_share_group_and_whatsapp_booking_respect_configuration(): void
    {
        $this->withInstitution([['platform' => 'WhatsApp', 'handle' => '', 'url' => 'https://wa.me/254712345678']]);
        $tour = $this->tour();

        config(['travel-tours.storefront.sharing.platforms' => ['facebook', 'copy']]);
        $this->show($tour)
            ->assertOk()
            ->assertSee('Share on Facebook', false)
            ->assertSee('Copy tour link', false)
            ->assertDontSee('Share on X', false)
            ->assertDontSee('Copy link to share on TikTok', false);

        config(['travel-tours.storefront.sharing.enabled' => false, 'travel-tours.storefront.whatsapp_booking.enabled' => false]);
        $this->show($tour)
            ->assertOk()
            ->assertDontSee('class="travel-share"', false)
            ->assertDontSee('Book via WhatsApp')
            ->assertSee('Ask on WhatsApp');
    }

    /** No number at all hides the booking button while sharing stays available. */
    public function test_whatsapp_booking_hidden_when_no_number_resolves(): void
    {
        $this->withInstitution([['platform' => 'Facebook', 'handle' => 'kenfam', 'url' => 'https://facebook.com/kenfam']]);

        $this->show($this->tour())
            ->assertOk()
            ->assertDontSee('Book via WhatsApp')
            ->assertSee('class="travel-share"', false);
    }

    /** An editorial preview is noindex and offers neither sharing nor WhatsApp booking. */
    public function test_editorial_preview_offers_no_share_or_booking_actions(): void
    {
        $this->withInstitution([['platform' => 'WhatsApp', 'handle' => '', 'url' => 'https://wa.me/254712345678']]);
        $this->seed(TravelToursAccessSeeder::class);
        $tour = Tour::factory()->create(['status' => PublicationStatus::Draft]);
        $manager = User::factory()->create(['is_active' => true]);
        $manager->assignRole(TravelToursRole::MANAGER);

        $this->actingAs($manager)
            ->get(route('travel-tours.admin.catalog.tours.preview', $tour))
            ->assertOk()
            ->assertSee('noindex, nofollow, noarchive', false)
            ->assertDontSee('class="travel-share"', false)
            ->assertDontSee('Book via WhatsApp');
    }
}
