<?php

/**
 * Verifies the TravelTours public experience and opt-in demonstration data.
 */

declare(strict_types=1);

namespace Tests\Feature\TravelTours;

use App\Enums\ReportOrientation;
use App\Models\User;
use App\Modules\TravelTours\Bookings\Models\BookingParticipant;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\Catalog\Enums\PublicationStatus;
use App\Modules\TravelTours\Catalog\Models\Destination;
use App\Modules\TravelTours\Catalog\Models\ItineraryActivity;
use App\Modules\TravelTours\Catalog\Models\ItineraryDay;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Catalog\Models\TourCategory;
use App\Modules\TravelTours\Catalog\Models\TourContentItem;
use App\Modules\TravelTours\Catalog\Models\TourExtra;
use App\Modules\TravelTours\Catalog\Models\TourFaq;
use App\Modules\TravelTours\Database\Seeders\TravelToursDemoSeeder;
use App\Modules\TravelTours\Pricing\Models\ParticipantRate;
use App\Modules\TravelTours\Pricing\Models\TourRatePlan;
use App\Modules\TravelTours\Scheduling\Models\TourDeparture;
use App\Modules\TravelTours\Storefront\Services\BookingAccessUrlService;
use App\Modules\TravelTours\Support\MoneyFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Exercise public discovery, deterministic fixtures, and display invariants. */
final class TravelToursStorefrontTest extends TestCase
{
    use RefreshDatabase;

