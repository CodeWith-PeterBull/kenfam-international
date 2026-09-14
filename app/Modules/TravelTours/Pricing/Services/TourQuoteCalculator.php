<?php

/**
 * Implements a focused TravelTours domain or application service.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Pricing\Services;

use App\Modules\TravelTours\Bookings\Enums\ParticipantType;
use App\Modules\TravelTours\Bookings\Exceptions\AvailabilityException;
use App\Modules\TravelTours\Contracts\CalculatesTourQuotes;
use App\Modules\TravelTours\Pricing\Data\TourQuote;
use App\Modules\TravelTours\Pricing\Data\TourQuoteLine;
use App\Modules\TravelTours\Pricing\Data\TourQuoteRequest;
use App\Modules\TravelTours\Pricing\Enums\AdjustmentType;
use App\Modules\TravelTours\Pricing\Exceptions\InvalidRateConfiguration;
use App\Modules\TravelTours\Pricing\Exceptions\PromotionNotApplicable;
use App\Modules\TravelTours\Pricing\Models\ParticipantRate;
use App\Modules\TravelTours\Pricing\Models\PricingRule;
use App\Modules\TravelTours\Pricing\Models\Promotion;
use App\Modules\TravelTours\Pricing\Models\TourRatePlan;
use App\Modules\TravelTours\Scheduling\Enums\DepartureStatus;
use App\Modules\TravelTours\Scheduling\Models\TourDeparture;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Deterministic integer-money engine for fares, rules, promotions, tax, and deposits. */
final class TourQuoteCalculator implements CalculatesTourQuotes
{
    /** Calculate an authoritative deterministic quote from persisted pricing records. */
    public function calculate(TourQuoteRequest $request): TourQuote
    {
        $quotedAt = $request->quotedAt ?? CarbonImmutable::now('UTC');
        $departure = TourDeparture::query()->findOrFail($request->departureId);
        $this->assertDepartureIsQuoteable($departure, $quotedAt);

        $ratePlan = TourRatePlan::query()
            ->whereKey($request->ratePlanId)
            ->where('tour_id', $departure->tour_id)
            ->where('is_active', true)
            ->firstOrFail();
        $participantCount = $request->participants->participants();
        if ($participantCount < $ratePlan->minimum_participants
            || ($ratePlan->maximum_participants !== null && $participantCount > $ratePlan->maximum_participants)) {
            throw new InvalidRateConfiguration('The selected rate plan does not support this participant count.');
        }

        $rates = $this->participantRates($ratePlan, $quotedAt);
        $lines = [];
        $baseMinor = 0;

        foreach ($request->participants->byType() as $type => $quantity) {
            if ($quantity === 0) {
                continue;
            }
            $matchingRates = $rates->filter(
                static fn (ParticipantRate $rate): bool => $rate->participant_type === ParticipantType::from($type),
            );
            if ($matchingRates->count() !== 1) {
                throw new InvalidRateConfiguration("Exactly one active {$type} rate must be configured for the quote date.");
            }
            $rate = $matchingRates->first();
            $total = (int) $rate->amount_minor * $quantity;
            $baseMinor += $total;
            $lines[] = new TourQuoteLine('fare', ucfirst($type).' fare', $quantity, (int) $rate->amount_minor, $total, ['participant_type' => $type]);
        }

        $adjustmentMinor = 0;
        foreach ($this->applicableRules($ratePlan, $departure, $request, $quotedAt) as $rule) {
            $value = $this->ruleAmount($rule, $baseMinor);
            if ($rule->adjustment_type === AdjustmentType::Override) {
                $value = (int) $rule->adjustment_value - ($baseMinor + $adjustmentMinor);
            }
            $adjustmentMinor += $value;
            $lines[] = new TourQuoteLine('pricing_rule', $rule->name, 1, $value, $value, ['pricing_rule_id' => $rule->id, 'rule_type' => $rule->rule_type->value]);
            if (! $rule->is_stackable) {
                break;
            }
        }

        $preDiscount = max($baseMinor + $adjustmentMinor, 0);
        $promotion = $this->promotion(
            $request->promotionCode,
            $departure->tour_id,
            $request->participants->participants(),
            $preDiscount,
            $ratePlan->currency,
            $request->customerId,
            $quotedAt,
        );
        $discountMinor = $promotion ? min($this->promotionAmount($promotion, $preDiscount), $preDiscount) : 0;
        if ($promotion) {
            $lines[] = new TourQuoteLine('promotion', $promotion->name, 1, -$discountMinor, -$discountMinor, ['promotion_id' => $promotion->id]);
        }

        $taxable = max($preDiscount - $discountMinor, 0);
        $taxMinor = $ratePlan->tax_inclusive
            ? ($ratePlan->tax_rate_basis_points > 0
                ? $this->divideRounded($taxable * (int) $ratePlan->tax_rate_basis_points, 10000 + (int) $ratePlan->tax_rate_basis_points)
                : 0)
            : $this->divideRounded($taxable * (int) $ratePlan->tax_rate_basis_points, 10000);
        $totalMinor = $ratePlan->tax_inclusive ? $taxable : $taxable + $taxMinor;
        $depositMinor = $ratePlan->deposit_type === 'fixed'
            ? min((int) $ratePlan->deposit_value, $totalMinor)
            : min($this->divideRounded($totalMinor * (int) $ratePlan->deposit_value, 10000), $totalMinor);

        $fingerprintData = [$departure->id, $ratePlan->id, $request->participants->byType(), $promotion?->id, $lines, $totalMinor, $depositMinor];
        $fingerprint = hash_hmac('sha256', json_encode($fingerprintData, JSON_THROW_ON_ERROR), (string) config('app.key'));

        return new TourQuote($departure->id, $ratePlan->id, $request->participants, $ratePlan->currency, $lines, $preDiscount, $discountMinor, $taxMinor, $totalMinor, $depositMinor, $promotion?->id, $fingerprint);
    }

