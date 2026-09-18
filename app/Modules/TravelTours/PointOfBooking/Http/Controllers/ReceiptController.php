<?php

/**
 * Coordinates an HTTP request with TravelTours services and presentation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TravelTours\Bookings\Enums\PaymentRecordStatus;
use App\Modules\TravelTours\Bookings\Models\BookingPayment;
use App\Modules\TravelTours\Storefront\Services\TravelStorefrontProfileResolver;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Renders a printable receipt for one confirmed desk payment to an authorized operator. */
final class ReceiptController extends Controller
{
    /** Render the receipt page; `auto=1` asks the browser to print it once. */
    public function __invoke(Request $request, BookingPayment $payment, TravelStorefrontProfileResolver $profiles): Response
    {
        abort_unless($payment->status === PaymentRecordStatus::Confirmed, 404);
        $payment->load(['booking.participants', 'shift.register', 'receiver']);

        return response()->view('travel-tours::pob.receipt', [
            'payment' => $payment,
            'profile' => $profiles->current(),
            'autoPrint' => $request->boolean('auto'),
        ], 200, ['Cache-Control' => 'private, no-store, max-age=0', 'X-Robots-Tag' => 'noindex, nofollow, noarchive']);
    }
}
