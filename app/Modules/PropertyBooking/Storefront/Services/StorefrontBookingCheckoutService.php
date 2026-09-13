<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Storefront\Services;

use App\Models\User;
use App\Modules\PropertyBooking\Bookings\Enums\BookingPaymentMethod;
use App\Modules\PropertyBooking\Bookings\Events\WebBookingPlaced;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Bookings\Services\BookingService;
use App\Modules\PropertyBooking\Guests\Data\GuestProfileData;
use App\Modules\PropertyBooking\Guests\Services\GuestService;
use App\Modules\PropertyBooking\Pricing\Models\RatePlan;
use App\Modules\PropertyBooking\Pricing\Services\BookingQuoteService;
use App\Modules\PropertyBooking\Storefront\Data\BookingSelectionData;
use App\Modules\PropertyBooking\Storefront\Exceptions\StorefrontException;
use Illuminate\Database\DatabaseManager;

/** Coordinates authoritative self-service re-quote, guest resolution, and placement. */
final readonly class StorefrontBookingCheckoutService
{
    /** Create checkout orchestration from module-owned transactional services. */
    public function __construct(
        private DatabaseManager $database,
        private BookingQuoteService $quotes,
        private GuestService $guests,
        private BookingService $bookings,
    ) {}

    /** Place a booking without accepting client-owned totals or unit identity. */
    public function place(
        BookingSelectionData $selection,
        GuestProfileData $guestData,
        BookingPaymentMethod $paymentPreference,
        ?string $specialRequests = null,
        ?User $account = null,
    ): Booking {
        $booking = $this->database->transaction(function () use ($selection, $guestData, $paymentPreference, $specialRequests, $account): Booking {
            $ratePlan = RatePlan::query()
                ->publiclyBookable()
                ->where('ulid', $selection->ratePlanUlid)
                ->whereHas('property', static fn ($query) => $query
                    ->published()
                    ->whereHas('category', static fn ($category) => $category->active()))
                ->whereHas('unitType', static fn ($query) => $query->published())
                ->with(['property', 'unitType'])
                ->lockForUpdate()
                ->first();

            if (! $ratePlan instanceof RatePlan) {
                throw new StorefrontException('The selected accommodation rate is no longer bookable.');
            }

            $quote = $this->quotes->quote(
                $ratePlan,
                $selection->startsAt,
                $selection->endsAt,
                $selection->adults,
                $selection->children,
                $selection->infants,
            );
            $guest = $this->guests->resolveForCheckout($guestData, $account);

            return $this->bookings->placeWeb(
                quote: $quote,
                ratePlan: $ratePlan,
                guest: $guest,
                paymentPreference: $paymentPreference,
                specialRequests: $specialRequests,
                account: $account,
            );
        });

        WebBookingPlaced::dispatch($booking->ulid, $booking->property_id);

        return $booking;
    }
}
