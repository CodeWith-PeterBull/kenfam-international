<?php

/**
 * Verifies K2C tour catalog querying, base editing, and route assignments.
 */

declare(strict_types=1);

namespace Tests\Feature\TravelTours;

use App\Enums\UserType;
use App\Models\User;
use App\Modules\TravelTours\Catalog\Data\TourAssignmentData;
use App\Modules\TravelTours\Catalog\Data\TourData;
use App\Modules\TravelTours\Catalog\Enums\PublicationStatus;
use App\Modules\TravelTours\Catalog\Enums\TourType;
use App\Modules\TravelTours\Catalog\Exceptions\CatalogException;
use App\Modules\TravelTours\Catalog\Livewire\Admin\TourCatalog;
use App\Modules\TravelTours\Catalog\Livewire\Admin\TourEditor;
use App\Modules\TravelTours\Catalog\Models\Destination;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Catalog\Models\TourCategory;
use App\Modules\TravelTours\Catalog\Services\TourAssignmentService;
use App\Modules\TravelTours\Catalog\Services\TourService;
use App\Modules\TravelTours\Database\Seeders\TravelToursAccessSeeder;
use App\Modules\TravelTours\Support\TravelToursRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

/** Verifies authorized tour CRUD, ordered assignments, and bounded catalog queries. */
final class TravelToursTourEditorTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    private User $editor;

    private User $viewer;

    /** Seed the module role catalogue and deterministic test operators. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TravelToursAccessSeeder::class);
        $this->administrator = User::factory()->create(['is_active' => true, 'user_type' => UserType::SystemAdministrator]);
        $this->editor = User::factory()->create(['is_active' => true]);
        $this->editor->assignRole(TravelToursRole::TOUR_EDITOR);
        $this->viewer = User::factory()->create(['is_active' => true]);
        $this->viewer->assignRole(TravelToursRole::BOOKING_AGENT);
    }

    /** Render the catalog and editor only for actors with the required capability. */
    public function test_catalog_and_editor_routes_render_module_livewire_components_for_authorized_actors(): void
    {
        $this->withoutVite();
        $tour = Tour::factory()->create();

        $this->actingAs($this->viewer)->get(route('travel-tours.admin.catalog.index'))
            ->assertOk()->assertSeeLivewire(TourCatalog::class);
        $this->actingAs($this->editor)->get(route('travel-tours.admin.catalog.tours.create'))
            ->assertOk()->assertSeeLivewire(TourEditor::class);
        $this->actingAs($this->editor)->get(route('travel-tours.admin.catalog.tours.edit', $tour))
            ->assertOk()->assertSeeLivewire(TourEditor::class);

        $this->actingAs($this->viewer)->get(route('travel-tours.admin.catalog.tours.create'))->assertForbidden();
        $this->actingAs($this->viewer)->get(route('travel-tours.admin.catalog.tours.edit', $tour))->assertForbidden();
    }

    /** Normalize typed tour writes and reject an incoherent update before persistence. */
    public function test_tour_service_creates_and_updates_only_coherent_drafts(): void
    {
        $service = app(TourService::class);
        $tour = $service->create(new TourData(
            code: 'egypt-08d',
            name: '  Egypt Heritage Journey  ',
            type: TourType::Escorted,
            durationDays: 8,
            durationNights: 7,
            minimumParticipants: 2,
            maximumParticipants: 24,
            languages: ['English', 'English', 'Swahili'],
        ), $this->administrator);

        $this->assertSame('EGYPT-08D', $tour->code);
        $this->assertSame('Egypt Heritage Journey', $tour->name);
        $this->assertSame(['English', 'Swahili'], $tour->languages);
        $this->assertSame($this->administrator->id, $tour->created_by);

        try {
            $service->update($tour, new TourData(
                code: $tour->code,
                name: 'Invalid participant range',
                type: $tour->type,
                durationDays: 8,
                minimumParticipants: 20,
                maximumParticipants: 10,
            ), $this->administrator);
            $this->fail('An incoherent participant range must be rejected.');
        } catch (CatalogException $exception) {
            $this->assertStringContainsString('Participant bounds', $exception->getMessage());
        }

        $this->assertSame('Egypt Heritage Journey', $tour->refresh()->name);
    }

    /** Replace category and destination assignments atomically after validating every target. */
    public function test_assignment_service_preserves_existing_route_when_a_replacement_is_invalid(): void
    {
        $tour = Tour::factory()->create();
        $category = TourCategory::factory()->create(['is_active' => true]);
        $destination = Destination::factory()->create(['is_active' => true]);
        $service = app(TourAssignmentService::class);

        $service->replace($tour, new TourAssignmentData(
            categories: [['category_id' => $category->id, 'is_primary' => true, 'sort_order' => 1]],
            destinations: [['destination_id' => $destination->id, 'role' => 'primary', 'sequence' => 1, 'is_overnight' => true]],
        ));

        try {
            $service->replace($tour, new TourAssignmentData(
                categories: [['category_id' => $category->id, 'is_primary' => true, 'sort_order' => 1]],
                destinations: [['destination_id' => 999999, 'role' => 'visit', 'sequence' => 1, 'is_overnight' => false]],
            ));
            $this->fail('An unavailable assignment target must be rejected.');
        } catch (CatalogException $exception) {
            $this->assertStringContainsString('must exist', $exception->getMessage());
        }

        $this->assertDatabaseHas('travel_tour_category', ['tour_id' => $tour->id, 'category_id' => $category->id, 'is_primary' => true]);
        $this->assertDatabaseHas('travel_tour_destination', ['tour_id' => $tour->id, 'destination_id' => $destination->id, 'sequence' => 1]);

        $otherTour = Tour::factory()->create();
        $service->replace($otherTour, new TourAssignmentData(
            categories: [['category_id' => $category->id, 'is_primary' => true, 'sort_order' => 1]],
            destinations: [],
        ));
        $foreignPivotId = DB::table('travel_tour_category')->where('tour_id', $otherTour->id)->value('id');
        try {
            $service->replace($tour, new TourAssignmentData(
                categories: [['category_id' => $category->id, 'is_primary' => true, 'sort_order' => 1, 'assignment_id' => $foreignPivotId]],
                destinations: [],
            ));
            $this->fail('Client-supplied pivot identifiers must be rejected.');
        } catch (CatalogException $exception) {
            $this->assertStringContainsString('incomplete', $exception->getMessage());
        }
        $this->assertDatabaseHas('travel_tour_destination', ['tour_id' => $tour->id, 'destination_id' => $destination->id]);

        $service->replace($tour, new TourAssignmentData(categories: [], destinations: []));
        $this->assertDatabaseMissing('travel_tour_category', ['tour_id' => $tour->id]);
        $this->assertDatabaseMissing('travel_tour_destination', ['tour_id' => $tour->id]);
    }

    /** Persist tour basics and route assignments through the Livewire forms. */
    public function test_editor_creates_updates_and_assigns_a_tour_through_livewire(): void
    {
        Livewire::actingAs($this->editor)
            ->test(TourEditor::class)
            ->set('form.code', 'SAFARI-06D')
            ->set('form.name', 'Kenya Wildlife Circuit')
            ->set('form.durationDays', 6)
            ->set('form.durationNights', 5)
            ->call('saveBasics')
            ->assertHasNoErrors();

        $tour = Tour::query()->where('code', 'SAFARI-06D')->firstOrFail();
        $category = TourCategory::factory()->create(['is_active' => true]);
        $destination = Destination::factory()->create(['is_active' => true]);

        Livewire::actingAs($this->editor)
            ->test(TourEditor::class, ['tourId' => $tour->id])
            ->set('form.name', 'Kenya Wildlife Explorer')
            ->call('saveBasics')
            ->assertHasNoErrors()
            ->call('switchTab', 'route')
            ->set('assignments.categoryIds', [$category->id])
            ->set('assignments.primaryCategoryId', (string) $category->id)
            ->set('assignments.destinationRows', [['destination_id' => $destination->id, 'role' => 'primary', 'is_overnight' => true]])
            ->call('saveRoute')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('travel_tours', ['id' => $tour->id, 'name' => 'Kenya Wildlife Explorer']);
        $this->assertDatabaseHas('travel_tour_category', ['tour_id' => $tour->id, 'category_id' => $category->id, 'is_primary' => true]);
        $this->assertDatabaseHas('travel_tour_destination', ['tour_id' => $tour->id, 'destination_id' => $destination->id, 'role' => 'primary']);
    }

    /** Deny read-only operators at the mutation boundary before validating submitted data. */
    public function test_view_only_operator_cannot_create_or_edit_tours(): void
    {
        Livewire::actingAs($this->viewer)->test(TourEditor::class)->call('saveBasics')->assertForbidden();
        Livewire::actingAs($this->viewer)->test(TourEditor::class, ['tourId' => Tour::factory()->create()->id])->assertForbidden();
    }

    /** Reject client-side substitution of the locked tour identifier. */
    public function test_editor_rejects_locked_tour_identifier_substitution(): void
    {
        $first = Tour::factory()->create();
        $second = Tour::factory()->create();

        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::actingAs($this->editor)
            ->test(TourEditor::class, ['tourId' => $first->id])
            ->set('tourId', $second->id);
    }

    /** Treat SQL wildcard characters literally and constrain unsupported page sizes. */
    public function test_catalog_search_escapes_wildcards_and_bounds_pagination(): void
    {
        Tour::factory()->create(['name' => '100% Nile Discovery', 'code' => 'NILE-100']);
        Tour::factory()->create(['name' => '100X Nile Discovery', 'code' => 'NILE-X']);
        Tour::factory()->count(12)->create();

        Livewire::actingAs($this->editor)
            ->test(TourCatalog::class)
            ->set('search', '%')
            ->assertSee('100% Nile Discovery')
            ->assertDontSee('100X Nile Discovery')
            ->set('search', '')
            ->set('perPage', 999)
            ->assertViewHas('tours', fn ($tours): bool => $tours->perPage() === 12 && $tours->count() === 12);
    }

    /** Filter by typed publication, format, category, and destination without widening the catalog. */
    public function test_catalog_filters_by_state_type_and_assigned_discovery_targets(): void
    {
        $category = TourCategory::factory()->create(['is_active' => true]);
        $destination = Destination::factory()->create(['is_active' => true]);
        $matched = Tour::factory()->create(['name' => 'Matched Heritage Journey', 'type' => TourType::Escorted]);
        Tour::factory()->create(['name' => 'Different Private Journey', 'type' => TourType::Private]);
        Tour::factory()->create(['name' => 'Published Journey', 'status' => PublicationStatus::Published]);
        app(TourAssignmentService::class)->replace($matched, new TourAssignmentData(
            categories: [['category_id' => $category->id, 'is_primary' => true, 'sort_order' => 1]],
            destinations: [['destination_id' => $destination->id, 'role' => 'primary', 'sequence' => 1, 'is_overnight' => false]],
        ));

        Livewire::actingAs($this->editor)
            ->test(TourCatalog::class)
            ->set('statusFilter', 'draft')
            ->set('typeFilter', 'escorted')
            ->set('categoryFilter', (string) $category->id)
            ->set('destinationFilter', (string) $destination->id)
            ->assertSee('Matched Heritage Journey')
            ->assertDontSee('Different Private Journey')
            ->assertDontSee('Published Journey')
            ->assertViewHas('tours', fn ($tours): bool => $tours->total() === 1);
    }
}
