<?php

declare(strict_types=1);

namespace Tests\Feature\Commerce;

use App\Models\User;
use App\Modules\Commerce\Catalog\Enums\ProductStatus;
use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Catalog\Models\ProductCategory;
use App\Modules\Commerce\Database\Seeders\CommerceDemoSeeder;
use App\Modules\Commerce\DemoData\Livewire\Admin\DemoDataManager;
use App\Modules\Commerce\Inventory\Models\StockMovement;
use App\Modules\Commerce\Orders\Models\Order;
use App\Modules\Commerce\Support\CommercePermission;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Verifies the guarded Livewire launcher over the canonical Commerce demo command.
 */
final class CommerceDemoDataInterfaceTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
        $this->administrator = User::query()->where('email', 'admin@aureon.test')->firstOrFail();
    }

    public function test_demo_data_route_and_component_require_the_dedicated_permission(): void
    {
        $plainUser = User::factory()->create();
        $manager = User::factory()->create();
        $manager->givePermissionTo(CommercePermission::MANAGE_DEMO_DATA);

        $this->get(route('commerce.admin.demo-data.index'))->assertRedirect(route('login'));
        $this->actingAs($plainUser)->get(route('commerce.admin.demo-data.index'))->assertForbidden();
        $this->actingAs($manager)
            ->get(route('commerce.admin.demo-data.index'))
            ->assertOk()
            ->assertSeeLivewire('commerce.admin.demo-data-manager')
            ->assertSee('Commerce demo data')
            ->assertSee('hardware-construction.jpg')
            ->assertSee('shoe-store.jpg')
            ->assertSee('Shoe store')
            ->assertDontSee(route('commerce.admin.dashboard'), false);

        Livewire::actingAs($plainUser)
            ->test(DemoDataManager::class)
            ->assertForbidden();
    }

    public function test_shoe_store_context_is_selectable_from_the_livewire_interface(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->administrator)
            ->test(DemoDataManager::class)
            ->assertCount('contexts', 11)
            ->set('form.context', 'shoe-store')
            ->call('seed')
            ->assertHasNoErrors()
            ->assertSet('lastSeededContext', 'shoe-store')
            ->assertSee('Shoe store demonstration data seeded successfully.')
            ->assertSee('Commerce demo context [shoe-store] seeded (keep existing).');

        $this->assertSame(12, Product::query()->count());
        $this->assertSame(6, ProductCategory::query()->count());
        $this->assertSame(2, Order::query()->count());
    }

    public function test_keep_mode_seeds_the_selected_context_and_records_the_run(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->administrator)
            ->test(DemoDataManager::class)
            ->set('form.context', 'hardware-construction')
            ->call('seed')
            ->assertHasNoErrors()
            ->assertSet('lastSeededContext', 'hardware-construction')
            ->assertSet('lastRunMode', 'Keep existing products')
            ->assertSee('Hardware and construction demonstration data seeded successfully.')
            ->assertSee('Commerce demo context [hardware-construction] seeded (keep existing).');

        $this->assertSame(12, Product::query()->count());
        $this->assertSame(15, StockMovement::query()->count());
        $this->assertSame(2, Order::query()->count());
        $this->assertDatabaseHas('system_activities', [
            'activity_type' => 'commerce.demo_data.seeded',
            'user_id' => $this->administrator->id,
        ]);
    }

    public function test_archive_mode_requires_confirmation_and_passes_the_command_option(): void
    {
        Storage::fake('public');
        $this->invokeSeeder(CommerceDemoSeeder::class);

        Livewire::actingAs($this->administrator)
            ->test(DemoDataManager::class)
            ->set('form.context', 'computers-it')
            ->set('form.archiveExisting', true)
            ->call('seed')
            ->assertHasErrors('form.archiveConfirmed');

        $this->assertSame(10, Product::query()->where('status', ProductStatus::Published->value)->count());

        Livewire::actingAs($this->administrator)
            ->test(DemoDataManager::class)
            ->set('form.context', 'computers-it')
            ->set('form.archiveExisting', true)
            ->set('form.archiveConfirmed', true)
            ->call('seed')
            ->assertHasNoErrors()
            ->assertSet('lastSeededContext', 'computers-it')
            ->assertSet('lastRunMode', 'Archive existing products')
            ->assertSee('Commerce demo context [computers-it] seeded (archive existing).');

        $this->assertSame(22, Product::query()->count());
        $this->assertSame(12, Product::query()->where('status', ProductStatus::Published->value)->count());
        $this->assertSame(10, Product::query()->where('status', ProductStatus::Archived->value)->count());
    }

    public function test_context_is_allowlisted_and_concurrent_runs_are_rejected(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->administrator)
            ->test(DemoDataManager::class)
            ->set('form.context', 'arbitrary-command')
            ->call('seed')
            ->assertHasErrors('form.context');

        $lock = Cache::lock('commerce:demo-data:run', 300);
        $this->assertTrue($lock->get());

        try {
            Livewire::actingAs($this->administrator)
                ->test(DemoDataManager::class)
                ->set('form.context', 'office-bookshop')
                ->call('seed')
                ->assertHasErrors('seeding')
                ->assertSee('Another Commerce demonstration-data run is still in progress.');
        } finally {
            $lock->release();
        }

        $this->assertDatabaseCount('products', 0);
    }

    /**
     * Invoke a parameterized Laravel seeder through the container path used by callWith().
     *
     * @param  class-string  $seederClass
     * @param  array<string, mixed>  $parameters
     */
    private function invokeSeeder(string $seederClass, array $parameters = []): void
    {
        $seeder = app($seederClass);
        $seeder->setContainer(app());
        $seeder->__invoke($parameters);
    }
}
