<?php

declare(strict_types=1);

namespace Tests\Feature\PropertyBooking;

use App\Enums\UserType;
use App\Models\User;
use App\Modules\PropertyBooking\Bookings\Data\AdministrationPaymentData;
use App\Modules\PropertyBooking\Bookings\Data\BookingModificationData;
use App\Modules\PropertyBooking\Bookings\Enums\BookingPaymentMethod;
use App\Modules\PropertyBooking\Bookings\Events\BookingCancelled;
use App\Modules\PropertyBooking\Bookings\Events\BookingCheckedIn;
use App\Modules\PropertyBooking\Bookings\Events\BookingCheckedOut;
use App\Modules\PropertyBooking\Bookings\Events\BookingConfirmed;
use App\Modules\PropertyBooking\Bookings\Events\BookingMarkedNoShow;
use App\Modules\PropertyBooking\Bookings\Events\BookingModified;
use App\Modules\PropertyBooking\Bookings\Events\BookingPaymentConfirmed;
use App\Modules\PropertyBooking\Bookings\Events\WebBookingPlaced;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Bookings\Notifications\BookingCancellationAlertNotification;
use App\Modules\PropertyBooking\Bookings\Notifications\BookingCancelledNotification;
use App\Modules\PropertyBooking\Bookings\Notifications\BookingCheckedInNotification;
use App\Modules\PropertyBooking\Bookings\Notifications\BookingCheckedOutNotification;
use App\Modules\PropertyBooking\Bookings\Notifications\BookingConfirmationNotification;
use App\Modules\PropertyBooking\Bookings\Notifications\BookingConfirmedNotification;
use App\Modules\PropertyBooking\Bookings\Notifications\BookingModifiedNotification;
use App\Modules\PropertyBooking\Bookings\Notifications\BookingNoShowNotification;
use App\Modules\PropertyBooking\Bookings\Notifications\BookingPaymentConfirmedNotification;
use App\Modules\PropertyBooking\Bookings\Notifications\NewWebBookingReceivedNotification;
use App\Modules\PropertyBooking\Bookings\Notifications\UnitTurnoverRequiredNotification;
use App\Modules\PropertyBooking\Bookings\Services\BookingAdministrationPaymentService;
use App\Modules\PropertyBooking\Bookings\Services\BookingLifecycleService;
use App\Modules\PropertyBooking\Bookings\Services\BookingModificationService;
use App\Modules\PropertyBooking\Bookings\Services\BookingService;
use App\Modules\PropertyBooking\Catalog\Models\AccommodationUnit;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Catalog\Models\UnitType;
use App\Modules\PropertyBooking\Guests\Data\GuestProfileData;
use App\Modules\PropertyBooking\Guests\Models\Guest;
use App\Modules\PropertyBooking\PointOfBooking\Events\ReceptionShiftVarianceDetected;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionRegister;
use App\Modules\PropertyBooking\PointOfBooking\Notifications\ReceptionShiftVarianceNotification;
use App\Modules\PropertyBooking\PointOfBooking\Services\ReceptionShiftService;
use App\Modules\PropertyBooking\Pricing\Models\RatePlan;
use App\Modules\PropertyBooking\Pricing\Services\BookingQuoteService;
use App\Modules\PropertyBooking\Storefront\Data\BookingSelectionData;
use App\Modules\PropertyBooking\Storefront\Services\StorefrontBookingCheckoutService;
use App\Modules\PropertyBooking\Support\PropertyBookingPermission;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

