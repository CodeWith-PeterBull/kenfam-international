<?php

/** Domain failure raised when a supplied promotion cannot be applied. */
declare(strict_types=1);

namespace App\Modules\TravelTours\Pricing\Exceptions;

use App\Modules\TravelTours\Exceptions\TravelToursException;

/** Provide a stable invalid-promotion failure without exposing internal rules. */
final class PromotionNotApplicable extends TravelToursException {}
