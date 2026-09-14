<?php

/**
 * Defines an ordered, reversible portion of the TravelTours persistence schema.
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Create the module-owned travel pricing schema in dependency order. */
return new class extends Migration
{
    /** Create the travel pricing tables without touching other modules. */
    public function up(): void
    {
        Schema::create('travel_tour_rate_plans', function (Blueprint $table): void {
            $table->id()->comment('Internal primary key.');
            $table->ulid('ulid')->unique()->comment('Public immutable identifier.');
            $table->foreignId('tour_id')->comment('Tour priced by this plan.')->constrained('travel_tours')->cascadeOnDelete();
            $table->string('code', 60)->comment('Stable operator-facing rate code.');
            $table->string('name', 160)->comment('Public rate-plan name.');
            $table->text('description')->nullable()->comment('Public conditions and value proposition.');
            $table->char('currency', 3)->comment('ISO 4217 currency for every plan amount.');
            $table->boolean('tax_inclusive')->default(true)->comment('Whether displayed prices include tax.');
            $table->unsignedInteger('tax_rate_basis_points')->default(0)->comment('Tax percentage in basis points.');
            $table->string('deposit_type', 24)->default('percentage')->comment('Deposit calculation method: percentage or fixed.');
            $table->unsignedBigInteger('deposit_value')->default(0)->comment('Deposit basis points or currency minor units according to type.');
            $table->unsignedSmallInteger('balance_due_days')->default(0)->comment('Days before departure when the remaining balance is due.');
            $table->boolean('is_refundable')->default(false)->comment('Whether cancellation may qualify for a refund.');
            $table->json('booking_restrictions')->nullable()->comment('Validated non-executable booking restrictions snapshotted at placement.');
            $table->boolean('is_active')->default(true)->comment('Whether operators may apply this plan.');
            $table->boolean('is_public')->default(true)->comment('Whether guests may select this plan.');
            $table->boolean('is_default')->default(false)->comment('Whether this is the default tour rate plan.');
            $table->unsignedInteger('minimum_participants')->default(1)->comment('Minimum participants accepted under this plan.');
            $table->unsignedInteger('maximum_participants')->nullable()->comment('Maximum participants accepted under this plan.');
            $table->unsignedInteger('display_order')->default(0)->comment('Deterministic storefront ordering.');
            $table->timestamp('created_at')->nullable()->comment('UTC instant this record was created.');
            $table->timestamp('updated_at')->nullable()->comment('UTC instant this record was last updated.');
            $table->unique(['tour_id', 'code']);
            $table->index(['tour_id', 'is_active', 'is_public']);
        });

        Schema::create('travel_participant_rates', function (Blueprint $table): void {
            $table->id()->comment('Internal primary key.');
            $table->ulid('ulid')->unique()->comment('Public immutable identifier.');
            $table->foreignId('rate_plan_id')->comment('Owning rate plan.')->constrained('travel_tour_rate_plans')->cascadeOnDelete();
            $table->string('participant_type', 20)->comment('Adult, child, or infant participant classification.');
            $table->unsignedTinyInteger('minimum_age')->nullable()->comment('Inclusive minimum age for this rate.');
            $table->unsignedTinyInteger('maximum_age')->nullable()->comment('Inclusive maximum age for this rate.');
            $table->unsignedBigInteger('amount_minor')->comment('Base per-participant amount in minor units.');
            $table->boolean('tax_inclusive')->nullable()->comment('Optional override of the plan tax treatment.');
            $table->date('active_from')->nullable()->comment('First booking date on which this rate applies.');
            $table->date('active_until')->nullable()->comment('Last booking date on which this rate applies.');
            $table->boolean('is_active')->default(true)->comment('Whether this participant rate can be quoted.');
            $table->timestamp('created_at')->nullable()->comment('UTC instant this record was created.');
            $table->timestamp('updated_at')->nullable()->comment('UTC instant this record was last updated.');
            $table->index(['rate_plan_id', 'participant_type', 'is_active'], 'travel_participant_rate_lookup_index');
        });

        Schema::create('travel_promotions', function (Blueprint $table): void {
            $table->id()->comment('Internal primary key.');
            $table->ulid('ulid')->unique()->comment('Public immutable identifier.');
            $table->string('code', 80)->unique()->comment('Case-normalized redemption code.');
            $table->string('name', 160)->comment('Operator-facing promotion name.');
            $table->text('description')->nullable()->comment('Public promotion explanation.');
            $table->string('adjustment_type', 24)->comment('Fixed or percentage discount calculation.');
            $table->unsignedBigInteger('adjustment_value')->comment('Discount basis points or minor units according to type.');
            $table->char('currency', 3)->nullable()->comment('Required ISO currency for fixed discounts.');
            $table->timestamp('valid_from')->nullable()->comment('Earliest accepted redemption instant in UTC.');
            $table->timestamp('valid_until')->nullable()->comment('Latest accepted redemption instant in UTC.');
            $table->unsignedBigInteger('minimum_booking_minor')->default(0)->comment('Minimum booking subtotal in minor units.');
            $table->unsignedInteger('minimum_participants')->default(1)->comment('Minimum participant count required.');
            $table->unsignedInteger('maximum_uses')->nullable()->comment('Global redemption ceiling.');
            $table->unsignedInteger('maximum_uses_per_customer')->nullable()->comment('Per-customer redemption ceiling.');
            $table->boolean('applies_to_all_tours')->default(false)->comment('Whether no explicit tour assignment is required.');
            $table->boolean('is_active')->default(true)->comment('Whether the promotion can be redeemed.');
            $table->json('conditions')->nullable()->comment('Validated non-executable applicability metadata.');
            $table->timestamp('created_at')->nullable()->comment('UTC instant this record was created.');
            $table->timestamp('updated_at')->nullable()->comment('UTC instant this record was last updated.');
            $table->index(['is_active', 'valid_from', 'valid_until']);
        });

        Schema::create('travel_promotion_tour', function (Blueprint $table): void {
            $table->id()->comment('Internal primary key.');
            $table->foreignId('promotion_id')->comment('Assigned promotion.')->constrained('travel_promotions')->cascadeOnDelete();
            $table->foreignId('tour_id')->comment('Included or excluded tour.')->constrained('travel_tours')->cascadeOnDelete();
            $table->boolean('is_exclusion')->default(false)->comment('Whether this assignment excludes rather than includes the tour.');
            $table->timestamp('created_at')->nullable()->comment('UTC instant this record was created.');
            $table->timestamp('updated_at')->nullable()->comment('UTC instant this record was last updated.');
            $table->unique(['promotion_id', 'tour_id']);
        });
    }

    /** Drop only these module tables in reverse foreign-key dependency order. */
    public function down(): void
    {
        Schema::dropIfExists('travel_promotion_tour');
        Schema::dropIfExists('travel_promotions');
        Schema::dropIfExists('travel_participant_rates');
        Schema::dropIfExists('travel_tour_rate_plans');
    }
};
