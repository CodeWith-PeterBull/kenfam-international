<?php

/** Expected, operator-readable scheduling failure. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Scheduling\Exceptions;

use App\Modules\TravelTours\Exceptions\TravelToursException;

/** Distinguish scheduling constraints from unexpected infrastructure failures. */
final class DepartureException extends TravelToursException {}
