<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Exceptions;

/**
 * Reports a tender that cannot be safely applied to an order balance.
 */
final class PaymentMismatchException extends CommerceException {}
