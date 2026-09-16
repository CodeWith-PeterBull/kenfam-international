<?php

/**
 * Reports a catalog record that does not satisfy public readiness rules.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Exceptions;

/** Prevent publication until all required public content is coherent. */
final class PublicationBlocked extends CatalogException
{
    /** @param list<string> $reasons */
    public function __construct(
        public readonly array $reasons,
    ) {
        parent::__construct(implode(' ', $reasons));
    }
}
