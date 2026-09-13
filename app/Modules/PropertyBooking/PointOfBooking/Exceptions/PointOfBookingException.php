<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\PointOfBooking\Exceptions;

use App\Modules\PropertyBooking\Exceptions\PropertyBookingException;

/** Expected terminal, hold, settlement, or receipt workflow rejection. */
final class PointOfBookingException extends PropertyBookingException {}
