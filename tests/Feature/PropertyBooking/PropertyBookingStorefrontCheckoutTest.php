<?php

declare(strict_types=1);

namespace Tests\Feature\PropertyBooking;

use App\Modules\PropertyBooking\Bookings\Enums\BookingPaymentMethod;
use App\Modules\PropertyBooking\Bookings\Enums\BookingStatus;
use App\Modules\PropertyBooking\Bookings\Events\WebBookingPlaced;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Bookings\Models\BookingPayment;
use App\Modules\PropertyBooking\Bookings\Models\BookingStay;
use App\Modules\PropertyBooking\Bookings\Models\UnitAssignment;
use App\Modules\PropertyBooking\Bookings\Notifications\BookingConfirmationNotification;
use App\Modules\PropertyBooking\Catalog\Enums\UnitOperationalStatus;
use App\Modules\PropertyBooking\Catalog\Models\AccommodationUnit;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Catalog\Models\UnitType;
use App\Modules\PropertyBooking\Guests\Data\GuestProfileData;
use App\Modules\PropertyBooking\Guests\Exceptions\GuestException;
use App\Modules\PropertyBooking\Guests\Models\Guest;
use App\Modules\PropertyBooking\Guests\Services\GuestService;
use App\Modules\PropertyBooking\Pricing\Data\BookingQuote;
use App\Modules\PropertyBooking\Pricing\Models\RatePlan;
use App\Modules\PropertyBooking\Pricing\Services\BookingQuoteService;
use App\Modules\PropertyBooking\Storefront\Data\BookingSelectionData;
use App\Modules\PropertyBooking\Storefront\Livewire\Checkout;
use App\Modules\PropertyBooking\Storefront\Services\BookingSelectionSession;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** Verifies minimal session state, authoritative placement, guest reuse, and rollback. */
final class PropertyBookingStorefrontCheckoutTest extends TestCase
{
    use RefreshDatabase;

