<?php

/**
 * Owns promotion code definitions and their tour scope.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Pricing\Services;

use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Pricing\Data\PromotionData;
use App\Modules\TravelTours\Pricing\Enums\AdjustmentType;
use App\Modules\TravelTours\Pricing\Exceptions\InvalidRateConfiguration;
use App\Modules\TravelTours\Pricing\Models\Promotion;
use Illuminate\Database\DatabaseManager;

/**
 * Codes are unique regardless of case, fixed discounts carry the currency they
 * apply to, and a promotion either applies to every tour (with optional
 * exclusions) or to an explicit list of tours. Redeemed promotions are never
 * deleted; they are deactivated.
 */
final readonly class PromotionService
{
    /** Create the service with its transaction boundary. */
    public function __construct(private DatabaseManager $database) {}

    /** Create a promotion and its tour assignments. */
    public function create(PromotionData $data): Promotion
    {
        return $this->database->transaction(function () use ($data): Promotion {
            $promotion = new Promotion;
            $promotion->forceFill($this->payload($data));
            $this->assertCodeUnique($promotion);
            $promotion->save();
            $this->syncTours($promotion, $data);

            return $promotion->refresh()->load('tours');
        });
    }

    /** Update a promotion and replace its tour assignments. */
    public function update(Promotion $promotion, PromotionData $data): Promotion
    {
        return $this->database->transaction(function () use ($promotion, $data): Promotion {
            $promotion = Promotion::query()->lockForUpdate()->findOrFail($promotion->getKey());
            $promotion->forceFill($this->payload($data));
            $this->assertCodeUnique($promotion);
            $promotion->save();
            $this->syncTours($promotion, $data);

            return $promotion->refresh()->load('tours');
        });
    }

    /** Switch a promotion on or off without touching its definition. */
    public function setActive(Promotion $promotion, bool $active): Promotion
    {
        return $this->database->transaction(function () use ($promotion, $active): Promotion {
            $promotion = Promotion::query()->lockForUpdate()->findOrFail($promotion->getKey());
            if ($promotion->is_active !== $active) {
                $promotion->forceFill(['is_active' => $active])->save();
            }

            return $promotion;
        });
    }

    /** Normalize and validate promotion input. */
    private function payload(PromotionData $data): array
    {
        $code = strtoupper(trim($data->code));
        $name = trim($data->name);
        $currency = $data->currency !== null ? strtoupper(trim($data->currency)) : null;
        if (preg_match('/^[A-Z0-9][A-Z0-9-]{1,79}$/', $code) !== 1) {
            throw new InvalidRateConfiguration('Promotion codes use letters, numbers, and hyphens (2 to 80 characters).');
        }
        if ($name === '' || mb_strlen($name) > 160) {
            throw new InvalidRateConfiguration('A promotion needs a name of up to 160 characters.');
        }
        if ($data->adjustmentType === AdjustmentType::Override) {
            throw new InvalidRateConfiguration('Promotions reduce a price; use a percentage or fixed amount.');
        }
        if ($data->adjustmentValue <= 0 || ($data->adjustmentType === AdjustmentType::Percentage && $data->adjustmentValue > 10000)) {
            throw new InvalidRateConfiguration('A promotion discount must be positive; percentages are basis points up to 10,000.');
        }
        if ($data->adjustmentType === AdjustmentType::Fixed && ($currency === null || preg_match('/^[A-Z]{3}$/', $currency) !== 1)) {
            throw new InvalidRateConfiguration('A fixed discount needs the ISO currency it applies to.');
        }
        if ($data->validFrom !== null && $data->validUntil !== null && $data->validUntil->lt($data->validFrom)) {
            throw new InvalidRateConfiguration('The validity window must end after it starts.');
        }
        if ($data->minimumBookingMinor < 0 || $data->minimumParticipants < 1
            || ($data->maximumUses !== null && $data->maximumUses < 1) || ($data->maximumUsesPerCustomer !== null && $data->maximumUsesPerCustomer < 1)) {
            throw new InvalidRateConfiguration('Minimums cannot be negative and usage limits must be at least one when set.');
        }
        if (! $data->appliesToAllTours && $data->includedTourIds === []) {
            throw new InvalidRateConfiguration('Choose the tours the promotion applies to, or apply it to all tours.');
        }

        return [
            'code' => $code,
            'name' => $name,
            'description' => filled($data->description) ? trim((string) $data->description) : null,
            'adjustment_type' => $data->adjustmentType,
            'adjustment_value' => $data->adjustmentValue,
            'currency' => $data->adjustmentType === AdjustmentType::Fixed ? $currency : null,
            'valid_from' => $data->validFrom,
            'valid_until' => $data->validUntil,
            'minimum_booking_minor' => $data->minimumBookingMinor,
            'minimum_participants' => $data->minimumParticipants,
            'maximum_uses' => $data->maximumUses,
            'maximum_uses_per_customer' => $data->maximumUsesPerCustomer,
            'applies_to_all_tours' => $data->appliesToAllTours,
            'is_active' => $data->isActive,
        ];
    }

    /** Codes are unique regardless of case. */
    private function assertCodeUnique(Promotion $promotion): void
    {
        $exists = Promotion::query()
            ->whereRaw('UPPER(code) = ?', [$promotion->code])
            ->when($promotion->exists, fn ($query) => $query->whereKeyNot($promotion->getKey()))
            ->exists();
        if ($exists) {
            throw new InvalidRateConfiguration('Another promotion already uses this code.');
        }
    }

    /** Replace the include/exclude tour assignments, verifying every tour exists. */
    private function syncTours(Promotion $promotion, PromotionData $data): void
    {
        $included = $data->appliesToAllTours ? [] : array_values(array_unique($data->includedTourIds));
        $excluded = $data->appliesToAllTours ? array_values(array_unique($data->excludedTourIds)) : [];
        $ids = array_merge($included, $excluded);
        if ($ids !== [] && Tour::query()->whereIn('id', $ids)->count() !== count(array_unique($ids))) {
            throw new InvalidRateConfiguration('One of the selected tours does not exist.');
        }

        $sync = [];
        foreach ($included as $tourId) {
            $sync[$tourId] = ['is_exclusion' => false];
        }
        foreach ($excluded as $tourId) {
            $sync[$tourId] = ['is_exclusion' => true];
        }
        $promotion->tours()->sync($sync);
    }
}
