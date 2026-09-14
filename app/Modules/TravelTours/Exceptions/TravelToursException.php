<?php

/**
 * Represents an expected TravelTours domain failure.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Exceptions;

use RuntimeException;

/** Expected domain failure safe for presentation after logging. */
class TravelToursException extends RuntimeException {}
