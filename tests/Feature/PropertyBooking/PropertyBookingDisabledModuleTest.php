<?php

declare(strict_types=1);

namespace Tests\Feature\PropertyBooking;

use App\Modules\PropertyBooking\Contracts\AllocatesUnits;
use App\Modules\PropertyBooking\Contracts\CalculatesBookingRates;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

/** Verifies the module environment switch prevents resource and binding boot. */
final class PropertyBookingDisabledModuleTest extends TestCase
{
    private string|false $originalValue;

    protected function setUp(): void
    {
        $this->originalValue = getenv('PROPERTY_BOOKING_ENABLED');
        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->setModuleEnvironment($this->originalValue === false ? null : $this->originalValue);
        $this->refreshApplication();
        parent::tearDown();
    }

    public function test_disabled_module_registers_no_runtime_resources_or_contracts(): void
    {
        $this->setModuleEnvironment('false');
        $this->refreshApplication();

        $this->assertFalse(config('property-booking.enabled'));
        $this->assertFalse($this->app->bound(CalculatesBookingRates::class));
        $this->assertFalse($this->app->bound(AllocatesUnits::class));
        $this->assertArrayNotHasKey('property-booking', $this->app['view']->getFinder()->getHints());
        $this->assertFalse(Route::has('property-booking.admin.properties.index'));
        $this->assertFalse(Route::has('property-booking.admin.amenities.index'));
        $this->assertFalse(Route::has('property-booking.admin.units.index'));
        $this->assertFalse(Route::has('property-booking.admin.rates.index'));
        $this->assertFalse(Route::has('property-booking.admin.availability.index'));
        $this->assertFalse(Route::has('property-booking.storefront.catalog.index'));
        $this->assertFalse(Route::has('property-booking.storefront.checkout.index'));
        $this->assertFalse(Livewire::exists('property-booking.admin.property-manager'));
        $this->assertFalse(Livewire::exists('property-booking.admin.availability-manager'));
        $this->assertFalse(Livewire::exists('property-booking.storefront.availability-browser'));
        $this->assertFalse(Livewire::exists('property-booking.storefront.checkout'));
        $this->get('/admin/accommodation/properties')->assertNotFound();
        $this->get('/stays')->assertNotFound();
    }

    private function setModuleEnvironment(?string $value): void
    {
        if ($value === null) {
            putenv('PROPERTY_BOOKING_ENABLED');
            unset($_ENV['PROPERTY_BOOKING_ENABLED'], $_SERVER['PROPERTY_BOOKING_ENABLED']);

            return;
        }

        putenv("PROPERTY_BOOKING_ENABLED={$value}");
        $_ENV['PROPERTY_BOOKING_ENABLED'] = $value;
        $_SERVER['PROPERTY_BOOKING_ENABLED'] = $value;
    }
}
