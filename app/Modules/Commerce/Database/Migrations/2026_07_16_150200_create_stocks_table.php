<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the one-row-per-product inventory projection.
 */
return new class extends Migration
{
    /**
     * Create current stock balances addressed through their products.
     */
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table): void {
            $table->id()->comment('Internal integer primary key for the stock projection.');
            $table->foreignId('product_id')->unique()->comment('Product whose current stock balance this row projects.')->constrained('products')->cascadeOnDelete();
            $table->bigInteger('on_hand')->default(0)->comment('Current physical and sellable integer unit balance.');
            $table->unsignedBigInteger('low_stock_threshold')->default(0)->comment('Balance at or below which low-stock UI warnings are shown.');
            $table->foreignId('updated_by')->nullable()->comment('Most recent user responsible for a manual projection change.')->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable()->comment('Timestamp when the stock projection was created.');
            $table->timestamp('updated_at')->nullable()->comment('Timestamp when the stock projection was last reconciled.');

            $table->index('on_hand', 'stocks_on_hand_index');
        });
    }

    /**
     * Remove product stock projections.
     */
    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
