<?php

declare(strict_types=1);

namespace App\Modules\Commerce\DemoData\Livewire\Admin;

use App\Console\Commands\SeedCommerceDemo;
use App\Contracts\RecordsSystemActivity;
use App\Enums\SystemActivitySeverity;
use App\Models\User;
use App\Modules\Commerce\Catalog\Enums\ProductStatus;
use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Catalog\Models\ProductCategory;
use App\Modules\Commerce\Database\Seeders\CatalogDemoSeeder;
use App\Modules\Commerce\DemoData\Livewire\Forms\DemoDataForm;
use App\Modules\Commerce\Inventory\Models\Stock;
use App\Modules\Commerce\Orders\Models\Order;
use App\Modules\Commerce\Support\CommercePermission;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * Executes one allowlisted Commerce fixture context through the canonical command.
 */
final class DemoDataManager extends Component
{
    private const RUN_LOCK = 'commerce:demo-data:run';

    /** @var array<string, array{name: string, description: string}> */
    private const CONTEXT_PRESENTATION = [
        'default-mixed' => ['name' => 'Mixed commerce', 'description' => 'Electronics, workspace, audio, mobile, wearables, and lifestyle.'],
        'computers-it' => ['name' => 'Computers and IT', 'description' => 'Business computers, displays, peripherals, networking, power, and storage.'],
        'hardware-construction' => ['name' => 'Hardware and construction', 'description' => 'Paint, cement, steel, roofing, timber, plumbing, electrical, and tools.'],
        'boutique-fashion' => ['name' => 'Boutique and fashion', 'description' => "Apparel, footwear, bags, accessories, tailoring, and children's wear."],
        'pharmacy-health' => ['name' => 'Pharmacy and health', 'description' => 'Non-prescription demonstration health, hygiene, monitoring, and first-aid lines.'],
        'supermarket-fmcg' => ['name' => 'Supermarket and FMCG', 'description' => 'Kenyan pantry staples, beverages, dairy, bakery, and household essentials.'],
        'beauty-personal-care' => ['name' => 'Beauty and personal care', 'description' => 'Hair, skin, hygiene, cosmetics, fragrance, and everyday body care.'],
        'automotive-parts' => ['name' => 'Automotive parts', 'description' => 'Fluids, filters, braking, electrical, tyres, batteries, and accessories.'],
        'agrovet-farm' => ['name' => 'Agrovet and farm', 'description' => 'Feed, seed, soil inputs, equipment, protective wear, hygiene, and fencing.'],
        'office-bookshop' => ['name' => 'Office and bookshop', 'description' => 'Paper, books, writing, filing, desk tools, presentation, and calculation.'],
        'shoe-store' => ['name' => 'Shoe store', 'description' => 'Trending sneakers, canvas shoes, running pairs, football boots, and everyday footwear.'],
    ];

    public DemoDataForm $form;

    #[Locked]
    public ?string $lastSeededContext = null;

    #[Locked]
    public ?string $lastRunMode = null;

    #[Locked]
    public ?string $lastRunAt = null;

    #[Locked]
    public string $lastCommandOutput = '';

    public function boot(): void
    {
        Gate::authorize(CommercePermission::MANAGE_DEMO_DATA);
    }

    /**
     * Build the stable context cards and their current database coverage.
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function contexts(): array
    {
        $definitions = collect(CatalogDemoSeeder::supportedContexts())
            ->mapWithKeys(static fn (string $context): array => [
                $context => CatalogDemoSeeder::contextDefinition($context),
            ]);
        $existingProducts = Product::query()
            ->select(['sku', 'status'])
            ->whereIn('sku', $definitions->flatMap(
                static fn (array $definition): array => array_column($definition['products'], 'sku'),
            ))
            ->get()
            ->keyBy('sku');

        return $definitions->map(function (array $definition, string $context) use ($existingProducts): array {
            $skus = array_column($definition['products'], 'sku');
            $slugs = array_column($definition['categories'], 'slug');
            $presentation = self::CONTEXT_PRESENTATION[$context];
            $seededProducts = $existingProducts->only($skus);

            return [
                'key' => $context,
                'name' => $presentation['name'],
                'description' => $presentation['description'],
                'thumbnail' => asset("build/img/commerce/demo-contexts/{$context}.jpg"),
                'products' => count($skus),
                'categories' => count($slugs),
                'seeded_products' => $seededProducts->count(),
                'active_products' => $seededProducts
                    ->filter(static fn (Product $product): bool => $product->status === ProductStatus::Published)
                    ->count(),
            ];
        })->values()->all();
    }

    /** @return array{products: int, categories: int, stocks: int, orders: int} */
    #[Computed]
    public function totals(): array
    {
        return [
            'products' => Product::query()->count(),
            'categories' => ProductCategory::query()->count(),
            'stocks' => Stock::query()->count(),
            'orders' => Order::query()->count(),
        ];
    }