    /** Prepare isolated media and deterministic institution settings. */
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('w', 32)),
            'travel-tours.enabled' => true,
            'institution.defaults.founded_year' => 1994,
        ]);
    }

    /** The optional seeder creates one complete, repeatable public catalogue. */
    public function test_demo_catalogue_is_complete_idempotent_and_publicly_browsable(): void
    {
        $this->seed(TravelToursDemoSeeder::class);
        $this->seed(TravelToursDemoSeeder::class);

        $this->assertSame(13, Tour::query()->where('code', 'like', 'DEMO-%')->count());
        $this->assertSame(6, Tour::query()->where('is_featured', true)->count());
        $this->assertSame(13, Destination::query()->count());
        $this->assertSame(10, TourCategory::query()->count());
        $this->assertSame(26, TourDeparture::query()->count());
        $this->assertSame(13, TourRatePlan::query()->where('code', 'DEMO-STANDARD')->count());
        $this->assertSame(39, ParticipantRate::query()->count());
        $this->assertSame(52, ItineraryDay::query()->count());
        $this->assertSame(52, ItineraryActivity::query()->count());
        $this->assertSame(130, TourContentItem::query()->count());
        $this->assertSame(26, TourFaq::query()->count());
        $this->assertSame(13, TourExtra::query()->count());
        $this->assertEqualsCanonicalizing([
            'china',
            'egypt',
            'europe',
            'holy-land',
            'israel',
            'maasai-mara-kenya',
            'serengeti-tanzania',
            'southeast-asia',
            'south-africa',
            'thailand',
            'turkey',
            'united-arab-emirates',
            'zanzibar-tanzania',
        ], Destination::query()->pluck('slug')->all());
        $operators = User::query()->whereIn('email', [
            'travel.manager@example.test',
            'booking.agent@example.test',
            'tour.editor@example.test',
        ])->get()->keyBy('email');
        $this->assertCount(3, $operators);
        $this->assertTrue($operators['travel.manager@example.test']->hasRole('travel-manager'));
        $this->assertTrue($operators['booking.agent@example.test']->hasRole('travel-booking-agent'));
        $this->assertTrue($operators['tour.editor@example.test']->hasRole('tour-editor'));

        $tour = Tour::query()->where('code', 'DEMO-EGY-01')->firstOrFail();
        $this->assertNotNull($tour->getFirstMedia('tour_cover'));
        Storage::disk('public')->assertExists($tour->getFirstMedia('tour_cover')->getPathRelativeToRoot());
        foreach (['DEMO-KEN-01', 'DEMO-TZA-01', 'DEMO-ZNZ-01', 'DEMO-THA-01', 'DEMO-TUR-01', 'DEMO-CHN-01', 'DEMO-ISR-01'] as $code) {
            $seededTour = Tour::query()->where('code', $code)->firstOrFail();
            $this->assertNotNull($seededTour->getFirstMedia('tour_cover'), $code.' is missing its tour cover.');
            $this->assertNotNull($seededTour->destinations()->firstOrFail()->getFirstMedia('destination_cover'), $code.' is missing its destination cover.');
        }

        $this->withoutVite();
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Cairo and the Nile Heritage Journey')
            ->assertSee('Maasai Mara Safari Escape')
            ->assertSee('data-travel-hero-slider', false)
            ->assertSee('data-travel-hero-intro', false)
            ->assertSee('data-travel-hero-tour', false)
            ->assertSee('Create account');
        $this->get(route('travel-tours.storefront.catalog.index'))
            ->assertOk()
            ->assertSee('13 journeys')
            ->assertSee('KES 385,000.00')
            ->assertSee('Cairo and the Nile Heritage Journey');
        $this->get(route('travel-tours.storefront.catalog.index', ['destination' => 'egypt']))
            ->assertOk()
            ->assertSee('Cairo and the Nile Heritage Journey')
            ->assertDontSee('Cape Town and Garden Route');
        $this->get(route('travel-tours.storefront.tours.show', $tour->slug))
            ->assertOk()
            ->assertSee('Available departures')
            ->assertSee('Giza and the ancient plateau')
            ->assertSee('What is the booking process?')
            ->assertSee('Send inquiry');

        $draft = Tour::factory()->create(['status' => PublicationStatus::Draft]);
        $this->get(route('travel-tours.storefront.tours.show', $draft->slug))->assertNotFound();
    }

    /** The homepage keeps its informational slide first and orders a bounded published tour set by featured state. */
    public function test_homepage_hero_is_informational_first_featured_first_and_published_only(): void
    {
        $fallback = Tour::factory()->create([
            'name' => 'Available Journey',
            'slug' => 'available-journey',
            'status' => PublicationStatus::Published,
            'published_at' => now()->subDay(),
            'is_featured' => false,
            'sort_order' => 1,
        ]);
        $featured = Tour::factory()->create([
            'name' => 'Featured Journey',
            'slug' => 'featured-journey',
            'status' => PublicationStatus::Published,
            'published_at' => now()->subDay(),
            'is_featured' => true,
            'sort_order' => 9,
        ]);
        Tour::factory()->create([
            'name' => 'Private Draft Journey',
            'status' => PublicationStatus::Draft,
            'is_featured' => true,
        ]);

        $this->withoutVite();
        $content = $this->get(route('home'))
            ->assertOk()
            ->assertSeeInOrder(['data-travel-hero-intro', $featured->name, $fallback->name])
            ->assertDontSee('Private Draft Journey')
            ->assertSee('aria-label="Previous hero slide"', false)
            ->assertSee('data-travel-hero-pagination', false)
            ->getContent();

        $this->assertSame(2, substr_count($content, 'data-travel-hero-tour'));
    }

    /** Currency formatting must honor zero, two, and three-decimal exponents. */
    public function test_money_formatter_honors_persisted_currency_exponents(): void
    {
        $this->assertSame('JPY 12,500', MoneyFormatter::format(12500, 'jpy', 0));
        $this->assertSame('KES 12,500.75', MoneyFormatter::format(1250075, 'kes', 2));
        $this->assertSame('KWD 1,250.075', MoneyFormatter::format(1250075, 'kwd', 3));
        $this->assertSame('−KES 60,100.00', MoneyFormatter::format(-6010000, 'KES'), 'Discount and refund lines render with a leading minus sign.');
    }

    /** Signed booking links must consume the documented booking configuration. */
    public function test_booking_access_urls_use_configured_lifetimes(): void
    {
        CarbonImmutable::setTestNow('2026-09-14 10:00:00 UTC');
        config([
            'travel-tours.booking.confirmation_link_minutes' => 45,
            'travel-tours.booking.tracking_link_days' => 12,
            'travel-tours.booking.document_link_days' => 5,
        ]);
        $booking = TourBooking::factory()->create();
        $urls = app(BookingAccessUrlService::class);

        $this->assertSame(now()->addMinutes(45)->timestamp, $this->expiry($urls->confirmation($booking)));
        $this->assertSame(now()->addDays(12)->timestamp, $this->expiry($urls->tracking($booking)));
        $this->assertSame(now()->addDays(5)->timestamp, $this->expiry($urls->document($booking, ReportOrientation::Landscape)));
    }

    /** Signed customer pages expose only intended status content and reject tampering. */
    public function test_signed_booking_status_pages_are_private_and_theme_ready(): void
    {
        $booking = TourBooking::factory()->create([
            'booking_number' => 'KFI-DEMO-0001',
            'customer_name_snapshot' => 'Demo Traveler',
            'tour_name_snapshot' => 'Cairo and the Nile Heritage Journey',
            'currency' => 'JPY',
            'currency_exponent' => 0,
            'paid_minor' => 12500,
            'total_minor' => 25000,
        ]);
        BookingParticipant::factory()->create(['booking_id' => $booking->id]);
        $this->withoutVite();
        $confirmation = app(BookingAccessUrlService::class)->confirmation($booking);

        $response = $this->get($confirmation);
        foreach (['private', 'no-store', 'max-age=0'] as $directive) {
            $this->assertStringContainsString($directive, (string) $response->headers->get('Cache-Control'));
        }

        $response
            ->assertOk()
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertSee('name="robots" content="noindex, nofollow, noarchive"', false)
            ->assertSee('KFI-DEMO-0001')
            ->assertSee('Cairo and the Nile Heritage Journey')
            ->assertSee('JPY 12,500 of JPY 25,000')
            ->assertSee('data-theme-controller', false)
            ->assertDontSee('identity_number');

        $document = app(BookingAccessUrlService::class)->document($booking, ReportOrientation::Portrait);
        $documentResponse = $this->get($document)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        foreach (['private', 'no-store', 'max-age=0'] as $directive) {
            $this->assertStringContainsString($directive, (string) $documentResponse->headers->get('Cache-Control'));
        }
        $documentResponse
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->get($confirmation.'&booking=INVALID')->assertForbidden();
    }

    /** Authenticated visitors receive a workspace action without a sign-up prompt. */
    public function test_authenticated_header_switches_from_signup_to_workspace(): void
    {
        $this->withoutVite();

        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Workspace')
            ->assertDontSee('Create account')
            ->assertDontSee('Sign up');
    }

    /** Extract the signed URL expiry query value for deterministic assertions. */
    private function expiry(string $url): int
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return (int) ($query['expires'] ?? 0);
    }
}
