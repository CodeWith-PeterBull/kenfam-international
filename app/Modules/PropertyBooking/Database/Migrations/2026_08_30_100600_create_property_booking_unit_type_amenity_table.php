<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Associates reusable amenities with sellable unit types. */
return new class extends Migration
{
    /** Create the documented module table and constraints. */
    public function up(): void
    {
        Schema::create('property_booking_unit_type_amenity', function (Blueprint $table): void {
            $table->foreignId('unit_type_id')->comment('Sellable accommodation type receiving the amenity.')->constrained('property_booking_unit_types')->cascadeOnDelete();
            $table->foreignId('amenity_id')->comment('Reusable amenity assigned to the unit type.')->constrained('property_booking_amenities')->cascadeOnDelete();
            $table->string('detail', 180)->nullable()->comment('Optional safe public qualifier such as private bathroom.');
            $table->timestamp('created_at')->nullable()->comment('Timestamp when the amenity was assigned.');
            $table->timestamp('updated_at')->nullable()->comment('Timestamp when the assignment detail last changed.');

            $table->primary(['unit_type_id', 'amenity_id'], 'pb_unit_type_amenity_primary');
        });
    }

    /** Remove the module table during rollback. */
    public function down(): void
    {
        Schema::dropIfExists('property_booking_unit_type_amenity');
    }
};
