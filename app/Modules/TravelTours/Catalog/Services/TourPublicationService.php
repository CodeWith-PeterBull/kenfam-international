<?php

/** Performs permission-gated TravelTours editorial state transitions. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Services;

use App\Models\User;
use App\Modules\TravelTours\Catalog\Enums\PublicationStatus;
use App\Modules\TravelTours\Catalog\Exceptions\PublicationBlocked;
use App\Modules\TravelTours\Catalog\Models\Tour;
use Carbon\CarbonImmutable;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Gate;

/** Keep review, publication, and archive writes out of components. */
final readonly class TourPublicationService
{
    /** Inject the catalog transaction and publication-readiness boundaries. */
    public function __construct(private DatabaseManager $database, private TourReadinessService $readiness) {}

    /** Submit a draft for manager review without making it public. */
    public function submitForReview(Tour $tour, User $actor): Tour
    {
        Gate::forUser($actor)->authorize('update', $tour);

        return $this->transition($tour, $actor, function (Tour $current): void {
            if ($current->status !== PublicationStatus::Draft) {
                throw new PublicationBlocked(['Only a draft can be submitted for review.']);
            }
            $current->status = PublicationStatus::Review;
            $current->published_at = null;
        });
    }

    /** Publish now or at one explicitly supplied future UTC instant. */
    public function publish(Tour $tour, User $actor, ?CarbonImmutable $at = null): Tour
    {
        Gate::forUser($actor)->authorize('publish', $tour);
        if ($at !== null && $at->isPast()) {
            throw new PublicationBlocked(['Scheduled publication must be in the future.']);
        }

        return $this->transition($tour, $actor, function (Tour $current) use ($at): void {
            if (! in_array($current->status, [PublicationStatus::Draft, PublicationStatus::Review, PublicationStatus::Published], true)) {
                throw new PublicationBlocked(['Restore an archived tour to a draft before publishing.']);
            }
            $reasons = $this->readiness->reasons($current);
            if ($reasons !== []) {
                throw new PublicationBlocked($reasons);
            }
            $current->status = PublicationStatus::Published;
            $current->published_at = $at ?? CarbonImmutable::now('UTC');
        });
    }

    /** Remove public eligibility while retaining slug, media, and booking history. */
    public function unpublish(Tour $tour, User $actor): Tour
    {
        Gate::forUser($actor)->authorize('unpublish', $tour);

        return $this->transition($tour, $actor, function (Tour $current): void {
            if ($current->status !== PublicationStatus::Published) {
                throw new PublicationBlocked(['Only a published tour can be unpublished.']);
            }
            $current->status = PublicationStatus::Draft;
            $current->published_at = null;
        });
    }

    /** Archive the catalog record without deleting historical references. */
    public function archive(Tour $tour, User $actor): Tour
    {
        Gate::forUser($actor)->authorize('publish', $tour);

        return $this->transition($tour, $actor, function (Tour $current): void {
            $current->status = PublicationStatus::Archived;
            $current->published_at = null;
        });
    }

    /** Restore an archived record to an unpublished draft. */
    public function restoreDraft(Tour $tour, User $actor): Tour
    {
        Gate::forUser($actor)->authorize('publish', $tour);

        return $this->transition($tour, $actor, function (Tour $current): void {
            if ($current->status !== PublicationStatus::Archived) {
                throw new PublicationBlocked(['Only an archived tour can be restored.']);
            }
            $current->status = PublicationStatus::Draft;
            $current->published_at = null;
        });
    }

    /** Serialize a state change against the persisted tour. */
    private function transition(Tour $tour, User $actor, callable $change): Tour
    {
        return $this->database->transaction(function () use ($tour, $actor, $change): Tour {
            $current = Tour::query()->lockForUpdate()->findOrFail($tour->id);
            $change($current);
            $current->updated_by = $actor->id;
            $current->save();

            return $current->refresh();
        });
    }
}
