<?php

/** Domain failure raised when supplied contact identifiers resolve ambiguously. */
declare(strict_types=1);

namespace App\Modules\TravelTours\Customers\Exceptions;

use App\Modules\TravelTours\Exceptions\TravelToursException;

/** Prevent unsafe merging or overwriting of independently owned customer profiles. */
final class CustomerIdentityConflict extends TravelToursException {}
