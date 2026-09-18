<?php

/**
 * Coordinates an HTTP request with TravelTours services and presentation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\Scheduling\Enums\HoldStatus;
use App\Modules\TravelTours\Scheduling\Exceptions\HoldOwnershipMismatch;
use App\Modules\TravelTours\Scheduling\Models\AvailabilityHold;
use App\Modules\TravelTours\Scheduling\Services\AvailabilityHoldService;
use App\Modules\TravelTours\Storefront\Services\BookingAccessUrlService;
use App\Modules\TravelTours\Storefront\Services\CheckoutSession;
use Symfony\Component\HttpFoundation\Response;

/** Presents the checkout for one hold that belongs to the visitor's session. */
final class CheckoutController extends Controller
{
    /** @var array<string, string> */
    private const PRIVATE_HEADERS = [
        'Cache-Control' => 'private, no-store, max-age=0',
        'Pragma' => 'no-cache',
        'X-Robots-Tag' => 'noindex, nofollow, noarchive',
    ];

    /**
     * Render the checkout, the expired notice, or the placed booking's confirmation.
     *
     * A hold that another session created is refused outright; a consumed hold
     * sends its owner to the private confirmation page; an expired or released
     * hold rotates the session's hold generation so the visitor can start again.
     */
    public function __invoke(
        AvailabilityHold $hold,
        AvailabilityHoldService $holds,
        CheckoutSession $checkout,
        BookingAccessUrlService $urls,
    ): Response {
        try {
            $holds->assertOwnedBy($hold, $checkout->ownerToken(), null);
        } catch (HoldOwnershipMismatch) {
            abort(403);
        }

        $hold->load(['departure.tour', 'booking']);

        if ($hold->status === HoldStatus::Consumed && $hold->booking instanceof TourBooking) {
            return redirect()->to($urls->confirmation($hold->booking));
        }

        if ($hold->status !== HoldStatus::Active || $hold->expires_at->isPast()) {
            $checkout->rotate();

            return response()->view('travel-tours::storefront.checkout.expired', ['hold' => $hold], 410, self::PRIVATE_HEADERS);
        }

        return response()->view('travel-tours::storefront.checkout.show', ['hold' => $hold], 200, self::PRIVATE_HEADERS);
    }
}
