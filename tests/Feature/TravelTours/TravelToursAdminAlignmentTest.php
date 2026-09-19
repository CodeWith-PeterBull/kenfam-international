<?php

/**
 * Verifies the aligned destination, departure, and inquiry managers: enumerated tables, switch actions, and details dialogs.
 */

declare(strict_types=1);

namespace Tests\Feature\TravelTours;

use App\Enums\UserType;
use App\Models\User;
use App\Modules\TravelTours\Bookings\Enums\ParticipantType;
use App\Modules\TravelTours\Catalog\Enums\PublicationStatus;
use App\Modules\TravelTours\Catalog\Livewire\Admin\DestinationManager;
use App\Modules\TravelTours\Catalog\Models\Destination;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Catalog\Services\CatalogMediaService;
use App\Modules\TravelTours\Database\Seeders\TravelToursAccessSeeder;
use App\Modules\TravelTours\Inquiries\Enums\InquiryStatus;
use App\Modules\TravelTours\Inquiries\Livewire\Admin\InquiryManager;
use App\Modules\TravelTours\Inquiries\Models\TourInquiry;
use App\Modules\TravelTours\Pricing\Models\ParticipantRate;
use App\Modules\TravelTours\Pricing\Models\TourRatePlan;
use App\Modules\TravelTours\Scheduling\Enums\DepartureStatus;
use App\Modules\TravelTours\Scheduling\Livewire\Admin\DepartureManager;
use App\Modules\TravelTours\Scheduling\Models\TourDeparture;
use App\Modules\TravelTours\Support\TravelToursRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The three managers now share the category manager's shape. These tests
 * pin the behaviour behind it: the publication and sales switches go
 * through the domain services and explain refusals, the details dialogs
 * carry what the eye icon promises (including images), and the inquiry
 * queue can be owned, scheduled, moved, and annotated.
 */
