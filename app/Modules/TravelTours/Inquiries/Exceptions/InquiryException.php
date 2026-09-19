<?php

/**
 * Signals a refused inquiry operation with an operator-facing message.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Inquiries\Exceptions;

use App\Modules\TravelTours\Exceptions\TravelToursException;

/** Raised when an inquiry follow-up action breaks a lifecycle rule. */
final class InquiryException extends TravelToursException {}
