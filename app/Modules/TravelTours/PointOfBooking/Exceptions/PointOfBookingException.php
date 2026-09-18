<?php

/**
 * Signals a refused booking-desk operation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Exceptions;

use App\Modules\TravelTours\Exceptions\TravelToursException;

/** Present a stable, operator-readable reason a desk action was refused. */
final class PointOfBookingException extends TravelToursException {}
