<?php

declare(strict_types=1);

namespace Tests\Feature\Commerce;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * Verifies module registration and the complete first-release schema contract.
 */
final class CommerceSchemaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ensure the module provider loads configuration and every owned migration.
     */
    public function test_module_provider_loads_configuration_and_schema(): void
    {
        $this->assertSame('KES', config('commerce.currency.code'));
        $this->assertSame(1600, config('commerce.tax.default_rate_bps'));

        $expectedColumns = [
            'product_categories' => ['id', 'ulid', 'parent_id', 'name', 'slug', 'is_active'],
            'products' => ['id', 'ulid', 'category_id', 'name', 'slug', 'sku', 'barcode', 'price_minor', 'specifications', 'published_at'],
            'stocks' => ['id', 'product_id', 'on_hand', 'low_stock_threshold'],
            'customers' => ['id', 'ulid', 'user_id', 'first_name', 'last_name', 'email', 'country_code'],
            'registers' => ['id', 'ulid', 'name', 'code', 'receipt_print_driver', 'receipt_print_mode', 'receipt_paper_width', 'receipt_printer_name', 'is_active'],
            'till_sessions' => ['id', 'ulid', 'register_id', 'opened_by', 'status', 'opening_float_minor', 'variance_minor'],
            'orders' => ['id', 'ulid', 'order_number', 'channel', 'status', 'payment_status', 'subtotal_minor', 'paid_minor', 'stock_committed_at'],
            'order_items' => ['id', 'order_id', 'product_id', 'product_name', 'quantity', 'unit_price_minor', 'unit_cost_minor', 'line_total_minor'],
            'payments' => ['id', 'ulid', 'order_id', 'method', 'status', 'amount_minor', 'tendered_minor', 'change_minor'],
            'stock_movements' => ['id', 'ulid', 'product_id', 'type', 'quantity_delta', 'balance_before', 'balance_after'],
        ];

        foreach ($expectedColumns as $table => $columns) {
            $this->assertTrue(Schema::hasTable($table), "Expected Commerce table [{$table}] was not loaded.");
            $this->assertTrue(Schema::hasColumns($table, $columns), "Commerce table [{$table}] is missing required columns.");
        }
    }

    /**
     * Guard the repository convention that every declared migration column is documented.
     */
    public function test_every_commerce_migration_column_has_a_comment(): void
    {
        $directory = app_path('Modules/Commerce/Database/Migrations');
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        $columnDeclaration = '/\$table->(?:id|ulid|foreignId|string|char|text|longText|json|boolean|unsignedInteger|unsignedSmallInteger|unsignedBigInteger|bigInteger|timestamp)\(/';

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $statements = explode(';', (string) file_get_contents($file->getPathname()));

            foreach ($statements as $statement) {
                if (preg_match($columnDeclaration, $statement) !== 1) {
                    continue;
                }

                $this->assertStringContainsString(
                    '->comment(',
                    $statement,
                    "Undocumented Commerce migration column in {$file->getFilename()}: ".trim($statement),
                );
            }
        }
    }

    /**
     * Verify child/projection tables do not carry redundant external identifiers.
     */
    public function test_ulids_are_limited_to_externally_addressable_resources(): void
    {
        foreach (['product_categories', 'products', 'customers', 'registers', 'till_sessions', 'orders', 'payments', 'stock_movements'] as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'ulid'), "{$table} must expose a ULID route key.");
        }

        foreach (['stocks', 'order_items'] as $table) {
            $this->assertFalse(Schema::hasColumn($table, 'ulid'), "{$table} should remain scoped to its owning aggregate.");
        }
    }
}
