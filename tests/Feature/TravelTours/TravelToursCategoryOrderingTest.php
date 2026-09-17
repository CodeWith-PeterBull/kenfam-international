<?php

/**
 * Verifies category reordering, the visibility switch, and the details dialog.
 */

declare(strict_types=1);

namespace Tests\Feature\TravelTours;

use App\Enums\UserType;
use App\Models\User;
use App\Modules\TravelTours\Catalog\Enums\PublicationStatus;
use App\Modules\TravelTours\Catalog\Exceptions\CatalogException;
use App\Modules\TravelTours\Catalog\Livewire\Admin\TourCategoryManager;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Catalog\Models\TourCategory;
use App\Modules\TravelTours\Catalog\Services\TourCategoryService;
use App\Modules\TravelTours\Database\Seeders\TravelToursAccessSeeder;
use App\Modules\TravelTours\Support\TravelToursRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Prove that ordering is service-owned, sibling-scoped, and dense, and that
 * every row control the manager exposes is separately authorized.
 */
final class TravelToursCategoryOrderingTest extends TestCase
{
    use RefreshDatabase;

    /** Seed the role catalogue so editor and viewer grants are the real ones. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TravelToursAccessSeeder::class);
    }

    /** Moving swaps with the adjacent sibling and renumbers the set densely. */
    public function test_move_swaps_with_the_adjacent_sibling_and_renumbers_densely(): void
    {
        [$first, $second, $third] = $this->siblings([10, 10, 40]);
        $service = app(TourCategoryService::class);

        $service->move($third, 'up');

        $this->assertSame([1, 3, 2], $this->orderOf($first, $second, $third));

        $service->move($first, 'down');

        $this->assertSame([2, 3, 1], $this->orderOf($first, $second, $third));
    }

    /** Boundary moves change nothing, and an unknown direction is refused. */
    public function test_boundary_moves_are_no_ops_and_invalid_directions_are_refused(): void
    {
        [$first, , $third] = $this->siblings([1, 2, 3]);
        $service = app(TourCategoryService::class);

        $service->move($first, 'up');
        $service->move($third, 'down');

        $this->assertSame(1, $first->fresh()->sort_order);
        $this->assertSame(3, $third->fresh()->sort_order);

        $this->expectException(CatalogException::class);
        $service->move($first, 'sideways');
    }

    /** Ordering is scoped to the parent; nested nodes never displace top-level ones. */
    public function test_move_never_crosses_hierarchy_levels(): void
    {
        [$topA, $topB] = $this->siblings([1, 2]);
        $childA = TourCategory::factory()->create(['parent_id' => $topA->getKey(), 'sort_order' => 1]);
        $childB = TourCategory::factory()->create(['parent_id' => $topA->getKey(), 'sort_order' => 2]);

        app(TourCategoryService::class)->move($childB, 'up');

        $this->assertSame(1, $childB->fresh()->sort_order);
        $this->assertSame(2, $childA->fresh()->sort_order);
        $this->assertSame(1, $topA->fresh()->sort_order, 'A nested move must not renumber top-level siblings.');
        $this->assertSame(2, $topB->fresh()->sort_order);
    }

    /** The manager reorders for editors and refuses viewers. */
    public function test_manager_reorder_is_authorized_per_actor(): void
    {
        [$first, $second] = $this->siblings([1, 2]);

        Livewire::actingAs($this->operator(TravelToursRole::TOUR_EDITOR))
            ->test(TourCategoryManager::class)
            ->call('move', $second->getKey(), 'up')
            ->assertHasNoErrors();

        $this->assertSame([2, 1], $this->orderOf($first, $second));

        Livewire::actingAs($this->operator(TravelToursRole::BOOKING_AGENT))
            ->test(TourCategoryManager::class)
            ->call('move', $first->getKey(), 'up')
            ->assertForbidden();
    }

    /** The visibility switch keeps the service guard and reports the blocker inline. */
    public function test_visibility_switch_keeps_the_published_tour_guard(): void
    {
        $category = TourCategory::factory()->create(['sort_order' => 1]);
        $tour = Tour::factory()->create(['status' => PublicationStatus::Published, 'published_at' => now()->subDay()]);
        $tour->categories()->attach($category->getKey(), ['is_primary' => true, 'sort_order' => 1]);
        $editor = $this->operator(TravelToursRole::TOUR_EDITOR);

        $component = Livewire::actingAs($editor)->test(TourCategoryManager::class);

        $component->assertSee('Assigned to 1 published tour')
            ->call('toggleActive', $category->getKey())
            ->assertHasErrors('management');

        $this->assertTrue($category->fresh()->is_active, 'A refused hide must leave the category active.');

        $tour->forceFill(['status' => PublicationStatus::Draft])->save();

        $component->call('toggleActive', $category->getKey())->assertHasNoErrors();

        $this->assertFalse($category->fresh()->is_active);
    }

    /** Anyone who may view the catalog can open details; only editors see the edit affordance. */
    public function test_details_dialog_is_viewable_by_viewers_and_editable_only_by_editors(): void
    {
        $category = TourCategory::factory()->create(['name' => 'Rail Journeys', 'sort_order' => 1]);
        $tour = Tour::factory()->create(['name' => 'Alpine Express']);
        $tour->categories()->attach($category->getKey(), ['is_primary' => true, 'sort_order' => 1]);

        Livewire::actingAs($this->operator(TravelToursRole::BOOKING_AGENT))
            ->test(TourCategoryManager::class)
            ->call('openDetails', $category->getKey())
            ->assertSet('dialog', 'details')
            ->assertSee('Alpine Express')
            ->assertSee('primary category')
            ->assertDontSee('Edit category');

        Livewire::actingAs($this->operator(TravelToursRole::TOUR_EDITOR))
            ->test(TourCategoryManager::class)
            ->call('openDetails', $category->getKey())
            ->assertSee('Edit category');
    }

    /** A viewer's table shows state, not controls. */
    public function test_viewer_table_exposes_no_write_controls(): void
    {
        $this->siblings([1, 2]);

        Livewire::actingAs($this->operator(TravelToursRole::BOOKING_AGENT))
            ->test(TourCategoryManager::class)
            ->assertDontSee('role="switch"', false)
            ->assertDontSee('Move earlier')
            ->assertDontSee('Add category')
            ->assertSee('View ');
    }

    /**
     * Create top-level siblings with the given sort orders in one deterministic name order.
     *
     * @param  list<int>  $orders
     * @return list<TourCategory>
     */
    private function siblings(array $orders): array
    {
        $created = [];
        foreach ($orders as $index => $order) {
            $created[] = TourCategory::factory()->create([
                'name' => 'Sibling '.chr(65 + $index),
                'sort_order' => $order,
            ]);
        }

        return $created;
    }

    /** @return list<int> */
    private function orderOf(TourCategory ...$categories): array
    {
        return array_map(static fn (TourCategory $category): int => $category->fresh()->sort_order, $categories);
    }

    /** Create an active non-administrator operator holding one module role. */
    private function operator(string $role): User
    {
        $user = User::factory()->create(['user_type' => UserType::Viewer, 'is_active' => true]);
        $user->assignRole($role);

        return $user->fresh();
    }
}
