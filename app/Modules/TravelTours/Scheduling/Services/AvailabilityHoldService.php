<?php

/**
 * Implements a focused TravelTours domain or application service.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Scheduling\Services;

use App\Modules\TravelTours\Bookings\Exceptions\AvailabilityException;
use App\Modules\TravelTours\Bookings\Exceptions\DuplicateOperationConflict;
use App\Modules\TravelTours\Scheduling\Data\AvailabilityHoldRequest;
use App\Modules\TravelTours\Scheduling\Enums\DepartureStatus;
use App\Modules\TravelTours\Scheduling\Enums\HoldStatus;
use App\Modules\TravelTours\Scheduling\Exceptions\HoldOwnershipMismatch;
use App\Modules\TravelTours\Scheduling\Models\AvailabilityHold;
use App\Modules\TravelTours\Scheduling\Models\TourDeparture;
use Illuminate\Support\Facades\DB;

/** Creates and expires capacity holds while serializing writes per departure. */
final readonly class AvailabilityHoldService
{
    /** Initialize the AvailabilityHoldService with its required dependencies or immutable state. */
    public function __construct(private DepartureAvailabilityService $availability) {}

    /** Create one retry-safe, owner-bound hold while serializing departure capacity. */
    public function create(AvailabilityHoldRequest $request): AvailabilityHold
    {
        return DB::transaction(function () use ($request): AvailabilityHold {
            $existing = AvailabilityHold::query()->where('operation_key', $request->operationKey)->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->departure_id !== $request->quote->departureId
                    || ! hash_equals($existing->quote_fingerprint, $request->quote->fingerprint)) {
                    throw new DuplicateOperationConflict('The hold operation key was already used with different quote data.');
                }

                $this->assertOwnedBy($existing, $request->ownerToken, $request->customerId);

                return $existing;
            }

            $departure = TourDeparture::query()->lockForUpdate()->findOrFail($request->quote->departureId);
            if (! in_array($departure->status, [DepartureStatus::Open, DepartureStatus::Guaranteed], true)) {
                throw new AvailabilityException('This departure is not open for booking.');
            }
            $availability = $this->availability->check($departure, $request->quote->participants->seats());
            if (! $availability->canAccommodate) {
                throw new AvailabilityException('The requested seats are no longer available.');
            }

            $hold = new AvailabilityHold;
            $hold->forceFill([
                'departure_id' => $departure->id,
                'customer_id' => $request->customerId,
                'owner_token_hash' => $request->ownerToken !== null ? $this->ownerHash($request->ownerToken) : null,
                'email_hash' => $request->email !== null ? $this->emailHash($request->email) : null,
                'operation_key' => $request->operationKey,
                'adult_count' => $request->quote->participants->adults,
                'child_count' => $request->quote->participants->children,
                'infant_count' => $request->quote->participants->infants,
                'seat_count' => $request->quote->participants->seats(),
                'currency' => $request->quote->currency,
                'quoted_total_minor' => $request->quote->totalMinor,
                'quote_fingerprint' => $request->quote->fingerprint,
                'quote_snapshot' => $request->quote->snapshot(),
                'status' => HoldStatus::Active,
                'expires_at' => now()->addMinutes((int) config('travel-tours.booking.hold_minutes', 15)),
            ]);
            $hold->save();

            return $hold;
        }, 3);
    }

    /** Release one active hold after proving the caller's ownership. */
    public function release(
        AvailabilityHold $hold,
        ?string $ownerToken = null,
        ?int $customerId = null,
        string $reason = 'customer_released',
    ): AvailabilityHold {
        return DB::transaction(function () use ($hold, $ownerToken, $customerId, $reason): AvailabilityHold {
            $locked = AvailabilityHold::query()->lockForUpdate()->findOrFail($hold->getKey());
            $this->assertOwnedBy($locked, $ownerToken, $customerId);

            if ($locked->status === HoldStatus::Active) {
                $locked->forceFill([
                    'status' => HoldStatus::Released,
                    'released_at' => now(),
                    'release_reason' => $reason,
                ])->save();
            }

            return $locked;
        }, 3);
    }

    /** Expire stale active holds without deleting their capacity audit evidence. */
    public function releaseExpired(): int
    {
        return AvailabilityHold::query()->where('status', HoldStatus::Active->value)->where('expires_at', '<=', now())
            ->update([
                'status' => HoldStatus::Expired->value,
                'released_at' => now(),
                'release_reason' => 'hold_expired',
                'updated_at' => now(),
            ]);
    }

    /** Assert authenticated-customer or anonymous-token ownership. */
    public function assertOwnedBy(AvailabilityHold $hold, ?string $ownerToken, ?int $customerId): void
    {
        $customerOwns = $customerId !== null && $hold->customer_id !== null && $hold->customer_id === $customerId;
        $tokenOwns = $ownerToken !== null && $hold->owner_token_hash !== null
            && hash_equals($hold->owner_token_hash, $this->ownerHash($ownerToken));

        if (! $customerOwns && ! $tokenOwns) {
            throw new HoldOwnershipMismatch('The availability hold does not belong to this booking session.');
        }
    }

    /** Derive a purpose-specific anonymous owner hash. */
    private function ownerHash(string $token): string
    {
        return hash_hmac('sha256', 'travel-hold-owner:v1:'.$token, (string) config('app.key'));
    }

    /** Derive a purpose-specific email lookup hash. */
    private function emailHash(string $email): string
    {
        return hash_hmac('sha256', 'travel-hold-email:v1:'.mb_strtolower(trim($email)), (string) config('app.key'));
    }
}
