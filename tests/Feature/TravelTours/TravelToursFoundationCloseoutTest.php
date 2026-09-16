<?php

/**
 * Verifies the deliberately narrow K1 runtime closeout boundary.
 */

declare(strict_types=1);

namespace Tests\Feature\TravelTours;

use App\Modules\TravelTours\Contracts\PrintsBookingReceipts;
use Tests\TestCase;

/** Keep deferred operational capabilities out of the enabled K1 container. */
final class TravelToursFoundationCloseoutTest extends TestCase
{
    /** K1 must not advertise a printer implementation that only writes a log. */
    public function test_receipt_printing_contract_is_deferred_until_a_real_driver_exists(): void
    {
        $this->assertFalse(app()->bound(PrintsBookingReceipts::class));
    }
}
