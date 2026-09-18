<?php

/**
 * Signals a refused booking lifecycle transition.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Bookings\Exceptions;

use App\Modules\TravelTours\Exceptions\TravelToursException;

/** Present a stable, operator-readable reason a booking cannot change state. */
final class BookingLifecycleException extends TravelToursException {}
