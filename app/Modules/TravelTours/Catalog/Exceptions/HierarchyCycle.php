<?php

/**
 * Reports a category or destination parent assignment that creates a cycle.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Exceptions;

/** Reject recursive catalog hierarchies with a stable exception type. */
final class HierarchyCycle extends CatalogException {}
