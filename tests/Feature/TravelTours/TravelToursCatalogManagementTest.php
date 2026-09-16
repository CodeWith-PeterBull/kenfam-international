<?php

/**
 * Verifies the K2B category, destination, media, and Livewire workflows.
 */

declare(strict_types=1);

namespace Tests\Feature\TravelTours;

use App\Enums\UserType;
use App\Models\User;
use App\Modules\TravelTours\Catalog\Data\DestinationData;
use App\Modules\TravelTours\Catalog\Data\TourCategoryData;
use App\Modules\TravelTours\Catalog\Enums\DestinationType;
use App\Modules\TravelTours\Catalog\Enums\PublicationStatus;
use App\Modules\TravelTours\Catalog\Exceptions\CatalogException;
use App\Modules\TravelTours\Catalog\Exceptions\HierarchyCycle;
use App\Modules\TravelTours\Catalog\Exceptions\PublicationBlocked;
use App\Modules\TravelTours\Catalog\Livewire\Admin\DestinationManager;
use App\Modules\TravelTours\Catalog\Livewire\Admin\TourCategoryManager;
use App\Modules\TravelTours\Catalog\Models\Destination;
use App\Modules\TravelTours\Catalog\Services\CatalogMediaService;
use App\Modules\TravelTours\Catalog\Services\DestinationService;
use App\Modules\TravelTours\Catalog\Services\TourCategoryService;
use App\Modules\TravelTours\Database\Seeders\TravelToursAccessSeeder;
use App\Modules\TravelTours\Support\TravelToursRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Exercise K2B writes at both the service and presentation boundaries. */
final class TravelToursCatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    /** Seed deterministic access roles and create one system administrator. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TravelToursAccessSeeder::class);
        $this->administrator = User::factory()->create([
            'is_active' => true,
            'user_type' => UserType::SystemAdministrator,
        ]);
    }

    /** Preserve an acyclic, active category hierarchy across every write path. */
    public function test_category_service_rejects_cycles_and_dependent_deactivation(): void
    {
        $service = app(TourCategoryService::class);
        $root = $service->create(new TourCategoryData(name: 'Escorted journeys'));
        $child = $service->create(new TourCategoryData(name: 'Heritage journeys', parentId: $root->id));

        try {
            $service->update($root, new TourCategoryData(name: $root->name, slug: $root->slug, parentId: $child->id));
            $this->fail('A recursive category parent must be rejected.');
        } catch (HierarchyCycle $exception) {
            $this->assertStringContainsString('cycle', $exception->getMessage());
        }

        try {
            $service->update($root, new TourCategoryData(name: $root->name, slug: $root->slug, isActive: false));
            $this->fail('A category with an active child must remain active.');
        } catch (CatalogException $exception) {
            $this->assertStringContainsString('child categories', $exception->getMessage());
        }

        $this->assertTrue($root->refresh()->is_active);
        $this->assertSame($root->id, $child->refresh()->parent_id);
    }

    /** Enforce destination levels, geography, and the K2 editorial-state boundary. */
    public function test_destination_service_enforces_hierarchy_geography_and_editorial_states(): void
    {
        $service = app(DestinationService::class);
        $continent = $service->create(new DestinationData(type: DestinationType::Continent, name: 'Africa'), $this->administrator);
        $country = $service->create(new DestinationData(type: DestinationType::Country, name: 'Kenya', parentId: $continent->id, countryCode: 'ke', timezone: 'Africa/Nairobi'), $this->administrator);
        $city = $service->create(new DestinationData(type: DestinationType::City, name: 'Nairobi', parentId: $country->id, countryCode: 'KE', latitude: '-1.286389', longitude: '36.817223', timezone: 'Africa/Nairobi'), $this->administrator);

        $this->assertSame('KE', $country->country_code);
        $this->assertSame($country->id, $city->parent_id);

        try {
            $service->create(new DestinationData(type: DestinationType::Country, name: 'Invalid child', parentId: $city->id, countryCode: 'KE'), $this->administrator);
            $this->fail('A country may not be nested below a city.');
        } catch (CatalogException $exception) {
            $this->assertStringContainsString('broader geographic level', $exception->getMessage());
        }

        try {
            $service->create(new DestinationData(type: DestinationType::City, name: 'Invalid timezone', countryCode: 'KE', timezone: 'Africa/Invalid'), $this->administrator);
            $this->fail('An invalid IANA timezone must be rejected.');
        } catch (CatalogException $exception) {
            $this->assertStringContainsString('IANA timezone', $exception->getMessage());
        }

        try {
            $service->update($city, new DestinationData(type: DestinationType::City, name: $city->name, slug: $city->slug, parentId: $country->id, countryCode: 'KE', timezone: 'Africa/Nairobi', status: PublicationStatus::Published), $this->administrator);
            $this->fail('K2 metadata editing must not publish a destination.');
        } catch (PublicationBlocked $exception) {
            $this->assertStringContainsString('publication workflow', $exception->getMessage());
        }

        $this->assertSame(PublicationStatus::Draft, $city->refresh()->status);
    }

    /** Attach accessible media, preserve ownership, and enforce gallery limits and ordering. */
    public function test_destination_media_service_owns_files_metadata_limits_and_order(): void
    {
        Storage::fake('public');
        config()->set('travel-tours.media.destination_gallery_limit', 2);
        $destination = Destination::factory()->create();
        $other = Destination::factory()->create();
        $service = app(CatalogMediaService::class);

        $destination = $service->replaceDestinationCover($destination, UploadedFile::fake()->image('cover.jpg', 800, 600), 'Nairobi skyline', 'City at dusk');
        $destination = $service->addDestinationGalleryImage($destination, UploadedFile::fake()->image('one.jpg', 800, 600), 'Museum entrance', null);
        $destination = $service->addDestinationGalleryImage($destination, UploadedFile::fake()->image('two.webp', 800, 600), 'National park view', 'Wildlife near Nairobi');
        $gallery = $destination->getMedia('destination_gallery');
        $this->assertCount(2, $gallery);

        $destination = $service->reorderDestinationGallery($destination, [$gallery[1]->id, $gallery[0]->id]);
        $this->assertSame($gallery[1]->id, $destination->getMedia('destination_gallery')->first()->id);

        $destination = $service->updateDestinationMedia($destination, $gallery[0]->id, 'Nairobi museum facade', 'Historic collection');
        $this->assertSame('Nairobi museum facade', $destination->media->firstWhere('id', $gallery[0]->id)?->getCustomProperty('alt_text'));

        try {
            $service->addDestinationGalleryImage($destination, UploadedFile::fake()->image('three.jpg'), 'Third image', null);
            $this->fail('The configured destination gallery limit must be enforced.');
        } catch (CatalogException $exception) {
            $this->assertStringContainsString('no more than 2', $exception->getMessage());
        }

        try {
            $service->removeDestinationGalleryImage($other, $gallery[0]->id);
            $this->fail('A destination must not mutate another destination\'s media.');
        } catch (CatalogException $exception) {
            $this->assertStringContainsString('unavailable', $exception->getMessage());
        }
    }

    /** Exercise completed routes and CRUD while preserving read-only role behavior. */
    public function test_catalog_managers_render_and_authorize_livewire_writes(): void
    {
        $this->withoutVite();

        $this->actingAs($this->administrator)->get(route('travel-tours.admin.catalog.categories'))
            ->assertOk()->assertSeeLivewire(TourCategoryManager::class);
        $this->actingAs($this->administrator)->get(route('travel-tours.admin.catalog.destinations'))
            ->assertOk()->assertSeeLivewire(DestinationManager::class);

        Livewire::actingAs($this->administrator)
            ->test(TourCategoryManager::class)
            ->call('openCreate')
            ->set('form.name', 'Family holidays')
            ->set('form.iconKey', 'users-group')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Tour category created.');

        Livewire::actingAs($this->administrator)
            ->test(DestinationManager::class)
            ->call('openCreate')
            ->set('form.type', DestinationType::Country->value)
            ->set('form.name', 'Egypt')
            ->set('form.countryCode', 'EG')
            ->set('form.timezone', 'Africa/Cairo')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Destination created as an editorial item.');

        $viewer = User::factory()->create(['is_active' => true]);
        $viewer->assignRole(TravelToursRole::BOOKING_AGENT);
        Livewire::actingAs($viewer)->test(TourCategoryManager::class)->call('openCreate')->assertForbidden();
        Livewire::actingAs($viewer)->test(DestinationManager::class)->call('openCreate')->assertForbidden();

        $this->assertDatabaseHas('travel_tour_categories', ['slug' => 'family-holidays']);
        $this->assertDatabaseHas('travel_destinations', ['slug' => 'egypt', 'country_code' => 'EG']);
    }
}
