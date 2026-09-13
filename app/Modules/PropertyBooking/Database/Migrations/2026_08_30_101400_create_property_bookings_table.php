<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Creates the reservation aggregate with immutable operational snapshots. */
return new class extends Migration
{
    /** Create the documented module table and constraints. */
    public function up(): void
    {
        Schema::create('property_bookings', function (Blueprint $table): void {
            $table->id()->comment('Internal integer primary key for booking relationships and locks.');
            $table->ulid('ulid')->unique()->comment('Immutable public route identifier; authorization remains mandatory.');
            $table->string('booking_number', 50)->nullable()->unique()->comment('Human-readable business number assigned once at placement.');
            $table->string('channel', 20)->comment('Originating surface backed by Bookings Enums BookingChannel.');
            $table->foreignId('property_id')->comment('Property owning every stay in this booking.')->constrained('property_booking_properties')->restrictOnDelete();
            $table->foreignId('primary_guest_id')->nullable()->comment('Reusable primary guest record linked to this booking.')->constrained('property_booking_guests')->nullOnDelete();
            $table->foreignId('register_id')->nullable()->comment('Reception register used for a POB booking.')->constrained('property_booking_registers')->nullOnDelete();
            $table->foreignId('reception_shift_id')->nullable()->comment('Open reception shift responsible for a POB booking.')->constrained('property_booking_shifts')->nullOnDelete();
            $table->foreignId('receptionist_id')->nullable()->comment('Receptionist responsible for a POB booking.')->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->comment('Staff creator, null for unauthenticated self-service placement.')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->comment('User who most recently changed the booking.')->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('held')->comment('Reservation lifecycle backed by Bookings Enums BookingStatus.');
            $table->string('stay_status', 20)->default('expected')->comment('Guest-presence lifecycle backed by Bookings Enums StayStatus.');
            $table->string('payment_status', 20)->default('unpaid')->comment('Settlement projection backed by Bookings Enums BookingPaymentStatus.');
            $table->timestamp('starts_at')->comment('Inclusive UTC start shared by every booking stay.');
            $table->timestamp('ends_at')->comment('Exclusive UTC end shared by every booking stay.');
            $table->string('property_timezone', 64)->comment('IANA property timezone snapshotted when the booking is calculated.');
            $table->unsignedSmallInteger('adult_count')->default(1)->comment('Total adults across all booking stays.');
            $table->unsignedSmallInteger('child_count')->default(0)->comment('Total children across all booking stays.');
            $table->unsignedSmallInteger('infant_count')->default(0)->comment('Total infants across all booking stays.');
            $table->char('currency', 3)->comment('ISO 4217 currency snapshotted from the property.');
            $table->unsignedBigInteger('accommodation_subtotal_minor')->default(0)->comment('Sum of stay subtotals before aggregate discount, in currency minor units.');
            $table->unsignedBigInteger('charges_subtotal_minor')->default(0)->comment('Sum of posted non-accommodation charges before tax, in currency minor units.');
            $table->unsignedBigInteger('discount_minor')->default(0)->comment('Aggregate booking discount in currency minor units.');
            $table->string('discount_reason', 180)->nullable()->comment('Optional staff-readable reason for the aggregate discount.');
            $table->unsignedBigInteger('tax_minor')->default(0)->comment('Total snapshotted tax across stays and posted charges, in currency minor units.');
            $table->unsignedBigInteger('total_minor')->default(0)->comment('Final booking amount payable in currency minor units.');
            $table->unsignedBigInteger('required_deposit_minor')->default(0)->comment('Required deposit projected from the selected rates, in currency minor units.');
            $table->unsignedBigInteger('paid_minor')->default(0)->comment('Net completed payments applied to the booking, in currency minor units.');
            $table->boolean('tax_inclusive')->default(true)->comment('Whether accommodation and charges were treated as tax inclusive.');
            $table->string('property_name', 180)->comment('Immutable property name snapshot for guest documents.');
            $table->string('property_code', 40)->comment('Immutable property operational code snapshot.');
            $table->string('property_address_summary', 320)->nullable()->comment('Immutable concise property address snapshot for guest documents.');
            $table->string('guest_first_name', 100)->comment('Immutable primary guest first-name snapshot.');
            $table->string('guest_middle_name', 100)->nullable()->comment('Immutable primary guest middle-name snapshot.');
            $table->string('guest_last_name', 100)->comment('Immutable primary guest last-name snapshot.');
            $table->string('guest_email', 255)->nullable()->comment('Immutable primary guest email snapshot for booking communication.');
            $table->string('guest_phone', 40)->nullable()->comment('Immutable primary guest phone snapshot for operational contact.');
            $table->char('guest_country_code', 2)->nullable()->comment('Immutable primary guest residence country code snapshot.');
            $table->text('special_requests')->nullable()->comment('Guest-provided stay request that does not alter confirmed inclusions.');
            $table->text('internal_note')->nullable()->comment('Staff-only operational note never rendered publicly.');
            $table->string('external_reference', 160)->nullable()->comment('Sanitized channel or partner reference without provider secrets.');
            $table->string('cancellation_reason', 255)->nullable()->comment('Reason recorded when an eligible booking is cancelled.');
            $table->string('no_show_reason', 255)->nullable()->comment('Reason recorded when an authorized operator marks a no-show.');
            $table->timestamp('hold_expires_at')->nullable()->comment('UTC expiry for an availability-consuming temporary hold.');
            $table->timestamp('pending_expires_at')->nullable()->comment('UTC expiry for a placed booking awaiting required action.');
            $table->timestamp('placed_at')->nullable()->comment('UTC timestamp when the final business number was assigned.');
            $table->timestamp('confirmed_at')->nullable()->comment('UTC timestamp when the booking became confirmed.');
            $table->timestamp('cancelled_at')->nullable()->comment('UTC timestamp when the booking was cancelled.');
            $table->timestamp('no_show_at')->nullable()->comment('UTC timestamp when the booking was explicitly marked no-show.');
            $table->timestamp('checked_in_at')->nullable()->comment('UTC timestamp when the primary booking check-in completed.');
            $table->timestamp('checked_out_at')->nullable()->comment('UTC timestamp when the primary booking check-out completed.');
            $table->timestamp('completed_at')->nullable()->comment('UTC timestamp when checkout completed the booking lifecycle.');
            $table->timestamp('hold_expiring_notified_at')->nullable()->comment('Idempotency marker for the bounded hold-expiry reminder.');
            $table->timestamp('arrival_due_notified_at')->nullable()->comment('Idempotency marker for the bounded arrival-due operational notice.');
            $table->timestamp('departure_due_notified_at')->nullable()->comment('Idempotency marker for the bounded departure-due operational notice.');
            $table->timestamp('created_at')->nullable()->comment('Timestamp when the booking aggregate was created.');
            $table->timestamp('updated_at')->nullable()->comment('Timestamp when the booking aggregate was last changed.');

            $table->index(['channel', 'status', 'created_at'], 'pb_bookings_channel_status_index');
            $table->index(['property_id', 'status', 'starts_at'], 'pb_bookings_property_arrival_index');
            $table->index(['property_id', 'stay_status', 'starts_at'], 'pb_bookings_stay_status_index');
            $table->index(['property_id', 'payment_status', 'created_at'], 'pb_bookings_payment_status_index');
            $table->index(['primary_guest_id', 'created_at'], 'pb_bookings_guest_index');
            $table->index(['reception_shift_id', 'created_at'], 'pb_bookings_shift_index');
            $table->index('hold_expires_at', 'pb_bookings_hold_expiry_index');
            $table->index('pending_expires_at', 'pb_bookings_pending_expiry_index');
        });
    }

    /** Remove the module table during rollback. */
    public function down(): void
    {
        Schema::dropIfExists('property_bookings');
    }
};
