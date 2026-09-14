<?php

/** Domain failure raised when one idempotency key carries different input. */
declare(strict_types=1);

namespace App\Modules\TravelTours\Bookings\Exceptions;

use App\Modules\TravelTours\Exceptions\TravelToursException;

/** Reject retries whose business identity no longer matches the original write. */
final class DuplicateOperationConflict extends TravelToursException {}
