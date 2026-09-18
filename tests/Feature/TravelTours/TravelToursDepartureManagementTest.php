<?php

/** Verifies K3 scheduling persistence, boundaries and operator surfaces. */

declare(strict_types=1);

namespace Tests\Feature\TravelTours;

use App\Models\User;
use App\Modules\TravelTours\Catalog\Enums\PublicationStatus;
use App\Modules\TravelTours\Catalog\Livewire\Admin\TourEditor;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Database\Seeders\TravelToursAccessSeeder;
use App\Modules\TravelTours\Pricing\Data\TourBasePriceData;
use App\Modules\TravelTours\Pricing\Models\ParticipantRate;
use App\Modules\TravelTours\Pricing\Models\TourRatePlan;
use App\Modules\TravelTours\Pricing\Services\TourBasePriceService;
use App\Modules\TravelTours\Scheduling\Data\DepartureData;
use App\Modules\TravelTours\Scheduling\Enums\DepartureStatus;
use App\Modules\TravelTours\Scheduling\Exceptions\DepartureException;
use App\Modules\TravelTours\Scheduling\Livewire\Admin\DepartureManager;
use App\Modules\TravelTours\Scheduling\Models\AvailabilityHold;
use App\Modules\TravelTours\Scheduling\Models\TourDeparture;
use App\Modules\TravelTours\Scheduling\Services\DepartureManagementService;
use App\Modules\TravelTours\Support\TravelToursRole;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Verifies schedule writes, lifecycle constraints, tour scoping and capability separation. */
final class TravelToursDepartureManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $editor;

    private User $agent;

    /** Seed only the access catalogue and isolated test actors. */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TravelToursAccessSeeder::class);
        $this->manager = User::factory()->create(['is_active' => true]);
        $this->manager->assignRole(TravelToursRole::MANAGER);
        $this->editor = User::factory()->create(['is_active' => true]);
        $this->editor->assignRole(TravelToursRole::TOUR_EDITOR);
        $this->agent = User::factory()->create(['is_active' => true]);
        $this->agent->assignRole(TravelToursRole::BOOKING_AGENT);
    }

    /** Preserve local wall time, UTC storage, attribution and a draft status. */
    public function test_service_creates_and_updates_a_tour_owned_departure(): void
    {
        $tour = Tour::factory()->create();
        $service = app(DepartureManagementService::class);
        $departure = $service->create($this->data($tour), $this->manager);
        $this->assertSame(DepartureStatus::Draft, $departure->status);
        $this->assertSame('2027-01-10 05:00:00', $departure->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame($this->manager->id, $departure->created_by);
        $this->assertSame($this->manager->id, $departure->updated_by);
        $this->assertSame('DEP-TEST-01', $departure->code);

        $updated = $service->update($departure, $this->data($tour, capacity: 30), $this->manager);
        $this->assertSame($departure->id, $updated->id);
        $this->assertSame(30, $updated->capacity);
        $this->assertDatabaseCount('travel_tour_departures', 1);
    }

    /** Reject a different tour's rate and an impossible or repeated local time before mutation. */
    public function test_service_rejects_invalid_rate_ownership_and_dst_wall_times(): void
    {
        $tour = Tour::factory()->create();
        $foreign = Tour::factory()->create();
        $plan = $foreign->ratePlans()->create(['code' => 'FOREIGN', 'name' => 'Foreign', 'currency' => 'KES', 'deposit_type' => 'percentage', 'deposit_value' => 0, 'tax_inclusive' => true]);
        $service = app(DepartureManagementService::class);

        foreach ([$this->data($tour, ratePlanId: $plan->id), $this->data($tour, timezone: 'America/New_York', start: '2027-03-14T02:30', end: '2027-03-18T10:00'), $this->data($tour, timezone: 'America/New_York', start: '2027-11-07T01:30', end: '2027-11-10T10:00')] as $invalid) {
            try {
                $service->create($invalid, $this->manager);
                $this->fail('The invalid schedule must be rejected.');
            } catch (DepartureException) {
                $this->assertDatabaseCount('travel_tour_departures', 0);
            }
        }
    }

    /** Preserve occupied capacity and reject a cross-tour transfer. */
    public function test_service_respects_active_holds_and_immutable_tour_ownership(): void
    {
        $tour = Tour::factory()->create();
        $departure = app(DepartureManagementService::class)->create($this->data($tour, capacity: 2), $this->manager);
        AvailabilityHold::factory()->create(['departure_id' => $departure->id, 'seat_count' => 2, 'adult_count' => 2]);
        $service = app(DepartureManagementService::class);
        foreach ([$this->data($tour, capacity: 1), $this->data(Tour::factory()->create())] as $invalid) {
            try {
                $service->update($departure, $invalid, $this->manager);
                $this->fail('Committed capacity or tour ownership cannot be overwritten.');
            } catch (DepartureException) {
                $this->assertSame(2, $departure->fresh()->capacity);
                $this->assertSame($tour->id, $departure->fresh()->tour_id);
            }
        }
    }

    /** Open only a published priced tour and follow declared status edges. */
    public function test_service_opens_and_advances_a_ready_departure(): void
    {
        $tour = Tour::factory()->create();
        $service = app(DepartureManagementService::class);
        $departure = $service->create($this->data($tour), $this->manager);
        $this->expectException(DepartureException::class);
        $service->transition($departure, DepartureStatus::Open, $this->manager);
    }

    /** Verify the positive lifecycle after editorial publication and base pricing. */
    public function test_ready_departure_can_open_then_be_guaranteed_and_closed(): void
    {
        $tour = Tour::factory()->create(['status' => PublicationStatus::Published, 'published_at' => now()->subDay()]);
        app(TourBasePriceService::class)->save($tour, new TourBasePriceData('Standard', 'KES', 1250000, null, null));
        $service = app(DepartureManagementService::class);
        $departure = $service->create($this->data($tour), $this->manager);
        $departure = $service->transition($departure, DepartureStatus::Open, $this->manager);
        $this->assertSame(DepartureStatus::Open, $departure->status);
        $departure = $service->transition($departure, DepartureStatus::Guaranteed, $this->manager);
        $departure = $service->transition($departure, DepartureStatus::Closed, $this->manager);
        $this->assertSame(DepartureStatus::Closed, $departure->status);
    }

    /** Render the same component in central and tour-scoped workspaces. */
    public function test_routes_and_editor_tab_render_the_departure_manager(): void
    {
        $this->withoutVite();
        $tour = Tour::factory()->create();
        $this->actingAs($this->manager)->get(route('travel-tours.admin.departures.index'))->assertOk()->assertSeeLivewire(DepartureManager::class);
        $this->actingAs($this->editor)->get(route('travel-tours.admin.catalog.tours.edit', ['tour' => $tour, 'section' => 'departures']))->assertOk()->assertSeeLivewire(DepartureManager::class);
        Livewire::actingAs($this->editor)->test(TourEditor::class, ['tourId' => $tour->id])->call('switchTab', 'departures')->assertSeeLivewire(DepartureManager::class);
    }

    /** Keep view-only operators out of every write action and reject a foreign scoped row. */
    public function test_livewire_enforces_write_permission_and_locked_tour_scope(): void
    {
        $tour = Tour::factory()->create();
        $other = Tour::factory()->create();
        $foreign = TourDeparture::factory()->create(['tour_id' => $other->id]);
        Livewire::actingAs($this->agent)->test(DepartureManager::class)->call('openCreate')->assertForbidden();
        foreach ([$this->editor, $this->manager] as $operator) {
            try {
                Livewire::actingAs($operator)->test(DepartureManager::class, ['tourId' => $tour->id])->call('openEdit', $foreign->id);
                $this->fail('A tour-scoped manager must not resolve another tour’s departure.');
            } catch (ModelNotFoundException) {
                $this->assertSame($other->id, $foreign->fresh()->tour_id);
            }
        }
    }

    /** Create a departure through the browser-facing form and persist exact local time. */
    public function test_livewire_form_creates_a_departure(): void
    {
        $tour = Tour::factory()->create();
        Livewire::actingAs($this->manager)->test(DepartureManager::class, ['tourId' => $tour->id])
            ->call('openCreate')->set('form.code', 'DEP-WEB-01')
            ->set('form.localStart', '2027-01-10T08:00')->set('form.localEnd', '2027-01-14T17:00')
            ->set('form.capacity', '20')->call('save')->assertHasNoErrors();
        $this->assertDatabaseHas('travel_tour_departures', ['tour_id' => $tour->id, 'code' => 'DEP-WEB-01', 'capacity' => 20]);
    }

    /** Assign, replace the lead, and remove only this departure's staff records. */
    public function test_staff_assignment_is_tour_owned_and_visible_in_the_manager(): void
    {
        $tour = Tour::factory()->create();
        $departure = app(DepartureManagementService::class)->create($this->data($tour), $this->manager);
        $service = app(DepartureManagementService::class);
        $lead = $service->assignStaff($departure, $this->editor, 'guide', true, 'English-speaking guide', $this->manager);
        $next = $service->assignStaff($departure, $this->agent, 'coordinator', true, null, $this->manager);
        $this->assertFalse($lead->fresh()->is_lead);
        $this->assertTrue($next->fresh()->is_lead);

        Livewire::actingAs($this->manager)->test(DepartureManager::class, ['tourId' => $tour->id])
            ->call('openStaff', $departure->id)->assertSee($this->editor->name)
            ->set('staffUserId', (string) $this->manager->id)->set('staffRole', 'host')->call('assignStaff')->assertHasNoErrors();
        $this->assertDatabaseCount('travel_departure_staff', 3);

        $service->removeStaff($departure, $lead->id);
        $this->assertDatabaseMissing('travel_departure_staff', ['id' => $lead->id]);
    }

    /** Show only future bookable dates, actual seats and the selected departure fare. */
    public function test_public_tour_dates_use_authoritative_availability_and_specific_fares(): void
    {
        $this->withoutVite();
        $tour = Tour::factory()->create(['status' => PublicationStatus::Published, 'published_at' => now()->subDay()]);
        app(TourBasePriceService::class)->save($tour, new TourBasePriceData('Standard', 'KES', 1250000, null, null));
        $premium = TourRatePlan::factory()->create(['tour_id' => $tour->id, 'code' => 'PREMIUM', 'name' => 'Premium', 'is_default' => false]);
        ParticipantRate::factory()->create(['rate_plan_id' => $premium->id, 'amount_minor' => 2000000, 'is_active' => true]);
        $departure = TourDeparture::factory()->create(['tour_id' => $tour->id, 'rate_plan_id' => $premium->id, 'code' => 'DEP-PREMIUM', 'capacity' => 4]);
        AvailabilityHold::factory()->create(['departure_id' => $departure->id, 'seat_count' => 2, 'adult_count' => 2]);
        TourDeparture::factory()->create(['tour_id' => $tour->id, 'code' => 'DEP-PAST', 'starts_at' => now()->subWeek(), 'ends_at' => now()->subDays(3), 'booking_closes_at' => now()->subDays(8)]);

        $bufferLevel = ob_get_level();
        $this->get(route('travel-tours.storefront.tours.show', $tour->slug))
            ->assertOk()->assertSee('DEP-PREMIUM')->assertSee('2 places currently available')
            ->assertSee('20,000.00')->assertDontSee('DEP-PAST');
        $this->actingAs($this->manager)->get(route('travel-tours.admin.catalog.tours.preview', $tour))
            ->assertOk()->assertSee('DEP-PREMIUM')->assertSee('2 places currently available');
        while (ob_get_level() > $bufferLevel) {
            $this->assertSame("\n", ob_get_contents());
            ob_end_clean();
        }
    }

    /** Build the small validated service input used by focused invariants. */
    private function data(Tour $tour, int $capacity = 24, ?int $ratePlanId = null, string $timezone = 'Africa/Nairobi', string $start = '2027-01-10T08:00', string $end = '2027-01-14T17:00'): DepartureData
    {
        return new DepartureData($tour->id, 'DEP-TEST-01', $timezone, $start, $end, null, null, $capacity, 1, $ratePlanId, null, null, null);
    }
}
