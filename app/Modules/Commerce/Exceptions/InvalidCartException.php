<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Exceptions;

/**
 * Reports invalid cart input or a product that can no longer be sold.
 */
final class InvalidCartException extends CommerceException {}
