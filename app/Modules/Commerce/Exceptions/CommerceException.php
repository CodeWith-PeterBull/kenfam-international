<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Exceptions;

use DomainException;

/**
 * Base type for expected, user-safe Commerce domain failures.
 */
class CommerceException extends DomainException {}
