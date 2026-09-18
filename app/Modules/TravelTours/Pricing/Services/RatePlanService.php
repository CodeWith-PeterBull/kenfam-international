<?php

/**
 * Owns rate plan policy and participant fare writes for a tour.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Pricing\Services;

use App\Modules\TravelTours\Bookings\Enums\ParticipantType;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Pricing\Data\ParticipantRateData;
use App\Modules\TravelTours\Pricing\Data\RatePlanData;
use App\Modules\TravelTours\Pricing\Exceptions\InvalidRateConfiguration;
use App\Modules\TravelTours\Pricing\Models\ParticipantRate;
use App\Modules\TravelTours\Pricing\Models\TourRatePlan;
use App\Modules\TravelTours\Scheduling\Models\TourDeparture;
use Illuminate\Database\DatabaseManager;

/**
 * A plan's currency is fixed once departures or bookings use it, one plan per
 * tour is the default, and participant fares of one type may never overlap in
 * time, because the calculator refuses to quote when more than one applies.
 */
final readonly class RatePlanService
{
    /** Create the service with its transaction boundary. */
    public function __construct(private DatabaseManager $database) {}

    /**
     * Save a plan and its fares as one unit: a refused fare set leaves no plan behind.
     *
     * @param  list<ParticipantRateData>  $rates
     */
    public function save(Tour $tour, ?TourRatePlan $plan, RatePlanData $data, array $rates): TourRatePlan
    {
        return $this->database->transaction(function () use ($tour, $plan, $data, $rates): TourRatePlan {
            $plan = $plan instanceof TourRatePlan ? $this->update($plan, $data) : $this->create($tour, $data);

            return $this->replaceRates($plan, $rates);
        });
    }

    /** Create a plan for a tour. */
    public function create(Tour $tour, RatePlanData $data): TourRatePlan
    {
        return $this->database->transaction(function () use ($tour, $data): TourRatePlan {
            Tour::query()->lockForUpdate()->findOrFail($tour->getKey());
            $plan = new TourRatePlan;
            $plan->forceFill(['tour_id' => $tour->getKey()] + $this->payload($data));
            $this->assertCodeUnique($plan);
            $this->assertParticipantBounds($data);
            $plan->save();
            $this->settleDefault($plan);

            return $plan->refresh();
        });
    }

    /** Update a plan while protecting the currency of plans already in use. */
    public function update(TourRatePlan $plan, RatePlanData $data): TourRatePlan
    {
        return $this->database->transaction(function () use ($plan, $data): TourRatePlan {
            $plan = TourRatePlan::query()->lockForUpdate()->findOrFail($plan->getKey());
            $payload = $this->payload($data);
            if ($plan->currency !== $payload['currency'] && $this->inUse($plan)) {
                throw new InvalidRateConfiguration('The currency of a rate plan already used by departures or bookings cannot be changed.');
            }
            if ($plan->is_active && ! $data->isActive) {
                $this->assertCanDeactivate($plan);
            }
            $plan->forceFill($payload);
            $this->assertCodeUnique($plan);
            $this->assertParticipantBounds($data);
            $plan->save();
            $this->settleDefault($plan);

            return $plan->refresh();
        });
    }

    /**
     * Replace every participant fare on a plan atomically.
     *
     * Fares are validated as a set: active fares of one participant type may
     * not overlap in time (open ends overlap everything on that side).
     *
     * @param  list<ParticipantRateData>  $rates
     */
    public function replaceRates(TourRatePlan $plan, array $rates): TourRatePlan
    {
        return $this->database->transaction(function () use ($plan, $rates): TourRatePlan {
            $plan = TourRatePlan::query()->lockForUpdate()->findOrFail($plan->getKey());
            $this->assertRates($rates);
            $plan->participantRates()->lockForUpdate()->get()->each->delete();
            foreach ($rates as $rate) {
                $row = new ParticipantRate;
                $row->forceFill([
                    'rate_plan_id' => $plan->getKey(),
                    'participant_type' => $rate->type,
                    'minimum_age' => $rate->minimumAge,
                    'maximum_age' => $rate->maximumAge,
                    'amount_minor' => $rate->amountMinor,
                    'tax_inclusive' => (bool) $plan->tax_inclusive,
                    'active_from' => $rate->activeFrom?->toDateString(),
                    'active_until' => $rate->activeUntil?->toDateString(),
                    'is_active' => $rate->isActive,
                ])->save();
            }

            return $plan->refresh()->load('participantRates');
        });
    }

    /** Normalize plan input into persistence values. */
    private function payload(RatePlanData $data): array
    {
        $code = strtoupper(trim($data->code));
        $name = trim($data->name);
        $currency = strtoupper(trim($data->currency));
        if (preg_match('/^[A-Z0-9][A-Z0-9-]{0,59}$/', $code) !== 1) {
            throw new InvalidRateConfiguration('Plan codes use uppercase letters, numbers, and hyphens (up to 60 characters).');
        }
        if ($name === '' || mb_strlen($name) > 160) {
            throw new InvalidRateConfiguration('A plan needs a name of up to 160 characters.');
        }
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidRateConfiguration('Currency must be an ISO 4217 alpha-3 code.');
        }
        if ($data->taxRateBasisPoints < 0 || $data->taxRateBasisPoints > 10000) {
            throw new InvalidRateConfiguration('Tax rate must be between 0 and 10,000 basis points.');
        }
        if (! in_array($data->depositType, ['percentage', 'fixed'], true)) {
            throw new InvalidRateConfiguration('Deposit type must be percentage or fixed.');
        }
        if ($data->depositValue < 0 || ($data->depositType === 'percentage' && $data->depositValue > 10000)) {
            throw new InvalidRateConfiguration('A percentage deposit is expressed in basis points up to 10,000; a fixed deposit in minor units.');
        }
        if ($data->balanceDueDays < 0 || $data->balanceDueDays > 730) {
            throw new InvalidRateConfiguration('Balance due days must be between 0 and 730.');
        }

        return [
            'code' => $code,
            'name' => $name,
            'description' => filled($data->description) ? trim((string) $data->description) : null,
            'currency' => $currency,
            'tax_inclusive' => $data->taxInclusive,
            'tax_rate_basis_points' => $data->taxRateBasisPoints,
            'deposit_type' => $data->depositType,
            'deposit_value' => $data->depositValue,
            'balance_due_days' => $data->balanceDueDays,
            'is_refundable' => $data->isRefundable,
            'is_active' => $data->isActive,
            'is_public' => $data->isPublic,
            'is_default' => $data->isDefault,
            'minimum_participants' => $data->minimumParticipants,
            'maximum_participants' => $data->maximumParticipants,
            'display_order' => max(0, $data->displayOrder),
        ];
    }

    /** Reject impossible participant bounds. */
    private function assertParticipantBounds(RatePlanData $data): void
    {
        if ($data->minimumParticipants < 1 || ($data->maximumParticipants !== null && $data->maximumParticipants < $data->minimumParticipants)) {
            throw new InvalidRateConfiguration('Minimum participants must be at least one and not exceed the maximum.');
        }
    }

    /** Plan codes are unique within a tour. */
    private function assertCodeUnique(TourRatePlan $plan): void
    {
        $exists = TourRatePlan::query()
            ->where('tour_id', $plan->tour_id)
            ->where('code', $plan->code)
            ->when($plan->exists, fn ($query) => $query->whereKeyNot($plan->getKey()))
            ->exists();
        if ($exists) {
            throw new InvalidRateConfiguration('Another plan on this tour already uses this code.');
        }
    }

    /** Keep exactly one default plan per tour when this plan claims the role. */
    private function settleDefault(TourRatePlan $plan): void
    {
        if (! $plan->is_default) {
            return;
        }
        TourRatePlan::query()
            ->where('tour_id', $plan->tour_id)
            ->whereKeyNot($plan->getKey())
            ->where('is_default', true)
            ->update(['is_default' => false, 'updated_at' => now()]);
    }

    /** A plan referenced by upcoming bookable departures must stay active. */
    private function assertCanDeactivate(TourRatePlan $plan): void
    {
        if ($plan->is_default) {
            throw new InvalidRateConfiguration('Make another plan the default before deactivating this one.');
        }
        if (TourDeparture::query()->where('rate_plan_id', $plan->getKey())->bookable()->exists()) {
            throw new InvalidRateConfiguration('Upcoming departures still sell under this plan; reassign them before deactivating it.');
        }
    }

    /** Whether departures or bookings already depend on the plan. */
    private function inUse(TourRatePlan $plan): bool
    {
        return $plan->departures()->exists() || $plan->bookings()->exists();
    }

    /**
     * Validate a fare set: sane amounts and ages, ordered windows, no overlap per type.
     *
     * @param  list<ParticipantRateData>  $rates
     */
    private function assertRates(array $rates): void
    {
        $byType = [];
        foreach ($rates as $rate) {
            if ($rate->amountMinor < 0 || $rate->amountMinor > 999_999_999_999) {
                throw new InvalidRateConfiguration('Fares must be non-negative amounts.');
            }
            if ($rate->minimumAge !== null && $rate->maximumAge !== null && $rate->maximumAge < $rate->minimumAge) {
                throw new InvalidRateConfiguration('A fare age band must end at or after it starts.');
            }
            if ($rate->activeFrom !== null && $rate->activeUntil !== null && $rate->activeUntil->lt($rate->activeFrom)) {
                throw new InvalidRateConfiguration('A fare date window must end on or after it starts.');
            }
            if ($rate->isActive) {
                $byType[$rate->type->value][] = $rate;
            }
        }
        foreach ($byType as $type => $set) {
            for ($i = 0; $i < count($set); $i++) {
                for ($j = $i + 1; $j < count($set); $j++) {
                    if ($this->overlaps($set[$i], $set[$j])) {
                        throw new InvalidRateConfiguration(ucfirst($type).' fares overlap in time; give each a distinct date window or deactivate one.');
                    }
                }
            }
        }
        if (! isset($byType[ParticipantType::Adult->value])) {
            throw new InvalidRateConfiguration('A plan needs at least one active adult fare.');
        }
    }

    /** Whether two fare windows share at least one day (open ends extend without limit). */
    private function overlaps(ParticipantRateData $a, ParticipantRateData $b): bool
    {
        $aStart = $a->activeFrom?->toDateString() ?? '0000-01-01';
        $aEnd = $a->activeUntil?->toDateString() ?? '9999-12-31';
        $bStart = $b->activeFrom?->toDateString() ?? '0000-01-01';
        $bEnd = $b->activeUntil?->toDateString() ?? '9999-12-31';

        return $aStart <= $bEnd && $bStart <= $aEnd;
    }
}
