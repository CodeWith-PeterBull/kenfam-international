<?php

/**
 * Verifies the live public tour search: filters apply as they change, stay in the URL, validate inline, and page correctly.
 */

declare(strict_types=1);

namespace Tests\Feature\TravelTours;

use App\Modules\TravelTours\Catalog\Enums\PublicationStatus;
use App\Modules\TravelTours\Catalog\Enums\TourType;
use App\Modules\TravelTours\Catalog\Models\Destination;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Catalog\Models\TourCategory;
use App\Modules\TravelTours\Storefront\Livewire\TourSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Every change to a filter re-runs the search inside the component and
 * never reloads the page; the query string carries the state so links and
 * refreshes reproduce it; invalid input is reported beside its field and
 * kept out of the query.
 */
final class TravelToursTourSearchTest extends TestCase
{
    use RefreshDatabase;

    /** Enable the module with a small page size so pagination is exercised. */
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('s', 32)), 'travel-tours.enabled' => true, 'travel-tours.storefront.page_size' => 2]);
    }

    /** The catalog page mounts the component and the classic query string still seeds it on first render. */
    public function test_catalog_page_mounts_the_live_search_from_the_query_string(): void
    {
        $this->publishedTour('Cairo and the Nile');
        $this->publishedTour('Cape Town and Garden Route');

        $this->withoutVite()->get(route('travel-tours.storefront.catalog.index', ['keyword' => 'Cairo']))
            ->assertOk()
            ->assertSeeLivewire(TourSearch::class)
            ->assertSee('name="keyword"', false)
            ->assertSee('wire:model.live.debounce.400ms="keyword"', false)
            ->assertSee('wire:offline', false)
            ->assertSee('Cairo and the Nile')
            ->assertDontSee('Cape Town and Garden Route');

        Livewire::withQueryParams(['destination' => 'egypt', 'maximum_duration_days' => '7'])
            ->test(TourSearch::class)
            ->assertSet('destination', 'egypt')
            ->assertSet('maximumDurationDays', '7');
    }

    /** Typing a keyword narrows the results without any redirect; clearing restores the whole catalogue. */
    public function test_keyword_filters_live_and_clear_restores_everything(): void
    {
        $this->publishedTour('Cairo and the Nile');
        $this->publishedTour('Cape Town and Garden Route');

        Livewire::test(TourSearch::class)
            ->assertSee('2 journeys')
            ->set('keyword', 'cairo')
            ->assertNoRedirect()
            ->assertSee('1 journey')
            ->assertSee('Cairo and the Nile')
            ->assertDontSee('Cape Town and Garden Route')
            ->assertSee('Clear filters')
            ->set('keyword', 'nowhere')
            ->assertSee('No tours match those filters.')
            ->call('clearFilters')
            ->assertSet('keyword', '')
            ->assertSee('2 journeys')
            ->assertDontSee('Clear filters');
    }

    /** Destination, category, type, duration, and travel date each narrow the results as they change. */
    public function test_each_filter_applies_as_it_changes(): void
    {
        $egypt = Destination::factory()->create(['name' => 'Egypt', 'slug' => 'egypt', 'status' => PublicationStatus::Published, 'is_active' => true, 'published_at' => now()->subDay()]);
        $heritage = TourCategory::factory()->create(['name' => 'Heritage', 'slug' => 'heritage', 'is_active' => true]);
        $cairo = $this->publishedTour('Cairo and the Nile', ['type' => TourType::Escorted, 'duration_days' => 8]);
        $cairo->destinations()->attach($egypt->getKey(), ['role' => 'primary', 'sequence' => 1, 'is_overnight' => true]);
        $cairo->categories()->attach($heritage->getKey(), ['is_primary' => true, 'sort_order' => 1]);
        $this->publishedTour('Cape Town and Garden Route', ['type' => TourType::Group, 'duration_days' => 6]);

        $component = Livewire::test(TourSearch::class)->assertSee('2 journeys');
        $component->set('destination', 'egypt')->assertSee('1 journey')->assertSee('Cairo and the Nile')->assertDontSee('Cape Town');
        $component->set('destination', '')->set('category', 'heritage')->assertSee('1 journey')->assertSee('Cairo and the Nile');
        $component->set('category', '')->set('type', 'group')->assertSee('1 journey')->assertSee('Cape Town')->assertDontSee('Cairo and the Nile');
        $component->set('type', '')->set('maximumDurationDays', '7')->assertSee('1 journey')->assertSee('Cape Town');
        $component->set('maximumDurationDays', '')->set('departureDate', now()->addYear()->toDateString())->assertSee('0 journeys');
    }

    /** Invalid input is reported beside its field and never narrows the search. */
    public function test_invalid_input_is_reported_and_kept_out_of_the_query(): void
    {
        $this->publishedTour('Cairo and the Nile');
        $this->publishedTour('Cape Town and Garden Route');

        Livewire::test(TourSearch::class)
            ->set('maximumDurationDays', '0')
            ->assertHasErrors(['maximumDurationDays' => 'min'])
            ->assertSee('Enter at least one day.')
            ->assertSee('2 journeys')
            ->set('maximumDurationDays', '')
            ->assertHasNoErrors()
            ->set('type', 'spaceflight')
            ->assertHasErrors(['type' => 'in'])
            ->assertSee('2 journeys')
            ->set('type', '')
            ->set('departureDate', 'not-a-date')
            ->assertHasErrors(['departureDate' => 'date'])
            ->assertSee('2 journeys')
            ->set('keyword', str_repeat('x', 121))
            ->assertHasErrors(['keyword' => 'max'])
            ->assertSee('Keep the search under 120 characters.');
    }

    /** Paging happens inside the component and every filter change returns to the first page. */
    public function test_pagination_stays_live_and_filters_reset_the_page(): void
    {
        foreach (['Alpha journey', 'Bravo journey', 'Charlie journey'] as $name) {
            $this->publishedTour($name);
        }

        $component = Livewire::test(TourSearch::class)
            ->assertSee('3 journeys')
            ->assertSee('Alpha journey')
            ->assertDontSee('Charlie journey')
            ->call('nextPage')
            ->assertSet('paginators.page', 2)
            ->assertSee('Charlie journey')
            ->assertDontSee('Alpha journey');

        $component->set('keyword', 'journey')->assertSet('paginators.page', 1)->assertSee('Alpha journey');
        $component->call('gotoPage', 2)->assertSet('paginators.page', 2)->call('clearFilters')->assertSet('paginators.page', 1);
    }

    /** Create one published tour visible to the public catalogue. */
    private function publishedTour(string $name, array $attributes = []): Tour
    {
        return Tour::factory()->create(['name' => $name, 'status' => PublicationStatus::Published, 'published_at' => now()->subDay(), 'short_description' => 'A public itinerary.'] + $attributes);
    }
}
