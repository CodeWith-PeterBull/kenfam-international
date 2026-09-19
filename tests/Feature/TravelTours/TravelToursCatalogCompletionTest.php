<?php

/** Verifies K2E media, simple pricing, readiness, and publication behavior. */

declare(strict_types=1);

namespace Tests\Feature\TravelTours;

use App\Models\User;
use App\Modules\TravelTours\Catalog\Data\ItineraryDayData;
use App\Modules\TravelTours\Catalog\Enums\PublicationStatus;
use App\Modules\TravelTours\Catalog\Exceptions\CatalogException;
use App\Modules\TravelTours\Catalog\Exceptions\PublicationBlocked;
use App\Modules\TravelTours\Catalog\Livewire\Admin\DestinationManager;
use App\Modules\TravelTours\Catalog\Livewire\Admin\TourEditor;
use App\Modules\TravelTours\Catalog\Livewire\Admin\TourMediaEditor;
use App\Modules\TravelTours\Catalog\Livewire\Admin\TourPublicationEditor;
use App\Modules\TravelTours\Catalog\Models\Destination;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Catalog\Models\TourCategory;
use App\Modules\TravelTours\Catalog\Services\CatalogMediaService;
use App\Modules\TravelTours\Catalog\Services\TourItineraryService;
use App\Modules\TravelTours\Catalog\Services\TourPublicationService;
use App\Modules\TravelTours\Catalog\Services\TourReadinessService;
use App\Modules\TravelTours\Database\Seeders\TravelToursAccessSeeder;
use App\Modules\TravelTours\Pricing\Data\TourBasePriceData;
use App\Modules\TravelTours\Pricing\Livewire\Admin\TourBasePriceEditor;
use App\Modules\TravelTours\Pricing\Services\TourBasePriceService;
use App\Modules\TravelTours\Support\TravelToursRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Exercise the K2E completion path without invoking K3 departures or offers. */
final class TravelToursCatalogCompletionTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $editor;

    /** Seed only access roles and isolated operator users. */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TravelToursAccessSeeder::class);
        $this->manager = User::factory()->create(['is_active' => true]);
        $this->manager->assignRole(TravelToursRole::MANAGER);
        $this->editor = User::factory()->create(['is_active' => true]);
        $this->editor->assignRole(TravelToursRole::TOUR_EDITOR);
    }

    /** Keep exact cents and prevent unsupported duplicate or dated rates. */
    public function test_default_base_prices_are_exact_and_tour_owned(): void
    {
        $tour = Tour::factory()->create();
        $service = app(TourBasePriceService::class);
        $plan = $service->save($tour, new TourBasePriceData('Standard', 'KES', 12_345_67, 6_789_01, 0));
        $this->assertSame('KES', $plan->currency);
        $this->assertSame([12_345_67, 6_789_01, 0], $plan->participantRates->sortBy(fn ($rate) => ['adult' => 0, 'child' => 1, 'infant' => 2][$rate->participant_type->value])->pluck('amount_minor')->all());

        $again = $service->save($tour, new TourBasePriceData('Standard', 'KES', 12_345_68, null, 0));
        $this->assertSame($plan->id, $again->id);
        $this->assertFalse($again->participantRates->first(fn ($rate) => $rate->participant_type->value === 'child')->is_active);
        $this->assertSame(3, $again->participantRates->count());
    }

    /** Allow catalog editors to inspect but not mutate price, publish, or bypass permissions. */
    public function test_base_price_editor_enforces_pricing_permission(): void
    {
        $tour = Tour::factory()->create();
        Livewire::actingAs($this->editor)->test(TourEditor::class, ['tourId' => $tour->id])
            ->call('switchTab', 'pricing')->assertSeeLivewire(TourBasePriceEditor::class);
        Livewire::actingAs($this->editor)->test(TourBasePriceEditor::class, ['tourId' => $tour->id])
            ->set('form.adult', '1200.50')->call('save')->assertForbidden();
        Livewire::actingAs($this->manager)->test(TourBasePriceEditor::class, ['tourId' => $tour->id])
            ->set('form.adult', '1200.50')->call('save')->assertHasNoErrors();
        $this->assertDatabaseHas('travel_participant_rates', ['amount_minor' => 120050]);
    }

    /** Enforce gallery ownership, limits, alt text, and public attachment scope. */
    public function test_tour_media_service_manages_images_and_public_documents(): void
    {
        Storage::fake('public');
        config()->set('travel-tours.media.tour_gallery_limit', 2);
        $tour = Tour::factory()->create();
        $other = Tour::factory()->create();
        $media = app(CatalogMediaService::class);
        $tour = $media->replaceTourCover($tour, UploadedFile::fake()->image('cover.jpg'), 'Kenya tour cover', null);
        $tour = $media->addTourGalleryImage($tour, UploadedFile::fake()->image('first.jpg'), 'First view', 'Day one');
        $tour = $media->addTourGalleryImage($tour, UploadedFile::fake()->image('second.png'), 'Second view', null);
        $images = $tour->getMedia('tour_gallery');
        $tour = $media->reorderTourGallery($tour, [$images[1]->id, $images[0]->id]);
        $this->assertSame($images[1]->id, $tour->getMedia('tour_gallery')->first()->id);
        $tour = $media->addTourDocument($tour, UploadedFile::fake()->createWithContent('guide.pdf', "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF"), 'Travel guide', 'What to pack');
        $this->assertCount(1, $tour->getMedia('tour_documents'));
        $this->assertSame('Travel guide', $tour->getMedia('tour_documents')->first()->getCustomProperty('title'));

        try {
            $media->removeTourGalleryImage($other, $images[0]->id);
            $this->fail('Another tour must not remove this image.');
        } catch (CatalogException $exception) {
            $this->assertStringContainsString('unavailable', $exception->getMessage());
        }
        $this->expectException(CatalogException::class);
        $media->addTourGalleryImage($tour, UploadedFile::fake()->image('third.jpg'), 'Third view', null);
    }

    /** Open the gallery form beside its toolbar and retain media ownership. */
    public function test_tour_media_editor_opens_contextual_upload_dialog(): void
    {
        $tour = Tour::factory()->create();
        Livewire::actingAs($this->editor)->test(TourMediaEditor::class, ['tourId' => $tour->id])
            ->call('openGalleryDialog')->assertSee('tour-gallery-add-heading')
            ->assertSee('tour-gallery-upload')
            ->call('closeGalleryDialog')->assertDontSee('tour-gallery-upload');
    }

    /** Suggest filename text for both uploads while retaining editor overrides. */
    public function test_tour_uploads_preview_and_accept_editable_filename_alt_text(): void
    {
        Storage::fake('public');
        $tour = Tour::factory()->create();
        Livewire::actingAs($this->editor)->test(TourMediaEditor::class, ['tourId' => $tour->id])
            ->set('coverUpload', UploadedFile::fake()->image('nile-river-sunset.jpg'))
            ->assertSet('coverAltText', 'Nile River Sunset')
            ->assertSee('travel-admin-upload-preview')
            ->set('coverAltText', 'Sunset over the Nile River')
            ->call('replaceCover')->assertHasNoErrors();
        $this->assertSame('Sunset over the Nile River', $tour->fresh()->getFirstMedia('tour_cover')->getCustomProperty('alt_text'));

        Livewire::actingAs($this->editor)->test(TourMediaEditor::class, ['tourId' => $tour->id])
            ->call('openGalleryDialog')
            ->set('galleryUpload', UploadedFile::fake()->image('morning-market.png'))
            ->assertSet('galleryAltText', 'Morning Market')
            ->assertSee('travel-admin-upload-preview')
            ->call('addGalleryImage')->assertHasNoErrors();
        $this->assertSame('Morning Market', $tour->fresh()->getFirstMedia('tour_gallery')->getCustomProperty('alt_text'));
    }

    /** Destination media uses the same editable suggestion and temporary preview. */
    public function test_destination_uploads_preview_and_accept_editable_filename_alt_text(): void
    {
        Storage::fake('public');
        $destination = Destination::factory()->create();
        Livewire::actingAs($this->editor)->test(DestinationManager::class)
            ->call('openDetails', $destination->id)
            ->set('coverUpload', UploadedFile::fake()->image('old-town-square.webp'))
            ->assertSet('coverAltText', 'Old Town Square')
            ->assertSee('travel-admin-upload-preview')
            ->call('replaceCover')->assertHasNoErrors()
            ->set('galleryUpload', UploadedFile::fake()->image('coastal-walk.jpg'))
            ->assertSet('galleryAltText', 'Coastal Walk')
            ->set('galleryAltText', 'Walking path beside the coast')
            ->call('addGalleryImage')->assertHasNoErrors();
        $this->assertSame('Old Town Square', $destination->fresh()->getFirstMedia('destination_cover')->getCustomProperty('alt_text'));
        $this->assertSame('Walking path beside the coast', $destination->fresh()->getFirstMedia('destination_gallery')->getCustomProperty('alt_text'));
    }

    /** Expose all implemented catalog filters and show owned public assets. */
    public function test_public_catalog_filters_and_tour_detail_media_render(): void
    {
        $this->withoutVite();
        Storage::fake('public');
        $tour = Tour::factory()->create([
            'status' => PublicationStatus::Published, 'published_at' => now()->subDay(),
            'short_description' => 'A public itinerary', 'description' => 'Public tour details.',
        ]);
        $category = TourCategory::factory()->create(['is_active' => true]);
        $tour->categories()->attach($category->id, ['is_primary' => true, 'sort_order' => 1]);
        $media = app(CatalogMediaService::class);
        $tour = $media->addTourGalleryImage($tour, UploadedFile::fake()->image('gallery.jpg'), 'Savannah landscape', 'Evening arrival');
        $tour = $media->addTourDocument($tour, UploadedFile::fake()->createWithContent('guide.pdf', "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF"), 'Travel guide', null);
        $this->get(route('travel-tours.storefront.catalog.index'))
            ->assertOk()->assertSee('name="category"', false)->assertSee('name="type"', false)
            ->assertSee('name="maximum_duration_days"', false)->assertSee($tour->name);
        $this->get(route('travel-tours.storefront.catalog.index', ['category' => $category->slug]))
            ->assertOk()->assertSee($tour->name);
        $this->get(route('travel-tours.storefront.catalog.index', ['maximum_duration_days' => 1]))
            ->assertOk()->assertDontSee($tour->name);
        $this->get(route('travel-tours.storefront.tours.show', $tour->slug))
            ->assertOk()->assertSee('Savannah landscape')->assertSee('Evening arrival')->assertSee('Travel guide')
            ->assertSee('data-tour-gallery-expand', false)->assertSee('data-tour-lightbox', false)
            ->assertSee('data-tour-lightbox-close', false)->assertSee('data-tour-lightbox-stage', false);
    }

    /** Explain missing essentials and publish only after they are supplied. */
    public function test_readiness_and_publication_lifecycle_preserve_history(): void
    {
        Storage::fake('public');
        $tour = Tour::factory()->create();
        $publication = app(TourPublicationService::class);
        $this->assertNotEmpty(app(TourReadinessService::class)->reasons($tour));
        try {
            $publication->publish($tour, $this->manager);
            $this->fail('An incomplete draft must not publish.');
        } catch (PublicationBlocked $exception) {
            $this->assertStringContainsString('cover', $exception->getMessage());
        }
        $this->complete($tour);
        $tour = $tour->refresh();
        $this->assertSame([], app(TourReadinessService::class)->reasons($tour));
        $publication->submitForReview($tour, $this->editor);
        $this->assertSame(PublicationStatus::Review, $tour->refresh()->status);
        $at = CarbonImmutable::now('UTC')->addDay();
        $publication->publish($tour, $this->manager, $at);
        $this->assertSame(PublicationStatus::Published, $tour->refresh()->status);
        $this->assertFalse(Tour::query()->published()->whereKey($tour->id)->exists());
        $publication->publish($tour, $this->manager);
        $this->assertTrue(Tour::query()->published()->whereKey($tour->id)->exists());
        try {
            app(CatalogMediaService::class)->removeTourCover($tour);
            $this->fail('A published tour must not lose its cover.');
        } catch (CatalogException $exception) {
            $this->assertStringContainsString('Unpublish', $exception->getMessage());
        }
        $publication->unpublish($tour, $this->manager);
        $this->assertFalse(Tour::query()->published()->whereKey($tour->id)->exists());
        $publication->archive($tour, $this->manager);
        $this->assertSame(PublicationStatus::Archived, $tour->refresh()->status);
        $publication->restoreDraft($tour, $this->manager);
        $this->assertSame(PublicationStatus::Draft, $tour->refresh()->status);
        $this->assertNull($tour->published_at);
    }

    /** Allow authorized private preview without opening draft inquiries or public URLs. */
    public function test_draft_preview_is_authorized_noindex_and_not_public(): void
    {
        $this->withoutVite();
        $tour = Tour::factory()->create(['short_description' => 'A private preview', 'description' => 'Tour information']);
        $this->get(route('travel-tours.storefront.tours.show', $tour->slug))->assertNotFound();
        $this->get(route('travel-tours.admin.catalog.tours.preview', $tour))->assertRedirect();
        $this->actingAs($this->editor)->get(route('travel-tours.admin.catalog.tours.preview', $tour))
            ->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertSee('Private editorial preview')->assertDontSee('Send inquiry');
        Livewire::actingAs($this->editor)->test(TourEditor::class, ['tourId' => $tour->id])
            ->call('switchTab', 'media')->assertSeeLivewire(TourMediaEditor::class)
            ->call('switchTab', 'publication')->assertSeeLivewire(TourPublicationEditor::class);
    }

    /** Assemble only the tour attributes required by K2 publication readiness. */
    private function complete(Tour $tour): void
    {
        $category = TourCategory::factory()->create(['is_active' => true]);
        $destination = Destination::factory()->create(['status' => PublicationStatus::Published, 'is_active' => true, 'published_at' => now()->subDay()]);
        $tour->categories()->attach($category->id, ['is_primary' => true, 'sort_order' => 1]);
        $tour->destinations()->attach($destination->id, ['role' => 'primary', 'sequence' => 1, 'is_overnight' => false]);
        $tour->forceFill([
            'short_description' => 'A complete traveler-facing summary', 'description' => 'A complete tour description.',
            'meta_title' => 'A complete tour title', 'meta_description' => 'A complete tour search description.',
            'terms' => 'Demo booking terms.', 'cancellation_summary' => 'Demo cancellation policy.',
        ])->save();
        app(TourItineraryService::class)->saveDay($tour, new ItineraryDayData('Arrival day'));
        app(CatalogMediaService::class)->replaceTourCover($tour, UploadedFile::fake()->image('tour.jpg'), 'Tour cover', null);
        app(TourBasePriceService::class)->save($tour, new TourBasePriceData('Standard', 'KES', 500_00, null, null));
    }
}