    /** @return Collection<int, ParticipantRate> */
    private function participantRates(TourRatePlan $plan, CarbonImmutable $at): Collection
    {
        return $plan->participantRates()->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('active_from')->orWhere('active_from', '<=', $at->toDateString()))
            ->where(fn ($query) => $query->whereNull('active_until')->orWhere('active_until', '>=', $at->toDateString()))
            ->get();
    }

    /** @return Collection<int, PricingRule> */
    private function applicableRules(TourRatePlan $plan, TourDeparture $departure, TourQuoteRequest $request, CarbonImmutable $at): Collection
    {
        return $plan->pricingRules()->active()
            ->where(fn ($query) => $query->whereNull('departure_id')->orWhere('departure_id', $departure->id))
            ->where(fn ($query) => $query->whereNull('sales_start_at')->orWhere('sales_start_at', '<=', $at))
            ->where(fn ($query) => $query->whereNull('sales_end_at')->orWhere('sales_end_at', '>=', $at))
            ->where(fn ($query) => $query->whereNull('travel_starts_on')->orWhere('travel_starts_on', '<=', $departure->starts_at->toDateString()))
            ->where(fn ($query) => $query->whereNull('travel_ends_on')->orWhere('travel_ends_on', '>=', $departure->starts_at->toDateString()))
            ->where(fn ($query) => $query->whereNull('minimum_participants')->orWhere('minimum_participants', '<=', $request->participants->participants()))
            ->where(fn ($query) => $query->whereNull('minimum_advance_days')->orWhere('minimum_advance_days', '<=', $at->diffInDays($departure->starts_at, false)))
            ->get();
    }

    /** Resolve one pricing-rule adjustment using deterministic integer arithmetic. */
    private function ruleAmount(PricingRule $rule, int $baseMinor): int
    {
        return $rule->adjustment_type === AdjustmentType::Percentage
            ? $this->divideRounded($baseMinor * (int) $rule->adjustment_value, 10000)
            : (int) $rule->adjustment_value;
    }

    /** Resolve and validate the requested promotion for the current quote. */
    private function promotion(
        ?string $code,
        int $tourId,
        int $participants,
        int $subtotal,
        string $currency,
        ?int $customerId,
        CarbonImmutable $at,
    ): ?Promotion {
        if (blank($code)) {
            return null;
        }

        $promotion = Promotion::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('valid_from')->orWhere('valid_from', '<=', $at))
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhere('valid_until', '>=', $at))
            ->whereRaw('UPPER(code) = ?', [mb_strtoupper(trim($code))])
            ->where('minimum_booking_minor', '<=', $subtotal)
            ->where('minimum_participants', '<=', $participants)
            ->whereDoesntHave('tours', fn ($tour) => $tour->where('travel_tours.id', $tourId)->where('travel_promotion_tour.is_exclusion', true))
            ->where(function ($query) use ($tourId): void {
                $query->where('applies_to_all_tours', true)
                    ->orWhereHas('tours', fn ($tour) => $tour->where('travel_tours.id', $tourId)->where('travel_promotion_tour.is_exclusion', false));
            })
            ->first();

        if (! $promotion instanceof Promotion) {
            throw new PromotionNotApplicable('The supplied promotion is not applicable to this quote.');
        }
        if ($promotion->adjustment_type === AdjustmentType::Fixed
            && ($promotion->currency === null || $promotion->currency !== $currency)) {
            throw new PromotionNotApplicable('The supplied fixed promotion does not match the quote currency.');
        }
        if ($promotion->maximum_uses !== null
            && $promotion->redemptions()->whereNull('released_at')->count() >= $promotion->maximum_uses) {
            throw new PromotionNotApplicable('The supplied promotion has reached its usage limit.');
        }
        if ($customerId !== null && $promotion->maximum_uses_per_customer !== null
            && $promotion->redemptions()->whereNull('released_at')->where('customer_id', $customerId)->count() >= $promotion->maximum_uses_per_customer) {
            throw new PromotionNotApplicable('The supplied promotion has reached its customer usage limit.');
        }

        return $promotion;
    }

    /** Calculate the bounded promotion adjustment in integer minor units. */
    private function promotionAmount(Promotion $promotion, int $subtotal): int
    {
        return $promotion->adjustment_type === AdjustmentType::Percentage
            ? $this->divideRounded($subtotal * (int) $promotion->adjustment_value, 10000)
            : (int) $promotion->adjustment_value;
    }

    /** Enforce departure state and booking-window constraints at quote time. */
    private function assertDepartureIsQuoteable(TourDeparture $departure, CarbonImmutable $at): void
    {
        if (! in_array($departure->status, [DepartureStatus::Open, DepartureStatus::Guaranteed], true)) {
            throw new AvailabilityException('This departure is not open for quoting.');
        }
        if ($departure->booking_opens_at !== null && $departure->booking_opens_at->isAfter($at)) {
            throw new AvailabilityException('Booking has not opened for this departure.');
        }
        if ($departure->booking_closes_at !== null && $departure->booking_closes_at->isBefore($at)) {
            throw new AvailabilityException('Booking has closed for this departure.');
        }
    }

    /** Divide signed integer values using deterministic half-up rounding. */
    private function divideRounded(int $numerator, int $denominator): int
    {
        if ($denominator <= 0) {
            throw new InvalidRateConfiguration('A pricing divisor must be positive.');
        }

        $sign = $numerator < 0 ? -1 : 1;

        return $sign * intdiv(abs($numerator) + intdiv($denominator, 2), $denominator);
    }
}
