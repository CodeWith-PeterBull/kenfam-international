<?php

/** Maintains the simple default fare while preserving advanced pricing policy. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Pricing\Services;

use App\Modules\TravelTours\Bookings\Enums\ParticipantType;
use App\Modules\TravelTours\Catalog\Exceptions\CatalogException;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Pricing\Data\TourBasePriceData;
use App\Modules\TravelTours\Pricing\Models\ParticipantRate;
use App\Modules\TravelTours\Pricing\Models\TourRatePlan;
use Illuminate\Database\DatabaseManager;

/** Own the default-plan and unbounded participant-rate transaction. */
final readonly class TourBasePriceService
{
    /** Inject the database boundary for one coherent fare update. */
    public function __construct(private DatabaseManager $database) {}

    /** Save fares without modifying tax, deposit, departure, or promotion rules. */
    public function save(Tour $tour, TourBasePriceData $data): TourRatePlan
    {
        if (trim($data->name) === '' || mb_strlen($data->name) > 160
            || preg_match('/^[A-Z]{3}$/', strtoupper($data->currency)) !== 1
            || $data->adultMinor <= 0 || $data->adultMinor > 999_999_999_999
            || collect([$data->childMinor, $data->infantMinor])->contains(fn (?int $amount): bool => $amount !== null && ($amount < 0 || $amount > 999_999_999_999))) {
            throw new CatalogException('Provide a name, ISO currency and valid non-negative participant prices; the adult fare must be positive.');
        }

        return $this->database->transaction(function () use ($tour, $data): TourRatePlan {
            $tour = Tour::query()->lockForUpdate()->findOrFail($tour->id);
            $plan = $tour->ratePlans()->where('is_default', true)->lockForUpdate()->first();
            if (! $plan) {
                $plan = $tour->ratePlans()->where('code', 'STANDARD')->lockForUpdate()->first() ?? new TourRatePlan;
                $plan->tour_id = $tour->id;
                $plan->code = 'STANDARD';
                $plan->deposit_type = 'percentage';
                $plan->deposit_value = 0;
                $plan->tax_inclusive = (bool) config('travel-tours.defaults.prices_include_tax', true);
                $plan->tax_rate_basis_points = (int) config('travel-tours.defaults.tax_rate_bps', 0);
            }
            if ($plan->exists && $plan->currency !== strtoupper($data->currency) && ($plan->departures()->exists() || $plan->bookings()->exists())) {
                throw new CatalogException('The currency of a rate plan already used by departures or bookings cannot be changed.');
            }
            $plan->name = trim($data->name);
            $plan->currency = strtoupper($data->currency);
            $plan->is_active = true;
            $plan->is_public = true;
            $plan->is_default = true;
            $plan->save();

            foreach ([
                ParticipantType::Adult->value => $data->adultMinor,
                ParticipantType::Child->value => $data->childMinor,
                ParticipantType::Infant->value => $data->infantMinor,
            ] as $type => $amount) {
                $rates = $plan->participantRates()->where('participant_type', $type)->lockForUpdate()->get();
                $base = $rates->first(fn (ParticipantRate $rate): bool => $rate->active_from === null && $rate->active_until === null);
                if ($rates->count() !== ($base ? 1 : 0)) {
                    throw new CatalogException('This plan has date-bound or duplicate '.$type.' fares; manage them in the advanced pricing phase.');
                }
                if ($amount === null) {
                    if ($base) {
                        $base->is_active = false;
                        $base->save();
                    }

                    continue;
                }
                $base ??= new ParticipantRate;
                $base->rate_plan_id = $plan->id;
                $base->participant_type = $type;
                $base->amount_minor = $amount;
                $base->is_active = true;
                $base->save();
            }

            return $plan->refresh()->load('participantRates');
        });
    }
}
