<?php

declare(strict_types=1);

namespace Tests\Feature\PropertyBooking;

use App\Enums\UserType;
use App\Models\User;
use App\Modules\PropertyBooking\Catalog\Exceptions\CatalogException;
use App\Modules\PropertyBooking\Catalog\Livewire\Admin\PropertyManager;
use App\Modules\PropertyBooking\Catalog\Livewire\Admin\UnitTypeManager;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Catalog\Models\PropertyCategory;
use App\Modules\PropertyBooking\Catalog\Models\UnitType;
use App\Modules\PropertyBooking\Catalog\Services\PropertyCategoryService;
use App\Modules\PropertyBooking\Catalog\Services\PropertyService;
use App\Modules\PropertyBooking\Catalog\Services\UnitTypeService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Verifies accessible, bounded, ordered, and owner-safe catalog media. */
final class PropertyBookingMediaTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    /** Prepare isolated media storage and a globally authorized actor. */
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(RoleSeeder::class);
        $this->administrator = User::factory()->create([
            'user_type' => UserType::SystemAdministrator,
            'is_active' => true,
        ]);
        $this->administrator->assignRole(UserType::SystemAdministrator->value);
    }

    /** Exercise property cover, gallery, and metadata writes through Livewire. */
    public function test_administrator_can_manage_accessible_property_media_through_livewire(): void
    {
        $property = Property::factory()->create(['name' => 'Aureon Garden House']);
        $component = Livewire::actingAs($this->administrator)
            ->test(PropertyManager::class)
            ->call('openMedia', $property->id)
            ->set('mediaAltText', 'Front entrance of Aureon Garden House')
            ->set('mediaCaption', 'Main guest entrance')
            ->set('coverUpload', UploadedFile::fake()->image('cover.jpg', 1200, 800))
            ->call('replaceCover')
            ->assertHasNoErrors();

        $component
            ->set('mediaAltText', 'Sunlit guest lounge')
            ->set('mediaCaption', 'Shared lounge')
            ->set('galleryUpload', UploadedFile::fake()->image('lounge.png', 900, 700))
            ->call('addGalleryImage')
            ->assertHasNoErrors();

        $property->refresh()->load('media');
        $cover = $property->getFirstMedia('property_cover');
        $gallery = $property->getFirstMedia('property_gallery');
        $this->assertNotNull($cover);
        $this->assertNotNull($gallery);
        $this->assertSame('Front entrance of Aureon Garden House', $cover->getCustomProperty('alt_text'));
        $this->assertSame('Shared lounge', $gallery->getCustomProperty('caption'));

        $component
            ->call('openMediaMetadata', $gallery->id)
            ->set('mediaAltText', 'Accessible shared guest lounge')
            ->set('mediaCaption', 'Ground-floor shared lounge')
            ->call('saveMediaMetadata')
            ->assertHasNoErrors();

        $gallery->refresh();
        $this->assertSame('Accessible shared guest lounge', $gallery->getCustomProperty('alt_text'));
        $this->assertDatabaseHas('system_activities', ['activity_type' => 'property-booking.property.media-metadata-updated']);

        $component
            ->call('closeDialog')
            ->call('openEdit', $property->id)
            ->assertSee('Front entrance of Aureon Garden House')
            ->assertSee('Accessible shared guest lounge');
    }

    /** Verify create/edit CRUD can persist covers and several accessible gallery files atomically from the operator's perspective. */
    public function test_property_and_unit_type_crud_forms_accept_media_during_creation(): void
    {
        Livewire::actingAs($this->administrator)
            ->test(PropertyManager::class)
            ->call('openCreate')
            ->set('form.name', 'Aureon Riverside Residence')
            ->set('form.code', 'AUREON-RIVER')
            ->set('formCoverUpload', UploadedFile::fake()->image('residence-cover.jpg', 1400, 900))
            ->set('formCoverAltText', 'Aureon Riverside Residence exterior')
            ->set('formCoverCaption', 'Main guest arrival')
            ->set('formGalleryUploads', [
                UploadedFile::fake()->image('lobby.jpg', 1200, 800),
                UploadedFile::fake()->image('terrace.png', 1200, 800),
            ])
            ->set('formGalleryAltTexts', ['Residence lobby seating', 'Riverside guest terrace'])
            ->set('formGalleryCaptions', ['Lobby', 'Guest terrace'])
            ->call('save')
            ->assertHasNoErrors();

        $property = Property::query()->where('code', 'AUREON-RIVER')->firstOrFail();
        $this->assertSame(1, $property->getMedia('property_cover')->count());
        $this->assertSame(2, $property->getMedia('property_gallery')->count());
        $this->assertSame('Residence lobby seating', $property->getMedia('property_gallery')->first()->getCustomProperty('alt_text'));

        Livewire::actingAs($this->administrator)
            ->test(UnitTypeManager::class)
            ->call('openCreate')
            ->set('form.propertyId', (string) $property->getKey())
            ->set('form.name', 'River View Suite')
            ->set('form.code', 'RIVER-SUITE')
            ->set('formCoverUpload', UploadedFile::fake()->image('suite-cover.jpg', 1400, 900))
            ->set('formCoverAltText', 'River View Suite bedroom and lounge')
            ->set('formGalleryUploads', [UploadedFile::fake()->image('suite-bathroom.png', 1000, 800)])
            ->set('formGalleryAltTexts', ['River View Suite bathroom'])
            ->set('formGalleryCaptions', ['Private bathroom'])
            ->call('save')
            ->assertHasNoErrors();

        $unitType = UnitType::query()->where('property_id', $property->getKey())->where('code', 'RIVER-SUITE')->firstOrFail();
        $this->assertSame(1, $unitType->getMedia('unit_type_cover')->count());
        $this->assertSame(1, $unitType->getMedia('unit_type_gallery')->count());
        $this->assertSame('Private bathroom', $unitType->getFirstMedia('unit_type_gallery')->getCustomProperty('caption'));
    }

    /** Verify gallery ordering, ownership, and count are service-level invariants. */
    public function test_property_gallery_rejects_foreign_or_incomplete_orders_and_honours_its_limit(): void
    {
        config()->set('property-booking.media.property_gallery_limit', 2);
        $property = Property::factory()->create();
        $other = Property::factory()->create();
        $service = app(PropertyService::class);
        $service->addGalleryImages(
            $property,
            [UploadedFile::fake()->image('first.jpg'), UploadedFile::fake()->image('second.jpg')],
            [
                ['alt_text' => 'First room view', 'caption' => 'First'],
                ['alt_text' => 'Second room view', 'caption' => 'Second'],
            ],
            $this->administrator,
        );
        $service->addGalleryImages(
            $other,
            [UploadedFile::fake()->image('foreign.jpg')],
            [['alt_text' => 'Foreign property image']],
            $this->administrator,
        );

        $ids = $property->refresh()->getMedia('property_gallery')->pluck('id')->all();
        $service->reorderGallery($property, array_reverse($ids), $this->administrator);
        $this->assertSame(array_reverse($ids), $property->refresh()->getMedia('property_gallery')->pluck('id')->all());

        try {
            $service->reorderGallery($property, [$ids[0]], $this->administrator);
            $this->fail('An incomplete gallery order must be rejected.');
        } catch (CatalogException $exception) {
            $this->assertStringContainsString('exactly once', $exception->getMessage());
        }

        $foreignMedia = $other->refresh()->getFirstMedia('property_gallery');
        try {
            $service->updateMediaMetadata($property, $foreignMedia->id, 'Not owned', null, $this->administrator);
            $this->fail('Foreign media ownership must be rejected.');
        } catch (CatalogException $exception) {
            $this->assertStringContainsString('does not exist', $exception->getMessage());
        }

        try {
            $service->addGalleryImages(
                $property,
                [UploadedFile::fake()->image('third.jpg')],
                [['alt_text' => 'Third room view']],
                $this->administrator,
            );
            $this->fail('A gallery above the configured limit must be rejected.');
        } catch (CatalogException $exception) {
            $this->assertStringContainsString('no more than 2', $exception->getMessage());
        }
    }

    /** Verify every catalog media owner applies MIME, size, and metadata contracts. */
    public function test_category_and_unit_type_media_share_the_strict_upload_contract(): void
    {
        $category = PropertyCategory::factory()->create();
        $unitType = UnitType::factory()->create();
        $categoryService = app(PropertyCategoryService::class);
        $unitTypeService = app(UnitTypeService::class);

        $categoryService->replaceImage(
            $category,
            UploadedFile::fake()->image('category.png', 700, 500),
            'Serviced apartment category',
            $this->administrator,
        );
        $unitTypeService->addGalleryImages(
            $unitType,
            [UploadedFile::fake()->image('bedroom.jpg', 900, 700)],
            [['alt_text' => 'King bedroom', 'caption' => 'Primary bedroom']],
            $this->administrator,
        );

        $this->assertTrue($category->refresh()->hasMedia('category_image'));
        $this->assertSame('King bedroom', $unitType->refresh()->getFirstMedia('unit_type_gallery')->getCustomProperty('alt_text'));

        try {
            $categoryService->replaceImage(
                $category,
                UploadedFile::fake()->create('category.txt', 1, 'text/plain'),
                'Invalid document',
                $this->administrator,
            );
            $this->fail('A non-image MIME type must be rejected.');
        } catch (CatalogException $exception) {
            $this->assertStringContainsString('JPEG, PNG, or WebP', $exception->getMessage());
        }

        config()->set('property-booking.media.upload_max_kilobytes', 1);
        try {
            $unitTypeService->replaceCover(
                $unitType,
                UploadedFile::fake()->create('oversized.png', 2, 'image/png'),
                'Oversized image',
                null,
                $this->administrator,
            );
            $this->fail('An oversized image must be rejected.');
        } catch (CatalogException $exception) {
            $this->assertStringContainsString('upload limit', $exception->getMessage());
        }
    }
}
