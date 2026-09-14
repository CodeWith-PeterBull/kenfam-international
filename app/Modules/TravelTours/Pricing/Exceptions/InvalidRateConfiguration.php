<?php

/** Domain failure raised when active pricing records are missing or ambiguous. */
declare(strict_types=1);

namespace App\Modules\TravelTours\Pricing\Exceptions;

use App\Modules\TravelTours\Exceptions\TravelToursException;

/** Stop quoting rather than selecting an arbitrary overlapping rate. */
final class InvalidRateConfiguration extends TravelToursException {}
