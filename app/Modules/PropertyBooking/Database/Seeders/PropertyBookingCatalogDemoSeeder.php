<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Database\Seeders;

use App\Models\User;
use App\Modules\PropertyBooking\Catalog\Enums\AmenityScope;
use App\Modules\PropertyBooking\Catalog\Enums\PropertyStatus;
use App\Modules\PropertyBooking\Catalog\Enums\UnitOperationalStatus;
use App\Modules\PropertyBooking\Catalog\Enums\UnitTypeStatus;
use App\Modules\PropertyBooking\Catalog\Models\AccommodationUnit;
use App\Modules\PropertyBooking\Catalog\Models\Amenity;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Catalog\Models\PropertyCategory;
use App\Modules\PropertyBooking\Catalog\Models\UnitType;
use App\Modules\PropertyBooking\PointOfBooking\Enums\ReceiptPaperWidth;
use App\Modules\PropertyBooking\PointOfBooking\Enums\ReceiptPrintMode;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionRegister;
use App\Modules\PropertyBooking\Pricing\Enums\DepositType;
use App\Modules\PropertyBooking\Pricing\Enums\RatePlanStatus;
use App\Modules\PropertyBooking\Pricing\Enums\StayPricingUnit;
use App\Modules\PropertyBooking\Pricing\Models\RatePlan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\MediaLibrary\HasMedia;

/** Seeds deterministic property, unit, amenity, rate, and register fixtures. */
final class PropertyBookingCatalogDemoSeeder extends Seeder
{
    /** Seed the module-owned demonstration records. */
    public function run(): void
    {
        $actor = User::query()->where('email', PropertyBookingAccessDemoSeeder::MANAGER_EMAIL)->firstOrFail();
        $receptionist = User::query()->where('email', PropertyBookingAccessDemoSeeder::RECEPTIONIST_EMAIL)->firstOrFail();

        DB::transaction(function () use ($actor, $receptionist): void {
            $categories = $this->categories();
            $amenities = $this->amenities();
            $property = $this->property($categories['hotel'], $actor);
            $property->amenities()->syncWithoutDetaching([
                $amenities['wi-fi']->getKey() => ['detail' => 'Complimentary throughout the property'],
                $amenities['parking']->getKey() => ['detail' => 'Free secure on-site parking'],
                $amenities['reception']->getKey() => ['detail' => 'Staffed around the clock'],
            ]);

            $this->assign($property, $actor, $actor, true);
            $this->assign($property, $receptionist, $actor, true);

            foreach ($this->unitTypeDefinitions() as $definition) {
                $unitType = $this->unitType($property, $definition, $actor);
                $unitType->amenities()->syncWithoutDetaching([
                    $amenities['wi-fi']->getKey() => ['detail' => 'High-speed private connection'],
                    $amenities['workspace']->getKey() => ['detail' => 'Dedicated desk and task lighting'],
                ]);
                $this->units($property, $unitType, $definition['unit_codes'], $actor);
                $this->ratePlan($property, $unitType, $definition, $actor);
            }

            $register = ReceptionRegister::withTrashed()->where('property_id', $property->getKey())->where('code', 'MAIN-DESK')->first() ?? new ReceptionRegister;
            if ($register->trashed()) {
                $register->restore();
            }
            $register->fill([
                'property_id' => $property->getKey(), 'code' => 'MAIN-DESK', 'name' => 'Main reception desk',
                'location_label' => 'Ground-floor lobby', 'description' => 'Primary Point of Booking demonstration endpoint.',
                'receipt_print_driver' => 'browser', 'receipt_print_mode' => ReceiptPrintMode::Manual,
                'receipt_paper_width' => ReceiptPaperWidth::Roll80, 'receipt_printer_name' => null, 'is_active' => true,
            ]);
            $register->forceFill(['created_by' => $register->created_by ?? $actor->getKey(), 'updated_by' => $actor->getKey()])->save();
        });

        $this->seedMedia();
    }

