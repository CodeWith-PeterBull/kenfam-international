<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Catalog\Exceptions;

use App\Modules\PropertyBooking\Exceptions\PropertyBookingException;

/** Raised when accommodation catalog input violates a domain invariant. */
final class CatalogException extends PropertyBookingException {}
