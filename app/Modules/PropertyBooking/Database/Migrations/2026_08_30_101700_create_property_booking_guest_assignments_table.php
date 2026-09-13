<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Associates reusable guests with bookings and optional stay lines. */
return new class extends Migration
{
    /** Create the documented module table and constraints. */
    public function up(): void
    {
        Schema::create('property_booking_guest_assignments', function (Blueprint $table): void {
            $table->foreignId('booking_id')->comment('Booking occupied by this guest.')->constrained('property_bookings')->cascadeOnDelete();
            $table->foreignId('guest_id')->comment('Reusable guest assigned to the booking.')->constrained('property_booking_guests')->restrictOnDelete();
            $table->foreignId('booking_stay_id')->nullable()->comment('Optional specific stay line occupied by the guest.')->constrained('property_booking_stays')->nullOnDelete();
            $table->boolean('is_primary')->default(false)->comment('Whether this guest is the booking contact and document recipient.');
            $table->unsignedBigInteger('primary_booking_guard')->nullable()->unique()->comment('Booking ID only for the primary row, enforcing one primary guest per booking.');
            $table->timestamp('checked_in_at')->nullable()->comment('UTC timestamp when this occupant was recorded as present.');
            $table->timestamp('checked_out_at')->nullable()->comment('UTC timestamp when this occupant departed.');
            $table->timestamp('created_at')->nullable()->comment('Timestamp when the guest was assigned.');
            $table->timestamp('updated_at')->nullable()->comment('Timestamp when the occupant assignment last changed.');

            $table->primary(['booking_id', 'guest_id'], 'pb_guest_assignments_primary');
            $table->index(['booking_stay_id', 'is_primary'], 'pb_guest_assignments_stay_index');
        });
    }

    /** Remove the module table during rollback. */
    public function down(): void
    {
        Schema::dropIfExists('property_booking_guest_assignments');
    }
};
