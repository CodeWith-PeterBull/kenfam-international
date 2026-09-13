<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Exceptions;

use App\Modules\PropertyBooking\Exceptions\PropertyBookingException;

/** Raised when an operational booking transition violates a domain invariant. */
final class BookingOperationException extends PropertyBookingException {}
