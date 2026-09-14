<?php

/**
 * Defines an ordered, reversible portion of the TravelTours persistence schema.
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Create the module-owned travel booking desk schema in dependency order. */
return new class extends Migration
{
    /** Create the travel booking desk tables without touching other modules. */
    public function up(): void
    {
        Schema::create('travel_booking_registers', function (Blueprint $table): void {
            $table->id()->comment('Internal primary key.');
            $table->ulid('ulid')->unique()->comment('Public immutable identifier.');
            $table->string('code', 60)->unique()->comment('Stable register code.');
            $table->string('name', 160)->comment('Human-readable booking desk name.');
            $table->string('location', 190)->nullable()->comment('Physical or operational register location.');
            $table->boolean('is_active')->default(true)->comment('Whether shifts can open on this register.');
            $table->string('receipt_printer_driver', 60)->default('browser')->comment('Configured receipt output adapter.');
            $table->string('receipt_printer_name', 120)->nullable()->comment('Operator-facing label for the configured receipt printer.');
            $table->unsignedSmallInteger('receipt_paper_width_mm')->default(80)->comment('Thermal receipt paper width in millimetres.');
            $table->boolean('automatic_receipt_print')->default(false)->comment('Whether completed desk sales trigger printing automatically.');
            $table->json('printer_options')->nullable()->comment('Validated driver-specific non-secret printer options.');
            $table->foreignId('created_by')->nullable()->comment('Operator who created the register.')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->comment('Operator who last updated the register.')->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable()->comment('UTC instant this record was created.');
            $table->timestamp('updated_at')->nullable()->comment('UTC instant this record was last updated.');
        });

        Schema::create('travel_booking_shifts', function (Blueprint $table): void {
            $table->id()->comment('Internal primary key.');
            $table->ulid('ulid')->unique()->comment('Public immutable identifier.');
            $table->foreignId('register_id')->comment('Register used for this shift.')->constrained('travel_booking_registers')->restrictOnDelete();
            $table->foreignId('operator_id')->comment('Operator owning the shift.')->constrained('users')->restrictOnDelete();
            $table->foreignId('register_open_guard')->nullable()->unique()->comment('Register identifier retained only while the shift is open to enforce one active shift.')->constrained('travel_booking_registers')->restrictOnDelete();
            $table->foreignId('operator_open_guard')->nullable()->unique()->comment('Operator identifier retained only while the shift is open to enforce one active shift.')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('open')->comment('Shift lifecycle state.');
            $table->char('currency', 3)->comment('ISO currency reconciled by this shift.');
            $table->unsignedTinyInteger('currency_exponent')->default(2)->comment('Minor-unit decimal exponent snapshotted for this shift currency.');
            $table->unsignedBigInteger('opening_float_minor')->default(0)->comment('Opening cash float in minor units.');
            $table->bigInteger('expected_cash_minor')->default(0)->comment('System-calculated closing cash in minor units.');
            $table->unsignedBigInteger('actual_cash_minor')->nullable()->comment('Operator-counted closing cash in minor units.');
            $table->bigInteger('variance_minor')->nullable()->comment('Actual minus expected cash in minor units.');
            $table->timestamp('opened_at')->comment('UTC shift opening instant.');
            $table->timestamp('closed_at')->nullable()->comment('UTC shift closing instant.');
            $table->timestamp('reconciled_at')->nullable()->comment('UTC supervisor reconciliation instant.');
            $table->foreignId('reconciled_by')->nullable()->comment('Supervisor who reconciled the shift.')->constrained('users')->nullOnDelete();
            $table->text('closing_notes')->nullable()->comment('Operator closing explanation.');
            $table->text('reconciliation_notes')->nullable()->comment('Supervisor variance or approval notes.');
            $table->timestamp('created_at')->nullable()->comment('UTC instant this record was created.');
            $table->timestamp('updated_at')->nullable()->comment('UTC instant this record was last updated.');
            $table->index(['register_id', 'status']);
            $table->index(['operator_id', 'opened_at']);
        });
    }

    /** Drop only these module tables in reverse foreign-key dependency order. */
    public function down(): void
    {
        Schema::dropIfExists('travel_booking_shifts');
        Schema::dropIfExists('travel_booking_registers');
    }
};
