<?php

/** Verifies K2D itinerary and experience editing through services and Livewire. */

declare(strict_types=1);

namespace Tests\Feature\TravelTours;

use App\Enums\UserType;
use App\Models\User;
use App\Modules\TravelTours\Catalog\Data\ItineraryActivityData;
use App\Modules\TravelTours\Catalog\Data\ItineraryDayData;
use App\Modules\TravelTours\Catalog\Data\TourContentItemData;
use App\Modules\TravelTours\Catalog\Data\TourExtraData;
use App\Modules\TravelTours\Catalog\Data\TourFaqData;
use App\Modules\TravelTours\Catalog\Enums\ContentItemType;
use App\Modules\TravelTours\Catalog\Enums\PublicationStatus;
use App\Modules\TravelTours\Catalog\Exceptions\CatalogException;
use App\Modules\TravelTours\Catalog\Livewire\Admin\TourEditor;
use App\Modules\TravelTours\Catalog\Livewire\Admin\TourExperienceEditor;
use App\Modules\TravelTours\Catalog\Livewire\Admin\TourItineraryEditor;
use App\Modules\TravelTours\Catalog\Models\Destination;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Catalog\Models\TourExtra;
use App\Modules\TravelTours\Catalog\Services\TourContentService;
use App\Modules\TravelTours\Catalog\Services\TourItineraryService;
use App\Modules\TravelTours\Database\Seeders\TravelToursAccessSeeder;
use App\Modules\TravelTours\Support\TravelToursRole;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Verifies child ownership, ordered content, exact money and authorized UI. */
final class TravelToursTourContentTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    private User $viewer;

    /** Seed only the module role catalogue and test users. */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TravelToursAccessSeeder::class);
        $this->editor = User::factory()->create(['is_active' => true, 'user_type' => UserType::Editor]);
        $this->editor->assignRole(TravelToursRole::TOUR_EDITOR);
        $this->viewer = User::factory()->create(['is_active' => true]);
        $this->viewer->assignRole(TravelToursRole::BOOKING_AGENT);
    }

    /** Show both implemented tabs only after a tour exists. */
    public function test_tour_editor_exposes_itinerary_and_experience_tabs_for_existing_tours(): void
    {
        $tour = Tour::factory()->create();
        Livewire::actingAs($this->editor)->test(TourEditor::class, ['tourId' => $tour->id])
            ->call('switchTab', 'itinerary')->assertSeeLivewire(TourItineraryEditor::class)
            ->call('switchTab', 'experience')->assertSeeLivewire(TourExperienceEditor::class);
        Livewire::actingAs($this->editor)->test(TourEditor::class)->call('switchTab', 'itinerary')->assertNotFound();
    }

    /** Keep day numbers consecutive while rejecting foreign children. */
    public function test_days_append_reorder_remove_and_never_cross_tours(): void
    {
        $tour = Tour::factory()->create(['duration_days' => 2]);
        $other = Tour::factory()->create();
        $service = app(TourItineraryService::class);
        $first = $service->saveDay($tour, new ItineraryDayData('Arrival'));
        $second = $service->saveDay($tour, new ItineraryDayData('Departure'));
        $this->assertSame([1, 2], $tour->itineraryDays()->pluck('day_number')->all());

        $service->moveDay($tour, $second->id, 'up');
        $this->assertSame([$second->id, $first->id], $tour->itineraryDays()->pluck('id')->all());
        $service->removeDay($tour, $second->id);
        $this->assertDatabaseHas('travel_itinerary_days', ['id' => $first->id, 'tour_id' => $tour->id, 'day_number' => 1]);
        $this->assertDatabaseMissing('travel_itinerary_days', ['id' => $second->id]);

        $this->expectException(ModelNotFoundException::class);
        $service->removeDay($other, $first->id);
    }

    /** Reject days beyond duration and inactive destination references. */
    public function test_itinerary_validates_duration_and_active_destinations_before_writing(): void
    {
        $tour = Tour::factory()->create(['duration_days' => 1]);
        $service = app(TourItineraryService::class);
        $service->saveDay($tour, new ItineraryDayData('One'));
        try {
            $service->saveDay($tour, new ItineraryDayData('Two'));
            $this->fail('A second day must not fit a one-day tour.');
        } catch (CatalogException $exception) {
            $this->assertStringContainsString('duration', $exception->getMessage());
        }
        $inactive = Destination::factory()->create(['is_active' => false]);
        try {
            $service->saveDay($tour, new ItineraryDayData('One', startDestinationId: $inactive->id), $tour->itineraryDays()->firstOrFail()->id);
            $this->fail('An inactive destination must be rejected.');
        } catch (CatalogException $exception) {
            $this->assertStringContainsString('active', $exception->getMessage());
        }
        $this->assertSame(1, $tour->itineraryDays()->count());
    }

    /** Refuse to shorten base tour duration below its persisted itinerary. */
    public function test_tour_basics_cannot_contract_below_existing_itinerary(): void
    {
        $tour = Tour::factory()->create(['duration_days' => 2, 'duration_nights' => 1]);
        $service = app(TourItineraryService::class);
        $service->saveDay($tour, new ItineraryDayData('First day'));
        $service->saveDay($tour, new ItineraryDayData('Second day'));

        Livewire::actingAs($this->editor)->test(TourEditor::class, ['tourId' => $tour->id])
            ->set('form.durationDays', 1)
            ->set('form.durationNights', 1)
            ->call('saveBasics')
            ->assertHasErrors('management');

        $this->assertSame(2, $tour->refresh()->duration_days);
    }

    /** Keep activity sequence scoped to its day, including after removal. */
    public function test_activities_append_move_remove_and_reject_foreign_days(): void
    {
        $tour = Tour::factory()->create();
        $other = Tour::factory()->create();
        $service = app(TourItineraryService::class);
        $day = $service->saveDay($tour, new ItineraryDayData('Nile day'));
        $first = $service->saveActivity($tour, $day->id, new ItineraryActivityData('Museum', startsAtLocal: '09:00'));
        $second = $service->saveActivity($tour, $day->id, new ItineraryActivityData('Cruise', startsAtLocal: '14:00'));
        $service->moveActivity($tour, $day->id, $second->id, 'up');
        $this->assertSame([$second->id, $first->id], $day->activities()->pluck('id')->all());
        $service->removeActivity($tour, $day->id, $second->id);
        $this->assertDatabaseHas('travel_itinerary_activities', ['id' => $first->id, 'sequence' => 1]);
        $this->expectException(ModelNotFoundException::class);
        $service->saveActivity($other, $day->id, new ItineraryActivityData('Wrong tour'));
    }

    /** Preserve a published itinerary's last remaining day. */
    public function test_published_tour_cannot_lose_its_only_itinerary_day(): void
    {
        $tour = Tour::factory()->create(['status' => PublicationStatus::Published]);
        $service = app(TourItineraryService::class);
        $day = $service->saveDay($tour, new ItineraryDayData('Public day'));
        $this->expectException(CatalogException::class);
        $service->removeDay($tour, $day->id);
    }

    /** Keep typed experience items and FAQs tour-owned and orderable. */
    public function test_content_items_and_faqs_persist_and_order_without_cross_tour_edits(): void
    {
        $tour = Tour::factory()->create();
        $other = Tour::factory()->create();
        $service = app(TourContentService::class);
        $first = $service->saveItem($tour, new TourContentItemData(ContentItemType::Highlight, 'Pyramids'));
        $second = $service->saveItem($tour, new TourContentItemData(ContentItemType::Highlight, 'Nile cruise'));
        $service->moveItem($tour, $second->id, 'up');
        $this->assertSame([$second->id, $first->id], $tour->contentItems()->pluck('id')->all());
        $faq = $service->saveFaq($tour, new TourFaqData('Is lunch included?', 'Yes.', false));
        $this->assertDatabaseHas('travel_tour_faqs', ['id' => $faq->id, 'is_active' => false]);
        $service->removeFaq($tour, $faq->id);
        $this->assertDatabaseMissing('travel_tour_faqs', ['id' => $faq->id]);
        $this->expectException(ModelNotFoundException::class);
        $service->removeItem($other, $first->id);
    }

    /** Parse extra prices to integer cents and retain archived code reservations. */
    public function test_extras_use_exact_minor_units_and_archive_instead_of_deleting(): void
    {
        $tour = Tour::factory()->create();
        $component = Livewire::actingAs($this->editor)->test(TourExperienceEditor::class, ['tourId' => $tour->id]);
        $component->call('open', 'extra')
            ->set('extraForm.code', 'HOT-AIR')
            ->set('extraForm.name', 'Balloon ride')
            ->set('extraForm.amount', '1234.56')
            ->set('extraForm.currency', 'KES')
            ->call('save')->assertHasNoErrors();
        $extra = TourExtra::query()->where('tour_id', $tour->id)->firstOrFail();
        $this->assertSame(123456, $extra->amount_minor);
        $this->assertSame($this->editor->id, $extra->created_by);
        $component->call('remove', 'extra', $extra->id)->assertHasNoErrors();
        $this->assertSoftDeleted('travel_tour_extras', ['id' => $extra->id]);
        $this->expectException(CatalogException::class);
        app(TourContentService::class)->saveExtra($tour, new TourExtraData('HOT-AIR', 'Duplicate', 'per_person', 1, 'KES'), $this->editor);
    }

    /** Exercise itinerary and experience save actions through Livewire Forms. */
    public function test_editors_save_day_activity_item_and_faq_through_livewire(): void
    {
        $tour = Tour::factory()->create();
        Livewire::actingAs($this->editor)->test(TourItineraryEditor::class, ['tourId' => $tour->id])
            ->call('openDay')->set('dayForm.title', 'Arrival in Nairobi')->call('saveDay')->assertHasNoErrors();
        $day = $tour->itineraryDays()->firstOrFail();
        Livewire::actingAs($this->editor)->test(TourItineraryEditor::class, ['tourId' => $tour->id])
            ->call('openActivity', $day->id)->set('activityForm.title', 'Hotel transfer')->call('saveActivity')->assertHasNoErrors();
        Livewire::actingAs($this->editor)->test(TourExperienceEditor::class, ['tourId' => $tour->id])
            ->call('open', 'item')->set('itemForm.content', 'Expert local guide')->call('save')->assertHasNoErrors()
            ->call('open', 'faq')->set('faqForm.question', 'What should I bring?')->set('faqForm.answer', 'A valid passport.')->call('save')->assertHasNoErrors();
        $this->assertDatabaseHas('travel_itinerary_activities', ['itinerary_day_id' => $day->id, 'title' => 'Hotel transfer']);
        $this->assertDatabaseHas('travel_tour_content_items', ['tour_id' => $tour->id, 'content' => 'Expert local guide']);
        $this->assertDatabaseHas('travel_tour_faqs', ['tour_id' => $tour->id, 'question' => 'What should I bring?']);
    }

    /** Open every K2D create form in an accessible contextual dialog. */
    public function test_itinerary_and_experience_actions_open_contextual_dialogs(): void
    {
        $tour = Tour::factory()->create();
        $day = app(TourItineraryService::class)->saveDay($tour, new ItineraryDayData('Arrival day'));

        Livewire::actingAs($this->editor)->test(TourItineraryEditor::class, ['tourId' => $tour->id])
            ->call('openDay')->assertSet('editor', 'day')->assertSeeHtml('aria-labelledby="itinerary-day-form-title"')
            ->call('cancel')->call('openActivity', $day->id)->assertSet('editor', 'activity')
            ->assertSet('activityDayId', $day->id)->assertSeeHtml('aria-labelledby="itinerary-activity-form-title"');

        foreach (['item' => 'experience-item-content', 'faq' => 'experience-faq-question', 'extra' => 'experience-extra-code'] as $kind => $fieldId) {
            Livewire::actingAs($this->editor)->test(TourExperienceEditor::class, ['tourId' => $tour->id])
                ->call('open', $kind)->assertSet('editor', $kind)
                ->assertSeeHtml('aria-labelledby="experience-edit-heading"')->assertSeeHtml('id="'.$fieldId.'"');
        }
    }

    /** Forbid view-only actors before any child mutation can reach a service. */
    public function test_viewer_cannot_open_mutable_child_editors(): void
    {
        $tour = Tour::factory()->create();
        Livewire::actingAs($this->viewer)->test(TourItineraryEditor::class, ['tourId' => $tour->id])->assertForbidden();
        Livewire::actingAs($this->viewer)->test(TourExperienceEditor::class, ['tourId' => $tour->id])->assertForbidden();
    }
}
