<?php

/**
 * Represents an expected TravelTours domain failure.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Bookings\Exceptions;

use App\Modules\TravelTours\Exceptions\TravelToursException;

/** Departure is closed or lacks requested capacity. */
final class AvailabilityException extends TravelToursException {}
