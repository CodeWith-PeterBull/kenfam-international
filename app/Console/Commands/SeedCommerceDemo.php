<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Commerce\Database\Seeders\CatalogDemoSeeder;
use App\Modules\Commerce\Database\Seeders\CommerceDemoSeeder;
use Illuminate\Console\Command;

/**
 * Seeds one allowlisted Commerce demonstration context through the canonical chain.
 */
final class SeedCommerceDemo extends Command
{
    /** @var string */
    protected $signature = 'commerce:demo-seed
        {context=default-mixed : Merchant context to seed}
        {--archive-existing : Archive existing products before publishing the selected context}';

    /** @var string */
    protected $description = 'Seed an optional contextual Commerce demonstration catalog and transaction graph';

    /**
     * Validate the selection and invoke the existing parameterized seeder chain.
     */
    public function handle(): int
    {
        $context = (string) $this->argument('context');
        if (! in_array($context, CatalogDemoSeeder::supportedContexts(), true)) {
            $this->components->error("Unsupported Commerce demo context [{$context}].");
            $this->line('Supported contexts: '.implode(', ', CatalogDemoSeeder::supportedContexts()));

            return self::INVALID;
        }

        /** @var CommerceDemoSeeder $seeder */
        $seeder = app(CommerceDemoSeeder::class);
        $seeder
            ->setContainer(app())
            ->setCommand($this)
            ->__invoke([
                'context' => $context,
                'archiveExisting' => (bool) $this->option('archive-existing'),
            ]);

        $mode = $this->option('archive-existing') ? 'archive existing' : 'keep existing';
        $this->newLine();
        $this->components->info("Commerce demo context [{$context}] seeded ({$mode}).");

        return self::SUCCESS;
    }
}
