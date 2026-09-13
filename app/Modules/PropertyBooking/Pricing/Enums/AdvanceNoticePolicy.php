<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Pricing\Enums;

/** Controls whether a pricing operation applies public advance-booking windows. */
enum AdvanceNoticePolicy: string
{
    case Enforce = 'enforce';
    case WaiveForOnSiteBooking = 'waive_for_on_site_booking';

    /** Determine whether property and rate lead-time restrictions must apply. */
    public function isEnforced(): bool
    {
        return $this === self::Enforce;
    }
}
