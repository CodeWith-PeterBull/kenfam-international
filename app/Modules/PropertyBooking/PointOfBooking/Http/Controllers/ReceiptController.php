<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\PointOfBooking\Http\Controllers;

use App\Contracts\RecordsSystemActivity;
use App\Contracts\ResolvesInstitutionProfile;
use App\Enums\SystemActivitySeverity;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\PropertyBooking\Bookings\Enums\BookingChannel;
use App\Modules\PropertyBooking\Bookings\Enums\BookingPaymentStatus;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\PointOfBooking\Printing\ReceiptPrinterManager;
use App\Modules\PropertyBooking\PointOfBooking\Services\BookingReceiptDataFactory;
use App\Modules\PropertyBooking\PointOfBooking\Services\BookingReceiptService;
use App\Modules\PropertyBooking\Support\PropertyAccessService;
use App\Modules\PropertyBooking\Support\PropertyBookingPermission;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Presents private POB receipts with explicit owner and property-scope checks. */
final class ReceiptController extends Controller
{
    /** Render the responsive thermal browser receipt. */
    public function show(
        Request $request,
        Booking $booking,
        BookingReceiptDataFactory $documents,
        ReceiptPrinterManager $printers,
        ResolvesInstitutionProfile $profiles,
    ): Response {
        $this->authorizeReceipt($request, $booking);
        $receipt = $documents->fromBooking($booking);
        $instruction = $printers->instruction(
            $booking->register,
            $receipt,
            $request->query('print') === 'checkout',
        );
        $response = response()->view('property-booking::pob.receipts.show', [
            'receipt' => $receipt,
            'institutionProfile' => $profiles->current(),
            'printInstruction' => $instruction,
            'receiptPdfUrl' => route('property-booking.pob.receipts.pdf', $booking),
        ]);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }

    /** Stream the institutional PDF receipt and record the render. */
    public function pdf(
        Request $request,
        Booking $booking,
        BookingReceiptService $receipts,
        RecordsSystemActivity $activities,
    ): Response {
        $user = $this->authorizeReceipt($request, $booking);
        $response = $receipts->stream($booking, $user->display_name);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $activities->record(
            activityType: 'property-booking.pob-receipt.rendered',
            description: "POB receipt {$booking->booking_number} rendered",
            actor: $user,
            subject: $booking,
            properties: ['format' => 'pdf'],
            severity: SystemActivitySeverity::Info,
            source: 'property-booking-pob',
        );

        return $response;
    }

    /** Return the authenticated operator after channel, payment, scope, and ownership checks. */
    private function authorizeReceipt(Request $request, Booking $booking): User
    {
        abort_unless(
            $booking->channel === BookingChannel::PointOfBooking
            && $booking->payment_status === BookingPaymentStatus::Paid,
            404,
        );
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $access = app(PropertyAccessService::class);
        abort_unless($access->canAccess($user, $booking->property_id), 403);
        $ownsBooking = $booking->receptionist_id === $user->getKey()
            && $user->can(PropertyBookingPermission::ACCESS_POB);
        $canReview = $user->can(PropertyBookingPermission::VIEW_BOOKINGS)
            || $user->can(PropertyBookingPermission::MANAGE_SHIFTS)
            || $user->can(PropertyBookingPermission::MANAGE_PAYMENTS);
        abort_unless($ownsBooking || $canReview, 403);

        return $user;
    }
}
