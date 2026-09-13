<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the optional manufacturer barcode alongside the internal store barcode.
 *
 * `barcode` remains the internal, store-generated Code 128 value. This column
 * records the optional external EAN/UPC printed by the manufacturer so the POS
 * can also match a factory barcode on scan. It is unique so the same external
 * code is never attached to two different products.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('manufacturer_barcode', 80)
                ->nullable()
                ->unique()
                ->after('barcode')
                ->comment('Optional external manufacturer barcode (EAN/UPC) matched on POS scan in addition to the internal barcode.');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropUnique(['manufacturer_barcode']);
            $table->dropColumn('manufacturer_barcode');
        });
    }
};
