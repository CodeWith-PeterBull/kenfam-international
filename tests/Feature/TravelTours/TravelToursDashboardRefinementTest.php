<?php

/**
 * Verifies the TravelTours operations dashboard as an authorized navigation surface.
 */

declare(strict_types=1);

namespace Tests\Feature\TravelTours;

use App\Models\User;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\Database\Seeders\TravelToursAccessSeeder;
use App\Modules\TravelTours\Inquiries\Models\TourInquiry;
use App\Modules\TravelTours\Scheduling\Models\TourDeparture;
use App\Modules\TravelTours\Support\TravelToursRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Proves dashboard summaries lead authorized operators to their owning workspaces. */
final class TravelToursDashboardRefinementTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    /** Enable TravelTours and provision its real capability catalogue. */
    protected function setUp(): void
    {
        parent::setUp();

        config(['travel-tours.enabled' => true]);
        $this->seed(TravelToursAccessSeeder::class);
        $this->manager = User::factory()->create(['is_active' => true]);
        $this->manager->assignRole(TravelToursRole::MANAGER);
    }

    /** Every statistic and populated queue exposes a permission-safe deep link. */
    public function test_dashboard_statistics_and_records_link_to_their_operational_workspaces(): void
    {
        $departure = TourDeparture::factory()->create(['code' => 'DEP-DASH-01']);
        $booking = TourBooking::factory()->create(['booking_number' => 'WEB-DASH-0001']);
        $inquiry = TourInquiry::factory()->create(['reference' => 'INQ-DASH-0001']);

        $response = $this->actingAs($this->manager)
            ->get(route('travel-tours.admin.dashboard'))
            ->assertOk()
            ->assertSee('travel-dashboard-stat', false)
            ->assertSee(route('travel-tours.admin.catalog.index'), false)
            ->assertSee(route('travel-tours.admin.pricing.index'), false)
            ->assertSee(route('travel-tours.admin.departures.index'), false)
            ->assertSee(route('travel-tours.admin.bookings.index'), false)
            ->assertSee(route('travel-tours.admin.inquiries.index'), false)
            ->assertSee(route('travel-tours.storefront.catalog.index'), false)
            ->assertSee(route('travel-tours.admin.departures.index', ['search' => $departure->code]), false)
            ->assertSee(route('travel-tours.admin.bookings.index', ['search' => $booking->booking_number]), false)
            ->assertSee(route('travel-tours.admin.inquiries.index', ['search' => $inquiry->reference]), false);

        $response->assertSee('travel-dashboard-panel', false);
    }
}
