<?php

/**
 * Verifies the pending → confirmed payment seam, rejection, refunds, and lifecycle transitions.
 */

declare(strict_types=1);

namespace Tests\Feature\TravelTours;

use App\Models\User;
use App\Modules\TravelTours\Bookings\Data\BookingPaymentData;
use App\Modules\TravelTours\Bookings\Data\BookingRefundData;
use App\Modules\TravelTours\Bookings\Enums\BookingStatus;
use App\Modules\TravelTours\Bookings\Enums\PaymentMethod;
use App\Modules\TravelTours\Bookings\Enums\PaymentRecordStatus;
use App\Modules\TravelTours\Bookings\Enums\PaymentStatus;
use App\Modules\TravelTours\Bookings\Enums\RefundStatus;
use App\Modules\TravelTours\Bookings\Exceptions\BookingLifecycleException;
use App\Modules\TravelTours\Bookings\Exceptions\PaymentException;
use App\Modules\TravelTours\Bookings\Models\BookingStatusHistory;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\Bookings\Services\BookingLifecycleService;
use App\Modules\TravelTours\Bookings\Services\BookingPaymentService;
use App\Modules\TravelTours\Events\BookingPaymentConfirmed;
use App\Modules\TravelTours\Pricing\Models\Promotion;
use App\Modules\TravelTours\Pricing\Models\PromotionRedemption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Prove that recorded money never settles a booking until it is confirmed,
 * that every confirmation is idempotent and bounded by the balance, and that
 * refunds and lifecycle transitions keep the aggregates and history honest.
 */