/** Verifies event timing, recipient isolation, stale guards, and focused mail. */
final class PropertyBookingOperationalNotificationTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Notification::fake();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_events_and_notifications_use_after_commit_queued_contracts(): void
    {
        $events = [
            new WebBookingPlaced('01ARZ3NDEKTSV4RRFFQ69G5FAV', 1),
            new BookingConfirmed('01ARZ3NDEKTSV4RRFFQ69G5FAV', 1),
            new BookingModified('01ARZ3NDEKTSV4RRFFQ69G5FAV', 1, 'interval'),
            new BookingCancelled('01ARZ3NDEKTSV4RRFFQ69G5FAV', 1),
            new BookingMarkedNoShow('01ARZ3NDEKTSV4RRFFQ69G5FAV', 1),
            new BookingCheckedIn('01ARZ3NDEKTSV4RRFFQ69G5FAV', 1),
            new BookingCheckedOut('01ARZ3NDEKTSV4RRFFQ69G5FAV', 1),
            new BookingPaymentConfirmed('01ARZ3NDEKTSV4RRFFQ69G5FAV', '01ARZ3NDEKTSV4RRFFQ69G5FAW', 1),
            new ReceptionShiftVarianceDetected('01ARZ3NDEKTSV4RRFFQ69G5FAX', -10_000, 10_000, 1),
        ];

        foreach ($events as $event) {
            $this->assertInstanceOf(ShouldDispatchAfterCommit::class, $event);
        }

        $notifications = [
            new BookingConfirmationNotification('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
            new NewWebBookingReceivedNotification('01ARZ3NDEKTSV4RRFFQ69G5FAV', 1),
            new BookingConfirmedNotification('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
            new BookingModifiedNotification('01ARZ3NDEKTSV4RRFFQ69G5FAV', 'interval'),
            new BookingCancelledNotification('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
            new BookingCancellationAlertNotification('01ARZ3NDEKTSV4RRFFQ69G5FAV', 1),
            new BookingNoShowNotification('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
            new BookingCheckedInNotification('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
            new BookingCheckedOutNotification('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
            new UnitTurnoverRequiredNotification('01ARZ3NDEKTSV4RRFFQ69G5FAV', 1),
            new BookingPaymentConfirmedNotification('01ARZ3NDEKTSV4RRFFQ69G5FAV', '01ARZ3NDEKTSV4RRFFQ69G5FAW'),
            new ReceptionShiftVarianceNotification('01ARZ3NDEKTSV4RRFFQ69G5FAX', 1, -10_000, 10_000),
        ];

        foreach ($notifications as $notification) {
            $this->assertInstanceOf(ShouldQueue::class, $notification);
            $this->assertTrue($notification->afterCommit);
            $this->assertSame(3, $notification->tries);
            $this->assertSame([30, 120, 300], $notification->backoff());
            $this->assertInstanceOf($notification::class, unserialize(serialize($notification)));
        }
    }

    public function test_web_checkout_notifies_guest_and_only_verified_scoped_booking_managers(): void
    {
        [$property, , $ratePlan] = $this->inventory();
        $manager = $this->operator($property, 'manager@example.test', true, true, PropertyBookingPermission::MANAGE_BOOKINGS);
        $inactive = $this->operator($property, 'inactive@example.test', false, true, PropertyBookingPermission::MANAGE_BOOKINGS);
        $unverified = $this->operator($property, 'unverified@example.test', true, false, PropertyBookingPermission::MANAGE_BOOKINGS);
        $otherProperty = Property::factory()->published()->create();
        $outside = $this->operator($otherProperty, 'outside@example.test', true, true, PropertyBookingPermission::MANAGE_BOOKINGS);

        $booking = $this->placeWebCheckout($ratePlan, 'guest@example.test');

        Notification::assertSentTo(
            $manager,
            NewWebBookingReceivedNotification::class,
            static fn (NewWebBookingReceivedNotification $notification): bool => $notification->bookingUlid === $booking->ulid
                && $notification->propertyId === $property->id,
        );
        Notification::assertNotSentTo($inactive, NewWebBookingReceivedNotification::class);
        Notification::assertNotSentTo($unverified, NewWebBookingReceivedNotification::class);
        Notification::assertNotSentTo($outside, NewWebBookingReceivedNotification::class);
        Notification::assertSentOnDemand(
            BookingConfirmationNotification::class,
            static fn (BookingConfirmationNotification $notification, array $channels, object $notifiable): bool => $notification->bookingUlid === $booking->ulid
                && $channels === ['mail']
                && data_get($notifiable, 'routes.mail') === 'guest@example.test',
        );
    }

    public function test_real_lifecycle_services_route_independent_customer_and_turnover_messages(): void
    {
        config()->set('property-booking.operations.confirmation_payment_policy', 'none');
        config()->set('property-booking.operations.check_in_payment_policy', 'none');
        config()->set('property-booking.operations.check_out_payment_policy', 'none');
        [$property, , $ratePlan] = $this->inventory(3);
        $manager = $this->manager();
        $readiness = $this->operator($property, 'readiness@example.test', true, true, PropertyBookingPermission::MANAGE_READINESS);
        $booking = $this->pendingBooking($ratePlan, 'lifecycle@example.test');
        $lifecycle = app(BookingLifecycleService::class);

        Notification::fake();
        $booking = $lifecycle->confirm($booking, $manager);
        Notification::assertSentOnDemand(BookingConfirmedNotification::class);

        $booking = app(BookingModificationService::class)->modifyInterval(
            $booking,
            new BookingModificationData(
                CarbonImmutable::instance($booking->starts_at),
                CarbonImmutable::instance($booking->ends_at)->addDay(),
                'Guest extended the stay.',
            ),
            $manager,
        );
        Notification::assertSentOnDemand(
            BookingModifiedNotification::class,
            static fn (BookingModifiedNotification $notification): bool => $notification->changeType === 'interval',
        );

        CarbonImmutable::setTestNow(CarbonImmutable::instance($booking->starts_at));
        $booking = $lifecycle->checkIn($booking, $manager);
        Notification::assertSentOnDemand(BookingCheckedInNotification::class);

        CarbonImmutable::setTestNow(CarbonImmutable::instance($booking->ends_at));
        $booking = $lifecycle->checkOut($booking, $manager);
        Notification::assertSentOnDemand(BookingCheckedOutNotification::class);
        Notification::assertSentTo(
            $readiness,
            UnitTurnoverRequiredNotification::class,
            static fn (UnitTurnoverRequiredNotification $notification): bool => $notification->bookingUlid === $booking->ulid,
        );
    }

    public function test_payment_cancellation_and_no_show_each_route_a_focused_message(): void
    {
        config()->set('property-booking.operations.confirmation_payment_policy', 'none');
        [$property, , $ratePlan] = $this->inventory(3);
        $manager = $this->manager();
        $bookingManager = $this->operator($property, 'bookings@example.test', true, true, PropertyBookingPermission::MANAGE_BOOKINGS);
        $paymentBooking = $this->pendingBooking($ratePlan, 'payment@example.test');

        Notification::fake();
        $payment = app(BookingAdministrationPaymentService::class)->recordCompleted(
            $paymentBooking,
            new AdministrationPaymentData(BookingPaymentMethod::Card, 10_000, 'NOTIFY-PAYMENT-001'),
            $manager,
        );
        Notification::assertSentOnDemand(
            BookingPaymentConfirmedNotification::class,
            static fn (BookingPaymentConfirmedNotification $notification): bool => $notification->paymentUlid === $payment->ulid,
        );

        $cancelledBooking = app(BookingLifecycleService::class)->confirm(
            $this->pendingBooking($ratePlan, 'cancelled@example.test'),
            $manager,
        );
        Notification::fake();
        $cancelledBooking = app(BookingLifecycleService::class)->cancel(
            $cancelledBooking,
            'Guest requested cancellation.',
            $manager,
        );
        Notification::assertSentOnDemand(BookingCancelledNotification::class);
        Notification::assertSentTo(
            $bookingManager,
            BookingCancellationAlertNotification::class,
            static fn (BookingCancellationAlertNotification $notification): bool => $notification->bookingUlid === $cancelledBooking->ulid,
        );

        $noShowBooking = app(BookingLifecycleService::class)->confirm(
            $this->pendingBooking($ratePlan, 'noshow@example.test'),
            $manager,
        );
        Notification::fake();
        CarbonImmutable::setTestNow(CarbonImmutable::instance($noShowBooking->starts_at)
            ->addMinutes((int) config('property-booking.operations.no_show_after_minutes')));
        app(BookingLifecycleService::class)->markNoShow($noShowBooking, 'Arrival did not occur.', $manager);
        Notification::assertSentOnDemand(BookingNoShowNotification::class);
    }

    public function test_shift_variance_alert_uses_absolute_materiality_and_property_scope(): void
    {
        config()->set('property-booking.pob.shift_variance_threshold_minor', 100);
        [$property] = $this->inventory();
        $manager = $this->operator(
            $property,
            'shifts@example.test',
            true,
            true,
            PropertyBookingPermission::MANAGE_SHIFTS,
        );
        $receptionist = $this->operator(
            $property,
            'reception@example.test',
            true,
            true,
            PropertyBookingPermission::ACCESS_POB,
        );
        $outsideProperty = Property::factory()->published()->create();
        $outside = $this->operator(
            $outsideProperty,
            'outside-shifts@example.test',
            true,
            true,
            PropertyBookingPermission::MANAGE_SHIFTS,
        );
        $register = ReceptionRegister::factory()->for($property)->create();
        $shifts = app(ReceptionShiftService::class);

        $shift = $shifts->open($register, $receptionist, $manager, 1_000);
        Notification::fake();
        $closed = $shifts->close($shift, $manager, 800);

        Notification::assertSentTo(
            $manager,
            ReceptionShiftVarianceNotification::class,
            static fn (ReceptionShiftVarianceNotification $notification): bool => $notification->shiftUlid === $closed->ulid
                && $notification->varianceMinor === -200,
        );
        Notification::assertNotSentTo($outside, ReceptionShiftVarianceNotification::class);
    }

    public function test_disabled_configuration_stale_state_and_private_values_fail_closed(): void
    {
        config()->set('property-booking.operations.confirmation_payment_policy', 'none');
        [, , $ratePlan] = $this->inventory();
        $booking = $this->pendingBooking($ratePlan, 'privacy@example.test');
        $notification = new BookingConfirmedNotification($booking->ulid);
        config()->set('property-booking.notifications.enabled', false);

        BookingConfirmed::dispatch($booking->ulid, $booking->property_id);
        Notification::assertNothingSent();

        config()->set('property-booking.notifications.enabled', true);
        $cancelled = app(BookingLifecycleService::class)->confirm($booking, $this->manager());
        $cancelled = app(BookingLifecycleService::class)->cancel($cancelled, 'Privacy guard.', $this->manager());
        $anonymous = (new AnonymousNotifiable)->route('mail', 'privacy@example.test');
        $this->assertFalse($notification->shouldSend($anonymous, 'mail'));

        $mail = (new BookingCancelledNotification($cancelled->ulid))->toMail($anonymous);
        $safeText = json_encode([$mail->subject, $mail->introLines, $mail->outroLines], JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('Privacy guard', $safeText);
        $this->assertStringNotContainsString('identity', strtolower($safeText));
        $this->assertStringNotContainsString('metadata', strtolower($safeText));
    }

    public function test_after_commit_booking_event_is_discarded_when_outer_transaction_rolls_back(): void
    {
        config()->set('property-booking.operations.confirmation_payment_policy', 'none');
        [, , $ratePlan] = $this->inventory();
        $booking = $this->pendingBooking($ratePlan, 'rollback@example.test');
        $manager = $this->manager();
        Notification::fake();

        try {
            DB::transaction(function () use ($booking, $manager): void {
                app(BookingLifecycleService::class)->confirm($booking, $manager);
                throw new RuntimeException('Force booking rollback');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('Force booking rollback', $exception->getMessage());
        }

        $this->assertSame('pending', $booking->fresh()->status->value);
        Notification::assertNothingSent();
    }

    /** @return array{Property, UnitType, RatePlan, list<AccommodationUnit>} */
    private function inventory(int $unitCount = 1): array
    {
        $property = Property::factory()->published()->create([
            'timezone' => 'Africa/Nairobi',
            'currency' => 'KES',
            'check_in_from' => null,
            'check_in_until' => null,
            'check_out_from' => null,
            'check_out_until' => null,
            'minimum_notice_minutes' => 0,
            'maximum_advance_days' => 365,
            'turnover_minutes' => 0,
        ]);
        $unitType = UnitType::factory()->for($property)->published()->create([
            'maximum_guests' => 4,
            'maximum_adults' => 2,
            'maximum_children' => 2,
        ]);
        $ratePlan = RatePlan::factory()->forUnitType($unitType)->active()->create([
            'base_rate_minor' => 120_000,
            'included_adults' => 2,
            'included_children' => 2,
            'extra_adult_minor' => 0,
            'extra_child_minor' => 0,
            'tax_rate_bps' => 0,
        ]);
        $units = AccommodationUnit::factory()->count($unitCount)->forUnitType($unitType)->create()->all();

        return [$property, $unitType, $ratePlan, $units];
    }

    private function pendingBooking(RatePlan $ratePlan, string $email): Booking
    {
        $startsAt = CarbonImmutable::now($ratePlan->property->timezone)->addDays(5)->setTime(14, 0)->utc();
        $quote = app(BookingQuoteService::class)->quote(
            $ratePlan,
            $startsAt,
            $startsAt->addDay()->subHours(3),
            2,
            0,
        );

        return app(BookingService::class)->placeWeb(
            $quote,
            $ratePlan,
            Guest::factory()->create(['email' => $email]),
            BookingPaymentMethod::MobileMoney,
        );
    }

    private function placeWebCheckout(RatePlan $ratePlan, string $email): Booking
    {
        $startsAt = CarbonImmutable::now($ratePlan->property->timezone)->addDays(10)->setTime(14, 0)->utc();
        $quote = app(BookingQuoteService::class)->quote(
            $ratePlan,
            $startsAt,
            $startsAt->addDays(2)->subHours(3),
            2,
            0,
        );

        return app(StorefrontBookingCheckoutService::class)->place(
            BookingSelectionData::fromQuote($quote),
            new GuestProfileData(
                'Amina',
                null,
                'Otieno',
                $email,
                '+254700000001',
                null,
                null,
                'Nairobi',
                null,
                null,
                'KE',
            ),
            BookingPaymentMethod::MobileMoney,
        );
    }

    private function manager(): User
    {
        $manager = User::factory()->create([
            'user_type' => UserType::SystemAdministrator,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $manager->assignRole(UserType::SystemAdministrator->value);

        return $manager;
    }

    private function operator(
        Property $property,
        string $email,
        bool $active,
        bool $verified,
        string ...$permissions,
    ): User {
        $operator = User::factory()->create([
            'email' => $email,
            'is_active' => $active,
            'email_verified_at' => $verified ? now() : null,
        ]);
        $operator->givePermissionTo($permissions);
        DB::table('property_booking_property_user')->insert([
            'property_id' => $property->getKey(),
            'user_id' => $operator->getKey(),
            'assigned_by' => null,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $operator;
    }
}
