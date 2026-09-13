<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Pricing\Services;

use App\Contracts\RecordsSystemActivity;
use App\Models\User;
use App\Modules\PropertyBooking\Pricing\Exceptions\RateConfigurationException;
use App\Modules\PropertyBooking\Pricing\Models\RateOverride;
use App\Modules\PropertyBooking\Pricing\Models\RatePlan;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Arr;

/** Owns non-overlapping local-date rate-override writes. */
final readonly class RateOverrideService
{
    /** Create the rate override service with its required dependencies. */
    public function __construct(private DatabaseManager $database, private RecordsSystemActivity $activities) {}

    /** @param array<string, mixed> $attributes */
    public function create(RatePlan $ratePlan, array $attributes, User $actor): RateOverride
    {
        return $this->database->transaction(function () use ($ratePlan, $attributes, $actor): RateOverride {
            $ratePlan = RatePlan::query()->lockForUpdate()->findOrFail($ratePlan->getKey());
            $override = new RateOverride;
            $override->fill($this->payload($attributes));
            $override->forceFill(['rate_plan_id' => $ratePlan->getKey(), 'created_by' => $actor->getKey(), 'updated_by' => $actor->getKey()]);
            $this->assertValid($override);
            $override->save();

            $this->activities->record(
                activityType: 'property-booking.rate-override.created',
                description: "Rate override created for {$ratePlan->name}",
                actor: $actor,
                subject: $override,
                properties: ['rate_plan_ulid' => $ratePlan->ulid, 'starts_on' => $override->starts_on->toDateString(), 'ends_on' => $override->ends_on->toDateString()],
                source: 'property-booking-pricing',
            );

            return $override->refresh();
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(RateOverride $override, array $attributes, User $actor): RateOverride
    {
        return $this->database->transaction(function () use ($override, $attributes, $actor): RateOverride {
            $override = RateOverride::query()->lockForUpdate()->findOrFail($override->getKey());
            $override->fill($this->payload($attributes));
            $override->forceFill(['updated_by' => $actor->getKey()]);
            $this->assertValid($override);
            $override->save();

            $this->activities->record(
                activityType: 'property-booking.rate-override.updated',
                description: 'Booking rate override updated',
                actor: $actor,
                subject: $override,
                properties: ['rate_plan_ulid' => $override->ratePlan->ulid],
                source: 'property-booking-pricing',
            );

            return $override->refresh();
        });
    }

    /** Remove an override whose historical pricing is already preserved in stay snapshots. */
    public function delete(RateOverride $override, User $actor): void
    {
        $this->database->transaction(function () use ($override, $actor): void {
            $override = RateOverride::query()->lockForUpdate()->findOrFail($override->getKey());
            $this->activities->record(
                activityType: 'property-booking.rate-override.deleted',
                description: 'Booking rate override deleted',
                actor: $actor,
                subject: $override,
                properties: [
                    'rate_plan_ulid' => $override->ratePlan->ulid,
                    'starts_on' => $override->starts_on->toDateString(),
                    'ends_on' => $override->ends_on->toDateString(),
                ],
                source: 'property-booking-pricing',
            );
            $override->delete();
        });
    }

    /** @param array<string, mixed> $attributes */
    private function payload(array $attributes): array
    {
        $payload = Arr::only($attributes, [
            'starts_on', 'ends_on', 'rate_minor', 'is_closed', 'closed_on_arrival',
            'closed_on_departure', 'minimum_units', 'maximum_units', 'reason',
        ]);

        if (array_key_exists('reason', $payload)) {
            $reason = trim((string) $payload['reason']);
            $payload['reason'] = $reason === '' ? null : $reason;
        }

        return $payload;
    }

    /** Validate the normalized domain input. */
    private function assertValid(RateOverride $override): void
    {
        if ($override->starts_on === null || $override->ends_on === null || $override->ends_on->lessThanOrEqualTo($override->starts_on)) {
            throw new RateConfigurationException('A rate override must end after it starts.');
        }
        if ($override->rate_minor !== null && $override->rate_minor < 1) {
            throw new RateConfigurationException('An override rate must be greater than zero when supplied.');
        }
        if ($override->minimum_units !== null && $override->minimum_units < 1) {
            throw new RateConfigurationException('Override minimum units must be at least one.');
        }
        if ($override->maximum_units !== null && $override->maximum_units < ($override->minimum_units ?? 1)) {
            throw new RateConfigurationException('Override maximum units cannot be lower than its minimum.');
        }

        $overlaps = RateOverride::query()
            ->where('rate_plan_id', $override->rate_plan_id)
            ->when($override->exists, fn ($query) => $query->whereKeyNot($override->getKey()))
            ->overlappingDates($override->starts_on->toDateString(), $override->ends_on->toDateString())
            ->lockForUpdate()
            ->exists();
        if ($overlaps) {
            throw new RateConfigurationException('Rate overrides for the same plan may not overlap.');
        }
    }
}
