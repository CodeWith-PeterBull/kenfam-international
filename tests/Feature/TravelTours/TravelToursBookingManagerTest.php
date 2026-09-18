<?php

/**
 * Verifies the booking workspace: listing, filters, per-action authorization, and settlement dialogs.
 */

declare(strict_types=1);

namespace Tests\Feature\TravelTours;

use App\Enums\UserType;
use App\Models\User;
use App\Modules\TravelTours\Bookings\Enums\BookingStatus;
use App\Modules\TravelTours\Bookings\Enums\PaymentRecordStatus;
use App\Modules\TravelTours\Bookings\Enums\PaymentStatus;
use App\Modules\TravelTours\Bookings\Livewire\Admin\BookingManager;
use App\Modules\TravelTours\Bookings\Models\BookingPayment;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\Database\Seeders\TravelToursAccessSeeder;
use App\Modules\TravelTours\Support\TravelToursRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Agents record evidence, managers confirm money and move bookings, editors
 * see nothing; every refusal is a policy decision, never a hidden button.
 */
final class TravelToursBookingManagerTest extends TestCase
{
    use RefreshDatabase;

    /** Seed the real role grants so the split between recording and confirming is exercised. */
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('m', 32)), 'travel-tours.enabled' => true]);
        $this->seed(TravelToursAccessSeeder::class);
    }

    /** The workspace lists bookings and narrows by search, status, and payment state. */
    public function test_workspace_lists_and_filters_bookings(): void
    {
        $pending = TourBooking::factory()->create(['booking_number' => 'WEB-TOUR-1', 'customer_name_snapshot' => 'Amina Wanjiru', 'status' => BookingStatus::Pending]);
        $confirmed = TourBooking::factory()->create(['booking_number' => 'WEB-TOUR-2', 'customer_name_snapshot' => 'Brian Otieno', 'status' => BookingStatus::Confirmed, 'payment_status' => PaymentStatus::Paid]);

        $this->withoutVite()->actingAs($this->operator(TravelToursRole::MANAGER))
            ->get(route('travel-tours.admin.bookings.index'))
            ->assertOk()
            ->assertSeeLivewire(BookingManager::class);

        Livewire::actingAs($this->operator(TravelToursRole::BOOKING_AGENT))
            ->test(BookingManager::class)
            ->assertSee('WEB-TOUR-1')->assertSee('WEB-TOUR-2')
            ->set('search', 'amina')
            ->assertSee('WEB-TOUR-1')->assertDontSee('WEB-TOUR-2')
            ->set('search', '')
            ->set('statusFilter', 'confirmed')
            ->assertDontSee('WEB-TOUR-1')->assertSee('WEB-TOUR-2')
            ->set('statusFilter', '')
            ->set('paymentFilter', 'unpaid')
            ->assertSee($pending->booking_number)->assertDontSee($confirmed->booking_number)
            ->set('search', '100%')
            ->assertSee('No bookings match these filters.');
    }

    /** Editors have no booking visibility at all. */
    public function test_workspace_is_refused_without_booking_visibility(): void
    {
        Livewire::actingAs($this->operator(TravelToursRole::TOUR_EDITOR))
            ->test(BookingManager::class)
            ->assertForbidden();
    }

    /** An agent records evidence but cannot confirm it; a manager confirms and the booking settles. */
    public function test_recording_and_confirming_payments_are_separate_grants(): void
    {
        $booking = TourBooking::factory()->create(['status' => BookingStatus::Pending, 'total_minor' => 50_000_00, 'subtotal_minor' => 50_000_00, 'deposit_required_minor' => 15_000_00]);
        $agent = $this->operator(TravelToursRole::BOOKING_AGENT);

        Livewire::actingAs($agent)
            ->test(BookingManager::class)
            ->call('openPayment', $booking->getKey())
            ->assertSet('dialog', 'payment')
            ->set('payment.amount', 'abc')
            ->call('recordPayment')
            ->assertHasErrors(['payment.amount'])
            ->set('payment.method', 'bank_transfer')
            ->set('payment.amount', '15000.00')
            ->set('payment.reference', 'BT-778')
            ->set('payment.provider', 'Equity Bank')
            ->call('recordPayment')
            ->assertHasNoErrors()
            ->assertSet('dialog', 'details')
            ->assertSee('Awaiting confirmation')
            ->assertDontSee('>Confirm<', false);

        $payment = BookingPayment::query()->sole();
        $this->assertSame(PaymentRecordStatus::Pending, $payment->status);
        $this->assertSame(15_000_00, $payment->amount_minor);
        $this->assertSame($agent->getKey(), $payment->received_by);
        $this->assertSame(0, $booking->fresh()->paid_minor);

        Livewire::actingAs($agent)
            ->test(BookingManager::class)
            ->call('openDetails', $booking->getKey())
            ->call('confirmPayment', $payment->getKey())
            ->assertForbidden();

        $manager = $this->operator(TravelToursRole::MANAGER);
        Livewire::actingAs($manager)
            ->test(BookingManager::class)
            ->call('openDetails', $booking->getKey())
            ->assertSee('Confirm')
            ->call('confirmPayment', $payment->getKey())
            ->assertHasNoErrors()
            ->assertSee('Payment confirmed.');

        $this->assertSame(PaymentRecordStatus::Confirmed, $payment->fresh()->status);
        $this->assertSame($manager->getKey(), $payment->fresh()->received_by);
        $this->assertSame(15_000_00, $booking->fresh()->paid_minor);
        $this->assertSame(PaymentStatus::Partial, $booking->fresh()->payment_status);
    }

    /** Rejection needs a reason and leaves the balance untouched. */
    public function test_rejecting_a_payment_requires_a_reason(): void
    {
        $booking = TourBooking::factory()->create(['status' => BookingStatus::Pending, 'total_minor' => 50_000_00, 'subtotal_minor' => 50_000_00]);
        $payment = BookingPayment::factory()->create(['booking_id' => $booking->getKey(), 'status' => PaymentRecordStatus::Pending, 'amount_minor' => 10_000_00, 'currency' => 'KES', 'paid_at' => null]);

        Livewire::actingAs($this->operator(TravelToursRole::MANAGER))
            ->test(BookingManager::class)
            ->call('openDetails', $booking->getKey())
            ->call('openReject', $payment->getKey())
            ->assertSet('dialog', 'reject')
            ->call('rejectPayment')
            ->assertHasErrors(['reason'])
            ->set('reason', 'No matching transfer was received.')
            ->call('rejectPayment')
            ->assertHasNoErrors()
            ->assertSee('No matching transfer was received.');

        $this->assertSame(PaymentRecordStatus::Failed, $payment->fresh()->status);
        $this->assertSame(0, $booking->fresh()->paid_minor);
    }

    /** Managers confirm and cancel bookings; agents see the cancel path refused. */
    public function test_booking_lifecycle_actions_are_authorized_and_recorded(): void
    {
        $booking = TourBooking::factory()->create(['status' => BookingStatus::Pending]);
        $manager = $this->operator(TravelToursRole::MANAGER);

        Livewire::actingAs($manager)
            ->test(BookingManager::class)
            ->call('confirmBooking', $booking->getKey())
            ->assertHasNoErrors()
            ->assertSee('Booking confirmed.');

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);

        Livewire::actingAs($manager)
            ->test(BookingManager::class)
            ->call('openCancel', $booking->getKey())
            ->call('cancelBooking')
            ->assertHasErrors(['reason'])
            ->set('reason', 'Customer changed travel plans.')
            ->call('cancelBooking')
            ->assertHasNoErrors()
            ->assertSee('Booking cancelled.');

        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        $this->assertSame(0, TourBooking::query()->capacityConsuming()->whereKey($booking->getKey())->count());
        $this->assertSame($manager->getKey(), $booking->fresh()->updated_by);
    }

    /** Refund entry is limited to the refund grant and validated before the service runs. */
    public function test_refunds_require_the_refund_grant(): void
    {
        $booking = TourBooking::factory()->create(['status' => BookingStatus::Confirmed, 'total_minor' => 50_000_00, 'subtotal_minor' => 50_000_00, 'paid_minor' => 50_000_00, 'payment_status' => PaymentStatus::Paid]);
        BookingPayment::factory()->create(['booking_id' => $booking->getKey(), 'status' => PaymentRecordStatus::Confirmed, 'amount_minor' => 50_000_00, 'currency' => 'KES']);

        Livewire::actingAs($this->operator(TravelToursRole::BOOKING_AGENT))
            ->test(BookingManager::class)
            ->call('openRefund', $booking->getKey())
            ->assertForbidden();

        Livewire::actingAs($this->operator(TravelToursRole::MANAGER))
            ->test(BookingManager::class)
            ->call('openRefund', $booking->getKey())
            ->assertSet('dialog', 'refund')
            ->set('refund.amount', '60000.00')
            ->set('refund.reason', 'Departure cancelled by the operator.')
            ->call('recordRefund')
            ->assertHasErrors(['management'])
            ->assertSee('Refund exceeds the amount available to refund.')
            ->set('refund.amount', '50000.00')
            ->call('recordRefund')
            ->assertHasNoErrors()
            ->assertSee('Refund recorded.');

        $this->assertSame(50_000_00, $booking->fresh()->refunded_minor);
        $this->assertSame(PaymentStatus::Refunded, $booking->fresh()->payment_status);
    }

    /** Create an active non-administrator operator holding one module role. */
    private function operator(string $role): User
    {
        $user = User::factory()->create(['user_type' => UserType::Viewer, 'is_active' => true]);
        $user->assignRole($role);

        return $user->fresh();
    }
}
