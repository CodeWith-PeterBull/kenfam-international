<?php

/**
 * Coordinates an HTTP request with TravelTours services and presentation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\TravelTours\Bookings\Enums\BookingChannel;
use App\Modules\TravelTours\Bookings\Enums\PaymentRecordStatus;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\PointOfBooking\Services\BookingReceiptDataFactory;
use App\Modules\TravelTours\PointOfBooking\Services\BookingReceiptService;
use App\Modules\TravelTours\PointOfBooking\Services\ReceiptPrinterManager;
use App\Modules\TravelTours\Storefront\Services\TravelStorefrontProfileResolver;
use App\Modules\TravelTours\Support\TravelToursPermission;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Presents private desk receipts to the operator who took the booking or a reviewer. */
final class ReceiptController extends Controller
{
    /** Render the thermal browser receipt; `print=checkout` lets the register auto-prompt once. */
    public function show(Request $request, TourBooking $booking, BookingReceiptDataFactory $documents, ReceiptPrinterManager $printers, TravelStorefrontProfileResolver $profiles): Response
    {
        $this->authorizeReceipt($request, $booking);
        $receipt = $documents->fromBooking($booking);
        $instruction = $printers->instruction($booking->register, $receipt, $request->query('print') === 'checkout');

        return response()->view('travel-tours::pob.receipts.show', [
            'receipt' => $receipt,
            'profile' => $profiles->current(),
            'printInstruction' => $instruction,
            'receiptPdfUrl' => route('travel-tours.pob.receipts.pdf', $booking),
        ], 200, ['Cache-Control' => 'private, no-store, max-age=0', 'Pragma' => 'no-cache', 'X-Robots-Tag' => 'noindex, nofollow, noarchive']);
    }

    /** Stream the A4 PDF counterpart of the receipt. */
    public function pdf(Request $request, TourBooking $booking, BookingReceiptService $receipts): Response
    {
        $user = $this->authorizeReceipt($request, $booking);
        $response = $receipts->stream($booking, $user->display_name);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }

    /** Return the operator after channel, payment, and ownership checks. */
    private function authorizeReceipt(Request $request, TourBooking $booking): User
    {
        abort_unless($booking->channel === BookingChannel::BookingDesk && $booking->payments()->where('status', PaymentRecordStatus::Confirmed->value)->exists(), 404);
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $ownsBooking = $booking->agent_id === $user->getKey() && $user->can(TravelToursPermission::ACCESS_POB);
        $canReview = $user->can(TravelToursPermission::VIEW_BOOKINGS) || $user->can(TravelToursPermission::MANAGE_SHIFTS) || $user->can(TravelToursPermission::MANAGE_PAYMENTS);
        abort_unless($ownsBooking || $canReview, 403);

        return $user;
    }
}
