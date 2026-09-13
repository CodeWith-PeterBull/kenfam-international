<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Guests\Exceptions;

use App\Modules\PropertyBooking\Exceptions\PropertyBookingException;

/** Raised when guest contact or protected identity input is invalid. */
final class GuestException extends PropertyBookingException {}
