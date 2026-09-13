<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Pricing\Exceptions;

use App\Modules\PropertyBooking\Exceptions\PropertyBookingException;

/** Raised when rate policy or requested stay pricing is invalid. */
final class RateConfigurationException extends PropertyBookingException {}