final class TravelToursAdminAlignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $editor;

    private User $agent;

    /** Enable the module with the real role grants. */
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32)), 'travel-tours.enabled' => true]);
        $this->seed(TravelToursAccessSeeder::class);
        $this->manager = $this->operator(TravelToursRole::MANAGER);
        $this->editor = $this->operator(TravelToursRole::TOUR_EDITOR);
        $this->agent = $this->operator(TravelToursRole::BOOKING_AGENT);
    }

    /** The published switch publishes and withdraws through the service, and the row explains what blocks it. */
    public function test_destination_published_switch_follows_the_publication_rules(): void
    {
        $country = Destination::factory()->create(['name' => 'Egypt', 'slug' => 'egypt', 'country_code' => 'EG', 'type' => 'country', 'status' => PublicationStatus::Draft, 'is_active' => true]);
        $city = Destination::factory()->create(['name' => 'Cairo', 'slug' => 'cairo', 'country_code' => 'EG', 'type' => 'city', 'parent_id' => $country->getKey(), 'status' => PublicationStatus::Draft, 'is_active' => true]);

        $component = Livewire::actingAs($this->manager)->test(DestinationManager::class)
            ->assertSee('Publish its parent first')
            ->call('togglePublished', $city->getKey())
            ->assertHasErrors(['management'])
            ->assertSee('Publish the parent destination first');
        $this->assertSame(PublicationStatus::Draft, $city->fresh()->status);

        $component->call('togglePublished', $country->getKey())->assertHasNoErrors()->assertSee('Destination published.');
        $this->assertSame(PublicationStatus::Published, $country->fresh()->status);
        $this->assertNotNull($country->fresh()->published_at);

        $component->call('togglePublished', $city->getKey())->assertHasNoErrors();
        $this->assertSame(PublicationStatus::Published, $city->fresh()->status);

        $component->assertSee('Unpublish its published child destinations first')
            ->call('togglePublished', $country->getKey())
            ->assertHasErrors(['management']);
        $this->assertSame(PublicationStatus::Published, $country->fresh()->status);

        $component->call('toggleActive', $city->getKey())->assertHasErrors(['management'])->assertSee('Unpublish this destination before making it inactive.');
        $component->call('togglePublished', $city->getKey())->assertHasNoErrors()->assertSee('Destination unpublished.');
        $this->assertSame(PublicationStatus::Draft, $city->fresh()->status);

        Livewire::actingAs($this->editor)->test(DestinationManager::class)->call('togglePublished', $country->getKey())->assertForbidden();
        Livewire::actingAs($this->agent)->test(DestinationManager::class)->call('openDetails', $country->getKey())->assertHasNoErrors()->assertSee('Egypt')->assertDontSee('Add cover');
    }

    /** The destination details dialog shows the record, its cover and gallery, and editors keep the media controls there. */
    public function test_destination_details_dialog_carries_images_and_relations(): void
    {
        Storage::fake('public');
        $country = Destination::factory()->create(['name' => 'Kenya', 'slug' => 'kenya', 'country_code' => 'KE', 'type' => 'country', 'status' => PublicationStatus::Published, 'is_active' => true, 'published_at' => now()->subDay(), 'short_description' => 'Savannah and coast']);
        $city = Destination::factory()->create(['name' => 'Nairobi', 'slug' => 'nairobi', 'country_code' => 'KE', 'type' => 'city', 'parent_id' => $country->getKey(), 'status' => PublicationStatus::Published, 'is_active' => true]);
        $tour = Tour::factory()->create(['name' => 'Kenya Highlights', 'status' => PublicationStatus::Published, 'published_at' => now()->subDay()]);
        $tour->destinations()->attach($country->getKey(), ['role' => 'primary', 'sequence' => 1, 'is_overnight' => true]);
        $media = app(CatalogMediaService::class);
        $media->replaceDestinationCover($country, UploadedFile::fake()->image('kenya-cover.jpg'), 'Kenya cover', null);
        $media->addDestinationGalleryImage($country, UploadedFile::fake()->image('coast.jpg'), 'Diani coast', null);

        Livewire::actingAs($this->manager)->test(DestinationManager::class)
            ->assertSeeHtml('class="travel-admin-index">1</td>')
            ->assertDontSee('ti-eye-off')
            ->call('openDetails', $country->getKey())
            ->assertSet('dialog', 'details')
            ->assertSee('Savannah and coast')
            ->assertSee('Kenya cover')
            ->assertSee('Diani coast')
            ->assertSee('Replace cover')
            ->assertSee('Add gallery image')
            ->assertSee('Nairobi')
            ->assertSee('Kenya Highlights')
            ->assertSee('Visited by 1 published tour');
    }

    /** The sales switch opens and closes sales and reports why it cannot; the eye opens a details dialog. */
    public function test_departure_sales_switch_and_details_dialog(): void
    {
        $tour = Tour::factory()->create(['status' => PublicationStatus::Published, 'published_at' => now()->subDay()]);
        $plan = TourRatePlan::factory()->create(['tour_id' => $tour->getKey(), 'is_default' => true, 'is_active' => true, 'is_public' => true, 'currency' => 'KES']);
        ParticipantRate::factory()->create(['rate_plan_id' => $plan->getKey(), 'participant_type' => ParticipantType::Adult, 'amount_minor' => 10_000_00, 'is_active' => true]);
        $departure = TourDeparture::factory()->create(['tour_id' => $tour->getKey(), 'rate_plan_id' => $plan->getKey(), 'status' => DepartureStatus::Draft, 'meeting_instructions' => 'Meet at the lobby at 07:00.']);
        $stale = TourDeparture::factory()->create(['tour_id' => $tour->getKey(), 'rate_plan_id' => $plan->getKey(), 'status' => DepartureStatus::Draft, 'starts_at' => now()->subDays(10), 'ends_at' => now()->subDays(5)]);

        $component = Livewire::actingAs($this->manager)->test(DepartureManager::class)
            ->assertSeeHtml('class="travel-admin-index">1</td>')
            ->assertSee('Start date has passed')
            ->call('toggleSales', $departure->getKey())->assertHasNoErrors()->assertSee('Sales opened.');
        $this->assertSame(DepartureStatus::Open, $departure->fresh()->status);

        $component->call('toggleSales', $departure->getKey())->assertHasNoErrors()->assertSee('Sales closed.');
        $this->assertSame(DepartureStatus::Closed, $departure->fresh()->status);

        $component->call('toggleSales', $stale->getKey())->assertHasErrors(['schedule']);
        $this->assertSame(DepartureStatus::Draft, $stale->fresh()->status);

        $component->call('openDetails', $departure->getKey())
            ->assertSet('detailsId', $departure->getKey())
            ->assertSee('Meet at the lobby at 07:00.')
            ->assertSee('Capacity and sales')
            ->assertSee('Departure team (0)')
            ->call('closeDetails')
            ->assertSet('detailsId', null);

        Livewire::actingAs($this->agent)->test(DepartureManager::class)->call('toggleSales', $departure->getKey())->assertForbidden();
    }

    /** The inquiry queue filters, assigns, schedules, moves, and annotates through the service and its timeline. */
    public function test_inquiry_manager_owns_the_follow_up_queue(): void
    {
        $fresh = TourInquiry::factory()->create(['reference' => 'INQ-FRESH', 'contact_name' => 'Amina Wanjiru', 'status' => InquiryStatus::New]);
        $overdue = TourInquiry::factory()->create(['reference' => 'INQ-LATE', 'contact_name' => 'Brian Otieno', 'status' => InquiryStatus::InProgress, 'assigned_to' => $this->agent->getKey(), 'follow_up_at' => now()->subDay()]);
        TourInquiry::factory()->create(['reference' => 'INQ-DONE', 'contact_name' => 'Chloe Njeri', 'status' => InquiryStatus::Closed, 'closed_at' => now()]);

        $this->actingAs($this->agent)->withoutVite()->get(route('travel-tours.admin.inquiries.index'))->assertOk()->assertSeeLivewire(InquiryManager::class);

        $component = Livewire::actingAs($this->agent)->test(InquiryManager::class)
            ->assertSee('INQ-FRESH')->assertSee('INQ-LATE')->assertSee('INQ-DONE')
            ->assertSeeHtml('class="travel-admin-index">1</td>')
            ->set('followUpFilter', 'overdue')
            ->assertSee('INQ-LATE')->assertDontSee('INQ-FRESH')
            ->set('followUpFilter', '')
            ->set('ownerFilter', 'unassigned')
            ->assertSee('INQ-FRESH')->assertDontSee('INQ-LATE')
            ->set('ownerFilter', 'mine')
            ->assertSee('INQ-LATE')->assertDontSee('INQ-FRESH')
            ->set('ownerFilter', '')
            ->set('search', 'Chloe')
            ->assertSee('INQ-DONE')->assertDontSee('INQ-FRESH')
            ->call('clearFilters')
            ->assertSet('search', '');

        $component->call('assignToMe', $fresh->getKey())->assertHasNoErrors()->assertSee('assigned to you');
        $fresh->refresh();
        $this->assertSame($this->agent->getKey(), $fresh->assigned_to);
        $this->assertSame(InquiryStatus::Assigned, $fresh->status);
        $this->assertSame(1, $fresh->activities()->where('activity_type', 'assignment')->count());

        $component->call('openDetails', $fresh->getKey())
            ->assertSet('dialog', 'details')
            ->assertSet('assigneeId', (string) $this->agent->getKey())
            ->assertSee('Amina Wanjiru')
            ->set('activityType', 'call')->set('activityNote', 'Called back and confirmed dates.')
            ->call('addActivity')->assertHasNoErrors()
            ->assertSee('Called back and confirmed dates.')
            ->set('status', InquiryStatus::AwaitingCustomer->value)
            ->set('followUpAt', now()->addDays(2)->format('Y-m-d\TH:i'))
            ->call('saveFollowUp')->assertHasNoErrors()
            ->assertSee('updated');
        $fresh->refresh();
        $this->assertSame(InquiryStatus::AwaitingCustomer, $fresh->status);
        $this->assertNotNull($fresh->responded_at);
        $this->assertNotNull($fresh->follow_up_at);

        $component->set('status', InquiryStatus::Closed->value)->call('saveFollowUp')->assertHasNoErrors();
        $fresh->refresh();
        $this->assertSame(InquiryStatus::Closed, $fresh->status);
        $this->assertNotNull($fresh->closed_at);
        $this->assertNull($fresh->follow_up_at);

        $component->set('status', InquiryStatus::Assigned->value)->call('saveFollowUp')->assertHasErrors(['management'])->assertSee('Reopen a closed inquiry');
        $component->set('status', InquiryStatus::Converted->value)->call('saveFollowUp')->assertHasErrors(['status']);

        $viewer = $this->operator(TravelToursRole::TOUR_EDITOR);
        Livewire::actingAs($viewer)->test(InquiryManager::class)->assertForbidden();
        $this->assertSame(InquiryStatus::InProgress, $overdue->fresh()->status);
    }

    /** Create an active non-administrator operator holding one module role. */
    private function operator(string $role): User
    {
        $user = User::factory()->create(['user_type' => UserType::Viewer, 'is_active' => true]);
        $user->assignRole($role);

        return $user->fresh();
    }
}
