<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates immutable product, price, cost, and tax snapshots for orders.
 */
return new class extends Migration
{
    /**
     * Create order line snapshots owned exclusively by an order aggregate.
     */
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table): void {
            $table->id()->comment('Internal integer primary key scoped to the owning order aggregate.');
            $table->foreignId('order_id')->comment('Order aggregate that owns this immutable line snapshot.')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->comment('Current catalog product link; snapshots survive product deletion.')->constrained('products')->nullOnDelete();
            $table->string('product_name', 180)->comment('Historical product name copied at calculation time.');
            $table->string('sku', 80)->comment('Historical stock-keeping code copied at calculation time.');
            $table->unsignedBigInteger('quantity')->comment('Positive integer units ordered on this line.');
            $table->unsignedBigInteger('unit_price_minor')->comment('Effective unit selling price in order currency minor units.');
            $table->unsignedBigInteger('unit_cost_minor')->nullable()->comment('Internal unit cost snapshot in order currency minor units for margin reporting.');
            $table->unsignedBigInteger('line_subtotal_minor')->comment('Unit price multiplied by quantity before allocated discount in currency minor units.');
            $table->unsignedBigInteger('discount_minor')->default(0)->comment('Order discount allocated to this line in currency minor units.');
            $table->unsignedSmallInteger('tax_rate_bps')->default(0)->comment('Tax rate snapshot in basis points where 1600 represents 16 percent.');
            $table->boolean('is_tax_inclusive')->default(true)->comment('Whether this line unit price was treated as tax inclusive.');
            $table->unsignedBigInteger('tax_minor')->default(0)->comment('Tax amount attributed to this line in currency minor units.');
            $table->unsignedBigInteger('line_total_minor')->comment('Final line total after allocated discount and tax treatment in currency minor units.');
            $table->timestamp('created_at')->nullable()->comment('Timestamp when the immutable order line was created.');
            $table->timestamp('updated_at')->nullable()->comment('Timestamp retained for Eloquent compatibility during held-order editing only.');

            $table->index('order_id', 'order_items_order_index');
            $table->index('product_id', 'order_items_product_index');
            $table->index(['order_id', 'product_id'], 'order_items_order_product_index');
        });
    }

    /**
     * Remove order line snapshots.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
