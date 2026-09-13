<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeds the complete optional Commerce demonstration graph.
 *
 * The host DatabaseSeeder deliberately does not call this class. Adopters may
 * opt in after seeding the host application and may rerun it safely.
 */
final class CommerceDemoSeeder extends Seeder
{
    /**
     * Seed access, catalog, customer, and transaction examples in dependency order.
     */
    public function run(
        string $context = CatalogDemoSeeder::DEFAULT_CONTEXT,
        bool $archiveExisting = false,
    ): void {
        $definition = CatalogDemoSeeder::contextDefinition($context);

        $this->call(CommerceAccessDemoSeeder::class);
        $this->callWith(CatalogDemoSeeder::class, [
            'context' => $context,
            'archiveExisting' => $archiveExisting,
        ]);
        $this->call(CustomerDemoSeeder::class);
        $this->callWith(TransactionDemoSeeder::class, [
            'webOrderSku' => $definition['transactions']['web_order_sku'],
            'posOrderSkus' => $definition['transactions']['pos_order_skus'],
        ]);
    }
}
