<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Availability\Exceptions;

use App\Modules\PropertyBooking\Exceptions\PropertyBookingException;

/** Raised when a unit cannot be blocked, allocated, moved, or released safely. */
final class AvailabilityException extends PropertyBookingException {}
