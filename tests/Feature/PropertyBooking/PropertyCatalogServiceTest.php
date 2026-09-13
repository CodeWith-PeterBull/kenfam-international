<?php

declare(strict_types=1);

namespace Tests\Feature\PropertyBooking;

use App\Models\User;
use App\Modules\PropertyBooking\Catalog\Enums\AmenityScope;
use App\Modules\PropertyBooking\Catalog\Enums\PropertyStatus;
use App\Modules\PropertyBooking\Catalog\Enums\UnitOperationalStatus;
use App\Modules\PropertyBooking\Catalog\Exceptions\CatalogException;
use App\Modules\PropertyBooking\Catalog\Models\AccommodationUnit;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Catalog\Models\PropertyCategory;
use App\Modules\PropertyBooking\Catalog\Models\UnitType;
use App\Modules\PropertyBooking\Catalog\Services\AccommodationUnitService;
use App\Modules\PropertyBooking\Catalog\Services\AmenityService;
use App\Modules\PropertyBooking\Catalog\Services\PropertyCategoryService;
use App\Modules\PropertyBooking\Catalog\Services\PropertyService;
use App\Modules\PropertyBooking\Pricing\Enums\DepositType;
use App\Modules\PropertyBooking\Pricing\Enums\StayPricingUnit;
use App\Modules\PropertyBooking\Pricing\Exceptions\RateConfigurationException;
use App\Modules\PropertyBooking\Pricing\Models\RatePlan;
use App\Modules\PropertyBooking\Pricing\Services\RatePlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Verifies catalog hierarchy, lifecycle, readiness, and pricing invariants. */
final class PropertyCatalogServiceTest extends TestCase
{
    use RefreshDatabase;

    /** Reject category cycles and incompatible amenity scope changes. */
    public function test_category_and_amenity_services_preserve_assignment_integrity(): void
    {
        $actor = User::factory()->create();
        $categories = app(PropertyCategoryService::class);
        $amenities = app(AmenityService::class);
        $root = $categories->create(['name' => 'Apartments'], $actor);
        $child = $categories->create(['name' => 'Serviced apartments', 'parent_id' => $root->id], $actor);

        try {
            $categories->update($root, ['name' => $root->name, 'slug' => $root->slug, 'parent_id' => $child->id], $actor);
            $this->fail('A category cycle must be rejected.');
        } catch (CatalogException $exception) {
            $this->assertStringContainsString('cycle', $exception->getMessage());
        }

        $property = Property::factory()->create();
        $amenity = $amenities->create([
            'name' => 'Accessible parking',
            'scope' => AmenityScope::Both,
            'is_active' => true,
        ], $actor);
        $amenities->syncPropertyAmenities($property, [$amenity->id], $actor);

        try {
            $amenities->update($amenity, [
                'name' => $amenity->name,
                'slug' => $amenity->slug,
                'scope' => AmenityScope::Unit,
                'is_active' => true,
            ], $actor);
            $this->fail('An assigned amenity cannot narrow to an incompatible scope.');
        } catch (CatalogException $exception) {
            $this->assertStringContainsString('properties', $exception->getMessage());
        }

        $this->assertSame($root->id, $child->refresh()->parent_id);
        $this->assertTrue($property->refresh()->amenities->contains($amenity));
    }

    /** Enforce publication prerequisites and category visibility dependencies. */
    public function test_property_publication_and_category_visibility_are_coordinated(): void
    {
        $actor = User::factory()->create();
        $category = PropertyCategory::factory()->create(['is_active' => false]);
        $property = Property::factory()->for($category, 'category')->create();
        $properties = app(PropertyService::class);
        $categories = app(PropertyCategoryService::class);

        try {
            $properties->transition($property, PropertyStatus::Published, $actor);
            $this->fail('A property under an inactive category must not be published.');
        } catch (CatalogException $exception) {
            $this->assertStringContainsString('inactive category', $exception->getMessage());
        }

        $category = $categories->setActive($category, true, $actor);
        $published = $properties->transition($property, PropertyStatus::Published, $actor);
        $this->assertSame(PropertyStatus::Published, $published->status);
        $this->assertNotNull($published->published_at);

        try {
            $categories->setActive($category, false, $actor);
            $this->fail('A category containing a published property must remain visible.');
        } catch (CatalogException $exception) {
            $this->assertStringContainsString('published properties', $exception->getMessage());
        }
    }

