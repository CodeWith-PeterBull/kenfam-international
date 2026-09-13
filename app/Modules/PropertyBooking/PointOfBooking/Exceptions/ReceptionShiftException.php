<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\PointOfBooking\Exceptions;

use App\Modules\PropertyBooking\Exceptions\PropertyBookingException;

/** Expected rejection raised by reception-shift lifecycle operations. */
final class ReceptionShiftException extends PropertyBookingException {}
