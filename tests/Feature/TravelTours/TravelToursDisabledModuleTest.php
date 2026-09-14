<?php

/**
 * Verifies that the TravelTours feature switch removes its runtime surface.
 */

declare(strict_types=1);

namespace Tests\Feature\TravelTours;

use App\Modules\TravelTours\Contracts\CalculatesTourQuotes;
use App\Modules\TravelTours\Contracts\SearchesTours;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Prove disabled-module isolation through a freshly booted application. */
final class TravelToursDisabledModuleTest extends TestCase
{
    private string|false $originalValue;

    /** Preserve the process-level switch before the Laravel application boots. */
    protected function setUp(): void
    {
        $this->originalValue = getenv('TRAVEL_TOURS_ENABLED');
        parent::setUp();
    }

    /** Restore the process environment for every following test process. */
    protected function tearDown(): void
    {
        $this->setModuleEnvironment($this->originalValue === false ? null : $this->originalValue);
        $this->refreshApplication();
        parent::tearDown();
    }

    /** The disabled provider registers no routes, views, or service contracts. */
    public function test_disabled_module_registers_no_runtime_resources_or_contracts(): void
    {
        $this->setModuleEnvironment('false');
        $this->refreshApplication();

        $this->assertFalse(config('travel-tours.enabled'));
        $this->assertFalse($this->app->bound(SearchesTours::class));
        $this->assertFalse($this->app->bound(CalculatesTourQuotes::class));
        $this->assertArrayNotHasKey('travel-tours', $this->app['view']->getFinder()->getHints());
        $this->assertFalse(Route::has('travel-tours.storefront.catalog.index'));
        $this->assertFalse(Route::has('travel-tours.admin.dashboard'));
        $this->assertFalse(Route::has('travel-tours.pob.terminal'));
        $this->get('/tours')->assertNotFound();
        $this->get('/admin/travel')->assertNotFound();
    }

    /** Set or remove the process flag consumed during provider registration. */
    private function setModuleEnvironment(?string $value): void
    {
        if ($value === null) {
            putenv('TRAVEL_TOURS_ENABLED');
            unset($_ENV['TRAVEL_TOURS_ENABLED'], $_SERVER['TRAVEL_TOURS_ENABLED']);

            return;
        }

        putenv("TRAVEL_TOURS_ENABLED={$value}");
        $_ENV['TRAVEL_TOURS_ENABLED'] = $value;
        $_SERVER['TRAVEL_TOURS_ENABLED'] = $value;
    }
}
