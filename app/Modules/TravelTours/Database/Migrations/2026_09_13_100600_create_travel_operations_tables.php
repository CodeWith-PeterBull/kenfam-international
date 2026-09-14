<?php

/**
 * Defines an ordered, reversible portion of the TravelTours persistence schema.
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Create the module-owned travel operations schema in dependency order. */
return new class extends Migration
{
    /** Create the travel operations tables without touching other modules. */
    public function up(): void
    {
        Schema::create('travel_booking_shift_movements', function (Blueprint $table): void {
            $table->id()->comment('Internal primary key.');
            $table->ulid('ulid')->unique()->comment('Public immutable identifier.');
            $table->foreignId('shift_id')->comment('Shift owning the movement.')->constrained('travel_booking_shifts')->restrictOnDelete();
            $table->string('operation_key', 64)->unique()->comment('Idempotency key preventing duplicate financial movements on retry.');
            $table->foreignId('booking_id')->nullable()->comment('Related booking when applicable.')->constrained('travel_tour_bookings')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->comment('Related payment when applicable.')->constrained('travel_booking_payments')->nullOnDelete();
            $table->foreignId('refund_id')->nullable()->comment('Related refund when applicable.')->constrained('travel_booking_refunds')->nullOnDelete();
            $table->string('movement_type', 30)->comment('Opening float, payment, cash-in, cash-out, or refund.');
            $table->bigInteger('amount_minor')->comment('Signed movement amount in shift currency minor units.');
            $table->char('currency', 3)->comment('ISO currency of the movement.');
            $table->text('reason')->nullable()->comment('Required business reason for manual movements.');
            $table->foreignId('actor_id')->comment('Operator responsible for the movement.')->constrained('users')->restrictOnDelete();
            $table->timestamp('occurred_at')->comment('UTC movement instant.');
            $table->timestamp('created_at')->nullable()->comment('UTC instant this record was created.');
            $table->timestamp('updated_at')->nullable()->comment('UTC instant this record was last updated.');
            $table->index(['shift_id', 'occurred_at']);
            $table->index(['movement_type', 'occurred_at']);
            $table->unique(['payment_id', 'movement_type'], 'travel_shift_payment_movement_unique');
            $table->unique(['refund_id', 'movement_type'], 'travel_shift_refund_movement_unique');
        });

        Schema::create('travel_tour_inquiries', function (Blueprint $table): void {
            $table->id()->comment('Internal primary key.');
            $table->ulid('ulid')->unique()->comment('Public immutable identifier.');
            $table->string('reference', 80)->unique()->comment('Human-readable inquiry reference.');
            $table->string('operation_key', 64)->unique()->comment('Idempotency key preventing duplicate public inquiry submission.');
            $table->foreignId('tour_id')->nullable()->comment('Tour being discussed when known.')->constrained('travel_tours')->nullOnDelete();
            $table->foreignId('departure_id')->nullable()->comment('Departure being discussed when known.')->constrained('travel_tour_departures')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->comment('Known customer profile.')->constrained('travel_customers')->nullOnDelete();
            $table->foreignId('converted_booking_id')->nullable()->comment('Booking created from this inquiry.')->constrained('travel_tour_bookings')->nullOnDelete();
            $table->string('inquiry_type', 30)->comment('General, tour, private-tour, or custom-tour inquiry.');
            $table->string('contact_name', 190)->comment('Enquirer full name.');
            $table->string('contact_email', 254)->nullable()->comment('Enquirer email address.');
            $table->string('contact_phone', 32)->nullable()->comment('Enquirer telephone number.');
            $table->boolean('whatsapp_preferred')->default(false)->comment('Whether WhatsApp is the preferred response channel.');
            $table->json('requested_destinations')->nullable()->comment('Validated ordered destination names or identifiers requested for a custom inquiry.');
            $table->date('preferred_start_date')->nullable()->comment('Preferred travel start date.');
            $table->date('preferred_end_date')->nullable()->comment('Preferred travel end date.');
            $table->unsignedInteger('adult_count')->default(0)->comment('Requested adult participant count.');
            $table->unsignedInteger('child_count')->default(0)->comment('Requested child participant count.');
            $table->unsignedInteger('infant_count')->default(0)->comment('Requested infant participant count.');
            $table->char('budget_currency', 3)->nullable()->comment('ISO currency of the stated budget.');
            $table->unsignedBigInteger('budget_minor')->nullable()->comment('Stated budget in minor units.');
            $table->text('message')->comment('Customer inquiry message.');
            $table->string('source', 50)->default('website')->comment('Inquiry acquisition source.');
            $table->foreignId('assigned_to')->nullable()->comment('Operator responsible for follow-up.')->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('new')->comment('Inquiry lifecycle state.');
            $table->timestamp('follow_up_at')->nullable()->comment('UTC instant for the next follow-up.');
            $table->timestamp('responded_at')->nullable()->comment('UTC instant of the first response.');
            $table->timestamp('closed_at')->nullable()->comment('UTC inquiry closure instant.');
            $table->string('consent_ip', 45)->nullable()->comment('IP address recorded with contact consent.');
            $table->timestamp('consent_recorded_at')->nullable()->comment('UTC contact-consent instant.');
            $table->string('consent_purpose', 80)->nullable()->comment('Specific communication purpose accepted with this inquiry.');
            $table->string('consent_version', 60)->nullable()->comment('Version of consent wording presented for this inquiry.');
            $table->timestamp('created_at')->nullable()->comment('UTC instant this record was created.');
            $table->timestamp('updated_at')->nullable()->comment('UTC instant this record was last updated.');
            $table->index(['status', 'follow_up_at']);
            $table->index(['assigned_to', 'status']);
            $table->index(['tour_id', 'created_at']);
        });

        Schema::create('travel_tour_inquiry_activities', function (Blueprint $table): void {
            $table->id()->comment('Internal primary key.');
            $table->ulid('ulid')->unique()->comment('Public immutable identifier.');
            $table->foreignId('inquiry_id')->comment('Owning inquiry.')->constrained('travel_tour_inquiries')->cascadeOnDelete();
            $table->string('activity_type', 50)->comment('Note, call, email, WhatsApp, assignment, state change, or conversion.');
            $table->text('note')->nullable()->comment('Human-readable activity detail.');
            $table->foreignId('actor_id')->nullable()->comment('Operator who recorded the activity.')->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at')->comment('UTC activity instant.');
            $table->timestamp('created_at')->nullable()->comment('UTC instant this record was created.');
            $table->timestamp('updated_at')->nullable()->comment('UTC instant this record was last updated.');
            $table->index(['inquiry_id', 'occurred_at']);
        });
    }

    /** Drop only these module tables in reverse foreign-key dependency order. */
    public function down(): void
    {
        Schema::dropIfExists('travel_tour_inquiry_activities');
        Schema::dropIfExists('travel_tour_inquiries');
        Schema::dropIfExists('travel_booking_shift_movements');
    }
};
