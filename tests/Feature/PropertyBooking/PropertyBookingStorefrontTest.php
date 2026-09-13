<?php

declare(strict_types=1);

namespace Tests\Feature\PropertyBooking;

use App\Modules\PropertyBooking\Catalog\Enums\PropertyStatus;
use App\Modules\PropertyBooking\Catalog\Models\AccommodationUnit;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Catalog\Models\PropertyCategory;
use App\Modules\PropertyBooking\Catalog\Models\UnitType;
use App\Modules\PropertyBooking\Pricing\Models\RatePlan;
use App\Modules\PropertyBooking\Storefront\Livewire\AvailabilityBrowser;
use App\Modules\PropertyBooking\Storefront\Livewire\PropertyAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Verifies published discovery, scoped detail routes, and public privacy. */
final class PropertyBookingStorefrontTest extends TestCase
{
    use RefreshDatabase;

    /** Render the shell, current result, institutional controls, and independent assets. */
    public function test_catalog_renders_the_availability_workspace_and_published_property(): void
    {
        [$property] = $this->inventory('Aureon Harbour Suites', 'aureon-harbour-suites');

        $this->get(route('property-booking.storefront.catalog.index'))
            ->assertOk()
            ->assertSeeLivewire('property-booking.storefront.availability-browser')
            ->assertSee('Find your next place to stay')
            ->assertSee($property->name)
            ->assertSee('Theme settings')
            ->assertDontSee('commerce::');

        Livewire::test(AvailabilityBrowser::class)
            ->assertSee($property->name)
            ->set('form.location', 'Nairobi')
            ->call('search')
            ->assertHasNoErrors()
            ->assertSee($property->name);
    }

    /** Exclude draft, scheduled, and inactive-category properties from every public entry. */
    public function test_non_public_property_states_are_absent_and_direct_links_are_not_found(): void
    {
        [$visible] = $this->inventory('Visible Residence', 'visible-residence');
        [$draft] = $this->inventory('Draft Residence', 'draft-residence', PropertyStatus::Draft);
        [$scheduled] = $this->inventory('Scheduled Residence', 'scheduled-residence');
        $scheduled->forceFill(['published_at' => now()->addDay()])->save();
        [$inactive] = $this->inventory('Inactive Category Residence', 'inactive-category-residence');
        $inactive->category->forceFill(['is_active' => false])->save();

        Livewire::test(AvailabilityBrowser::class)
            ->assertSee($visible->name)
            ->assertDontSee($draft->name)
            ->assertDontSee($scheduled->name)
            ->assertDontSee($inactive->name);

        foreach ([$draft, $scheduled, $inactive] as $hidden) {
            $this->get(route('property-booking.storefront.properties.show', ['property' => $hidden->slug]))->assertNotFound();
        }
    }

    /** Enforce parent scope while rendering image-first property and unit pages. */
    public function test_property_and_unit_routes_use_slugs_and_enforce_nested_ownership(): void
    {
        [$property, $unitType, , $unit] = $this->inventory('Canvas House', 'canvas-house');
        [$otherProperty, $otherUnitType] = $this->inventory('Other House', 'other-house');

        $propertyUrl = route('property-booking.storefront.properties.show', ['property' => $property->slug]);
        $unitUrl = route('property-booking.storefront.units.show', ['property' => $property->slug, 'unitType' => $unitType->slug]);
        $this->assertStringContainsString('/stays/'.$property->slug, $propertyUrl);
        $this->assertStringNotContainsString($property->ulid, $propertyUrl);

        $this->get($propertyUrl)
            ->assertOk()
            ->assertSee('data-stay-gallery', false)
            ->assertSee($unitType->name)
            ->assertDontSee($unit->code);
        $this->get($unitUrl)
            ->assertOk()
            ->assertSee('About this space')
            ->assertSeeLivewire('property-booking.storefront.property-availability')
            ->assertDontSee($unit->display_name);
        $this->get(route('property-booking.storefront.units.show', [
            'property' => $otherProperty->slug,
            'unitType' => $unitType->slug,
        ]))->assertNotFound();
        $this->assertNotSame($unitType->property_id, $otherUnitType->property_id);
    }

    /** Fail closed to an empty result instead of a server error for malformed URL intervals. */
    public function test_malformed_or_reversed_query_intervals_do_not_crash_the_catalog(): void
    {
        [$property] = $this->inventory('Resilient Stay', 'resilient-stay');

        $this->get(route('property-booking.storefront.catalog.index', [
            'arrival' => 'not-a-date',
            'departure' => 'also-invalid',
            'arrival_time' => '99:99',
        ]))->assertOk();

        Livewire::withQueryParams([
            'arrival' => now()->addDays(3)->toDateString(),
            'departure' => now()->addDays(3)->toDateString(),
            'arrival_time' => '18:00',
            'departure_time' => '10:00',
        ])->test(AvailabilityBrowser::class)
            ->assertHasErrors('availability')
            ->assertSee('Departure must be after arrival');

        Livewire::withQueryParams([
            'arrival' => now()->addDays(3)->toDateString(),
            'departure' => now()->addDays(3)->toDateString(),
            'arrival_time' => '18:00',
            'departure_time' => '10:00',
        ])->test(PropertyAvailability::class, ['propertyId' => $property->id])
            ->assertHasErrors('availability')
            ->assertSee('Departure must be after arrival');
    }

    /** @return array{Property, UnitType, RatePlan, AccommodationUnit} */
    private function inventory(string $name, string $slug, PropertyStatus $status = PropertyStatus::Published): array
    {
        $category = PropertyCategory::factory()->create();
        $property = Property::factory()->for($category, 'category')->create([
            'name' => $name,
            'slug' => $slug,
            'status' => $status,
            'published_at' => $status === PropertyStatus::Published ? now() : null,
            'city' => 'Nairobi',
            'minimum_notice_minutes' => 0,
        ]);
        $unitType = UnitType::factory()->for($property)->published()->create([
            'maximum_guests' => 4,
            'maximum_adults' => 2,
            'maximum_children' => 2,
        ]);
        $ratePlan = RatePlan::factory()->forUnitType($unitType)->active()->create([
            'base_rate_minor' => 120_000,
            'included_adults' => 2,
            'included_children' => 2,
            'extra_adult_minor' => 0,
            'extra_child_minor' => 0,
            'tax_rate_bps' => 0,
        ]);
        $unit = AccommodationUnit::factory()->forUnitType($unitType)->create();

        return [$property, $unitType, $ratePlan, $unit];
    }
}
