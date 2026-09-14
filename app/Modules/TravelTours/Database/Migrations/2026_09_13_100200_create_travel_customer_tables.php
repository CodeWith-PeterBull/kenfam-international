<?php

/**
 * Defines an ordered, reversible portion of the TravelTours persistence schema.
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Create the module-owned travel customer schema in dependency order. */
return new class extends Migration
{
    /** Create the travel customer tables without touching other modules. */
    public function up(): void
    {
        Schema::create('travel_customers', function (Blueprint $table): void {
            $table->id()->comment('Internal primary key.');
            $table->ulid('ulid')->unique()->comment('Public immutable identifier.');
            $table->foreignId('user_id')->nullable()->unique()->comment('Optional authenticated platform account owned by this customer.')->constrained()->nullOnDelete();
            $table->string('title', 30)->nullable()->comment('Courtesy title supplied by the customer.');
            $table->string('first_name', 100)->comment('Customer given name.');
            $table->string('middle_name', 100)->nullable()->comment('Customer middle name.');
            $table->string('last_name', 100)->comment('Customer family name.');
            $table->string('email', 254)->nullable()->comment('Normalized primary email address.');
            $table->string('email_hash', 64)->nullable()->index()->comment('Keyed lookup hash for normalized email.');
            $table->string('phone', 32)->nullable()->comment('Normalized primary telephone number.');
            $table->string('phone_hash', 64)->nullable()->index()->comment('Keyed lookup hash for normalized phone.');
            $table->unsignedSmallInteger('contact_hash_version')->default(1)->comment('Key version used to derive purpose-specific contact hashes.');
            $table->string('whatsapp_phone', 32)->nullable()->comment('Normalized WhatsApp telephone number.');
            $table->timestamp('email_verified_at')->nullable()->comment('UTC instant the customer email was verified for profile ownership.');
            $table->timestamp('phone_verified_at')->nullable()->comment('UTC instant the customer phone was verified for profile ownership.');
            $table->date('date_of_birth')->nullable()->comment('Customer date of birth.');
            $table->char('nationality_code', 2)->nullable()->comment('ISO 3166-1 alpha-2 nationality.');
            $table->string('address_line_1', 190)->nullable()->comment('Primary postal or street address.');
            $table->string('address_line_2', 190)->nullable()->comment('Secondary address detail.');
            $table->string('city', 120)->nullable()->comment('Address city or locality.');
            $table->string('region', 120)->nullable()->comment('Address region, state, or county.');
            $table->string('postal_code', 30)->nullable()->comment('Address postal code.');
            $table->char('country_code', 2)->nullable()->comment('ISO 3166-1 alpha-2 address country.');
            $table->boolean('email_consent')->default(false)->comment('Customer consent to non-transactional email.');
            $table->boolean('sms_consent')->default(false)->comment('Customer consent to non-transactional SMS.');
            $table->boolean('whatsapp_consent')->default(false)->comment('Customer consent to non-transactional WhatsApp.');
            $table->timestamp('consent_recorded_at')->nullable()->comment('UTC instant at which communication consent was recorded.');
            $table->string('consent_source', 40)->nullable()->comment('Storefront, account, booking desk, or import source of communication consent.');
            $table->string('consent_version', 60)->nullable()->comment('Version of communication consent wording accepted by the customer.');
            $table->string('status', 20)->default('active')->comment('Customer operational status.');
            $table->text('notes')->nullable()->comment('Restricted internal customer notes.');
            $table->foreignId('created_by')->nullable()->comment('Operator who created the customer.')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->comment('Operator who last updated the customer.')->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable()->comment('UTC instant this record was created.');
            $table->timestamp('updated_at')->nullable()->comment('UTC instant this record was last updated.');
            $table->softDeletes()->comment('Retention-safe administrative removal timestamp.');
            $table->index(['last_name', 'first_name']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('travel_travelers', function (Blueprint $table): void {
            $table->id()->comment('Internal primary key.');
            $table->ulid('ulid')->unique()->comment('Public immutable identifier.');
            $table->foreignId('customer_id')->nullable()->comment('Optional owning customer profile.')->constrained('travel_customers')->nullOnDelete();
            $table->string('title', 30)->nullable()->comment('Traveler courtesy title.');
            $table->string('first_name', 100)->comment('Traveler given name.');
            $table->string('middle_name', 100)->nullable()->comment('Traveler middle name.');
            $table->string('last_name', 100)->comment('Traveler family name.');
            $table->date('date_of_birth')->nullable()->comment('Traveler date of birth used for participant classification.');
            $table->string('participant_type', 20)->comment('Adult, child, or infant classification.');
            $table->char('nationality_code', 2)->nullable()->comment('ISO 3166-1 alpha-2 nationality.');
            $table->string('email', 254)->nullable()->comment('Traveler contact email.');
            $table->string('phone', 32)->nullable()->comment('Traveler contact telephone number.');
            $table->text('identity_number')->nullable()->comment('Encrypted national identity or passport number.');
            $table->string('identity_number_hash', 64)->nullable()->index()->comment('Keyed lookup hash for the identity number.');
            $table->string('identity_type', 30)->nullable()->comment('Identity document type.');
            $table->char('passport_issuing_country', 2)->nullable()->comment('ISO country issuing the passport.');
            $table->date('passport_expiry_date')->nullable()->comment('Passport expiry date.');
            $table->text('dietary_requirements')->nullable()->comment('Encrypted dietary requirements.');
            $table->text('accessibility_requirements')->nullable()->comment('Encrypted accessibility requirements.');
            $table->text('medical_notes')->nullable()->comment('Encrypted travel-relevant medical notes.');
            $table->string('emergency_contact_name', 190)->nullable()->comment('Emergency contact full name.');
            $table->string('emergency_contact_phone', 32)->nullable()->comment('Emergency contact telephone number.');
            $table->string('emergency_contact_relationship', 80)->nullable()->comment('Emergency contact relationship.');
            $table->boolean('is_active')->default(true)->comment('Whether the traveler profile can be selected.');
            $table->timestamp('created_at')->nullable()->comment('UTC instant this record was created.');
            $table->timestamp('updated_at')->nullable()->comment('UTC instant this record was last updated.');
            $table->softDeletes()->comment('Retention-safe administrative removal timestamp.');
            $table->index(['customer_id', 'is_active']);
            $table->index(['last_name', 'first_name']);
        });
    }

    /** Drop only these module tables in reverse foreign-key dependency order. */
    public function down(): void
    {
        Schema::dropIfExists('travel_travelers');
        Schema::dropIfExists('travel_customers');
    }
};
