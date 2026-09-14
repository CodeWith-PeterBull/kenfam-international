<?php

/**
 * Behavioral verification for TravelTours pricing, holds, booking, and payments.
 */
declare(strict_types=1);

namespace Tests\Feature\TravelTours;

use App\Modules\TravelTours\Bookings\Data\BookingParticipantData;
use App\Modules\TravelTours\Bookings\Data\BookingPaymentData;
use App\Modules\TravelTours\Bookings\Data\BookingPlacementData;
use App\Modules\TravelTours\Bookings\Enums\BookingChannel;
use App\Modules\TravelTours\Bookings\Enums\BookingStatus;
use App\Modules\TravelTours\Bookings\Enums\ParticipantType;
use App\Modules\TravelTours\Bookings\Enums\PaymentMethod;
use App\Modules\TravelTours\Bookings\Enums\PaymentScheduleStatus;
use App\Modules\TravelTours\Bookings\Exceptions\DuplicateOperationConflict;
use App\Modules\TravelTours\Bookings\Models\PaymentSchedule;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\Bookings\Services\BookingPaymentService;
use App\Modules\TravelTours\Catalog\Enums\PublicationStatus;
use App\Modules\TravelTours\Contracts\CalculatesTourQuotes;
use App\Modules\TravelTours\Contracts\PlacesTourBookings;
use App\Modules\TravelTours\Customers\Data\TravelCustomerData;
use App\Modules\TravelTours\Inquiries\Data\TourInquiryData;
use App\Modules\TravelTours\Inquiries\Enums\InquiryType;
use App\Modules\TravelTours\Inquiries\Models\TourInquiry;
use App\Modules\TravelTours\Inquiries\Services\TourInquiryService;
use App\Modules\TravelTours\Pricing\Data\ParticipantMix;
use App\Modules\TravelTours\Pricing\Data\TourQuote;
use App\Modules\TravelTours\Pricing\Data\TourQuoteRequest;
use App\Modules\TravelTours\Pricing\Models\ParticipantRate;
use App\Modules\TravelTours\Pricing\Models\TourRatePlan;
use App\Modules\TravelTours\Scheduling\Data\AvailabilityHoldRequest;
use App\Modules\TravelTours\Scheduling\Enums\HoldStatus;
use App\Modules\TravelTours\Scheduling\Exceptions\HoldOwnershipMismatch;
use App\Modules\TravelTours\Scheduling\Models\AvailabilityHold;
use App\Modules\TravelTours\Scheduling\Models\TourDeparture;
use App\Modules\TravelTours\Scheduling\Services\AvailabilityHoldService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Verify authoritative services against the actual module schema. */
final class TravelToursServiceTest extends TestCase
{
    use RefreshDatabase;

