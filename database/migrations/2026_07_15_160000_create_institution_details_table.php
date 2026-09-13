<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_details', function (Blueprint $table): void {
            $table->id()->comment('Primary key; the canonical institution profile uses id 1');
            $table->string('name')->comment('Full legal or public institution name');
            $table->string('short_name', 80)->comment('Compact institution name used in constrained layouts');
            $table->string('descriptor')->nullable()->comment('Institution tagline or communication descriptor');
            $table->string('primary_email')->nullable()->comment('Primary public contact email address');
            $table->string('secondary_email')->nullable()->comment('Secondary or support contact email address');
            $table->string('primary_phone', 40)->nullable()->comment('Primary public telephone number');
            $table->string('secondary_phone', 40)->nullable()->comment('Secondary public telephone number');
            $table->string('website')->nullable()->comment('Canonical public institution website URL');
            $table->string('physical_address')->nullable()->comment('Physical or street address');
            $table->string('city', 120)->nullable()->comment('Physical address city or town');
            $table->string('county', 120)->nullable()->comment('Physical address county, state, or province');
            $table->string('postal_code', 30)->nullable()->comment('Postal or ZIP code');
            $table->string('postal_address')->nullable()->comment('Postal or post office box address');
            $table->string('postal_city', 120)->nullable()->comment('Postal address city or town');
            $table->json('social_media')->nullable()->comment('Ordered public social platform, handle, and URL records');
            $table->timestamp('created_at')->nullable()->comment('Date and time the institution profile was created');
            $table->timestamp('updated_at')->nullable()->comment('Date and time the institution profile was last updated');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_details');
    }
};