    /** Enforce property ownership and the explicit concrete-unit readiness state machine. */
    public function test_concrete_unit_service_rejects_cross_property_types_and_invalid_readiness_jumps(): void
    {
        $actor = User::factory()->create();
        $property = Property::factory()->create();
        $otherProperty = Property::factory()->create();
        $unitType = UnitType::factory()->for($property)->create();
        $foreignType = UnitType::factory()->for($otherProperty)->create();
        $service = app(AccommodationUnitService::class);

        try {
            $service->create($property, $foreignType, ['code' => 'FOREIGN-01'], $actor);
            $this->fail('A concrete unit cannot use another property\'s unit type.');
        } catch (CatalogException $exception) {
            $this->assertStringContainsString('different property', $exception->getMessage());
        }

        $unit = $service->create($property, $unitType, ['code' => 'ROOM-101', 'display_name' => 'Room 101'], $actor);
        try {
            $service->transitionReadiness($unit, UnitOperationalStatus::Cleaning, $actor);
            $this->fail('Ready inventory cannot jump directly to cleaning.');
        } catch (CatalogException $exception) {
            $this->assertStringContainsString('cannot move directly', $exception->getMessage());
        }

        $unit = $service->transitionReadiness($unit, UnitOperationalStatus::Dirty, $actor);
        $unit = $service->transitionReadiness($unit, UnitOperationalStatus::Cleaning, $actor);
        $unit = $service->transitionReadiness($unit, UnitOperationalStatus::Ready, $actor);
        $this->assertSame(UnitOperationalStatus::Ready, $unit->operational_status);
        $this->assertNotNull($unit->last_ready_at);
        $this->assertDatabaseHas('system_activities', ['activity_type' => 'property-booking.unit.readiness-changed']);
    }

    /** Enforce exact currency, occupancy, duration, tax, and deposit representation at the service boundary. */
    public function test_rate_plan_service_rejects_inconsistent_deposits_and_persists_exact_minor_units(): void
    {
        $actor = User::factory()->create();
        $property = Property::factory()->create(['currency' => 'KES']);
        $unitType = UnitType::factory()->for($property)->create([
            'maximum_guests' => 2,
            'maximum_adults' => 2,
            'maximum_children' => 1,
        ]);
        $service = app(RatePlanService::class);
        $attributes = [
            'code' => 'FLEX-01',
            'name' => 'Flexible nightly',
            'pricing_unit' => StayPricingUnit::Night,
            'currency' => 'KES',
            'base_rate_minor' => 985_050,
            'included_adults' => 2,
            'included_children' => 0,
            'extra_adult_minor' => 0,
            'extra_child_minor' => 50_000,
            'minimum_units' => 1,
            'maximum_units' => 14,
            'minimum_advance_minutes' => 0,
            'tax_rate_bps' => 1600,
            'is_tax_inclusive' => true,
            'deposit_type' => DepositType::Percentage,
            'deposit_amount_minor' => null,
            'deposit_rate_bps' => 5000,
            'is_refundable' => true,
            'is_public' => true,
            'sort_order' => 0,
        ];

        $rate = $service->create($unitType, $attributes, $actor);
        $this->assertSame(985_050, $rate->base_rate_minor);
        $this->assertSame(5000, $rate->deposit_rate_bps);

        try {
            $service->update($rate, array_merge($attributes, [
                'deposit_type' => DepositType::Fixed,
                'deposit_amount_minor' => 200_000,
                'deposit_rate_bps' => 5000,
            ]), $actor);
            $this->fail('A deposit may not retain both fixed and percentage representations.');
        } catch (RateConfigurationException $exception) {
            $this->assertStringContainsString('deposit configuration', $exception->getMessage());
        }

        $this->assertDatabaseCount((new RatePlan)->getTable(), 1);
        $this->assertSame(DepositType::Percentage, $rate->refresh()->deposit_type);
        $this->assertDatabaseCount((new AccommodationUnit)->getTable(), 0);
    }
}
