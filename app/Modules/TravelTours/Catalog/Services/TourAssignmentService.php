<?php

/** Owns validated category and destination assignments for one tour. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Services;

use App\Modules\TravelTours\Catalog\Data\TourAssignmentData;
use App\Modules\TravelTours\Catalog\Enums\PublicationStatus;
use App\Modules\TravelTours\Catalog\Exceptions\CatalogException;
use App\Modules\TravelTours\Catalog\Exceptions\PublicationBlocked;
use App\Modules\TravelTours\Catalog\Models\Destination;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Catalog\Models\TourCategory;
use App\Modules\TravelTours\Catalog\Models\TourCategoryAssignment;
use App\Modules\TravelTours\Catalog\Models\TourDestinationAssignment;
use Illuminate\Database\DatabaseManager;

/** Validate the entire route before atomically replacing disposable pivot rows. */
final readonly class TourAssignmentService
{
    /** Inject the database transaction boundary. */
    public function __construct(private DatabaseManager $database) {}

    /** Replace one tour's ordered category and destination assignments. */
    public function replace(Tour $tour, TourAssignmentData $data): Tour
    {
        return $this->database->transaction(function () use ($tour, $data): Tour {
            $tour = Tour::query()->lockForUpdate()->findOrFail($tour->id);
            $this->assertCategories($tour, $data->categories);
            $this->assertDestinations($tour, $data->destinations);

            TourCategoryAssignment::query()->where('tour_id', $tour->id)->delete();
            foreach ($data->categories as $row) {
                TourCategoryAssignment::query()->create([
                    'tour_id' => $tour->id, 'category_id' => $row['category_id'],
                    'is_primary' => $row['is_primary'], 'sort_order' => $row['sort_order'],
                ]);
            }
            TourDestinationAssignment::query()->where('tour_id', $tour->id)->delete();
            foreach ($data->destinations as $row) {
                TourDestinationAssignment::query()->create([
                    'tour_id' => $tour->id, 'destination_id' => $row['destination_id'],
                    'role' => $row['role'], 'sequence' => $row['sequence'],
                    'is_overnight' => $row['is_overnight'],
                ]);
            }

            return $tour->refresh()->load(['categories', 'destinations']);
        });
    }

    /** Validate distinct active categories and exactly one primary assignment. */
    private function assertCategories(Tour $tour, array $rows): void
    {
        if (count($rows) > 50) {
            throw new CatalogException('A tour may use no more than 50 categories.');
        }
        if ($tour->status === PublicationStatus::Published && $rows === []) {
            throw new PublicationBlocked(['A published tour must retain a category.']);
        }
        $ids = [];
        $primaryCount = 0;
        foreach ($rows as $row) {
            if (! is_array($row) || array_diff(array_keys($row), ['category_id', 'is_primary', 'sort_order']) !== []
                || ! isset($row['category_id'], $row['is_primary'], $row['sort_order'])
                || ! is_int($row['category_id']) || $row['category_id'] < 1 || ! is_bool($row['is_primary'])
                || ! is_int($row['sort_order']) || $row['sort_order'] < 0) {
                throw new CatalogException('The category assignment is incomplete.');
            }
            $ids[] = $row['category_id'];
            $primaryCount += (int) $row['is_primary'];
        }
        if (count($ids) !== count(array_unique($ids)) || ($rows !== [] && $primaryCount !== 1)) {
            throw new CatalogException('Categories must be distinct and have exactly one primary category.');
        }
        if (TourCategory::query()->active()->whereKey($ids)->count() !== count($ids)) {
            throw new CatalogException('Every assigned category must exist and be active.');
        }
    }

    /** Validate distinct active destinations, sequence, role, and public dependencies. */
    private function assertDestinations(Tour $tour, array $rows): void
    {
        if (count($rows) > 100) {
            throw new CatalogException('A tour may use no more than 100 destinations.');
        }
        if ($tour->status === PublicationStatus::Published && $rows === []) {
            throw new PublicationBlocked(['A published tour must retain a destination.']);
        }
        $ids = [];
        $sequences = [];
        foreach ($rows as $row) {
            if (! is_array($row) || array_diff(array_keys($row), ['destination_id', 'role', 'sequence', 'is_overnight']) !== []
                || ! isset($row['destination_id'], $row['role'], $row['sequence'], $row['is_overnight'])
                || ! is_int($row['destination_id']) || $row['destination_id'] < 1
                || ! in_array($row['role'], ['primary', 'start', 'visit', 'overnight', 'end'], true)
                || ! is_int($row['sequence']) || $row['sequence'] < 1 || $row['sequence'] > 100
                || ! is_bool($row['is_overnight'])) {
                throw new CatalogException('The destination assignment is incomplete or invalid.');
            }
            $ids[] = $row['destination_id'];
            $sequences[] = $row['sequence'];
        }
        $expectedSequence = $sequences === [] ? [] : range(1, count($sequences));
        if (count($ids) !== count(array_unique($ids)) || count($sequences) !== count(array_unique($sequences))
            || $sequences !== $expectedSequence) {
            throw new CatalogException('Destinations must be distinct and sequenced from one without gaps.');
        }
        $query = Destination::query()->where('is_active', true)->whereKey($ids);
        if ($tour->status === PublicationStatus::Published) {
            $query->published();
        }
        if ($query->count() !== count($ids)) {
            throw new CatalogException('Every assigned destination must exist, be active, and be public when the tour is public.');
        }
    }
}
