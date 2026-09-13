<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Adds immutable self-service preference and consent evidence to bookings. */
return new class extends Migration
{
    /** Add the documented storefront snapshot columns. */
    public function up(): void
    {
        Schema::table('property_bookings', function (Blueprint $table): void {
            $table->string('preferred_payment_method', 30)->nullable()->comment('Guest-selected payment preference; it is not a completed payment ledger record.');
            $table->timestamp('terms_accepted_at')->nullable()->comment('UTC timestamp when the guest accepted booking and property policies at placement.');
        });
    }

    /** Remove the storefront snapshot columns during rollback. */
    public function down(): void
    {
        Schema::table('property_bookings', function (Blueprint $table): void {
            $table->dropColumn(['preferred_payment_method', 'terms_accepted_at']);
        });
    }
};
