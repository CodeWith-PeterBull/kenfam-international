<?php

/**
 * Defines an ordered, reversible portion of the TravelTours persistence schema.
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Create the module-owned travel scheduling schema in dependency order. */
return new class extends Migration
{
    /** Create the travel scheduling tables without touching other modules. */
    public function up(): void
    {
        Schema::create('travel_tour_departures', function (Blueprint $table): void {
            $table->id()->comment('Internal primary key.');
            $table->ulid('ulid')->unique()->comment('Public immutable identifier.');
            $table->foreignId('tour_id')->comment('Scheduled tour.')->constrained('travel_tours')->restrictOnDelete();
            $table->foreignId('rate_plan_id')->nullable()->comment('Optional departure-specific default rate plan.')->constrained('travel_tour_rate_plans')->nullOnDelete();
            $table->string('code', 80)->unique()->comment('Stable operator-facing departure code.');
            $table->string('timezone', 64)->comment('IANA timezone governing local departure times.');
            $table->timestamp('starts_at')->comment('Departure start instant stored in UTC.');
            $table->timestamp('ends_at')->comment('Departure end instant stored in UTC.');
            $table->string('booking_mode', 24)->nullable()->comment('Optional instant or approval override.');
            $table->timestamp('booking_opens_at')->nullable()->comment('UTC instant when reservations open.');
            $table->timestamp('booking_closes_at')->nullable()->comment('UTC instant when reservations close.');
            $table->unsignedInteger('capacity')->comment('Maximum confirmed and held seats.');
            $table->unsignedInteger('minimum_participants')->default(1)->comment('Minimum participants required to operate.');
            $table->boolean('waitlist_enabled')->default(false)->comment('Whether inquiries may join a waitlist when full.');
            $table->string('status', 24)->default('draft')->comment('Operational departure lifecycle state.');
            $table->text('meeting_instructions')->nullable()->comment('Departure-specific meeting guidance.');
            $table->text('operational_notes')->nullable()->comment('Restricted internal execution notes.');
            $table->foreignId('created_by')->nullable()->comment('Operator who scheduled the departure.')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->comment('Operator who last changed the departure.')->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable()->comment('UTC instant this record was created.');
            $table->timestamp('updated_at')->nullable()->comment('UTC instant this record was last updated.');
            $table->softDeletes()->comment('Retention-safe administrative removal timestamp.');
            $table->index(['tour_id', 'starts_at'], 'travel_departure_tour_start_index');
            $table->index(['status', 'starts_at'], 'travel_departure_status_start_index');
            $table->index(['tour_id', 'booking_opens_at', 'booking_closes_at'], 'travel_departure_booking_window_index');
        });

        Schema::create('travel_departure_staff', function (Blueprint $table): void {
            $table->id()->comment('Internal primary key.');
            $table->ulid('ulid')->unique()->comment('Public immutable identifier.');
            $table->foreignId('departure_id')->comment('Assigned departure.')->constrained('travel_tour_departures')->cascadeOnDelete();
            $table->foreignId('user_id')->comment('Assigned staff account.')->constrained()->restrictOnDelete();
            $table->string('role', 80)->comment('Operational role on this departure.');
            $table->boolean('is_lead')->default(false)->comment('Whether this staff member leads the assignment.');
            $table->text('notes')->nullable()->comment('Assignment-specific operational notes.');
            $table->foreignId('assigned_by')->nullable()->comment('Operator who made the assignment.')->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable()->comment('UTC instant this record was created.');
            $table->timestamp('updated_at')->nullable()->comment('UTC instant this record was last updated.');
            $table->unique(['departure_id', 'user_id', 'role']);
        });

        Schema::create('travel_availability_holds', function (Blueprint $table): void {
            $table->id()->comment('Internal primary key.');
            $table->ulid('ulid')->unique()->comment('Public immutable hold identifier.');
            $table->foreignId('departure_id')->comment('Departure whose seats are held.')->constrained('travel_tour_departures')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->comment('Known customer requesting the hold.')->constrained('travel_customers')->nullOnDelete();
            $table->string('owner_token_hash', 64)->nullable()->index()->comment('Purpose-keyed hash binding an anonymous hold to its storefront owner token.');
            $table->string('email_hash', 64)->nullable()->index()->comment('Keyed lookup hash for anonymous customer email.');
            $table->string('operation_key', 64)->unique()->comment('Idempotency key for safe hold creation and retry handling.');
            $table->unsignedInteger('adult_count')->default(0)->comment('Adult seats held.');
            $table->unsignedInteger('child_count')->default(0)->comment('Child seats held.');
            $table->unsignedInteger('infant_count')->default(0)->comment('Infant participants recorded.');
            $table->unsignedInteger('seat_count')->comment('Capacity-consuming seat total.');
            $table->char('currency', 3)->comment('ISO currency of the associated quote.');
            $table->unsignedBigInteger('quoted_total_minor')->comment('Server-calculated quote total in minor units.');
            $table->string('quote_fingerprint', 64)->comment('Integrity fingerprint for quote inputs and result.');
            $table->json('quote_snapshot')->comment('Validated immutable quote breakdown.');
            $table->string('status', 20)->default('active')->comment('Hold lifecycle state.');
            $table->timestamp('expires_at')->comment('UTC instant when the hold releases capacity.');
            $table->timestamp('consumed_at')->nullable()->comment('UTC instant when converted to a booking.');
            $table->timestamp('released_at')->nullable()->comment('UTC instant an active hold was explicitly released.');
            $table->string('release_reason', 80)->nullable()->comment('Stable reason code for explicit release or expiry processing.');
            $table->timestamp('created_at')->nullable()->comment('UTC instant this record was created.');
            $table->timestamp('updated_at')->nullable()->comment('UTC instant this record was last updated.');
            $table->index(['departure_id', 'status', 'expires_at']);
            $table->index(['owner_token_hash', 'status'], 'travel_hold_owner_status_index');
        });

        Schema::create('travel_pricing_rules', function (Blueprint $table): void {
            $table->id()->comment('Internal primary key.');
            $table->ulid('ulid')->unique()->comment('Public immutable identifier.');
            $table->foreignId('rate_plan_id')->comment('Rate plan modified by this rule.')->constrained('travel_tour_rate_plans')->cascadeOnDelete();
            $table->foreignId('departure_id')->nullable()->comment('Optional departure-specific scope.')->constrained('travel_tour_departures')->cascadeOnDelete();
            $table->string('name', 160)->comment('Operator-facing rule name.');
            $table->string('rule_type', 24)->comment('Seasonal, group, or early-bird rule type.');
            $table->date('travel_starts_on')->nullable()->comment('Inclusive first travel date in scope.');
            $table->date('travel_ends_on')->nullable()->comment('Inclusive last travel date in scope.');
            $table->timestamp('sales_start_at')->nullable()->comment('UTC instant when the rule starts selling.');
            $table->timestamp('sales_end_at')->nullable()->comment('UTC instant when the rule stops selling.');
            $table->unsignedInteger('minimum_participants')->nullable()->comment('Minimum participants required for a group rule.');
            $table->unsignedInteger('minimum_advance_days')->nullable()->comment('Minimum advance booking days for early-bird rules.');
            $table->string('adjustment_type', 24)->comment('Fixed, percentage, or override calculation.');
            $table->bigInteger('adjustment_value')->comment('Signed basis points or minor units according to adjustment type.');
            $table->unsignedInteger('priority')->default(100)->comment('Lower values evaluate first.');
            $table->boolean('is_stackable')->default(false)->comment('Whether another compatible rule may also apply.');
            $table->boolean('is_active')->default(true)->comment('Whether the pricing engine evaluates this rule.');
            $table->json('conditions')->nullable()->comment('Validated non-executable rule conditions.');
            $table->timestamp('created_at')->nullable()->comment('UTC instant this record was created.');
            $table->timestamp('updated_at')->nullable()->comment('UTC instant this record was last updated.');
            $table->index(['rate_plan_id', 'is_active', 'priority']);
            $table->index(['departure_id', 'is_active']);
        });
    }

    /** Drop only these module tables in reverse foreign-key dependency order. */
    public function down(): void
    {
        Schema::dropIfExists('travel_pricing_rules');
        Schema::dropIfExists('travel_availability_holds');
        Schema::dropIfExists('travel_departure_staff');
        Schema::dropIfExists('travel_tour_departures');
    }
};