    /** Configure deterministic module limits and encryption for every test. */
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('s', 32)),
            'travel-tours.enabled' => true,
            'travel-tours.booking.hold_minutes' => 20,
            'travel-tours.booking.pending_minutes' => 1440,
        ]);
    }

    /** Pricing uses exact minor units for each participant and deposit. */
    public function test_quote_calculator_returns_deterministic_integer_totals(): void
    {
        [$quote] = $this->quoteFixture();

        $this->assertSame(25_000_00, $quote->subtotalMinor);
        $this->assertSame(0, $quote->discountMinor);
        $this->assertSame(0, $quote->taxMinor);
        $this->assertSame(25_000_00, $quote->totalMinor);
        $this->assertSame(7_500_00, $quote->depositMinor);
        $this->assertCount(3, $quote->lines);
        $this->assertSame($quote->fingerprint, $quote->snapshot()['fingerprint']);
    }

    /** A hold retry returns one row and never weakens anonymous ownership. */
    public function test_hold_creation_is_idempotent_and_owner_bound(): void
    {
        [$quote] = $this->quoteFixture();
        $service = app(AvailabilityHoldService::class);
        $request = new AvailabilityHoldRequest('hold-operation-1', $quote, 'anonymous-owner-token');

        $first = $service->create($request);
        $retry = $service->create($request);

        $this->assertTrue($first->is($retry));
        $this->assertSame(1, AvailabilityHold::query()->count());
        $this->assertSame(HoldStatus::Active, $first->status);
        $this->assertSame(3, $first->seat_count);
        $this->assertNotSame('anonymous-owner-token', $first->owner_token_hash);

        $this->expectException(HoldOwnershipMismatch::class);
        $service->assertOwnedBy($first, 'different-owner-token', null);
    }

    /** Placement consumes one hold and persists the full immutable booking graph. */
    public function test_booking_placement_is_atomic_snapshot_complete_and_idempotent(): void
    {
        [$quote, $departure] = $this->quoteFixture();
        $hold = app(AvailabilityHoldService::class)->create(
            new AvailabilityHoldRequest('hold-operation-2', $quote, 'booking-owner-token'),
        );
        $data = new BookingPlacementData(
            operationKey: 'booking-operation-1',
            holdUlid: $hold->ulid,
            holdOwnerToken: 'booking-owner-token',
            customer: new TravelCustomerData('Grace', 'Wanjiku', email: 'grace@example.test', phone: '+254722000111'),
            participants: [
                new BookingParticipantData(ParticipantType::Adult, 'Grace', 'Wanjiku', isLead: true),
                new BookingParticipantData(ParticipantType::Adult, 'Peter', 'Wanjiku'),
                new BookingParticipantData(ParticipantType::Child, 'Amani', 'Wanjiku'),
                new BookingParticipantData(ParticipantType::Infant, 'Imani', 'Wanjiku'),
            ],
            channel: BookingChannel::Web,
            preferredPaymentMethod: PaymentMethod::MobileMoney,
            termsVersion: 'fixture-v1',
        );

        $booking = app(PlacesTourBookings::class)->place($data);
        $retry = app(PlacesTourBookings::class)->place($data);

        $this->assertTrue($booking->is($retry));
        $this->assertSame(1, TourBooking::query()->count());
        $this->assertSame(BookingStatus::Pending, $booking->status);
        $this->assertSame($departure->tour->code, $booking->tour_code_snapshot);
        $this->assertSame('fixture-v1', $booking->policy_version_snapshot);
        $this->assertSame(2, $booking->currency_exponent);
        $this->assertSame(4, $booking->participants()->count());
        $this->assertSame(25_000_00, (int) $booking->participants()->sum('allocated_price_minor'));
        $this->assertSame(1, $booking->statusHistory()->count());
        $this->assertSame(HoldStatus::Consumed, $hold->fresh()->status);
    }

    /** Confirmed payments preserve operation identity and allocate instalments. */
    public function test_payment_recording_is_idempotent_and_updates_schedule_and_aggregate(): void
    {
        $booking = TourBooking::factory()->create(['total_minor' => 100_000, 'deposit_required_minor' => 40_000]);
        $schedule = PaymentSchedule::factory()->create([
            'booking_id' => $booking,
            'expected_amount_minor' => 40_000,
        ]);
        $data = new BookingPaymentData(
            operationKey: 'payment-operation-1',
            method: PaymentMethod::MobileMoney,
            amountMinor: 40_000,
            currency: 'kes',
            reference: 'TEST-MPESA-001',
            provider: 'manual',
            paymentScheduleId: $schedule->id,
        );

        $payment = app(BookingPaymentService::class)->record($booking, $data);
        $retry = app(BookingPaymentService::class)->record($booking, $data);

        $this->assertTrue($payment->is($retry));
        $this->assertSame(1, $booking->payments()->count());
        $this->assertSame(40_000, $booking->fresh()->paid_minor);
        $this->assertSame(40_000, $schedule->fresh()->paid_amount_minor);
        $this->assertSame(PaymentScheduleStatus::Paid, $schedule->fresh()->status);

        $this->expectException(DuplicateOperationConflict::class);
        app(BookingPaymentService::class)->record($booking, new BookingPaymentData(
            operationKey: 'payment-operation-1',
            method: PaymentMethod::MobileMoney,
            amountMinor: 30_000,
            currency: 'KES',
        ));
    }

    /** Public inquiry submission is idempotent and bound to published tour context. */
    public function test_inquiry_submission_is_idempotent_and_rejects_incompatible_retries(): void
    {
        $departure = TourDeparture::factory()->create();
        $departure->tour->forceFill([
            'status' => PublicationStatus::Published,
            'published_at' => now()->subMinute(),
        ])->save();
        $data = new TourInquiryData(
            operationKey: 'inquiry-operation-1',
            type: InquiryType::Tour,
            contactName: 'Amina Kamau',
            contactEmail: 'AMINA@example.test',
            contactPhone: '+254722111222',
            whatsappPreferred: true,
            requestedDestinations: ['Nairobi', 'Cairo'],
            preferredStartDate: null,
            preferredEndDate: null,
            adultCount: 2,
            childCount: 0,
            infantCount: 0,
            budgetCurrency: 'kes',
            budgetMinor: 350_000_00,
            message: 'Please share the available departure details.',
            tourId: $departure->tour_id,
            departureId: $departure->id,
            consentIp: '127.0.0.1',
            consentPurpose: 'Respond to this travel inquiry',
            consentVersion: 'fixture-v1',
        );

        $service = app(TourInquiryService::class);
        $inquiry = $service->submit($data);
        $retry = $service->submit($data);

        $this->assertTrue($inquiry->is($retry));
        $this->assertSame(1, TourInquiry::query()->count());
        $this->assertSame('amina@example.test', $inquiry->contact_email);
        $this->assertSame(['Nairobi', 'Cairo'], $inquiry->requested_destinations);

        $this->expectException(ValidationException::class);
        $service->submit(new TourInquiryData(
            operationKey: 'inquiry-operation-1',
            type: InquiryType::Tour,
            contactName: 'Different Contact',
            contactEmail: 'different@example.test',
            contactPhone: null,
            whatsappPreferred: false,
            requestedDestinations: [],
            preferredStartDate: null,
            preferredEndDate: null,
            adultCount: 1,
            childCount: 0,
            infantCount: 0,
            budgetCurrency: null,
            budgetMinor: null,
            message: 'Different submission.',
            tourId: $departure->tour_id,
            departureId: $departure->id,
            consentIp: null,
            consentPurpose: 'Respond to this travel inquiry',
            consentVersion: 'fixture-v1',
        ));
    }

    /** A public inquiry cannot pair a tour with another tour's departure. */
    public function test_inquiry_submission_requires_a_departure_owned_by_a_published_tour(): void
    {
        $selectedDeparture = TourDeparture::factory()->create();
        $selectedDeparture->tour->forceFill([
            'status' => PublicationStatus::Published,
            'published_at' => now()->subMinute(),
        ])->save();
        $otherDeparture = TourDeparture::factory()->create();

        $this->expectException(ValidationException::class);
        app(TourInquiryService::class)->submit(new TourInquiryData(
            operationKey: 'inquiry-operation-mismatched-departure',
            type: InquiryType::Tour,
            contactName: 'Amina Kamau',
            contactEmail: 'amina@example.test',
            contactPhone: null,
            whatsappPreferred: false,
            requestedDestinations: [],
            preferredStartDate: null,
            preferredEndDate: null,
            adultCount: 1,
            childCount: 0,
            infantCount: 0,
            budgetCurrency: null,
            budgetMinor: null,
            message: 'Please share the available dates.',
            tourId: $selectedDeparture->tour_id,
            departureId: $otherDeparture->id,
            consentIp: null,
            consentPurpose: 'Respond to this travel inquiry',
            consentVersion: 'fixture-v1',
        ));
    }

    /**
     * Create a coherent three-rate departure and calculate a reusable quote.
     *
     * @return array{TourQuote, TourDeparture}
     */
    private function quoteFixture(): array
    {
        $departure = TourDeparture::factory()->create();
        $plan = TourRatePlan::factory()->create([
            'tour_id' => $departure->tour_id,
            'code' => 'STANDARD',
            'currency' => 'KES',
            'tax_rate_basis_points' => 0,
            'deposit_type' => 'percentage',
            'deposit_value' => 3000,
            'minimum_participants' => 1,
            'maximum_participants' => 10,
        ]);
        $departure->forceFill(['rate_plan_id' => $plan->id])->save();

        ParticipantRate::factory()->create([
            'rate_plan_id' => $plan,
            'participant_type' => ParticipantType::Adult,
            'minimum_age' => 18,
            'maximum_age' => null,
            'amount_minor' => 10_000_00,
        ]);
        ParticipantRate::factory()->create([
            'rate_plan_id' => $plan,
            'participant_type' => ParticipantType::Child,
            'minimum_age' => 2,
            'maximum_age' => 17,
            'amount_minor' => 5_000_00,
        ]);
        ParticipantRate::factory()->create([
            'rate_plan_id' => $plan,
            'participant_type' => ParticipantType::Infant,
            'minimum_age' => 0,
            'maximum_age' => 1,
            'amount_minor' => 0,
        ]);

        $quote = app(CalculatesTourQuotes::class)->calculate(new TourQuoteRequest(
            departureId: $departure->id,
            ratePlanId: $plan->id,
            participants: new ParticipantMix(adults: 2, children: 1, infants: 1),
        ));

        return [$quote, $departure->fresh('tour')];
    }
}
