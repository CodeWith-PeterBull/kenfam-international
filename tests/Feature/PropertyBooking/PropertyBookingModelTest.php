<?php

declare(strict_types=1);

namespace Tests\Feature\PropertyBooking;

use App\Modules\PropertyBooking\Bookings\Enums\BookingStatus;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Bookings\Models\BookingStay;
use App\Modules\PropertyBooking\Catalog\Enums\PropertyStatus;
use App\Modules\PropertyBooking\Catalog\Enums\UnitOperationalStatus;
use App\Modules\PropertyBooking\Catalog\Models\AccommodationUnit;
use App\Modules\PropertyBooking\Catalog\Models\Amenity;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Catalog\Models\PropertyCategory;
use App\Modules\PropertyBooking\Catalog\Models\UnitType;
use App\Modules\PropertyBooking\Guests\Models\Guest;
use App\Modules\PropertyBooking\Pricing\Enums\StayPricingUnit;
use App\Modules\PropertyBooking\Pricing\Models\RatePlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Verifies model casts, relations, derived values, media, and retention behavior. */
final class PropertyBookingModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_connected_catalog_and_booking_models_expose_typed_domain_state(): void
    {
        $category = PropertyCategory::factory()->create();
        $property = Property::factory()->for($category, 'category')->published()->create();
        $amenity = Amenity::factory()->create();
        $property->amenities()->attach($amenity, ['detail' => 'Complimentary', 'created_at' => now(), 'updated_at' => now()]);
        $unitType = UnitType::factory()->for($property)->published()->create();
        $unit = AccommodationUnit::factory()->forUnitType($unitType)->create();
        $rate = RatePlan::factory()->forUnitType($unitType)->active()->nightly()->create();
        $guest = Guest::factory()->create();
        $booking = Booking::factory()->forProperty($property)->forGuest($guest)->confirmed()->create([
            'total_minor' => 125_000,
            'paid_minor' => 25_000,
        ]);
        $stay = BookingStay::factory()->create([
            'booking_id' => $booking->id,
            'unit_type_id' => $unitType->id,
            'rate_plan_id' => $rate->id,
            'starts_at' => $booking->starts_at,
            'ends_at' => $booking->ends_at,
            'pricing_unit' => StayPricingUnit::Night,
            'unit_type_name' => $unitType->name,
            'unit_type_code' => $unitType->code,
            'rate_plan_name' => $rate->name,
            'rate_plan_code' => $rate->code,
        ]);
        $booking->guests()->attach($guest, [
            'booking_stay_id' => $stay->id,
            'is_primary' => true,
            'primary_booking_guard' => $booking->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(PropertyStatus::Published, $property->status);
        $this->assertSame(UnitOperationalStatus::Ready, $unit->operational_status);
        $this->assertSame(StayPricingUnit::Night, $rate->pricing_unit);
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame(100_000, $booking->balance_minor);
        $this->assertTrue($property->unitTypes->contains($unitType));
        $this->assertTrue($unitType->units->contains($unit));
        $this->assertTrue($unitType->ratePlans->contains($rate));
        $this->assertTrue($booking->stays->contains($stay));
        $this->assertTrue($booking->guests->contains($guest));
        $this->assertSame('Complimentary', $property->amenities->first()->pivot->detail);
        $this->assertSame($guest->first_name.' '.$guest->last_name, $guest->fullName());
    }

    public function test_media_collections_use_integer_polymorphic_keys_and_soft_deletes_retain_records(): void
    {
        Storage::fake('public');
        $property = Property::factory()->create();
        $unitType = UnitType::factory()->for($property)->create();

        $cover = $property->addMedia(UploadedFile::fake()->image('property.webp', 900, 600))->toMediaCollection('property_cover');
        $unitType->addMedia(UploadedFile::fake()->image('room.jpg', 900, 600))->toMediaCollection('unit_type_gallery');

        $this->assertSame($property->id, $cover->model_id);
        $this->assertSame(Property::class, $cover->model_type);
        $this->assertTrue($property->hasMedia('property_cover'));
        $this->assertTrue($unitType->hasMedia('unit_type_gallery'));

        $property->delete();
        $this->assertSoftDeleted('property_booking_properties', ['id' => $property->id]);
    }

    public function test_service_controlled_snapshot_and_identity_fields_are_not_mass_assignable(): void
    {
        $booking = new Booking;
        $guest = new Guest;
        $guest->fill(['identity_number_ciphertext' => 'secret', 'identity_number_hash' => 'hash']);

        $this->assertNotContains('status', $booking->getFillable());
        $this->assertNotContains('total_minor', $booking->getFillable());
        $this->assertNotContains('identity_number_ciphertext', $guest->getFillable());
        $this->assertNotContains('identity_number_hash', $guest->getFillable());
        $this->assertNull($guest->identity_number_ciphertext);
        $this->assertNull($guest->identity_number_hash);
    }
}