    /**
     * Invoke the fixed Commerce command with validated argument and option values.
     */
    public function seed(RecordsSystemActivity $activities): void
    {
        Gate::authorize(CommercePermission::MANAGE_DEMO_DATA);
        $this->resetErrorBag('seeding');
        $this->form->validate();
        $arguments = $this->form->commandArguments();
        $actor = $this->actor();
        $lock = Cache::lock(self::RUN_LOCK, 300);

        if (! $lock->get()) {
            $this->addError('seeding', 'Another Commerce demonstration-data run is still in progress.');

            return;
        }

        try {
            $this->lastSeededContext = null;
            $this->lastRunMode = null;
            $this->lastRunAt = null;
            $this->lastCommandOutput = '';

            $exitCode = Artisan::call(SeedCommerceDemo::class, [
                'context' => $arguments['context'],
                '--archive-existing' => $arguments['archive_existing'],
            ]);
            $this->lastCommandOutput = Str::limit(trim(Artisan::output()), 4000, PHP_EOL.'...');

            if ($exitCode !== 0) {
                $this->recordFailure($activities, $actor, $arguments, $exitCode);
                $this->addError('seeding', 'The demonstration-data command did not complete successfully.');

                return;
            }

            $this->lastSeededContext = $arguments['context'];
            $this->lastRunMode = $arguments['archive_existing'] ? 'Archive existing products' : 'Keep existing products';
            $this->lastRunAt = now()->toIso8601String();

            $this->recordSuccess($activities, $actor, $arguments);
            unset($this->contexts, $this->totals);
            session()->flash('success', self::CONTEXT_PRESENTATION[$arguments['context']]['name'].' demonstration data seeded successfully.');
        } catch (Throwable $exception) {
            report($exception);
            $this->lastCommandOutput = '';
            $this->recordFailure($activities, $actor, $arguments, null, $exception);
            $this->addError('seeding', 'The demonstration-data run failed. Review the application log before retrying.');
        } finally {
            $lock->release();
        }
    }

    public function updatedFormArchiveExisting(bool $archiveExisting): void
    {
        Gate::authorize(CommercePermission::MANAGE_DEMO_DATA);

        if (! $archiveExisting) {
            $this->form->archiveConfirmed = false;
            $this->resetValidation('form.archiveConfirmed');
        }
    }

    public function render(): View
    {
        return view('commerce::livewire.admin.demo-data-manager');
    }

    private function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    /** @param array{context: string, archive_existing: bool} $arguments */
    private function recordSuccess(RecordsSystemActivity $activities, User $actor, array $arguments): void
    {
        try {
            $activities->record(
                activityType: 'commerce.demo_data.seeded',
                description: 'Commerce demonstration data seeded: '.self::CONTEXT_PRESENTATION[$arguments['context']]['name'],
                actor: $actor,
                properties: [
                    'context' => $arguments['context'],
                    'archive_existing' => $arguments['archive_existing'],
                    'products_total' => Product::query()->count(),
                    'categories_total' => ProductCategory::query()->count(),
                ],
                severity: SystemActivitySeverity::Notice,
                source: 'commerce-demo-data',
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /** @param array{context: string, archive_existing: bool} $arguments */
    private function recordFailure(
        RecordsSystemActivity $activities,
        User $actor,
        array $arguments,
        ?int $exitCode,
        ?Throwable $failure = null,
    ): void {
        try {
            $activities->record(
                activityType: 'commerce.demo_data.failed',
                description: 'Commerce demonstration-data run failed.',
                actor: $actor,
                properties: [
                    'context' => $arguments['context'],
                    'archive_existing' => $arguments['archive_existing'],
                    'exit_code' => $exitCode,
                    'failure_class' => $failure === null ? null : $failure::class,
                ],
                severity: SystemActivitySeverity::Error,
                source: 'commerce-demo-data',
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
