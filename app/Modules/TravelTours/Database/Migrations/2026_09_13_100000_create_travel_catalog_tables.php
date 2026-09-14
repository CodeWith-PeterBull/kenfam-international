<?php

/**
 * Defines an ordered, reversible portion of the TravelTours persistence schema.
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Create the independent travel catalog, itinerary, content, and extras schema. */
return new class extends Migration
{
    /** Create the travel catalog tables without touching other modules. */
    public function up(): void
    {
        Schema::create('travel_tour_categories', function (Blueprint $table): void {
            $table->id()->comment('Internal category primary key.');
            $table->ulid('ulid')->unique()->comment('Immutable public category identifier.');
            $table->foreignId('parent_id')->nullable()->comment('Optional parent category in the adjacency hierarchy.')->constrained('travel_tour_categories')->nullOnDelete();
            $table->string('name', 160)->comment('Public category name.');
            $table->string('slug', 180)->unique()->comment('Unique URL-safe category identifier.');
            $table->text('description')->nullable()->comment('Public category description.');
            $table->string('icon_key', 80)->nullable()->comment('Validated icon-library key, never raw markup.');
            $table->unsignedInteger('sort_order')->default(0)->comment('Ascending storefront display order.');
            $table->boolean('is_active')->default(true)->comment('Whether the category is available for assignment and discovery.');
            $table->string('meta_title', 160)->nullable()->comment('Optional SEO title override.');
            $table->string('meta_description', 320)->nullable()->comment('Optional SEO description override.');
            $table->timestamp('created_at')->nullable()->comment('UTC instant this record was created.');
            $table->timestamp('updated_at')->nullable()->comment('UTC instant this record was last updated.');
            $table->index(['parent_id', 'is_active', 'sort_order'], 'travel_categories_navigation_index');
        });

        Schema::create('travel_destinations', function (Blueprint $table): void {
            $table->id()->comment('Internal destination primary key.');
            $table->ulid('ulid')->unique()->comment('Immutable public destination identifier.');
            $table->foreignId('parent_id')->nullable()->comment('Optional parent destination.')->constrained('travel_destinations')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->comment('User who created the destination.')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->comment('User who last changed the destination.')->constrained('users')->nullOnDelete();
            $table->string('type', 24)->comment('Destination hierarchy type backed by DestinationType.');
            $table->char('country_code', 2)->nullable()->comment('ISO 3166-1 alpha-2 country code when applicable.');
            $table->string('code', 40)->nullable()->unique()->comment('Optional stable operational destination code.');
            $table->string('name', 180)->comment('Public destination name.');
            $table->string('slug', 180)->unique()->comment('Unique URL-safe destination identifier.');
            $table->string('short_description', 320)->nullable()->comment('Compact public destination summary.');
            $table->longText('description')->nullable()->comment('Full editorial destination narrative.');
            $table->decimal('latitude', 10, 7)->nullable()->comment('WGS84 latitude validated between -90 and 90.');
            $table->decimal('longitude', 10, 7)->nullable()->comment('WGS84 longitude validated between -180 and 180.');
            $table->string('timezone', 64)->nullable()->comment('IANA timezone used for destination-local presentation.');
            $table->boolean('is_featured')->default(false)->comment('Whether the destination receives featured placement.');
            $table->boolean('is_active')->default(true)->comment('Whether the destination is assignable.');
            $table->string('status', 20)->default('draft')->comment('Publication lifecycle backed by PublicationStatus.');
            $table->unsignedInteger('sort_order')->default(0)->comment('Ascending display order among sibling destinations.');
            $table->string('meta_title', 160)->nullable()->comment('Optional SEO title override.');
            $table->string('meta_description', 320)->nullable()->comment('Optional SEO description override.');
            $table->timestamp('published_at')->nullable()->comment('UTC publication eligibility timestamp.');
            $table->timestamp('created_at')->nullable()->comment('UTC instant this record was created.');
            $table->timestamp('updated_at')->nullable()->comment('UTC instant this record was last updated.');
            $table->softDeletes()->comment('Archive timestamp excluded from ordinary queries.');
            $table->index(['parent_id', 'type', 'is_active'], 'travel_destinations_hierarchy_index');
            $table->index(['status', 'published_at'], 'travel_destinations_publication_index');
            $table->index(['country_code', 'is_featured'], 'travel_destinations_country_index');
        });

        Schema::create('travel_tours', function (Blueprint $table): void {
            $table->id()->comment('Internal tour primary key used for joins and locking.');
            $table->ulid('ulid')->unique()->comment('Immutable public tour identifier.');
            $table->foreignId('created_by')->nullable()->comment('User who created the tour.')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->comment('User who last changed the tour.')->constrained('users')->nullOnDelete();
            $table->string('code', 40)->unique()->comment('Stable globally unique operational tour code.');
            $table->string('slug', 180)->unique()->comment('Unique URL-safe tour identifier.');
            $table->string('type', 24)->comment('Tour product type backed by TourType.');
            $table->string('status', 20)->default('draft')->comment('Publication lifecycle backed by PublicationStatus.');
            $table->string('name', 200)->comment('Public tour name.');
            $table->string('tagline', 240)->nullable()->comment('Optional short marketing tagline.');
            $table->string('short_description', 360)->nullable()->comment('Compact public card and search description.');
            $table->longText('description')->nullable()->comment('Full public tour overview.');
            $table->unsignedSmallInteger('duration_days')->comment('Published tour duration in calendar days.');
            $table->unsignedSmallInteger('duration_nights')->default(0)->comment('Published overnight count.');
            $table->unsignedSmallInteger('minimum_age')->default(0)->comment('Minimum traveler age in years.');
            $table->string('difficulty', 20)->default('easy')->comment('Physical activity level backed by TourDifficulty.');
            $table->unsignedSmallInteger('minimum_participants')->default(1)->comment('Smallest bookable participant count.');
            $table->unsignedSmallInteger('maximum_participants')->nullable()->comment('Optional tour-level participant ceiling.');
            $table->json('languages')->nullable()->comment('Validated list of languages available for the tour.');
            $table->string('booking_mode', 20)->default('approval')->comment('Default instant or approval booking behavior.');
            $table->string('meeting_point_name', 200)->nullable()->comment('Default public meeting point label.');
            $table->text('meeting_point_details')->nullable()->comment('Default meeting instructions.');
            $table->decimal('meeting_latitude', 10, 7)->nullable()->comment('Optional default meeting-point latitude.');
            $table->decimal('meeting_longitude', 10, 7)->nullable()->comment('Optional default meeting-point longitude.');
            $table->string('end_point_name', 200)->nullable()->comment('Default public end point label.');
            $table->text('end_point_details')->nullable()->comment('Default tour completion instructions.');
            $table->decimal('end_latitude', 10, 7)->nullable()->comment('Optional default tour end-point latitude.');
            $table->decimal('end_longitude', 10, 7)->nullable()->comment('Optional default tour end-point longitude.');
            $table->longText('terms')->nullable()->comment('Tour-specific operating terms presented before booking.');
            $table->text('cancellation_summary')->nullable()->comment('Public cancellation policy summary.');
            $table->string('policy_version', 60)->nullable()->comment('Version of the approved tour terms presented for new bookings.');
            $table->boolean('is_featured')->default(false)->comment('Whether the tour receives featured placement.');
            $table->unsignedInteger('sort_order')->default(0)->comment('Ascending merchandising order.');
            $table->string('meta_title', 160)->nullable()->comment('Optional SEO title override.');
            $table->string('meta_description', 320)->nullable()->comment('Optional SEO description override.');
            $table->timestamp('published_at')->nullable()->comment('UTC publication eligibility timestamp.');
            $table->timestamp('created_at')->nullable()->comment('UTC instant this record was created.');
            $table->timestamp('updated_at')->nullable()->comment('UTC instant this record was last updated.');
            $table->softDeletes()->comment('Archive timestamp excluded from ordinary queries.');
            $table->index(['status', 'published_at'], 'travel_tours_publication_index');
            $table->index(['type', 'status'], 'travel_tours_type_index');
            $table->index(['is_featured', 'status', 'sort_order'], 'travel_tours_featured_index');
            $table->index(['duration_days', 'minimum_participants'], 'travel_tours_duration_index');
        });

        Schema::create('travel_tour_category', function (Blueprint $table): void {
            $table->id()->comment('Internal tour-category assignment primary key.');
            $table->ulid('ulid')->unique()->comment('Immutable public assignment identifier.');
            $table->foreignId('tour_id')->comment('Assigned tour.')->constrained('travel_tours')->cascadeOnDelete();
            $table->foreignId('category_id')->comment('Assigned category.')->constrained('travel_tour_categories')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false)->comment('Whether this is the primary category for display.');
            $table->unsignedInteger('sort_order')->default(0)->comment('Category order within the tour.');
            $table->timestamp('created_at')->nullable()->comment('UTC instant this record was created.');
            $table->timestamp('updated_at')->nullable()->comment('UTC instant this record was last updated.');
            $table->unique(['tour_id', 'category_id'], 'travel_tour_category_unique');
        });

        Schema::create('travel_tour_destination', function (Blueprint $table): void {
            $table->id()->comment('Internal tour-destination assignment primary key.');
            $table->ulid('ulid')->unique()->comment('Immutable public assignment identifier.');
            $table->foreignId('tour_id')->comment('Assigned tour.')->constrained('travel_tours')->cascadeOnDelete();
            $table->foreignId('destination_id')->comment('Assigned destination.')->constrained('travel_destinations')->cascadeOnDelete();
            $table->string('role', 24)->default('visit')->comment('Destination role such as start, visit, overnight, or end.');
            $table->unsignedSmallInteger('sequence')->default(0)->comment('Chronological destination visit sequence.');
            $table->boolean('is_overnight')->default(false)->comment('Whether the itinerary includes an overnight stay here.');
            $table->timestamp('created_at')->nullable()->comment('UTC instant this record was created.');
            $table->timestamp('updated_at')->nullable()->comment('UTC instant this record was last updated.');
            $table->unique(['tour_id', 'sequence'], 'travel_tour_destination_sequence_unique');
            $table->index(['destination_id', 'role'], 'travel_tour_destination_lookup_index');
        });

        Schema::create('travel_itinerary_days', function (Blueprint $table): void {
            $table->id()->comment('Internal itinerary-day primary key.');
            $table->ulid('ulid')->unique()->comment('Immutable public itinerary-day identifier.');
            $table->foreignId('tour_id')->comment('Owning tour.')->constrained('travel_tours')->cascadeOnDelete();
            $table->foreignId('start_destination_id')->nullable()->comment('Optional day start destination.')->constrained('travel_destinations')->nullOnDelete();
            $table->foreignId('end_destination_id')->nullable()->comment('Optional day end destination.')->constrained('travel_destinations')->nullOnDelete();
            $table->unsignedSmallInteger('day_number')->comment('One-based itinerary day number.');
            $table->string('title', 200)->comment('Public itinerary-day title.');
            $table->longText('description')->nullable()->comment('Full day narrative.');
            $table->json('meals')->nullable()->comment('Validated ordered list of included meal labels.');
            $table->string('accommodation', 240)->nullable()->comment('Human-readable overnight accommodation summary.');
            $table->unsignedInteger('sort_order')->default(0)->comment('Stable display order.');
            $table->timestamp('created_at')->nullable()->comment('UTC instant this record was created.');
            $table->timestamp('updated_at')->nullable()->comment('UTC instant this record was last updated.');
            $table->unique(['tour_id', 'day_number'], 'travel_itinerary_day_number_unique');
            $table->index(['tour_id', 'sort_order'], 'travel_itinerary_day_order_index');
        });

        Schema::create('travel_itinerary_activities', function (Blueprint $table): void {
            $table->id()->comment('Internal itinerary-activity primary key.');
            $table->ulid('ulid')->unique()->comment('Immutable public itinerary-activity identifier.');
            $table->foreignId('itinerary_day_id')->comment('Owning itinerary day.')->constrained('travel_itinerary_days')->cascadeOnDelete();
            $table->foreignId('destination_id')->nullable()->comment('Optional activity destination.')->constrained('travel_destinations')->nullOnDelete();
            $table->string('title', 200)->comment('Public activity title.');
            $table->text('description')->nullable()->comment('Public activity description.');
            $table->time('starts_at_local')->nullable()->comment('Optional advertised local start time.');
            $table->time('ends_at_local')->nullable()->comment('Optional advertised local end time.');
            $table->string('location_name', 200)->nullable()->comment('Public activity location label.');
            $table->decimal('latitude', 10, 7)->nullable()->comment('Optional WGS84 activity latitude.');
            $table->decimal('longitude', 10, 7)->nullable()->comment('Optional WGS84 activity longitude.');
            $table->boolean('is_included')->default(true)->comment('Whether the activity is included in the tour price.');
            $table->boolean('is_optional')->default(false)->comment('Whether travelers may opt out or purchase separately.');
            $table->unsignedInteger('sequence')->default(0)->comment('Ascending activity sequence within the day.');
            $table->timestamp('created_at')->nullable()->comment('UTC instant this record was created.');
            $table->timestamp('updated_at')->nullable()->comment('UTC instant this record was last updated.');
            $table->unique(['itinerary_day_id', 'sequence'], 'travel_itinerary_activity_sequence_unique');
        });

        Schema::create('travel_tour_content_items', function (Blueprint $table): void {
            $table->id()->comment('Internal structured-content item primary key.');
            $table->ulid('ulid')->unique()->comment('Immutable public content item identifier.');
            $table->foreignId('tour_id')->comment('Owning tour.')->constrained('travel_tours')->cascadeOnDelete();
            $table->string('type', 24)->comment('Content type backed by ContentItemType.');
            $table->string('title', 180)->nullable()->comment('Optional short content item heading.');
            $table->text('content')->comment('Sanitized public item text.');
            $table->unsignedInteger('sort_order')->default(0)->comment('Ascending display order within its type.');
            $table->timestamp('created_at')->nullable()->comment('UTC instant this record was created.');
            $table->timestamp('updated_at')->nullable()->comment('UTC instant this record was last updated.');
            $table->index(['tour_id', 'type', 'sort_order'], 'travel_tour_content_type_index');
        });

        Schema::create('travel_tour_faqs', function (Blueprint $table): void {
            $table->id()->comment('Internal tour FAQ primary key.');
            $table->ulid('ulid')->unique()->comment('Immutable public FAQ identifier.');
            $table->foreignId('tour_id')->comment('Owning tour.')->constrained('travel_tours')->cascadeOnDelete();
            $table->string('question', 320)->comment('Public frequently asked question.');
            $table->longText('answer')->comment('Sanitized public answer.');
            $table->unsignedInteger('sort_order')->default(0)->comment('Ascending FAQ display order.');
            $table->boolean('is_active')->default(true)->comment('Whether the FAQ is publicly eligible.');
            $table->timestamp('created_at')->nullable()->comment('UTC instant this record was created.');
            $table->timestamp('updated_at')->nullable()->comment('UTC instant this record was last updated.');
            $table->index(['tour_id', 'is_active', 'sort_order'], 'travel_tour_faq_order_index');
        });

        Schema::create('travel_tour_extras', function (Blueprint $table): void {
            $table->id()->comment('Internal tour-extra primary key.');
            $table->ulid('ulid')->unique()->comment('Immutable public tour-extra identifier.');
            $table->foreignId('tour_id')->comment('Owning tour.')->constrained('travel_tours')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->comment('User who created the extra.')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->comment('User who last changed the extra.')->constrained('users')->nullOnDelete();
            $table->string('code', 40)->comment('Stable code unique within the tour.');
            $table->string('name', 180)->comment('Public extra name.');
            $table->text('description')->nullable()->comment('Public extra description.');
            $table->string('pricing_unit', 24)->default('per_person')->comment('Pricing unit such as per person or per booking.');
            $table->unsignedBigInteger('amount_minor')->comment('Price in integer minor currency units.');
            $table->char('currency', 3)->comment('ISO 4217 price currency.');
            $table->json('participant_types')->nullable()->comment('Optional validated participant-class allowlist.');
            $table->unsignedSmallInteger('minimum_quantity')->default(0)->comment('Minimum selectable quantity.');
            $table->unsignedSmallInteger('maximum_quantity')->nullable()->comment('Optional maximum selectable quantity.');
            $table->boolean('is_active')->default(true)->comment('Whether the extra remains selectable.');
            $table->boolean('is_public')->default(true)->comment('Whether the extra is offered in public checkout.');
            $table->unsignedInteger('sort_order')->default(0)->comment('Ascending display order.');
            $table->timestamp('created_at')->nullable()->comment('UTC instant this record was created.');
            $table->timestamp('updated_at')->nullable()->comment('UTC instant this record was last updated.');
            $table->softDeletes()->comment('Archive timestamp excluded from ordinary queries.');
            $table->unique(['tour_id', 'code'], 'travel_tour_extra_code_unique');
            $table->index(['tour_id', 'is_active', 'is_public'], 'travel_tour_extra_public_index');
        });
    }

    /** Drop only these module tables in reverse foreign-key dependency order. */
    public function down(): void
    {
        Schema::dropIfExists('travel_tour_extras');
        Schema::dropIfExists('travel_tour_faqs');
        Schema::dropIfExists('travel_tour_content_items');
        Schema::dropIfExists('travel_itinerary_activities');
        Schema::dropIfExists('travel_itinerary_days');
        Schema::dropIfExists('travel_tour_destination');
        Schema::dropIfExists('travel_tour_category');
        Schema::dropIfExists('travel_tours');
        Schema::dropIfExists('travel_destinations');
        Schema::dropIfExists('travel_tour_categories');
    }
};