final class TravelToursPaymentConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private int $actor;

    /** Enable the module with a deterministic key and one operator to attribute writes to. */
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('p', 32)), 'travel-tours.enabled' => true]);
        $this->actor = User::factory()->create()->getKey();
    }

    /** Pending evidence leaves aggregates alone; confirmation settles them exactly once. */
    public function test_pending_payment_settles_only_when_confirmed(): void
    {
        Event::fake([BookingPaymentConfirmed::class]);
        $booking = $this->booking(total: 100_000_00, deposit: 30_000_00);
        $service = app(BookingPaymentService::class);

        $payment = $service->recordPending($booking, $this->payment('evidence-1', 30_000_00, provider: 'M-PESA', transaction: 'QX1'));

        $this->assertSame(PaymentRecordStatus::Pending, $payment->status);
        $this->assertNull($payment->paid_at);
        $this->assertSame(0, $booking->fresh()->paid_minor);
        $this->assertSame(PaymentStatus::Unpaid, $booking->fresh()->payment_status);
        Event::assertNotDispatched(BookingPaymentConfirmed::class);

        $confirmed = $service->confirm($payment, actorId: $this->actor);
        $again = $service->confirm($payment, actorId: $this->actor);

        $this->assertSame(PaymentRecordStatus::Confirmed, $confirmed->status);
        $this->assertNotNull($confirmed->paid_at);
        $this->assertSame($this->actor, $confirmed->received_by);
        $this->assertTrue($confirmed->is($again));
        $this->assertSame(30_000_00, $booking->fresh()->paid_minor);
        $this->assertSame(PaymentStatus::Partial, $booking->fresh()->payment_status);
        Event::assertDispatchedTimes(BookingPaymentConfirmed::class, 1);

        $service->confirm($service->recordPending($booking, $this->payment('evidence-2', 70_000_00)), $this->actor);

        $this->assertSame(PaymentStatus::Paid, $booking->fresh()->payment_status);
        $this->assertSame(100_000_00, $booking->fresh()->paid_minor);
    }

    /** Evidence that would overpay is refused at recording and again at confirmation. */
    public function test_payments_never_exceed_the_outstanding_balance(): void
    {
        $booking = $this->booking(total: 50_000_00);
        $service = app(BookingPaymentService::class);
        $first = $service->recordPending($booking, $this->payment('a', 40_000_00));
        $second = $service->recordPending($booking, $this->payment('b', 40_000_00));
        $service->confirm($first, $this->actor);

        try {
            $service->confirm($second, $this->actor);
            $this->fail('An overpaying confirmation must be refused.');
        } catch (PaymentException $exception) {
            $this->assertSame('Payment exceeds the outstanding booking balance.', $exception->getMessage());
        }
        $this->assertSame(PaymentRecordStatus::Pending, $second->fresh()->status);

        $this->expectException(PaymentException::class);
        $service->recordPending($booking, $this->payment('c', 10_000_01));
    }

    /** Rejection keeps the row for audit, records the reason, and changes no money. */
    public function test_rejected_payments_keep_their_reason_and_change_nothing(): void
    {
        $booking = $this->booking(total: 50_000_00);
        $service = app(BookingPaymentService::class);
        $payment = $service->recordPending($booking, $this->payment('a', 10_000_00));

        $rejected = $service->reject($payment, $this->actor, 'Reference not found on the bank statement.');

        $this->assertSame(PaymentRecordStatus::Failed, $rejected->status);
        $this->assertSame('Reference not found on the bank statement.', $rejected->safe_metadata['rejection_reason']);
        $this->assertSame(0, $booking->fresh()->paid_minor);

        $this->expectException(PaymentException::class);
        $service->confirm($rejected, $this->actor);
    }

    /** Refunds are capped by confirmed money, tracked per payment, and drive the payment status. */
    public function test_refunds_are_bounded_and_update_the_payment_status(): void
    {
        $booking = $this->booking(total: 50_000_00);
        $service = app(BookingPaymentService::class);
        $payment = $service->record($booking, $this->payment('a', 50_000_00));

        try {
            $service->refund($booking, $this->refund('r-over', 50_000_01, paymentId: $payment->getKey()));
            $this->fail('A refund above the payment must be refused.');
        } catch (PaymentException $exception) {
            $this->assertSame('Refund exceeds the amount available to refund.', $exception->getMessage());
        }

        $partial = $service->refund($booking, $this->refund('r-1', 20_000_00, paymentId: $payment->getKey()));
        $retry = $service->refund($booking, $this->refund('r-1', 20_000_00, paymentId: $payment->getKey()));

        $this->assertTrue($partial->is($retry));
        $this->assertSame(RefundStatus::Processed, $partial->status);
        $this->assertStringStartsWith('RF-', $partial->reference);
        $this->assertSame(20_000_00, $booking->fresh()->refunded_minor);
        $this->assertSame(PaymentStatus::PartiallyRefunded, $booking->fresh()->payment_status);

        $service->refund($booking, $this->refund('r-2', 30_000_00));

        $this->assertSame(PaymentStatus::Refunded, $booking->fresh()->payment_status);
        $this->assertSame(1, app(BookingLifecycleService::class)->cancel($booking, $this->actor, 'Customer withdrew after full refund.')->statusHistory()->where('new_status', 'cancelled')->count());
    }

    /** Lifecycle transitions are guarded, historied, and release promotion uses on cancellation. */
    public function test_lifecycle_transitions_are_guarded_and_historied(): void
    {
        $booking = $this->booking(total: 50_000_00);
        $promotion = Promotion::factory()->create(['maximum_uses' => 1]);
        $redemption = PromotionRedemption::query()->create([
            'promotion_id' => $promotion->getKey(), 'booking_id' => $booking->getKey(), 'customer_id' => $booking->customer_id,
            'amount_applied_minor' => 1_000_00, 'currency' => 'KES', 'redeemed_at' => now(),
        ]);
        $lifecycle = app(BookingLifecycleService::class);

        try {
            $lifecycle->complete($booking, $this->actor);
            $this->fail('A pending booking cannot be completed.');
        } catch (BookingLifecycleException $exception) {
            $this->assertSame('Only confirmed bookings can be completed.', $exception->getMessage());
        }

        $confirmed = $lifecycle->confirm($booking, $this->actor);

        $this->assertSame(BookingStatus::Confirmed, $confirmed->status);
        $this->assertNotNull($confirmed->confirmed_at);
        $this->assertNull($confirmed->pending_expires_at);

        try {
            $lifecycle->complete($confirmed, $this->actor);
            $this->fail('A booking cannot be completed before its departure ends.');
        } catch (BookingLifecycleException $exception) {
            $this->assertStringContainsString('after its departure has ended', $exception->getMessage());
        }

        $cancelled = $lifecycle->cancel($confirmed, $this->actor, 'Customer requested cancellation.');

        $this->assertSame(BookingStatus::Cancelled, $cancelled->status);
        $this->assertNotNull($redemption->fresh()->released_at);
        $this->assertSame(0, TourBooking::query()->capacityConsuming()->whereKey($booking->getKey())->count());
        $this->assertSame(
            ['confirmed', 'cancelled'],
            BookingStatusHistory::query()->where('booking_id', $booking->getKey())->orderBy('id')->pluck('new_status')->map(fn ($s) => $s->value)->all(),
        );

        $this->expectException(BookingLifecycleException::class);
        $lifecycle->confirm($cancelled, $this->actor);
    }

    /** Create a pending web booking with the given money shape. */
    private function booking(int $total, int $deposit = 0): TourBooking
    {
        return TourBooking::factory()->create([
            'status' => BookingStatus::Pending, 'payment_status' => PaymentStatus::Unpaid,
            'subtotal_minor' => $total, 'total_minor' => $total, 'deposit_required_minor' => $deposit,
            'currency' => 'KES', 'currency_exponent' => 2, 'pending_expires_at' => now()->addDay(),
        ]);
    }

    /** Build typed payment evidence. */
    private function payment(string $key, int $amount, ?string $provider = null, ?string $transaction = null): BookingPaymentData
    {
        return new BookingPaymentData(operationKey: $key, method: PaymentMethod::MobileMoney, amountMinor: $amount, currency: 'KES', reference: 'REF-'.$key, provider: $provider, transactionIdentifier: $transaction, actorId: $this->actor);
    }

    /** Build typed refund input. */
    private function refund(string $key, int $amount, ?int $paymentId = null): BookingRefundData
    {
        return new BookingRefundData(operationKey: $key, amountMinor: $amount, currency: 'KES', reason: 'Customer cancelled within the free period.', paymentId: $paymentId, actorId: $this->actor);
    }
}
