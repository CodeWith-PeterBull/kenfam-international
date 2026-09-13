<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Services;

use App\Contracts\RecordsSystemActivity;
use App\Enums\SystemActivitySeverity;
use App\Models\User;
use App\Modules\PropertyBooking\Bookings\Data\AdministrationPaymentData;
use App\Modules\PropertyBooking\Bookings\Enums\BookingPaymentMethod;
use App\Modules\PropertyBooking\Bookings\Enums\BookingPaymentRecordStatus;
use App\Modules\PropertyBooking\Bookings\Enums\BookingPaymentStatus;
use App\Modules\PropertyBooking\Bookings\Events\BookingPaymentConfirmed;
use App\Modules\PropertyBooking\Bookings\Exceptions\BookingOperationException;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Bookings\Models\BookingPayment;
use App\Modules\PropertyBooking\Support\PropertyAccessService;
use Illuminate\Database\DatabaseManager;

/** Records shift-independent administration payments without accepting browser totals. */
final readonly class BookingAdministrationPaymentService
{
    /** Create the payment service with transaction, scope, and audit dependencies. */
    public function __construct(
        private DatabaseManager $database,
        private PropertyAccessService $propertyAccess,
        private RecordsSystemActivity $activities,
    ) {}

    /** Record one completed non-cash payment against an eligible booking. */
    public function recordCompleted(
        Booking $booking,
        AdministrationPaymentData $data,
        User $actor,
    ): BookingPayment {
        return $this->database->transaction(function () use ($booking, $data, $actor): BookingPayment {
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->getKey());
            $this->propertyAccess->authorize($actor, $booking->property_id);
            if ($booking->status->isTerminal()) {
                throw new BookingOperationException('Payments cannot be added to a terminal booking.');
            }
            if ($data->method === BookingPaymentMethod::Cash) {
                throw new BookingOperationException('Cash payments must be recorded through an owned open reception shift.');
            }

            $balance = max((int) $booking->total_minor - (int) $booking->paid_minor, 0);
            if ($data->amountMinor <= 0 || $data->amountMinor > $balance) {
                throw new BookingOperationException('The payment must be greater than zero and cannot exceed the booking balance.');
            }
            $reference = $this->reference($data->reference);
            if (BookingPayment::query()
                ->where('method', $data->method->value)
                ->where('reference', $reference)
                ->where('status', BookingPaymentRecordStatus::Completed->value)
                ->lockForUpdate()
                ->exists()) {
                throw new BookingOperationException('That completed payment reference has already been recorded.');
            }

            $payment = new BookingPayment;
            $payment->forceFill([
                'booking_id' => $booking->getKey(),
                'reception_shift_id' => null,
                'recorded_by' => $actor->getKey(),
                'method' => $data->method,
                'status' => BookingPaymentRecordStatus::Completed,
                'currency' => $booking->currency,
                'amount_minor' => $data->amountMinor,
                'tendered_minor' => null,
                'change_minor' => 0,
                'refunded_amount_minor' => 0,
                'reference' => $reference,
                'metadata' => ['source' => 'booking-administration'],
                'paid_at' => now(),
                'failed_at' => null,
                'refunded_at' => null,
            ])->save();

            $paid = (int) $booking->payments()
                ->where('status', BookingPaymentRecordStatus::Completed->value)
                ->lockForUpdate()
                ->sum('amount_minor');
            $booking->forceFill([
                'paid_minor' => $paid,
                'payment_status' => $paid >= $booking->total_minor
                    ? BookingPaymentStatus::Paid
                    : BookingPaymentStatus::Partial,
                'updated_by' => $actor->getKey(),
            ])->save();

            $this->activities->record(
                activityType: 'property-booking.payment.completed',
                description: "Administration payment recorded for booking {$booking->booking_number}",
                actor: $actor,
                subject: $payment,
                properties: [
                    'booking_ulid' => $booking->ulid,
                    'property_id' => $booking->property_id,
                    'method' => $data->method->value,
                    'amount_minor' => $data->amountMinor,
                ],
                severity: SystemActivitySeverity::Notice,
                source: 'property-booking-operations',
            );
            BookingPaymentConfirmed::dispatch($booking->ulid, $payment->ulid, $booking->property_id);

            return $payment->refresh();
        });
    }

    /** Normalize the required non-secret provider or manual reference. */
    private function reference(string $reference): string
    {
        $reference = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', trim($reference));
        $reference = is_string($reference) ? trim($reference) : '';
        if ($reference === '' || strlen($reference) > 160) {
            throw new BookingOperationException('Administration payments require a reference of at most 160 characters.');
        }

        return $reference;
    }
}
