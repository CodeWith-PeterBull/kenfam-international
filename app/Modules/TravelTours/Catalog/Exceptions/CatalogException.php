<?php

/**
 * Defines a user-safe TravelTours catalog-domain failure.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Exceptions;

use App\Modules\TravelTours\Exceptions\TravelToursException;

/** Base exception for expected catalog validation and lifecycle failures. */
class CatalogException extends TravelToursException {}
