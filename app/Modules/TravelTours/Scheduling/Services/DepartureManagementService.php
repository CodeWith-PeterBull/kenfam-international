<?php

/** Owns dated departure writes and operational state transitions. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Scheduling\Services;

use App\Models\User;
use App\Modules\TravelTours\Bookings\Enums\ParticipantType;
use App\Modules\TravelTours\Catalog\Enums\PublicationStatus;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Pricing\Models\TourRatePlan;
use App\Modules\TravelTours\Scheduling\Data\DepartureData;
use App\Modules\TravelTours\Scheduling\Enums\DepartureStatus;
use App\Modules\TravelTours\Scheduling\Exceptions\DepartureException;
use App\Modules\TravelTours\Scheduling\Models\DepartureStaffAssignment;
use App\Modules\TravelTours\Scheduling\Models\TourDeparture;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\DatabaseManager;

/** Enforce schedule, capacity and publication invariants in one transaction boundary. */
final readonly class DepartureManagementService
{
    /** Inject database and availability dependencies. */
    public function __construct(private DatabaseManager $database, private DepartureAvailabilityService $availability) {}

    /** Create a draft for an existing tour. */
    public function create(DepartureData $data, User $actor): TourDeparture
    {
        return $this->database->transaction(fn (): TourDeparture => $this->persist(null, $data, $actor));
    }

    /** Update a schedule while preserving its tour, status and financial commitments. */
    public function update(TourDeparture $departure, DepartureData $data, User $actor): TourDeparture
    {
        return $this->database->transaction(function () use ($departure, $data, $actor): TourDeparture {
            $locked = TourDeparture::query()->lockForUpdate()->findOrFail($departure->id);

            return $this->persist($locked, $data, $actor);
        });
    }

    /** Move through a declared state edge; do not use form input to set status. */
    public function transition(TourDeparture $departure, DepartureStatus $target, User $actor): TourDeparture
    {
        return $this->database->transaction(function () use ($departure, $target, $actor): TourDeparture {
            $departure = TourDeparture::query()->with('tour')->lockForUpdate()->findOrFail($departure->id);
            $edges = [
                'draft' => ['open', 'cancelled'],
                'open' => ['guaranteed', 'closed', 'cancelled'],
                'guaranteed' => ['closed', 'departed', 'cancelled'],
                'closed' => ['open', 'cancelled'],
                'departed' => ['completed'],
            ];
            if (! in_array($target->value, $edges[$departure->status->value] ?? [], true)) {
                throw new DepartureException('This departure cannot move to the requested status.');
            }
            if ($target === DepartureStatus::Open) {
                $this->assertCanOpen($departure);
            }
            if ($target === DepartureStatus::Cancelled && $departure->bookings()->exists()) {
                throw new DepartureException('Bookings exist. Cancel them through the booking lifecycle before cancelling the departure.');
            }
            if ($target === DepartureStatus::Departed && $departure->starts_at->isFuture()) {
                throw new DepartureException('A departure cannot be marked departed before its start time.');
            }
            $departure->status = $target;
            $departure->updated_by = $actor->id;
            $departure->save();

            return $departure->refresh();
        });
    }

    /** Assign an active operator to a departure, updating an existing matching role. */
    public function assignStaff(TourDeparture $departure, User $staff, string $role, bool $lead, ?string $notes, User $actor): DepartureStaffAssignment
    {
        $role = trim($role);
        if (! $staff->is_active || ! in_array($role, ['guide', 'coordinator', 'driver', 'host'], true) || mb_strlen((string) $notes) > 2000) {
            throw new DepartureException('Select an active team member, a supported role, and a brief assignment note.');
        }

        return $this->database->transaction(function () use ($departure, $staff, $role, $lead, $notes, $actor): DepartureStaffAssignment {
            TourDeparture::query()->lockForUpdate()->findOrFail($departure->id);
            if ($lead) {
                DepartureStaffAssignment::query()->where('departure_id', $departure->id)->update(['is_lead' => false]);
            }
            $assignment = DepartureStaffAssignment::query()->firstOrNew([
                'departure_id' => $departure->id, 'user_id' => $staff->id, 'role' => $role,
            ]);
            $assignment->is_lead = $lead;
            $assignment->notes = $notes;
            $assignment->assigned_by = $actor->id;
            $assignment->save();

            return $assignment->refresh();
        });
    }

    /** Remove an assignment without changing existing booking or departure records. */
    public function removeStaff(TourDeparture $departure, int $assignmentId): void
    {
        $this->database->transaction(function () use ($departure, $assignmentId): void {
            TourDeparture::query()->lockForUpdate()->findOrFail($departure->id);
            DepartureStaffAssignment::query()->where('departure_id', $departure->id)->findOrFail($assignmentId)->delete();
        });
    }

    /** Validate and persist the operator-owned schedule fields. */
    private function persist(?TourDeparture $departure, DepartureData $data, User $actor): TourDeparture
    {
        $tour = Tour::query()->findOrFail($data->tourId);
        if ($departure && $departure->tour_id !== $tour->id) {
            throw new DepartureException('A departure cannot be transferred to another tour.');
        }
        $code = strtoupper(trim($data->code));
        if (! preg_match('/^[A-Z0-9][A-Z0-9-]{2,79}$/', $code)
            || TourDeparture::query()->where('code', $code)->when($departure, fn ($query) => $query->whereKeyNot($departure->id))->exists()) {
            throw new DepartureException('Enter a unique departure code using letters, numbers and hyphens.');
        }
        if ($data->capacity < 1 || $data->capacity > 100000 || $data->minimumParticipants < 1 || $data->minimumParticipants > $data->capacity) {
            throw new DepartureException('Capacity must be positive and at least the minimum participant count.');
        }
        $start = $this->utc($data->localStart, $data->timezone);
        $end = $this->utc($data->localEnd, $data->timezone);
        $open = $data->localBookingOpen ? $this->utc($data->localBookingOpen, $data->timezone) : null;
        $close = $data->localBookingClose ? $this->utc($data->localBookingClose, $data->timezone) : null;
        if ($end <= $start || ($open && $open >= $start) || ($close && $close >= $start) || ($open && $close && $open >= $close)) {
            throw new DepartureException('Departure and booking windows must be ordered and bookings must close before travel starts.');
        }
        if ($data->ratePlanId !== null && ! TourRatePlan::query()->whereKey($data->ratePlanId)->where('tour_id', $tour->id)->exists()) {
            throw new DepartureException('Select a rate plan belonging to this tour.');
        }
        if ($departure) {
            $committed = $this->availability->check($departure);
            if ($data->capacity < $committed->bookedSeats + $committed->heldSeats) {
                throw new DepartureException('Capacity cannot be lower than booked and currently held seats.');
            }
            if (($committed->bookedSeats + $committed->heldSeats) > 0 && (
                $departure->starts_at->toDateTimeString() !== $start->format('Y-m-d H:i:s')
                || $departure->ends_at->toDateTimeString() !== $end->format('Y-m-d H:i:s')
                || $departure->timezone !== $data->timezone
                || $departure->rate_plan_id !== $data->ratePlanId
            )) {
                throw new DepartureException('Release active holds and amend bookings before changing committed travel dates or pricing.');
            }
        }
        $departure ??= new TourDeparture;
        $departure->tour_id = $tour->id;
        $departure->code = $code;
        $departure->timezone = $data->timezone;
        $departure->starts_at = $start;
        $departure->ends_at = $end;
        $departure->booking_opens_at = $open;
        $departure->booking_closes_at = $close;
        $departure->capacity = $data->capacity;
        $departure->minimum_participants = $data->minimumParticipants;
        $departure->rate_plan_id = $data->ratePlanId;
        $departure->booking_mode = $data->bookingMode;
        $departure->meeting_instructions = $data->meetingInstructions;
        $departure->operational_notes = $data->operationalNotes;
        $departure->created_by ??= $actor->id;
        $departure->updated_by = $actor->id;
        $departure->save();

        if (in_array($departure->status, [DepartureStatus::Open, DepartureStatus::Guaranteed], true)) {
            $departure->setRelation('tour', $tour);
            $this->assertCanOpen($departure);
        }

        return $departure->refresh();
    }

    /** Require a published, priced, future tour before accepting reservations. */
    private function assertCanOpen(TourDeparture $departure): void
    {
        if ($departure->starts_at->isPast() || ($departure->booking_closes_at && $departure->booking_closes_at->isPast())
            || $departure->tour->status !== PublicationStatus::Published
            || ($departure->tour->published_at && $departure->tour->published_at->isFuture())) {
            throw new DepartureException('Publish the tour and choose a future, bookable departure window before opening sales.');
        }
        $plan = $departure->ratePlan ?: $departure->tour->ratePlans()->where('is_default', true)->first();
        if (! $plan || ! $plan->is_active || ! $plan->is_public
            || ! $plan->participantRates()->where('participant_type', ParticipantType::Adult->value)->where('is_active', true)->where('amount_minor', '>', 0)->exists()) {
            throw new DepartureException('An active public rate plan with an adult fare is required before opening sales.');
        }
    }

    /** Resolve an unambiguous local wall time to its UTC instant. */
    private function utc(string $local, string $timezone): DateTimeImmutable
    {
        if (! in_array($timezone, DateTimeZone::listIdentifiers(), true) || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $local)) {
            throw new DepartureException('Enter a valid local date and an IANA timezone.');
        }
        $wall = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $local, new DateTimeZone('UTC'));
        if (! $wall || $wall->format('Y-m-d\TH:i') !== $local) {
            throw new DepartureException('Enter a valid local date and time.');
        }
        $zone = new DateTimeZone($timezone);
        $offsets = array_unique(array_column($zone->getTransitions($wall->getTimestamp() - 172800, $wall->getTimestamp() + 172800), 'offset'));
        $matches = [];
        foreach ($offsets as $offset) {
            $candidate = $wall->setTimestamp($wall->getTimestamp() - $offset);
            if ($candidate->setTimezone($zone)->format('Y-m-d\TH:i') === $local) {
                $matches[$candidate->getTimestamp()] = $candidate;
            }
        }
        if (count($matches) !== 1) {
            throw new DepartureException('This local time is skipped or repeated by a timezone change. Choose another time.');
        }

        return reset($matches);
    }
}
