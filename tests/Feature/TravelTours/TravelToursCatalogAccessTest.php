<?php

/**
 * Verifies the K2 catalog capability, policy, and typed-input boundary.
 */

declare(strict_types=1);

namespace Tests\Feature\TravelTours;

use App\Enums\UserType;
use App\Models\User;
use App\Modules\TravelTours\Bookings\Enums\BookingMode;
use App\Modules\TravelTours\Catalog\Data\DestinationData;
use App\Modules\TravelTours\Catalog\Data\TourAssignmentData;
use App\Modules\TravelTours\Catalog\Data\TourCategoryData;
use App\Modules\TravelTours\Catalog\Data\TourData;
use App\Modules\TravelTours\Catalog\Enums\DestinationType;
use App\Modules\TravelTours\Catalog\Enums\TourType;
use App\Modules\TravelTours\Catalog\Models\Destination;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Catalog\Models\TourCategory;
use App\Modules\TravelTours\Catalog\Policies\DestinationPolicy;
use App\Modules\TravelTours\Catalog\Policies\TourCategoryPolicy;
use App\Modules\TravelTours\Catalog\Policies\TourPolicy;
use App\Modules\TravelTours\Catalog\Services\CatalogQueryService;
use App\Modules\TravelTours\Database\Seeders\TravelToursAccessSeeder;
use App\Modules\TravelTours\Support\TravelToursPermission;
use App\Modules\TravelTours\Support\TravelToursRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/** Exercise K2A access distinctions without exposing unfinished CRUD routes. */
final class TravelToursCatalogAccessTest extends TestCase
{
    use RefreshDatabase;

    /** Seed the code-owned role and permission catalogue for each access case. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TravelToursAccessSeeder::class);
    }

    /** The role matrix separates viewing, editing, and publication authority. */
    public function test_catalog_roles_receive_the_intended_capabilities(): void
    {
        $manager = $this->operator(TravelToursRole::MANAGER);
        $editor = $this->operator(TravelToursRole::TOUR_EDITOR);
        $agent = $this->operator(TravelToursRole::BOOKING_AGENT);

        $this->assertTrue($manager->can(TravelToursPermission::VIEW_CATALOG));
        $this->assertTrue($manager->can(TravelToursPermission::MANAGE_CATALOG));
        $this->assertTrue($manager->can(TravelToursPermission::PUBLISH_CATALOG));

        $this->assertTrue($editor->can(TravelToursPermission::VIEW_CATALOG));
        $this->assertTrue($editor->can(TravelToursPermission::MANAGE_CATALOG));
        $this->assertFalse($editor->can(TravelToursPermission::PUBLISH_CATALOG));

        $this->assertTrue($agent->can(TravelToursPermission::VIEW_CATALOG));
        $this->assertFalse($agent->can(TravelToursPermission::MANAGE_CATALOG));
        $this->assertFalse($agent->can(TravelToursPermission::PUBLISH_CATALOG));
    }

    /** Context-owned policies protect categories, destinations, and tours. */
    public function test_catalog_policies_are_registered_and_action_specific(): void
    {
        $manager = $this->operator(TravelToursRole::MANAGER);
        $editor = $this->operator(TravelToursRole::TOUR_EDITOR);
        $agent = $this->operator(TravelToursRole::BOOKING_AGENT);
        $administrator = User::factory()->create(['user_type' => UserType::SystemAdministrator]);
        $tour = Tour::factory()->create();
        $destination = Destination::factory()->create();
        $category = TourCategory::factory()->create();

        $this->assertInstanceOf(TourPolicy::class, Gate::getPolicyFor(Tour::class));
        $this->assertInstanceOf(DestinationPolicy::class, Gate::getPolicyFor(Destination::class));
        $this->assertInstanceOf(TourCategoryPolicy::class, Gate::getPolicyFor(TourCategory::class));

        $this->assertTrue(Gate::forUser($agent)->allows('viewAny', Tour::class));
        $this->assertFalse(Gate::forUser($agent)->allows('update', $tour));
        $this->assertTrue(Gate::forUser($editor)->allows('update', $tour));
        $this->assertFalse(Gate::forUser($editor)->allows('publish', $tour));
        $this->assertTrue(Gate::forUser($manager)->allows('publish', $tour));
        $this->assertTrue(Gate::forUser($manager)->allows('publish', $destination));
        $this->assertTrue(Gate::forUser($editor)->allows('update', $category));
        $this->assertTrue(Gate::forUser($administrator)->allows('publish', $tour));
    }

    /** Implemented K2 catalog workspaces are authorized while the unfinished tour editor stays absent. */
    public function test_catalog_routes_expose_only_completed_workspaces(): void
    {
        $viewer = $this->operator(TravelToursRole::BOOKING_AGENT);
        $outsider = User::factory()->create();
        $this->withoutVite();

        $this->actingAs($viewer)
            ->get(route('travel-tours.admin.catalog.index'))
            ->assertOk()
            ->assertSee('Tour catalog');

        $this->actingAs($outsider)
            ->get(route('travel-tours.admin.catalog.index'))
            ->assertForbidden();

        $this->actingAs($viewer)->get(route('travel-tours.admin.catalog.categories'))->assertOk();
        $this->actingAs($viewer)->get(route('travel-tours.admin.catalog.destinations'))->assertOk();
        $this->assertTrue(app('router')->has('travel-tours.admin.catalog.categories'));
        $this->assertTrue(app('router')->has('travel-tours.admin.catalog.destinations'));
        $this->assertFalse(app('router')->has('travel-tours.admin.catalog.tours.create'));
    }

    /** Staff list queries enforce the same collection policy as their routes. */
    public function test_catalog_query_service_rejects_unpermitted_callers(): void
    {
        $viewer = $this->operator(TravelToursRole::BOOKING_AGENT);
        $outsider = User::factory()->create();
        $catalog = app(CatalogQueryService::class);

        Tour::factory()->count(2)->create();
        $this->assertSame(2, $catalog->tours($viewer)->count());

        $this->expectException(AuthorizationException::class);
        $catalog->tours($outsider)->count();
    }

    /** Catalog write inputs are immutable and carry domain enums instead of strings. */
    public function test_catalog_data_objects_preserve_typed_inputs(): void
    {
        $category = new TourCategoryData(name: 'Escorted journeys', parentId: 4);
        $destination = new DestinationData(type: DestinationType::Country, name: 'Egypt', countryCode: 'EG');
        $tour = new TourData(
            code: 'EGY-001',
            name: 'Cairo and the Nile',
            type: TourType::Escorted,
            durationDays: 8,
            bookingMode: BookingMode::Approval,
            languages: ['English'],
        );
        $assignments = new TourAssignmentData(
            categories: [['category_id' => 4, 'is_primary' => true, 'sort_order' => 0]],
            destinations: [['destination_id' => 7, 'role' => 'visit', 'sequence' => 1, 'is_overnight' => true]],
        );

        $this->assertSame(4, $category->parentId);
        $this->assertSame(DestinationType::Country, $destination->type);
        $this->assertSame(BookingMode::Approval, $tour->bookingMode);
        $this->assertSame(4, $assignments->categories[0]['category_id']);
        $this->assertSame(7, $assignments->destinations[0]['destination_id']);
    }

    /** Create an active operator holding one deterministic module role. */
    private function operator(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
