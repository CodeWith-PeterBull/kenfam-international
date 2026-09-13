<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Storefront\Exceptions;

use App\Modules\PropertyBooking\Exceptions\PropertyBookingException;

/** Recoverable public discovery, selection, or checkout failure. */
final class StorefrontException extends PropertyBookingException {}