    /** Attach deterministic local photography without relying on remote URLs. */
    private function seedMedia(): void
    {
        $categories = PropertyCategory::query()->whereIn('slug', ['hotel', 'serviced-apartment', 'guest-house'])->get()->keyBy('slug');
        $property = Property::query()->where('code', 'AUREON-CITY')->firstOrFail();

        $this->syncMedia($categories->get('hotel'), 'category_image', [
            ['path' => 'aureon-city-suites-exterior.webp', 'alt_text' => 'Contemporary city hotel exterior'],
        ]);
        $this->syncMedia($categories->get('serviced-apartment'), 'category_image', [
            ['path' => 'city-studio-apartment.webp', 'alt_text' => 'Serviced city studio apartment'],
        ]);
        $this->syncMedia($categories->get('guest-house'), 'category_image', [
            ['path' => 'family-suite.webp', 'alt_text' => 'Spacious hosted family accommodation'],
        ]);
        $this->syncMedia($property, 'property_cover', [
            ['path' => 'aureon-city-suites-exterior.webp', 'alt_text' => 'Aureon City Suites exterior', 'caption' => 'A calm city base in central Nairobi'],
        ]);
        $this->syncMedia($property, 'property_gallery', [
            ['path' => 'aureon-city-suites-lobby.webp', 'alt_text' => 'Aureon City Suites reception lobby', 'caption' => 'Staffed reception and guest lounge'],
            ['path' => 'deluxe-king-room.webp', 'alt_text' => 'Deluxe King Room at Aureon City Suites', 'caption' => 'King bed and dedicated workspace'],
            ['path' => 'city-studio-apartment.webp', 'alt_text' => 'City Studio Apartment at Aureon City Suites', 'caption' => 'Self-contained studio with kitchenette'],
            ['path' => 'family-suite.webp', 'alt_text' => 'Family Suite at Aureon City Suites', 'caption' => 'Flexible sleeping and lounge zones'],
        ]);

        $unitMedia = [
            'DELUXE-KING' => ['path' => 'deluxe-king-room.webp', 'alt_text' => 'Deluxe King Room with workspace', 'caption' => 'Quiet accommodation for one or two guests'],
            'CITY-STUDIO' => ['path' => 'city-studio-apartment.webp', 'alt_text' => 'City Studio Apartment with kitchenette', 'caption' => 'A self-contained longer-stay studio'],
            'FAMILY-SUITE' => ['path' => 'family-suite.webp', 'alt_text' => 'Family Suite with sleeping and lounge areas', 'caption' => 'A flexible suite for small families'],
        ];

        foreach ($unitMedia as $code => $media) {
            $unitType = UnitType::query()->where('property_id', $property->getKey())->where('code', $code)->firstOrFail();
            $this->syncMedia($unitType, 'unit_type_cover', [$media]);
        }
    }

    /**
     * Repair absent demo media while preserving any usable adopter replacement.
     *
     * @param  list<array{path: string, alt_text: string, caption?: string}>  $definitions
     */
    private function syncMedia(HasMedia $model, string $collection, array $definitions): void
    {
        $existing = $model->getMedia($collection);
        $usable = $existing->isNotEmpty()
            && $existing->every(static fn ($media): bool => is_file($media->getPath()));

        if ($usable) {
            return;
        }

        $model->clearMediaCollection($collection);

        foreach ($definitions as $definition) {
            $path = __DIR__.'/../../Resources/demo/accommodation/'.$definition['path'];
            if (! is_file($path)) {
                throw new RuntimeException("Property Booking demonstration media is missing: {$definition['path']}");
            }

            $model->addMedia($path)
                ->preservingOriginal()
                ->usingName(pathinfo($definition['path'], PATHINFO_FILENAME))
                ->withCustomProperties([
                    'alt_text' => $definition['alt_text'],
                    'caption' => $definition['caption'] ?? null,
                ])
                ->toMediaCollection($collection);
        }
    }

    /** @return array<string, PropertyCategory> */
    private function categories(): array
    {
        $definitions = [
            'hotel' => ['Hotel', 'A staffed accommodation establishment with individually allocated rooms.'],
            'serviced-apartment' => ['Serviced Apartment', 'Self-contained apartments offered for managed short stays.'],
            'guest-house' => ['Guest House', 'A smaller hosted property with private guest accommodation.'],
        ];
        $models = [];
        foreach ($definitions as $slug => [$name, $description]) {
            $models[$slug] = PropertyCategory::query()->updateOrCreate(
                ['slug' => $slug],
                ['parent_id' => null, 'name' => $name, 'description' => $description, 'sort_order' => count($models) * 10, 'is_active' => true],
            );
        }

        return $models;
    }

