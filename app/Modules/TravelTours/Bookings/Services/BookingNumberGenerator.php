<?php

/**
 * Implements a focused TravelTours domain or application service.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Bookings\Services;

use App\Modules\TravelTours\Bookings\Enums\BookingChannel;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use Illuminate\Support\Str;

/** Generates readable collision-resistant booking references. */
final class BookingNumberGenerator
{
    /** Generate a channel-aware human reference with a monotonic ULID suffix. */
    public function next(BookingChannel $channel): string
    {
        $configuredPrefix = match ($channel) {
            BookingChannel::Web => config('travel-tours.numbering.web_prefix', 'WEB-TOUR'),
            BookingChannel::BookingDesk => config('travel-tours.numbering.pob_prefix', 'POB-TOUR'),
            BookingChannel::Admin, BookingChannel::Agent => config('travel-tours.numbering.admin_prefix', 'ADM-TOUR'),
        };
        $prefix = trim((string) preg_replace('/[^A-Z0-9-]/', '', mb_strtoupper((string) $configuredPrefix)), '-');
        $prefix = $prefix !== '' ? $prefix : 'TRV';

        do {
            $suffix = substr((string) Str::ulid(), -10);
            $number = sprintf('%s-%s-%s', $prefix, now()->format('Ymd'), $suffix);
        } while (TourBooking::query()->where('booking_number', $number)->exists());

        return $number;
    }
}
