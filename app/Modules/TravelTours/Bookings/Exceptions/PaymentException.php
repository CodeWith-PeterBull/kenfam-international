<?php

/** Domain failure raised for invalid payment currency, amount, scope, or state. */
declare(strict_types=1);

namespace App\Modules\TravelTours\Bookings\Exceptions;

use App\Modules\TravelTours\Exceptions\TravelToursException;

/** Present a stable service-layer payment failure without exposing provider data. */
final class PaymentException extends TravelToursException {}
