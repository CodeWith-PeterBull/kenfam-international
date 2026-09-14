<?php

/**
 * Defines an ordered, reversible portion of the TravelTours persistence schema.
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Create the module-owned travel booking schema in dependency order. */
return new class extends Migration
{
    /** Create the travel booking tables without touching other modules. */
    public function up(): void
    {
        Schema::create('travel_tour_bookings', function (Blueprint $table): void {
            $table->id()->comment('Internal primary key.');
            $table->ulid('ulid')->unique()->comment('Public immutable identifier.');
            $table->string('booking_number', 80)->unique()->comment('Human-readable booking reference.');
            $table->string('operation_key', 64)->unique()->comment('Idempotency key binding retries to one normalized booking request.');
            $table->foreignId('tour_id')->comment('Booked tour.')->constrained('travel_tours')->restrictOnDelete();
            $table->foreignId('departure_id')->comment('Booked departure.')->constrained('travel_tour_departures')->restrictOnDelete();
            $table->foreignId('customer_id')->comment('Customer owning the booking.')->constrained('travel_customers')->restrictOnDelete();
            $table->foreignId('availability_hold_id')->nullable()->unique()->comment('Availability hold consumed at most once by this booking.')->constrained('travel_availability_holds')->nullOnDelete();
            $table->foreignId('rate_plan_id')->nullable()->comment('Rate plan used to calculate the booking.')->constrained('travel_tour_rate_plans')->nullOnDelete();
            $table->foreignId('promotion_id')->nullable()->comment('Promotion applied to the booking.')->constrained('travel_promotions')->nullOnDelete();
            $table->foreignId('register_id')->nullable()->comment('Booking desk register used for placement.')->constrained('travel_booking_registers')->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->comment('Booking desk shift used for placement.')->constrained('travel_booking_shifts')->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->comment('Agent or operator responsible for the booking.')->constrained('users')->nullOnDelete();
            $table->string('channel', 30)->comment('Web, booking-desk, administration, or agent source.');
            $table->string('confirmation_mode', 24)->comment('Instant or approval confirmation mode snapshot.');
            $table->string('status', 24)->comment('Booking lifecycle state.');
            $table->string('payment_status', 24)->comment('Aggregate payment lifecycle state.');
            $table->unsignedInteger('adult_count')->default(0)->comment('Adult participant count.');
            $table->unsignedInteger('child_count')->default(0)->comment('Child participant count.');
            $table->unsignedInteger('infant_count')->default(0)->comment('Infant participant count.');
            $table->unsignedInteger('seat_count')->comment('Capacity-consuming participant count.');
            $table->char('currency', 3)->comment('ISO currency used by all monetary totals.');
            $table->unsignedTinyInteger('currency_exponent')->default(2)->comment('Minor-unit decimal exponent snapshotted for the booking currency.');
            $table->unsignedBigInteger('subtotal_minor')->comment('Pre-discount and pre-tax subtotal in minor units.');
            $table->unsignedBigInteger('extras_total_minor')->default(0)->comment('Selected extras total in minor units.');
            $table->unsignedBigInteger('discount_total_minor')->default(0)->comment('Discount total in minor units.');
            $table->unsignedBigInteger('tax_total_minor')->default(0)->comment('Tax total in minor units.');
            $table->unsignedBigInteger('total_minor')->comment('Final booking total in minor units.');
            $table->unsignedBigInteger('deposit_required_minor')->default(0)->comment('Required deposit in minor units.');
            $table->unsignedBigInteger('paid_minor')->default(0)->comment('Confirmed payment total in minor units.');
            $table->unsignedBigInteger('refunded_minor')->default(0)->comment('Successfully refunded total in booking currency minor units.');
            $table->string('preferred_payment_method', 30)->nullable()->comment('Customer-selected payment method.');
            $table->string('customer_name_snapshot', 240)->comment('Immutable lead-customer name snapshot.');
            $table->string('customer_email_snapshot', 254)->nullable()->comment('Immutable lead-customer email snapshot.');
            $table->string('customer_phone_snapshot', 32)->nullable()->comment('Immutable lead-customer phone snapshot.');
            $table->string('tour_name_snapshot', 220)->comment('Immutable tour name snapshot.');
            $table->string('tour_code_snapshot', 40)->comment('Immutable operational tour-code snapshot.');
            $table->string('departure_timezone_snapshot', 64)->comment('Immutable departure IANA timezone snapshot.');
            $table->timestamp('departure_starts_at_snapshot')->comment('Immutable UTC departure start snapshot.');
            $table->timestamp('departure_ends_at_snapshot')->comment('Immutable UTC departure end snapshot.');
            $table->text('cancellation_terms_snapshot')->nullable()->comment('Immutable cancellation policy snapshot.');
            $table->string('policy_version_snapshot', 60)->comment('Immutable approved booking-policy version accepted for this reservation.');
            $table->json('meeting_point_snapshot')->nullable()->comment('Validated public meeting-point details captured at placement.');
            $table->json('rate_plan_snapshot')->comment('Immutable selected rate, tax, deposit, refundability, and restriction details.');
            $table->json('pricing_snapshot')->comment('Immutable validated pricing inputs and adjustments.');
            $table->text('special_requests')->nullable()->comment('Encrypted customer-supplied booking requests.');
            $table->json('attribution')->nullable()->comment('Validated campaign and referral attribution metadata.');
            $table->timestamp('terms_accepted_at')->comment('UTC instant when booking terms were accepted.');
            $table->string('terms_version', 60)->comment('Version identifier for accepted booking terms.');
            $table->timestamp('placed_at')->comment('UTC booking placement instant.');
            $table->timestamp('pending_expires_at')->nullable()->comment('UTC approval/payment expiry instant.');
            $table->timestamp('confirmed_at')->nullable()->comment('UTC confirmation instant.');
            $table->timestamp('cancelled_at')->nullable()->comment('UTC cancellation instant.');
            $table->timestamp('expired_at')->nullable()->comment('UTC instant a pending booking expired and released capacity.');
            $table->timestamp('completed_at')->nullable()->comment('UTC tour completion instant.');
            $table->string('cancellation_reason_code', 80)->nullable()->comment('Stable cancellation, rejection, or expiry reason code.');
            $table->unsignedSmallInteger('public_access_version')->default(1)->comment('Version used to revoke previously issued signed customer links.');
            $table->foreignId('created_by')->nullable()->comment('Authenticated actor who placed the booking.')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->comment('Actor who last changed the booking.')->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable()->comment('UTC instant this record was created.');
            $table->timestamp('updated_at')->nullable()->comment('UTC instant this record was last updated.');
            $table->index(['departure_id', 'status']);
            $table->index(['customer_id', 'placed_at']);
            $table->index(['status', 'payment_status', 'placed_at']);
            $table->index(['shift_id', 'placed_at']);
            $table->index(['status', 'pending_expires_at'], 'travel_booking_pending_expiry_index');
        });

        Schema::create('travel_booking_participants', function (Blueprint $table): void {
            $table->id()->comment('Internal primary key.');
            $table->ulid('ulid')->unique()->comment('Public immutable identifier.');
            $table->foreignId('booking_id')->comment('Owning booking.')->constrained('travel_tour_bookings')->cascadeOnDelete();
            $table->foreignId('traveler_id')->nullable()->comment('Optional reusable traveler profile.')->constrained('travel_travelers')->nullOnDelete();
            $table->unsignedSmallInteger('sequence')->comment('One-based stable participant order within the booking.');
            $table->boolean('is_lead')->default(false)->comment('Whether this participant is the booking lead.');
            $table->string('participant_type', 20)->comment('Adult, child, or infant price classification.');
            $table->unsignedSmallInteger('age_at_departure')->nullable()->comment('Age in completed years on the local departure date.');
            $table->boolean('consumes_seat')->default(true)->comment('Whether this participant consumed one departure-capacity seat.');
            $table->unsignedSmallInteger('guardian_sequence')->nullable()->comment('Optional sequence of the adult guardian in this booking.');
            $table->string('title', 30)->nullable()->comment('Participant title snapshot.');
            $table->string('first_name', 100)->comment('Participant given-name snapshot.');
            $table->string('middle_name', 100)->nullable()->comment('Participant middle-name snapshot.');
            $table->string('last_name', 100)->comment('Participant family-name snapshot.');
            $table->date('date_of_birth')->nullable()->comment('Participant date-of-birth snapshot.');
            $table->char('nationality_code', 2)->nullable()->comment('Participant ISO nationality snapshot.');
            $table->string('email', 254)->nullable()->comment('Participant contact email snapshot.');
            $table->string('phone', 32)->nullable()->comment('Participant contact telephone snapshot.');
            $table->text('identity_number')->nullable()->comment('Encrypted travel-document number snapshot.');
            $table->string('identity_type', 30)->nullable()->comment('Travel-document type snapshot.');
            $table->char('passport_issuing_country', 2)->nullable()->comment('Passport issuing-country snapshot.');
            $table->date('passport_expiry_date')->nullable()->comment('Passport expiry-date snapshot.');
            $table->text('dietary_requirements')->nullable()->comment('Encrypted dietary requirement snapshot.');
            $table->text('accessibility_requirements')->nullable()->comment('Encrypted accessibility requirement snapshot.');
            $table->text('medical_notes')->nullable()->comment('Encrypted relevant medical-note snapshot.');
            $table->string('emergency_contact_name', 190)->nullable()->comment('Emergency contact name snapshot.');
            $table->string('emergency_contact_phone', 32)->nullable()->comment('Emergency contact phone snapshot.');
            $table->unsignedBigInteger('allocated_price_minor')->comment('Final participant price in minor units.');
            $table->timestamp('created_at')->nullable()->comment('UTC instant this record was created.');
            $table->timestamp('updated_at')->nullable()->comment('UTC instant this record was last updated.');
            $table->index(['booking_id', 'participant_type']);
            $table->unique(['booking_id', 'sequence'], 'travel_booking_participant_sequence_unique');
        });

        Schema::create('travel_booking_extras', function (Blueprint $table): void {
            $table->id()->comment('Internal primary key.');
            $table->ulid('ulid')->unique()->comment('Public immutable identifier.');
            $table->foreignId('booking_id')->comment('Owning booking.')->constrained('travel_tour_bookings')->cascadeOnDelete();
            $table->foreignId('tour_extra_id')->nullable()->comment('Source tour extra when still available.')->constrained('travel_tour_extras')->nullOnDelete();
            $table->foreignId('booking_participant_id')->nullable()->comment('Optional participant receiving the extra.')->constrained('travel_booking_participants')->nullOnDelete();
            $table->string('code_snapshot', 80)->comment('Immutable extra code.');
            $table->string('name_snapshot', 160)->comment('Immutable extra name.');
            $table->string('pricing_unit_snapshot', 30)->comment('Immutable extra pricing unit.');
            $table->unsignedInteger('quantity')->default(1)->comment('Purchased extra quantity.');
            $table->char('currency', 3)->comment('ISO currency of the extra.');
            $table->unsignedBigInteger('unit_amount_minor')->comment('Unit amount in minor units.');
            $table->unsignedBigInteger('discount_minor')->default(0)->comment('Discount allocated to the purchased extra in minor units.');
            $table->unsignedBigInteger('tax_minor')->default(0)->comment('Tax allocated to the purchased extra in minor units.');
            $table->unsignedBigInteger('total_minor')->comment('Extended total in minor units.');
            $table->timestamp('created_at')->nullable()->comment('UTC instant this record was created.');
            $table->timestamp('updated_at')->nullable()->comment('UTC instant this record was last updated.');
            $table->index(['booking_id', 'tour_extra_id']);
        });

        Schema::create('travel_booking_price_lines', function (Blueprint $table): void {
            $table->id()->comment('Internal primary key.');
            $table->ulid('ulid')->unique()->comment('Public immutable identifier.');
            $table->foreignId('booking_id')->comment('Owning booking.')->constrained('travel_tour_bookings')->cascadeOnDelete();
            $table->foreignId('pricing_rule_id')->nullable()->comment('Pricing rule that produced this line.')->constrained('travel_pricing_rules')->nullOnDelete();
            $table->foreignId('promotion_id')->nullable()->comment('Promotion that produced this line.')->constrained('travel_promotions')->nullOnDelete();
            $table->string('line_type', 40)->comment('Fare, extra, discount, tax, fee, or adjustment type.');
            $table->string('description', 220)->comment('Human-readable immutable line description.');
            $table->unsignedInteger('quantity')->default(1)->comment('Line quantity.');
            $table->bigInteger('unit_amount_minor')->comment('Signed unit amount in minor units.');
            $table->unsignedBigInteger('discount_minor')->default(0)->comment('Line discount in minor units.');
            $table->unsignedBigInteger('tax_minor')->default(0)->comment('Line tax in minor units.');
            $table->bigInteger('total_minor')->comment('Signed final line total in minor units.');
            $table->json('calculation_metadata')->nullable()->comment('Safe deterministic calculation evidence.');
            $table->unsignedInteger('display_order')->default(0)->comment('Document and interface line order.');
            $table->timestamp('created_at')->nullable()->comment('UTC instant this record was created.');
            $table->timestamp('updated_at')->nullable()->comment('UTC instant this record was last updated.');
            $table->index(['booking_id', 'display_order']);
        });

        Schema::create('travel_booking_status_history', function (Blueprint $table): void {
            $table->id()->comment('Internal primary key.');
            $table->ulid('ulid')->unique()->comment('Public immutable identifier.');
            $table->foreignId('booking_id')->comment('Booking whose state changed.')->constrained('travel_tour_bookings')->cascadeOnDelete();
            $table->string('previous_status', 24)->nullable()->comment('Lifecycle state before the transition.');
            $table->string('new_status', 24)->comment('Lifecycle state after the transition.');
            $table->foreignId('actor_id')->nullable()->comment('Authenticated actor responsible for the change.')->constrained('users')->nullOnDelete();
            $table->string('source', 40)->comment('Storefront, admin, booking desk, scheduler, or integration source.');
            $table->text('reason')->nullable()->comment('Operator or system transition reason.');
            $table->timestamp('changed_at')->comment('UTC transition instant.');
            $table->timestamp('created_at')->nullable()->comment('UTC instant this record was created.');
            $table->timestamp('updated_at')->nullable()->comment('UTC instant this record was last updated.');
            $table->index(['booking_id', 'changed_at']);
        });

        Schema::create('travel_payment_schedules', function (Blueprint $table): void {
            $table->id()->comment('Internal primary key.');
            $table->ulid('ulid')->unique()->comment('Public immutable identifier.');
            $table->foreignId('booking_id')->comment('Booking funded by this instalment.')->constrained('travel_tour_bookings')->cascadeOnDelete();
            $table->unsignedSmallInteger('instalment_number')->comment('One-based instalment sequence.');
            $table->date('due_date')->comment('Calendar due date.');
            $table->unsignedBigInteger('expected_amount_minor')->comment('Expected amount in booking currency minor units.');
            $table->unsignedBigInteger('paid_amount_minor')->default(0)->comment('Confirmed amount allocated to the instalment.');
            $table->string('status', 20)->default('pending')->comment('Instalment lifecycle state.');
            $table->timestamp('created_at')->nullable()->comment('UTC instant this record was created.');
            $table->timestamp('updated_at')->nullable()->comment('UTC instant this record was last updated.');
            $table->unique(['booking_id', 'instalment_number']);
            $table->index(['status', 'due_date']);
        });

        Schema::create('travel_booking_payments', function (Blueprint $table): void {
            $table->id()->comment('Internal primary key.');
            $table->ulid('ulid')->unique()->comment('Public immutable identifier.');
            $table->foreignId('booking_id')->comment('Booking receiving payment.')->constrained('travel_tour_bookings')->restrictOnDelete();
            $table->string('operation_key', 64)->unique()->comment('Idempotency key for payment recording or provider callback retries.');
            $table->foreignId('payment_schedule_id')->nullable()->comment('Optional instalment funded by this payment.')->constrained('travel_payment_schedules')->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->comment('Booking-desk shift receiving payment.')->constrained('travel_booking_shifts')->nullOnDelete();
            $table->string('method', 30)->comment('Mobile money, card, transfer, cash, or other method.');
            $table->string('provider', 80)->nullable()->comment('Payment provider or bank name.');
            $table->string('reference', 120)->nullable()->index()->comment('Customer-visible payment reference.');
            $table->string('transaction_identifier', 160)->nullable()->comment('Provider transaction identifier used with provider scope for deduplication.');
            $table->unsignedBigInteger('amount_minor')->comment('Payment amount in minor units.');
            $table->char('currency', 3)->comment('ISO currency of the payment.');
            $table->string('status', 24)->default('pending')->comment('Payment record lifecycle state.');
            $table->timestamp('paid_at')->nullable()->comment('UTC instant confirmed by the provider or operator.');
            $table->foreignId('received_by')->nullable()->comment('Operator who recorded or confirmed the payment.')->constrained('users')->nullOnDelete();
            $table->json('safe_metadata')->nullable()->comment('Redacted non-secret provider evidence.');
            $table->timestamp('created_at')->nullable()->comment('UTC instant this record was created.');
            $table->timestamp('updated_at')->nullable()->comment('UTC instant this record was last updated.');
            $table->index(['booking_id', 'status']);
            $table->index(['shift_id', 'method', 'status']);
            $table->unique(['provider', 'transaction_identifier'], 'travel_payment_provider_transaction_unique');
        });

        Schema::create('travel_booking_refunds', function (Blueprint $table): void {
            $table->id()->comment('Internal primary key.');
            $table->ulid('ulid')->unique()->comment('Public immutable identifier.');
            $table->foreignId('booking_id')->comment('Booking being refunded.')->constrained('travel_tour_bookings')->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->comment('Original payment when refunding a specific transaction.')->constrained('travel_booking_payments')->nullOnDelete();
            $table->string('operation_key', 64)->unique()->comment('Idempotency key for a refund request or callback retry.');
            $table->string('reference', 120)->unique()->comment('Internal refund reference.');
            $table->unsignedBigInteger('amount_minor')->comment('Refund amount in minor units.');
            $table->char('currency', 3)->comment('ISO currency of the refund.');
            $table->text('reason')->comment('Required refund rationale.');
            $table->string('status', 24)->default('pending')->comment('Refund lifecycle state.');
            $table->string('processor', 80)->nullable()->comment('Refund processor or payment provider.');
            $table->string('transaction_identifier', 160)->nullable()->comment('Provider refund transaction identifier unique within processor scope.');
            $table->foreignId('requested_by')->nullable()->comment('Operator who requested or initiated the refund.')->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->comment('UTC instant the refund was requested.');
            $table->foreignId('processed_by')->nullable()->comment('Operator who processed the refund.')->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable()->comment('UTC refund completion instant.');
            $table->json('safe_metadata')->nullable()->comment('Redacted non-secret processor evidence.');
            $table->timestamp('created_at')->nullable()->comment('UTC instant this record was created.');
            $table->timestamp('updated_at')->nullable()->comment('UTC instant this record was last updated.');
            $table->index(['booking_id', 'status']);
            $table->unique(['processor', 'transaction_identifier'], 'travel_refund_processor_transaction_unique');
        });

        Schema::create('travel_promotion_redemptions', function (Blueprint $table): void {
            $table->id()->comment('Internal primary key.');
            $table->ulid('ulid')->unique()->comment('Public immutable identifier.');
            $table->foreignId('promotion_id')->comment('Redeemed promotion.')->constrained('travel_promotions')->restrictOnDelete();
            $table->foreignId('booking_id')->comment('Booking receiving the discount.')->constrained('travel_tour_bookings')->cascadeOnDelete();
            $table->foreignId('customer_id')->comment('Customer consuming the promotion.')->constrained('travel_customers')->restrictOnDelete();
            $table->unsignedBigInteger('amount_applied_minor')->comment('Discount applied in booking currency minor units.');
            $table->char('currency', 3)->comment('ISO currency in which the promotion amount was applied.');
            $table->timestamp('redeemed_at')->comment('UTC redemption instant.');
            $table->timestamp('released_at')->nullable()->comment('UTC instant usage was explicitly released under approved promotion policy.');
            $table->string('release_reason', 80)->nullable()->comment('Stable reason code for an explicitly released promotion use.');
            $table->timestamp('created_at')->nullable()->comment('UTC instant this record was created.');
            $table->timestamp('updated_at')->nullable()->comment('UTC instant this record was last updated.');
            $table->unique(['promotion_id', 'booking_id']);
            $table->index(['promotion_id', 'customer_id']);
        });
    }

    /** Drop only these module tables in reverse foreign-key dependency order. */
    public function down(): void
    {
        Schema::dropIfExists('travel_promotion_redemptions');
        Schema::dropIfExists('travel_booking_refunds');
        Schema::dropIfExists('travel_booking_payments');
        Schema::dropIfExists('travel_payment_schedules');
        Schema::dropIfExists('travel_booking_status_history');
        Schema::dropIfExists('travel_booking_price_lines');
        Schema::dropIfExists('travel_booking_extras');
        Schema::dropIfExists('travel_booking_participants');
        Schema::dropIfExists('travel_tour_bookings');
    }
};
