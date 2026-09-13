<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds register-owned receipt layout and browser printing preferences.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registers', function (Blueprint $table): void {
            $table->string('receipt_print_driver', 40)->default('browser')->after('description')->comment('Configured module print-driver key; browser is the safe default.');
            $table->string('receipt_print_mode', 30)->default('manual')->after('receipt_print_driver')->comment('Whether receipts print on request or prompt after a completed sale.');
            $table->unsignedSmallInteger('receipt_paper_width')->default(80)->after('receipt_print_mode')->comment('Thermal receipt layout width in millimetres; supported defaults are 58 and 80.');
            $table->string('receipt_printer_name', 120)->nullable()->after('receipt_paper_width')->comment('Optional operator-facing printer or queue label for this register.');
        });
    }

    public function down(): void
    {
        Schema::table('registers', function (Blueprint $table): void {
            $table->dropColumn([
                'receipt_print_driver',
                'receipt_print_mode',
                'receipt_paper_width',
                'receipt_printer_name',
            ]);
        });
    }
};