    /** Keep session state scalar-only and re-price it from the current rate. */
    public function test_selection_session_stores_no_money_or_concrete_unit_and_requotes_current_values(): void
    {
        [, , $ratePlan] = $this->inventory(2);
        $quote = $this->quote($ratePlan);
        $selection = app(BookingSelectionSession::class);

        $selection->select(BookingSelectionData::fromQuote($quote));
        $payload = session((string) config('property-booking.storefront.selection_session_key'));
        $this->assertSame(['rate_plan_ulid', 'starts_at', 'ends_at', 'adults', 'children', 'infants'], array_keys($payload));
        $this->assertStringNotContainsString('total', json_encode($payload, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('unit_id', json_encode($payload, JSON_THROW_ON_ERROR));

        $ratePlan->forceFill(['base_rate_minor' => 150_000])->save();
        $this->assertSame(300_000, $selection->current()->calculation->totalMinor);
    }

    /** Place one pending booking with guest, immutable stay, allocation, consent, and one mail. */
    public function test_livewire_checkout_places_one_atomic_web_booking_and_creates_the_guest(): void
    {
        Notification::fake();
        [, , $ratePlan] = $this->inventory(2);
        app(BookingSelectionSession::class)->select(BookingSelectionData::fromQuote($this->quote($ratePlan)));

        Livewire::test(Checkout::class)
            ->set('form.firstName', 'Amina')
            ->set('form.middleName', 'Wanjiru')
            ->set('form.lastName', 'Otieno')
            ->set('form.email', 'amina@example.test')
            ->set('form.phone', '+254700000001')
            ->set('form.city', 'Nairobi')
            ->set('form.countryCode', 'ke')
            ->set('form.paymentMethod', BookingPaymentMethod::MobileMoney->value)
            ->set('form.specialRequests', 'A quiet space away from the lift.')
            ->set('form.acceptedTerms', true)
            ->call('place')
            ->assertHasNoErrors()
            ->assertRedirect();

        $booking = Booking::query()->with(['stays', 'guests'])->sole();
        $guest = Guest::query()->sole();
        $this->assertSame(BookingStatus::Pending, $booking->status);
        $this->assertSame(BookingPaymentMethod::MobileMoney, $booking->preferred_payment_method);
        $this->assertNotNull($booking->terms_accepted_at);
        $this->assertNotNull($booking->booking_number);
        $this->assertSame($guest->id, $booking->primary_guest_id);
        $this->assertSame('amina@example.test', $guest->email);
        $this->assertSame('KE', $guest->country_code);
        $this->assertNull($guest->identity_number_hash);
        $this->assertCount(1, $booking->stays);
        $this->assertCount(1, $booking->guests);
        $this->assertDatabaseCount((new UnitAssignment)->getTable(), 1);
        $this->assertDatabaseCount((new BookingPayment)->getTable(), 0);
        $this->assertFalse(session()->has((string) config('property-booking.storefront.selection_session_key')));

        Notification::assertSentOnDemand(
            BookingConfirmationNotification::class,
            static fn (BookingConfirmationNotification $notification, array $channels, object $notifiable): bool => $notification->bookingUlid === $booking->ulid
                && $channels === ['mail']
                && data_get($notifiable, 'routes.mail') === 'amina@example.test',
        );
        Notification::assertSentOnDemandTimes(BookingConfirmationNotification::class, 1);
    }

    /** Enforce adopter-configured public identity and persist it only through protected fields. */
    public function test_storefront_identity_requirement_is_configurable_and_protected(): void
    {
        Notification::fake();
        config()->set('property-booking.storefront.guest_identity_required', true);
        [, , $ratePlan] = $this->inventory(1);
        app(BookingSelectionSession::class)->select(BookingSelectionData::fromQuote($this->quote($ratePlan)));

        $component = Livewire::test(Checkout::class)
            ->set('form.firstName', 'Wanjiku')
            ->set('form.lastName', 'Njoroge')
            ->set('form.email', 'wanjiku@example.test')
            ->set('form.phone', '+254700000099')
            ->set('form.acceptedTerms', true)
            ->call('place')
            ->assertHasErrors(['form.identityType', 'form.identityNumber']);

        $component
            ->set('form.identityType', 'passport')
            ->set('form.identityNumber', 'P-9088-7766')
            ->call('place')
            ->assertHasNoErrors()
            ->assertRedirect();

        $guest = Guest::query()->sole();
        $this->assertSame('passport', $guest->identity_type);
        $this->assertNotNull($guest->identity_number_hash);
        $this->assertNotSame('P-9088-7766', $guest->getRawOriginal('identity_number_ciphertext'));
        $this->assertSame('P-9088-7766', app(GuestService::class)->revealIdentity($guest));
    }

    /** Reuse an exact guest contact tuple and update its public-safe profile fields. */
    public function test_guest_resolution_reuses_exact_email_and_phone_tuple(): void
    {
        $service = app(GuestService::class);
        $first = $service->resolveForCheckout($this->guestData('Nairobi'));
        $repeat = $service->resolveForCheckout($this->guestData('Kisumu'));

        $this->assertSame($first->id, $repeat->id);
        $this->assertSame('Kisumu', $repeat->city);
        $this->assertDatabaseCount((new Guest)->getTable(), 1);
    }

    /** Match rather than overwrite protected identity when public contact resolution finds a guest. */
    public function test_guest_resolution_rejects_replacement_of_an_existing_protected_identity(): void
    {
        $service = app(GuestService::class);
        $existing = $service->resolveForCheckout(new GuestProfileData(
            firstName: 'Amina',
            middleName: null,
            lastName: 'Otieno',
            email: 'amina.identity@example.test',
            phone: '+254700000010',
            addressLine1: null,
            addressLine2: null,
            city: null,
            region: null,
            postalCode: null,
            countryCode: 'KE',
            identityType: 'passport',
            identityNumber: 'PASSPORT-ORIGINAL-1',
        ));

        try {
            $service->resolveForCheckout(new GuestProfileData(
                firstName: 'Amina',
                middleName: null,
                lastName: 'Otieno',
                email: 'amina.identity@example.test',
                phone: '+254700000010',
                addressLine1: null,
                addressLine2: null,
                city: null,
                region: null,
                postalCode: null,
                countryCode: 'KE',
                identityType: 'passport',
                identityNumber: 'PASSPORT-REPLACEMENT-2',
            ));
            $this->fail('Public guest resolution replaced an existing protected identity.');
        } catch (GuestException $exception) {
            $this->assertStringContainsString('does not match', $exception->getMessage());
        }

        $this->assertSame('PASSPORT-ORIGINAL-1', $service->revealIdentity($existing->fresh()));
        $this->assertDatabaseCount((new Guest)->getTable(), 1);
    }

    /** Reject stale availability without leaving a guest, booking, stay, or allocation. */
    public function test_stale_selection_fails_closed_without_partial_records(): void
    {
        Notification::fake();
        [, , $ratePlan, $units] = $this->inventory(1);
        app(BookingSelectionSession::class)->select(BookingSelectionData::fromQuote($this->quote($ratePlan)));
        $units[0]->forceFill(['operational_status' => UnitOperationalStatus::Maintenance])->save();

        Livewire::test(Checkout::class)
            ->set('form.firstName', 'Njeri')
            ->set('form.lastName', 'Kamau')
            ->set('form.email', 'njeri@example.test')
            ->set('form.phone', '+254700000002')
            ->set('form.acceptedTerms', true)
            ->call('place')
            ->assertHasErrors('checkout');

        $this->assertDatabaseCount((new Guest)->getTable(), 0);
        $this->assertDatabaseCount((new Booking)->getTable(), 0);
        $this->assertDatabaseCount((new BookingStay)->getTable(), 0);
        $this->assertDatabaseCount((new UnitAssignment)->getTable(), 0);
        $this->assertTrue(session()->has((string) config('property-booking.storefront.selection_session_key')));
        Notification::assertNothingSent();
    }

    /** Preserve the expected event and queue contracts for async production delivery. */
    public function test_web_booking_event_and_confirmation_notification_are_after_commit_and_queued(): void
    {
        $event = new WebBookingPlaced('01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $notification = new BookingConfirmationNotification('01ARZ3NDEKTSV4RRFFQ69G5FAV');

        $this->assertInstanceOf(ShouldDispatchAfterCommit::class, $event);
        $this->assertInstanceOf(ShouldQueue::class, $notification);
        $this->assertTrue($notification->afterCommit);
        $this->assertSame(3, $notification->tries);
        $this->assertSame('notifications', $notification->queue);
        $this->assertInstanceOf(BookingConfirmationNotification::class, unserialize(serialize($notification)));
    }

    /** @return array{Property, UnitType, RatePlan, list<AccommodationUnit>} */
    private function inventory(int $unitCount): array
    {
        $property = Property::factory()->published()->create([
            'timezone' => 'Africa/Nairobi',
            'currency' => 'KES',
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

    /** Generate a deterministic two-night current quote. */
    private function quote(RatePlan $ratePlan): BookingQuote
    {
        $startsAt = CarbonImmutable::now($ratePlan->property->timezone)->addDays(10)->setTime(15, 0)->utc();

        return app(BookingQuoteService::class)->quote($ratePlan, $startsAt, $startsAt->addDays(2)->subHours(5), 2, 0);
    }

    /** Build one reusable public-safe guest profile. */
    private function guestData(string $city): GuestProfileData
    {
        return new GuestProfileData('Amina', null, 'Otieno', 'amina@example.test', '+254700000001', null, null, $city, null, null, 'KE');
    }
}
