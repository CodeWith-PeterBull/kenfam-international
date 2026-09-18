<?php

/**
 * Verifies that each booking event yields exactly one queued, config-gated notification.
 */

declare(strict_types=1);

namespace Tests\Feature\TravelTours;

use App\Enums\UserType;
use App\Models\User;
use App\Modules\TravelTours\Bookings\Data\BookingPaymentData;
use App\Modules\TravelTours\Bookings\Enums\BookingStatus;
use App\Modules\TravelTours\Bookings\Enums\PaymentMethod;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\Bookings\Services\BookingLifecycleService;
use App\Modules\TravelTours\Bookings\Services\BookingPaymentService;
use App\Modules\TravelTours\Database\Seeders\TravelToursAccessSeeder;
use App\Modules\TravelTours\Events\TourBookingPlaced;
use App\Modules\TravelTours\Notifications\BookingPaymentConfirmedNotification;
use App\Modules\TravelTours\Notifications\BookingPaymentRecordedNotification;
use App\Modules\TravelTours\Notifications\TourBookingConfirmedNotification;
use App\Modules\TravelTours\Notifications\TourBookingPlacedNotification;
use App\Modules\TravelTours\Support\TravelToursRole;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Listeners run inline and hand off to one queued notification each; the
 * recipient is the booking's email snapshot for customers and the holders
 * of the confirm-payments grant for staff, and nothing is sent when delivery
 * is disabled.
 */
final class TravelToursNotificationsTest extends TestCase
{
    use RefreshDatabase;

    /** Enable the module, seed the role grants, and fake delivery. */
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('n', 32)), 'travel-tours.enabled' => true, 'travel-tours.notifications.queue' => 'travel-mail']);
        $this->seed(TravelToursAccessSeeder::class);
        Notification::fake();
    }

    /** Placement mails the customer once, on the configured queue, after commit. */
    public function test_placement_sends_one_queued_customer_notification(): void
    {
        $booking = $this->booking();

        TourBookingPlaced::dispatch($booking);

        Notification::assertSentOnDemand(TourBookingPlacedNotification::class, function (TourBookingPlacedNotification $notification, array $channels, AnonymousNotifiable $notifiable) use ($booking): bool {
            return $notifiable->routes['mail'] === 'amina@example.test'
                && $channels === ['mail']
                && $notification instanceof ShouldQueue
                && $notification->queue === 'travel-mail'
                && $notification->afterCommit === true
                && $notification->tries === 3
                && $notification->booking->is($booking)
                && str_contains((string) $notification->toMail($notifiable)->subject, $booking->booking_number);
        });
        Notification::assertCount(1);
    }

    /** Recorded evidence notifies confirmers only; confirmation notifies the customer only. */
    public function test_payment_evidence_notifies_confirmers_and_confirmation_notifies_the_customer(): void
    {
        $booking = $this->booking();
        $manager = $this->operator(TravelToursRole::MANAGER);
        $agent = $this->operator(TravelToursRole::BOOKING_AGENT);
        $inactive = $this->operator(TravelToursRole::MANAGER);
        $inactive->forceFill(['is_active' => false])->save();
        $service = app(BookingPaymentService::class);

        $payment = $service->recordPending($booking, new BookingPaymentData('evidence-1', PaymentMethod::BankTransfer, 10_000_00, 'KES', 'BT-1', 'Equity Bank', actorId: $agent->getKey()));

        Notification::assertSentTo($manager, BookingPaymentRecordedNotification::class, function (BookingPaymentRecordedNotification $notification) use ($payment, $manager): bool {
            $mail = $notification->toMail($manager);

            return $notification->payment->is($payment)
                && $notification->queue === 'travel-mail'
                && str_contains((string) $mail->subject, $payment->booking->booking_number)
                && str_contains((string) $mail->actionUrl, 'admin/travel/bookings');
        });
        Notification::assertNotSentTo($agent, BookingPaymentRecordedNotification::class);
        Notification::assertNotSentTo($inactive, BookingPaymentRecordedNotification::class);
        Notification::assertCount(1);

        $service->confirm($payment, $manager->getKey());

        Notification::assertSentOnDemand(BookingPaymentConfirmedNotification::class, fn ($notification, $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'amina@example.test');
        Notification::assertCount(2);
    }

    /** Booking confirmation mails the customer once with the travel dates. */
    public function test_booking_confirmation_mails_the_customer_once(): void
    {
        $booking = $this->booking();

        app(BookingLifecycleService::class)->confirm($booking, null);

        Notification::assertSentOnDemand(TourBookingConfirmedNotification::class, function (TourBookingConfirmedNotification $notification, array $channels, AnonymousNotifiable $notifiable) use ($booking): bool {
            $mail = $notification->toMail($notifiable);

            return $notifiable->routes['mail'] === 'amina@example.test'
                && str_contains((string) $mail->subject, $booking->booking_number.' confirmed')
                && collect($mail->introLines)->contains(fn (string $line): bool => str_contains($line, 'Travel dates:'));
        });
        Notification::assertCount(1);
    }

    /** Disabling delivery silences every listener without touching the domain writes. */
    public function test_disabled_delivery_sends_nothing(): void
    {
        config(['travel-tours.notifications.enabled' => false]);
        $booking = $this->booking();
        $manager = $this->operator(TravelToursRole::MANAGER);

        TourBookingPlaced::dispatch($booking);
        $payment = app(BookingPaymentService::class)->recordPending($booking, new BookingPaymentData('evidence-2', PaymentMethod::Cash, 5_000_00, 'KES', actorId: $manager->getKey()));
        app(BookingPaymentService::class)->confirm($payment, $manager->getKey());
        app(BookingLifecycleService::class)->confirm($booking, $manager->getKey());

        Notification::assertNothingSent();
        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
        $this->assertSame(5_000_00, $booking->fresh()->paid_minor);
    }

    /** A booking without an email snapshot produces no customer mail and no error. */
    public function test_bookings_without_email_are_skipped_quietly(): void
    {
        $booking = $this->booking(email: null);

        TourBookingPlaced::dispatch($booking);
        app(BookingLifecycleService::class)->confirm($booking, null);

        Notification::assertNothingSent();
    }

    /** Create a pending booking addressed to one customer email. */
    private function booking(?string $email = 'amina@example.test'): TourBooking
    {
        return TourBooking::factory()->create([
            'status' => BookingStatus::Pending, 'customer_name_snapshot' => 'Amina Wanjiru', 'customer_email_snapshot' => $email,
            'total_minor' => 50_000_00, 'subtotal_minor' => 50_000_00, 'deposit_required_minor' => 15_000_00, 'pending_expires_at' => now()->addDay(),
        ]);
    }

    /** Create an active non-administrator operator holding one module role. */
    private function operator(string $role): User
    {
        $user = User::factory()->create(['user_type' => UserType::Viewer, 'is_active' => true]);
        $user->assignRole($role);

        return $user->fresh();
    }
}
