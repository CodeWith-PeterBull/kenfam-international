<?php

/** Explains which catalog essentials still block tour publication. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Services;

use App\Modules\TravelTours\Bookings\Enums\ParticipantType;
use App\Modules\TravelTours\Catalog\Models\Tour;

/** Provide one shared readiness result for staff UI and publication writes. */
final class TourReadinessService
{
    /** @return list<string> */
    public function reasons(Tour $tour): array
    {
        $reasons = [];
        if (blank($tour->name) || blank($tour->short_description) || blank($tour->description)) {
            $reasons[] = 'Add a name, catalog summary, and full tour description.';
        }
        if (blank($tour->meta_title) || blank($tour->meta_description)) {
            $reasons[] = 'Add an SEO title and description.';
        }
        if (blank($tour->terms) || blank($tour->cancellation_summary) || blank($tour->policy_version)) {
            $reasons[] = 'Add versioned booking terms and a cancellation summary.';
        }
        if (! $tour->categories()->active()->exists()
            || ! $tour->categories()->wherePivot('is_primary', true)->active()->exists()) {
            $reasons[] = 'Assign an active primary category.';
        }
        if (! $tour->destinations()->published()->exists()
            || $tour->destinations()->count() !== $tour->destinations()->published()->count()) {
            $reasons[] = 'Assign at least one active published destination; every route stop must remain public.';
        }
        $days = $tour->itineraryDays()->pluck('day_number')->map(static fn ($number): int => (int) $number)->all();
        if ($days === [] || $days !== range(1, count($days)) || count($days) > $tour->duration_days) {
            $reasons[] = 'Add an ordered itinerary that starts on day one and fits the tour duration.';
        }
        if (! $tour->getFirstMedia('tour_cover')) {
            $reasons[] = 'Upload a tour cover image.';
        }
        $plan = $tour->ratePlans()->where('is_default', true)->publiclyAvailable()->first();
        if (! $plan || ! $plan->participantRates()->where('participant_type', ParticipantType::Adult->value)
            ->where('is_active', true)->whereNull('active_from')->whereNull('active_until')->where('amount_minor', '>', 0)->exists()) {
            $reasons[] = 'Set a positive adult base fare on the active public default rate plan.';
        }

        return $reasons;
    }
}
