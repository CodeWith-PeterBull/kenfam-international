<?php

declare(strict_types=1);

namespace Tests\Feature\Commerce;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Verifies the Commerce provider can be omitted through its environment switch.
 */
final class CommerceDisabledModuleTest extends TestCase
{
    private string|false $originalValue;

    protected function setUp(): void
    {
        $this->originalValue = getenv('COMMERCE_ENABLED');

        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->setCommerceEnvironment($this->originalValue === false ? null : $this->originalValue);
        $this->refreshApplication();

        parent::tearDown();
    }

    public function test_disabled_module_does_not_register_routes_or_resources(): void
    {
        $this->setCommerceEnvironment('false');
        $this->refreshApplication();

        $this->assertFalse(config('commerce.enabled'));
        $this->assertFalse(Route::has('commerce.admin.dashboard'));
        $this->assertFalse(Route::has('commerce.admin.demo-data.index'));
        $this->assertFalse(Route::has('commerce.storefront.catalog.index'));
        $this->assertFalse(Route::has('commerce.pos.terminal'));
        $this->get('/admin/commerce')->assertNotFound();
    }

    /**
     * Set or remove the process-level flag consumed during application boot.
     */
    private function setCommerceEnvironment(?string $value): void
    {
        if ($value === null) {
            putenv('COMMERCE_ENABLED');
            unset($_ENV['COMMERCE_ENABLED'], $_SERVER['COMMERCE_ENABLED']);

            return;
        }

        putenv("COMMERCE_ENABLED={$value}");
        $_ENV['COMMERCE_ENABLED'] = $value;
        $_SERVER['COMMERCE_ENABLED'] = $value;
    }
}
