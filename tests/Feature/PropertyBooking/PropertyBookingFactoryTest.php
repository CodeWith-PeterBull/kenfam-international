<?php

declare(strict_types=1);

namespace Tests\Feature\PropertyBooking;

use App\Modules\PropertyBooking\Availability\Models\AvailabilityBlock;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Bookings\Models\BookingCharge;
use App\Modules\PropertyBooking\Bookings\Models\BookingPayment;
use App\Modules\PropertyBooking\Bookings\Models\BookingStay;
use App\Modules\PropertyBooking\Bookings\Models\UnitAssignment;
use App\Modules\PropertyBooking\Catalog\Models\AccommodationUnit;
use App\Modules\PropertyBooking\Catalog\Models\Amenity;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Catalog\Models\PropertyCategory;
use App\Modules\PropertyBooking\Catalog\Models\UnitType;
use App\Modules\PropertyBooking\Database\Seeders\PropertyBookingDemoSeeder;
use App\Modules\PropertyBooking\Guests\Models\Guest;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionRegister;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionShift;
use App\Modules\PropertyBooking\Pricing\Models\RateOverride;
use App\Modules\PropertyBooking\Pricing\Models\RatePlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

/** Verifies valid-by-default factories and idempotent opt-in demonstration data. */
final class PropertyBookingFactoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_every_foundation_model_factory_creates_a_valid_relationship_graph(): void
    {
        $models = [
            PropertyCategory::factory()->create(),
            Property::factory()->create(),
            Amenity::factory()->create(),
            UnitType::factory()->create(),
            AccommodationUnit::factory()->create(),
            RatePlan::factory()->create(),
            RateOverride::factory()->create(),
            AvailabilityBlock::factory()->create(),
            Guest::factory()->create(),
            ReceptionRegister::factory()->create(),
            ReceptionShift::factory()->create(),
            Booking::factory()->create(),
            BookingStay::factory()->create(),
            UnitAssignment::factory()->create(),
            BookingCharge::factory()->create(),
            BookingPayment::factory()->create(),
        ];

        foreach ($models as $model) {
            $this->assertTrue($model->exists, $model::class.' factory did not persist a model.');
        }

        $unit = collect($models)->first(fn ($model): bool => $model instanceof AccommodationUnit);
        $rate = collect($models)->first(fn ($model): bool => $model instanceof RatePlan);
        $shift = collect($models)->first(fn ($model): bool => $model instanceof ReceptionShift);
        $assignment = collect($models)->first(fn ($model): bool => $model instanceof UnitAssignment);

        $this->assertSame($unit->property_id, $unit->unitType->property_id);
        $this->assertSame($rate->property_id, $rate->unitType->property_id);
        $this->assertSame($shift->property_id, $shift->register->property_id);
        $this->assertSame($assignment->booking_id, $assignment->bookingStay->booking_id);
        $this->assertSame($assignment->property_id, $assignment->booking->property_id);
        $this->assertSame($assignment->unit->unit_type_id, $assignment->bookingStay->unit_type_id);
    }

    public function test_optional_demo_seeder_is_safe_to_rerun_and_is_not_root_coupled(): void
    {
        $this->seed(PropertyBookingDemoSeeder::class);
        $counts = [
            'categories' => PropertyCategory::query()->count(),
            'properties' => Property::query()->count(),
            'amenities' => Amenity::query()->count(),
            'unit_types' => UnitType::query()->count(),
            'units' => AccommodationUnit::query()->count(),
            'rate_plans' => RatePlan::query()->count(),
            'rate_overrides' => RateOverride::query()->count(),
            'availability_blocks' => AvailabilityBlock::query()->count(),
            'guests' => Guest::query()->count(),
            'registers' => ReceptionRegister::query()->count(),
            'shifts' => ReceptionShift::query()->count(),
            'bookings' => Booking::query()->count(),
            'stays' => BookingStay::query()->count(),
            'unit_assignments' => UnitAssignment::query()->count(),
            'guest_assignments' => DB::table('property_booking_guest_assignments')->count(),
            'charges' => BookingCharge::query()->count(),
            'payments' => BookingPayment::query()->count(),
            'media' => Media::query()->count(),
        ];

        $this->seed(PropertyBookingDemoSeeder::class);

        $this->assertSame($counts, [
            'categories' => PropertyCategory::query()->count(),
            'properties' => Property::query()->count(),
            'amenities' => Amenity::query()->count(),
            'unit_types' => UnitType::query()->count(),
            'units' => AccommodationUnit::query()->count(),
            'rate_plans' => RatePlan::query()->count(),
            'rate_overrides' => RateOverride::query()->count(),
            'availability_blocks' => AvailabilityBlock::query()->count(),
            'guests' => Guest::query()->count(),
            'registers' => ReceptionRegister::query()->count(),
            'shifts' => ReceptionShift::query()->count(),
            'bookings' => Booking::query()->count(),
            'stays' => BookingStay::query()->count(),
            'unit_assignments' => UnitAssignment::query()->count(),
            'guest_assignments' => DB::table('property_booking_guest_assignments')->count(),
            'charges' => BookingCharge::query()->count(),
            'payments' => BookingPayment::query()->count(),
            'media' => Media::query()->count(),
        ]);
        $this->assertSame([
            'categories' => 3,
            'properties' => 1,
            'amenities' => 4,
            'unit_types' => 3,
            'units' => 7,
            'rate_plans' => 3,
            'rate_overrides' => 3,
            'availability_blocks' => 1,
            'guests' => 2,
            'registers' => 1,
            'shifts' => 1,
            'bookings' => 2,
            'stays' => 2,
            'unit_assignments' => 2,
            'guest_assignments' => 2,
            'charges' => 1,
            'payments' => 2,
            'media' => 11,
        ], $counts);
        $property = Property::query()->where('code', 'AUREON-CITY')->firstOrFail();
        $this->assertTrue($property->hasMedia('property_cover'));
        $this->assertCount(4, $property->getMedia('property_gallery'));
        $this->assertSame(1, UnitAssignment::query()->active()->count());
        $this->assertSame(1, ReceptionShift::query()->where('status', 'closed')->count());
        $this->assertStringNotContainsString('PropertyBookingDemoSeeder', (string) file_get_contents(database_path('seeders/DatabaseSeeder.php')));
    }
}
