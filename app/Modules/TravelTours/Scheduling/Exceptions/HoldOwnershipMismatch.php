<?php

/** Domain failure raised when a caller cannot prove ownership of a hold. */
declare(strict_types=1);

namespace App\Modules\TravelTours\Scheduling\Exceptions;

use App\Modules\TravelTours\Exceptions\TravelToursException;

/** Prevent anonymous or authenticated callers from consuming another owner's hold. */
final class HoldOwnershipMismatch extends TravelToursException {}