    /** @return array<string, Amenity> */
    private function amenities(): array
    {
        $definitions = [
            'wi-fi' => ['Wi-Fi', AmenityScope::Both, 'wifi'],
            'parking' => ['Parking', AmenityScope::Property, 'car-front'],
            'reception' => ['24-hour reception', AmenityScope::Property, 'concierge-bell'],
            'workspace' => ['Workspace', AmenityScope::Unit, 'briefcase-business'],
        ];
        $models = [];
        foreach ($definitions as $slug => [$name, $scope, $icon]) {
            $models[$slug] = Amenity::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'scope' => $scope, 'description' => "{$name} available as described.", 'icon_key' => $icon, 'sort_order' => count($models) * 10, 'is_active' => true],
            );
        }

        return $models;
    }

    /** Build the deterministic property fixture. */
    private function property(PropertyCategory $category, User $actor): Property
    {
        $property = Property::withTrashed()->where('code', 'AUREON-CITY')->first() ?? new Property;
        if ($property->trashed()) {
            $property->restore();
        }
        $property->fill([
            'category_id' => $category->getKey(), 'code' => 'AUREON-CITY', 'slug' => 'aureon-city-suites',
            'name' => 'Aureon City Suites', 'status' => PropertyStatus::Published,
            'short_description' => 'Quiet city accommodation designed for business visits and considered short stays.',
            'description' => 'A demonstration property with independently allocatable rooms, rates, amenities, and reception operations.',
            'house_rules' => 'Valid identification is required at check-in. Quiet hours begin at 10:00 PM.',
            'cancellation_summary' => 'Cancellation conditions follow the selected rate plan.',
            'email' => 'stays@aureon.test', 'phone' => '+254700000100', 'whatsapp_phone' => '+254700000100',
            'website_url' => null, 'address_line_1' => '1 Aureon Way', 'address_line_2' => 'Central Business District',
            'city' => 'Nairobi', 'region' => 'Nairobi', 'postal_code' => '00100', 'country_code' => 'KE',
            'latitude' => '-1.2863890', 'longitude' => '36.8172230', 'timezone' => 'Africa/Nairobi', 'currency' => 'KES',
            'check_in_from' => '14:00:00', 'check_in_until' => '22:00:00', 'check_out_from' => '07:00:00',
            'check_out_until' => '11:00:00', 'minimum_notice_minutes' => 60, 'maximum_advance_days' => 365,
            'turnover_minutes' => 60, 'is_featured' => true, 'meta_title' => 'Aureon City Suites',
            'meta_description' => 'Demonstration city suites for the Aureon Property Booking module.', 'published_at' => now()->subMonth(),
        ]);
        $property->forceFill(['created_by' => $property->created_by ?? $actor->getKey(), 'updated_by' => $actor->getKey()])->save();

        return $property;
    }

    /** @param array<string, mixed> $definition */
    private function unitType(Property $property, array $definition, User $actor): UnitType
    {
        $unitType = UnitType::withTrashed()->where('property_id', $property->getKey())->where('code', $definition['code'])->first() ?? new UnitType;
        if ($unitType->trashed()) {
            $unitType->restore();
        }
        $unitType->fill([
            'property_id' => $property->getKey(), 'code' => $definition['code'], 'slug' => $definition['slug'],
            'name' => $definition['name'], 'status' => UnitTypeStatus::Published,
            'short_description' => $definition['description'], 'description' => $definition['description'],
            'size_square_metres' => $definition['size'], 'bedroom_count' => $definition['bedrooms'],
            'bathroom_count' => 1, 'living_room_count' => $definition['living_rooms'], 'bed_count' => $definition['beds'],
            'bed_configuration' => $definition['bed_configuration'], 'maximum_guests' => $definition['guests'],
            'maximum_adults' => $definition['adults'], 'maximum_children' => $definition['children'],
            'maximum_infants' => 1, 'allows_infants_on_top' => true, 'is_entire_unit' => true,
            'smoking_allowed' => false, 'is_featured' => $definition['featured'], 'meta_title' => $definition['name'],
            'meta_description' => $definition['description'], 'published_at' => now()->subMonth(),
        ]);
        $unitType->forceFill(['created_by' => $unitType->created_by ?? $actor->getKey(), 'updated_by' => $actor->getKey()])->save();

        return $unitType;
    }

    /** @param list<string> $codes */
    private function units(Property $property, UnitType $unitType, array $codes, User $actor): void
    {
        foreach ($codes as $code) {
            $unit = AccommodationUnit::withTrashed()->where('property_id', $property->getKey())->where('code', $code)->first() ?? new AccommodationUnit;
            if ($unit->trashed()) {
                $unit->restore();
            }
            $unit->fill([
                'property_id' => $property->getKey(), 'unit_type_id' => $unitType->getKey(), 'code' => $code,
                'display_name' => 'Room '.$code, 'floor_label' => str_starts_with($code, '1') ? 'First floor' : 'Second floor',
                'location_note' => null, 'operational_status' => UnitOperationalStatus::Ready, 'is_active' => true,
                'internal_note' => null, 'last_ready_at' => now(),
            ]);
            $unit->forceFill(['created_by' => $unit->created_by ?? $actor->getKey(), 'updated_by' => $actor->getKey()])->save();
        }
    }

    /** @param array<string, mixed> $definition */
    private function ratePlan(Property $property, UnitType $unitType, array $definition, User $actor): void
    {
        $rate = RatePlan::withTrashed()->where('unit_type_id', $unitType->getKey())->where('code', 'FLEX-NIGHT')->first() ?? new RatePlan;
        if ($rate->trashed()) {
            $rate->restore();
        }
        $rate->fill([
            'property_id' => $property->getKey(), 'unit_type_id' => $unitType->getKey(), 'code' => 'FLEX-NIGHT',
            'name' => 'Flexible nightly rate', 'description' => 'Flexible demonstration rate with a 50 percent deposit.',
            'status' => RatePlanStatus::Active, 'pricing_unit' => StayPricingUnit::Night, 'currency' => $property->currency,
            'base_rate_minor' => $definition['rate_minor'], 'included_adults' => 1, 'included_children' => 0,
            'extra_adult_minor' => 150_000, 'extra_child_minor' => 75_000, 'minimum_units' => 1,
            'maximum_units' => 30, 'minimum_advance_minutes' => 60, 'maximum_advance_days' => 365,
            'tax_rate_bps' => 0, 'is_tax_inclusive' => true, 'deposit_type' => DepositType::Percentage,
            'deposit_amount_minor' => null, 'deposit_rate_bps' => 5000, 'is_refundable' => true,
            'free_cancel_before_minutes' => 1440, 'cancellation_terms' => 'Free cancellation until 24 hours before arrival.',
            'is_public' => true, 'sort_order' => 10, 'published_at' => now()->subMonth(),
        ]);
        $rate->forceFill(['created_by' => $rate->created_by ?? $actor->getKey(), 'updated_by' => $actor->getKey()])->save();
    }

    /** Assign a stable channel-aware booking number. */
    private function assign(Property $property, User $user, User $actor, bool $default): void
    {
        DB::table('property_booking_property_user')->updateOrInsert(
            ['property_id' => $property->getKey(), 'user_id' => $user->getKey()],
            ['assigned_by' => $actor->getKey(), 'is_default' => $default, 'created_at' => now(), 'updated_at' => now()],
        );
    }

    /** @return list<array<string, mixed>> */
    private function unitTypeDefinitions(): array
    {
        return [
            ['code' => 'DELUXE-KING', 'slug' => 'deluxe-king-room', 'name' => 'Deluxe King Room', 'description' => 'A calm king room with a dedicated workspace.', 'size' => '34.00', 'bedrooms' => 1, 'living_rooms' => 0, 'beds' => 1, 'bed_configuration' => [['type' => 'king', 'quantity' => 1]], 'guests' => 2, 'adults' => 2, 'children' => 1, 'featured' => true, 'unit_codes' => ['101', '102', '103'], 'rate_minor' => 1_250_000],
            ['code' => 'CITY-STUDIO', 'slug' => 'city-studio-apartment', 'name' => 'City Studio Apartment', 'description' => 'A self-contained studio for longer business stays.', 'size' => '48.00', 'bedrooms' => 1, 'living_rooms' => 1, 'beds' => 1, 'bed_configuration' => [['type' => 'queen', 'quantity' => 1]], 'guests' => 3, 'adults' => 2, 'children' => 1, 'featured' => true, 'unit_codes' => ['201', '202'], 'rate_minor' => 1_750_000],
            ['code' => 'FAMILY-SUITE', 'slug' => 'family-suite', 'name' => 'Family Suite', 'description' => 'A two-room suite arranged for small families.', 'size' => '72.00', 'bedrooms' => 2, 'living_rooms' => 1, 'beds' => 2, 'bed_configuration' => [['type' => 'queen', 'quantity' => 1], ['type' => 'single', 'quantity' => 1]], 'guests' => 4, 'adults' => 3, 'children' => 2, 'featured' => false, 'unit_codes' => ['301', '302'], 'rate_minor' => 2_400_000],
        ];
    }
}
