<?php

/** Validates and translates editable tour basics into TourData. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Livewire\Forms;

use App\Modules\TravelTours\Bookings\Enums\BookingMode;
use App\Modules\TravelTours\Catalog\Data\TourData;
use App\Modules\TravelTours\Catalog\Enums\PublicationStatus;
use App\Modules\TravelTours\Catalog\Enums\TourDifficulty;
use App\Modules\TravelTours\Catalog\Enums\TourType;
use App\Modules\TravelTours\Catalog\Models\Tour;
use Illuminate\Validation\Rule;
use Livewire\Form;

/** Keep UI validation separate from transactional tour invariants. */
final class TourForm extends Form
{
    public string $code = '';

    public string $name = '';

    public string $slug = '';

    public string $type = 'escorted';

    public string $status = 'draft';

    public string $tagline = '';

    public string $shortDescription = '';

    public string $description = '';

    public int $durationDays = 1;

    public int $durationNights = 0;

    public int $minimumAge = 0;

    public string $difficulty = 'easy';

    public int $minimumParticipants = 1;

    public string $maximumParticipants = '';

    public string $languages = 'English';

    public string $bookingMode = 'approval';

    public string $meetingPointName = '';

    public string $meetingPointDetails = '';

    public string $meetingLatitude = '';

    public string $meetingLongitude = '';

    public string $endPointName = '';

    public string $endPointDetails = '';

    public string $endLatitude = '';

    public string $endLongitude = '';

    public string $terms = '';

    public string $cancellationSummary = '';

    public string $policyVersion = '';

    public bool $isFeatured = false;

    public int $sortOrder = 0;

    public string $metaTitle = '';

    public string $metaDescription = '';

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9][A-Za-z0-9-]+$/'],
            'name' => ['required', 'string', 'max:200'], 'slug' => ['nullable', 'string', 'max:180'],
            'type' => ['required', Rule::enum(TourType::class)],
            'status' => ['required', Rule::enum(PublicationStatus::class)],
            'tagline' => ['nullable', 'string', 'max:240'],
            'shortDescription' => ['nullable', 'string', 'max:360'],
            'description' => ['nullable', 'string', 'max:50000'],
            'durationDays' => ['required', 'integer', 'between:1,365'],
            'durationNights' => ['required', 'integer', 'min:0', 'lte:durationDays'],
            'minimumAge' => ['required', 'integer', 'between:0,120'],
            'difficulty' => ['required', Rule::enum(TourDifficulty::class)],
            'minimumParticipants' => ['required', 'integer', 'between:1,1000'],
            'maximumParticipants' => ['nullable', 'integer', 'between:1,1000', 'gte:minimumParticipants'],
            'languages' => ['nullable', 'string', 'max:1200'],
            'bookingMode' => ['required', Rule::enum(BookingMode::class)],
            'meetingPointName' => ['nullable', 'string', 'max:200'],
            'meetingPointDetails' => ['nullable', 'string', 'max:10000'],
            'meetingLatitude' => ['nullable', 'required_with:meetingLongitude', 'numeric', 'between:-90,90'],
            'meetingLongitude' => ['nullable', 'required_with:meetingLatitude', 'numeric', 'between:-180,180'],
            'endPointName' => ['nullable', 'string', 'max:200'],
            'endPointDetails' => ['nullable', 'string', 'max:10000'],
            'endLatitude' => ['nullable', 'required_with:endLongitude', 'numeric', 'between:-90,90'],
            'endLongitude' => ['nullable', 'required_with:endLatitude', 'numeric', 'between:-180,180'],
            'terms' => ['nullable', 'string', 'max:50000'],
            'cancellationSummary' => ['nullable', 'string', 'max:10000'],
            'policyVersion' => ['nullable', 'string', 'max:60'],
            'isFeatured' => ['boolean'], 'sortOrder' => ['required', 'integer', 'min:0'],
            'metaTitle' => ['nullable', 'string', 'max:160'],
            'metaDescription' => ['nullable', 'string', 'max:320'],
        ];
    }

    /** Populate editable fields from an authorized tour. */
    public function fillFromTour(Tour $tour): void
    {
        foreach (['code', 'name', 'slug', 'tagline', 'short_description', 'description', 'meeting_point_name', 'meeting_point_details', 'meeting_latitude', 'meeting_longitude', 'end_point_name', 'end_point_details', 'end_latitude', 'end_longitude', 'terms', 'cancellation_summary', 'policy_version', 'meta_title', 'meta_description'] as $field) {
            $property = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $field))));
            $this->{$property} = (string) $tour->{$field};
        }
        $this->type = $tour->type->value;
        $this->status = $tour->status->value;
        $this->durationDays = $tour->duration_days;
        $this->durationNights = $tour->duration_nights;
        $this->minimumAge = $tour->minimum_age;
        $this->difficulty = $tour->difficulty->value;
        $this->minimumParticipants = $tour->minimum_participants;
        $this->maximumParticipants = $tour->maximum_participants === null ? '' : (string) $tour->maximum_participants;
        $this->languages = implode(', ', $tour->languages ?? []);
        $this->bookingMode = $tour->booking_mode->value;
        $this->isFeatured = $tour->is_featured;
        $this->sortOrder = $tour->sort_order;
    }

    /** Convert validated UI input to the immutable service contract. */
    public function toData(): TourData
    {
        return new TourData(
            code: $this->code, name: $this->name, type: TourType::from($this->type),
            durationDays: $this->durationDays, slug: self::optional($this->slug),
            status: PublicationStatus::from($this->status),
            tagline: self::optional($this->tagline), shortDescription: self::optional($this->shortDescription),
            description: self::optional($this->description), durationNights: $this->durationNights,
            minimumAge: $this->minimumAge, difficulty: TourDifficulty::from($this->difficulty),
            minimumParticipants: $this->minimumParticipants,
            maximumParticipants: $this->maximumParticipants === '' ? null : (int) $this->maximumParticipants,
            languages: array_values(array_filter(array_map(trim(...), explode(',', $this->languages)))),
            bookingMode: BookingMode::from($this->bookingMode),
            meetingPointName: self::optional($this->meetingPointName),
            meetingPointDetails: self::optional($this->meetingPointDetails),
            meetingLatitude: self::optional($this->meetingLatitude),
            meetingLongitude: self::optional($this->meetingLongitude),
            endPointName: self::optional($this->endPointName),
            endPointDetails: self::optional($this->endPointDetails),
            endLatitude: self::optional($this->endLatitude), endLongitude: self::optional($this->endLongitude),
            terms: self::optional($this->terms), cancellationSummary: self::optional($this->cancellationSummary),
            policyVersion: self::optional($this->policyVersion), isFeatured: $this->isFeatured,
            sortOrder: $this->sortOrder, metaTitle: self::optional($this->metaTitle),
            metaDescription: self::optional($this->metaDescription),
        );
    }

    /** Convert blank optional strings to null. */
    private static function optional(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
