<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Creates explicit property access assignments for non-global operators. */
return new class extends Migration
{
    /** Create the documented module table and constraints. */
    public function up(): void
    {
        Schema::create('property_booking_property_user', function (Blueprint $table): void {
            $table->foreignId('property_id')->comment('Property available to the assigned operator.')->constrained('property_booking_properties')->cascadeOnDelete();
            $table->foreignId('user_id')->comment('Operator receiving property-scoped access.')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->comment('User who created or last reconciled the access assignment.')->constrained('users')->nullOnDelete();
            $table->boolean('is_default')->default(false)->comment('Whether this property should be selected first for the operator.');
            $table->timestamp('created_at')->nullable()->comment('Timestamp when property access was assigned.');
            $table->timestamp('updated_at')->nullable()->comment('Timestamp when the assignment was last changed.');

            $table->primary(['property_id', 'user_id'], 'pb_property_user_primary');
            $table->index(['user_id', 'is_default'], 'pb_property_user_default_index');
        });
    }

    /** Remove the module table during rollback. */
    public function down(): void
    {
        Schema::dropIfExists('property_booking_property_user');
    }
};
