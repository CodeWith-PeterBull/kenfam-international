<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the one-to-one personal profile extension for user accounts.
     */
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table): void {
            $table->id()->comment('Primary key for the user profile record.');
            $table->foreignId('user_id')
                ->unique()
                ->comment('User account that owns this profile.')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('first_name', 100)->nullable()->comment('User legal or preferred first name.');
            $table->string('middle_name', 100)->nullable()->comment('Optional user middle name.');
            $table->string('last_name', 100)->nullable()->comment('User family or last name.');
            $table->date('date_of_birth')->nullable()->comment('User date of birth when operationally required.');
            $table->string('identification_type', 30)->nullable()->comment('Identification category backed by App\\Enums\\IdentificationType.');
            $table->string('identification_number', 100)->nullable()->unique()->comment('Unique national ID, passport, or equivalent reference.');
            $table->string('phone', 40)->nullable()->comment('Primary user contact telephone number in a display-safe international format.');
            $table->string('job_title', 150)->nullable()->comment('Corporate role or job title shown on the user profile.');
            $table->text('bio')->nullable()->comment('Short user biography or internal profile summary.');
            $table->timestamp('created_at')->nullable()->comment('Timestamp when the user profile was created.');
            $table->timestamp('updated_at')->nullable()->comment('Timestamp when the user profile was last updated.');

            $table->index(['last_name', 'first_name']);
            $table->index('phone');
        });
    }

    /**
     * Remove the user profile extension.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
