<?php

/** Owns ordered itinerary days and activities within one tour. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Services;

use App\Modules\TravelTours\Catalog\Data\ItineraryActivityData;
use App\Modules\TravelTours\Catalog\Data\ItineraryDayData;
use App\Modules\TravelTours\Catalog\Enums\PublicationStatus;
use App\Modules\TravelTours\Catalog\Exceptions\CatalogException;
use App\Modules\TravelTours\Catalog\Models\Destination;
use App\Modules\TravelTours\Catalog\Models\ItineraryActivity;
use App\Modules\TravelTours\Catalog\Models\ItineraryDay;
use App\Modules\TravelTours\Catalog\Models\Tour;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Validator;

/** Keep child ownership, consecutive order and validation in transactions. */
final readonly class TourItineraryService
{
    /** Inject the module's database transaction boundary. */
    public function __construct(private DatabaseManager $database) {}

    /** Append a day or update one belonging to the given tour. */
    public function saveDay(Tour $tour, ItineraryDayData $data, ?int $dayId = null): ItineraryDay
    {
        return $this->database->transaction(function () use ($tour, $data, $dayId): ItineraryDay {
            $tour = $this->lockedTour($tour);
            $values = [
                'title' => trim($data->title), 'description' => $this->optional($data->description),
                'meals' => array_values(array_unique(array_map(trim(...), $data->meals))),
                'accommodation' => $this->optional($data->accommodation),
                'start_destination_id' => $data->startDestinationId,
                'end_destination_id' => $data->endDestinationId,
            ];
            Validator::make($values, [
                'title' => ['required', 'string', 'max:200'],
                'description' => ['nullable', 'string', 'max:50000'],
                'meals' => ['array', 'max:6'], 'meals.*' => ['required', 'string', 'max:60'],
                'accommodation' => ['nullable', 'string', 'max:240'],
                'start_destination_id' => ['nullable', 'integer', 'min:1'],
                'end_destination_id' => ['nullable', 'integer', 'min:1'],
            ])->validate();
            $this->assertActiveDestinations([$data->startDestinationId, $data->endDestinationId]);

            if ($dayId === null) {
                $number = (int) ItineraryDay::query()->where('tour_id', $tour->id)->max('day_number') + 1;
                if ($number > $tour->duration_days) {
                    throw new CatalogException('The itinerary cannot exceed the tour duration.');
                }
                $day = new ItineraryDay;
                $day->forceFill(['tour_id' => $tour->id, 'day_number' => $number, 'sort_order' => $number]);
            } else {
                $day = $this->ownedDay($tour, $dayId);
            }
            $day->forceFill($values)->save();

            return $day->refresh();
        });
    }

    /** Remove a day and close the remaining one-based day-number gap. */
    public function removeDay(Tour $tour, int $dayId): void
    {
        $this->database->transaction(function () use ($tour, $dayId): void {
            $tour = $this->lockedTour($tour);
            $day = $this->ownedDay($tour, $dayId);
            if ($tour->status === PublicationStatus::Published && $tour->itineraryDays()->count() <= 1) {
                throw new CatalogException('A published tour must retain an itinerary day.');
            }
            $day->delete();
            foreach ($tour->itineraryDays()->get() as $index => $remaining) {
                $remaining->forceFill(['day_number' => $index + 1, 'sort_order' => $index + 1])->save();
            }
        });
    }

    /** Move a day one position without exposing sequence numbers to the client. */
    public function moveDay(Tour $tour, int $dayId, string $direction): void
    {
        $this->database->transaction(function () use ($tour, $dayId, $direction): void {
            $tour = $this->lockedTour($tour);
            $day = $this->ownedDay($tour, $dayId);
            $other = $this->adjacentDay($tour, $day, $direction);
            if ($other === null) {
                return;
            }
            $number = $day->day_number;
            $otherNumber = $other->day_number;
            $day->forceFill(['day_number' => 0, 'sort_order' => 0])->save();
            $other->forceFill(['day_number' => $number, 'sort_order' => $number])->save();
            $day->forceFill(['day_number' => $otherNumber, 'sort_order' => $otherNumber])->save();
        });
    }

    /** Append an activity or update one scoped through its parent day. */
    public function saveActivity(Tour $tour, int $dayId, ItineraryActivityData $data, ?int $activityId = null): ItineraryActivity
    {
        return $this->database->transaction(function () use ($tour, $dayId, $data, $activityId): ItineraryActivity {
            $tour = $this->lockedTour($tour);
            $day = $this->ownedDay($tour, $dayId);
            $values = [
                'title' => trim($data->title), 'description' => $this->optional($data->description),
                'starts_at_local' => $data->startsAtLocal, 'ends_at_local' => $data->endsAtLocal,
                'location_name' => $this->optional($data->locationName),
                'destination_id' => $data->destinationId,
                'latitude' => $data->latitude, 'longitude' => $data->longitude,
                'is_included' => $data->isIncluded, 'is_optional' => $data->isOptional,
            ];
            Validator::make($values, [
                'title' => ['required', 'string', 'max:200'],
                'description' => ['nullable', 'string', 'max:10000'],
                'starts_at_local' => ['nullable', 'date_format:H:i'],
                'ends_at_local' => ['nullable', 'date_format:H:i'],
                'location_name' => ['nullable', 'string', 'max:200'],
                'destination_id' => ['nullable', 'integer', 'min:1'],
                'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
                'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
                'is_included' => ['boolean'], 'is_optional' => ['boolean'],
            ])->validate();
            $this->assertActiveDestinations([$data->destinationId]);
            if ($data->startsAtLocal !== null && $data->endsAtLocal !== null && $data->endsAtLocal < $data->startsAtLocal) {
                throw new CatalogException('Activity end time must not precede its start time.');
            }
            if ($activityId === null) {
                $sequence = (int) $day->activities()->max('sequence') + 1;
                if ($sequence > 100) {
                    throw new CatalogException('A day may contain no more than 100 activities.');
                }
                $activity = new ItineraryActivity;
                $activity->forceFill(['itinerary_day_id' => $day->id, 'sequence' => $sequence]);
            } else {
                $activity = $this->ownedActivity($day, $activityId);
            }
            $activity->forceFill($values)->save();

            return $activity->refresh();
        });
    }

    /** Remove an activity and resequence only its owning day. */
    public function removeActivity(Tour $tour, int $dayId, int $activityId): void
    {
        $this->database->transaction(function () use ($tour, $dayId, $activityId): void {
            $day = $this->ownedDay($this->lockedTour($tour), $dayId);
            $this->ownedActivity($day, $activityId)->delete();
            foreach ($day->activities()->get() as $index => $activity) {
                $activity->forceFill(['sequence' => $index + 1])->save();
            }
        });
    }

    /** Move an activity one position within its owning day. */
    public function moveActivity(Tour $tour, int $dayId, int $activityId, string $direction): void
    {
        $this->database->transaction(function () use ($tour, $dayId, $activityId, $direction): void {
            $day = $this->ownedDay($this->lockedTour($tour), $dayId);
            $activity = $this->ownedActivity($day, $activityId);
            $step = $this->step($direction);
            $other = $day->activities()->where('sequence', $activity->sequence + $step)->first();
            if ($other === null) {
                return;
            }
            $otherSequence = $other->sequence;
            $other->forceFill(['sequence' => 0])->save();
            $activity->forceFill(['sequence' => $otherSequence])->save();
            $other->forceFill(['sequence' => $otherSequence - $step])->save();
        });
    }

    /** Lock the tour to serialize mutations to its ordered children. */
    private function lockedTour(Tour $tour): Tour
    {
        return Tour::query()->lockForUpdate()->findOrFail($tour->id);
    }

    /** Find a day only through its owning tour. */
    private function ownedDay(Tour $tour, int $dayId): ItineraryDay
    {
        return $tour->itineraryDays()->whereKey($dayId)->firstOrFail();
    }

    /** Find an activity only through its owning day. */
    private function ownedActivity(ItineraryDay $day, int $activityId): ItineraryActivity
    {
        return $day->activities()->whereKey($activityId)->firstOrFail();
    }

    /** Resolve a neighboring day after validating the requested direction. */
    private function adjacentDay(Tour $tour, ItineraryDay $day, string $direction): ?ItineraryDay
    {
        return $tour->itineraryDays()->where('day_number', $day->day_number + $this->step($direction))->first();
    }

    /** Accept only one-step ordering commands. */
    private function step(string $direction): int
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            throw new CatalogException('Invalid itinerary ordering direction.');
        }

        return $direction === 'up' ? -1 : 1;
    }

    /** Ensure optional destination references remain selectable. */
    private function assertActiveDestinations(array $ids): void
    {
        $ids = array_values(array_unique(array_filter($ids, static fn ($id): bool => $id !== null)));
        if ($ids !== [] && Destination::query()->where('is_active', true)->whereKey($ids)->count() !== count($ids)) {
            throw new CatalogException('Itinerary destinations must be active.');
        }
    }

    /** Normalize optional text without changing meaningful content. */
    private function optional(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
