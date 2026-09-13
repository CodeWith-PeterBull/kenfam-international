<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Catalog\Services;

use App\Contracts\RecordsSystemActivity;
use App\Enums\SystemActivitySeverity;
use App\Models\User;
use App\Modules\PropertyBooking\Bookings\Enums\UnitAssignmentStatus;
use App\Modules\PropertyBooking\Catalog\Enums\PropertyStatus;
use App\Modules\PropertyBooking\Catalog\Enums\UnitTypeStatus;
use App\Modules\PropertyBooking\Catalog\Exceptions\CatalogException;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Catalog\Models\UnitType;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** Owns sellable unit-type metadata, lifecycle, occupancy, and ordered media. */
final readonly class UnitTypeService
{
    /** Create the unit-type service with transaction and activity dependencies. */
    public function __construct(
        private DatabaseManager $database,
        private RecordsSystemActivity $activities,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function create(Property $property, array $attributes, User $actor): UnitType
    {
        return $this->database->transaction(function () use ($property, $attributes, $actor): UnitType {
            $property = Property::query()->lockForUpdate()->findOrFail($property->getKey());
            if ($property->status === PropertyStatus::Archived) {
                throw new CatalogException('Unit types cannot be added to an archived property.');
            }

            $unitType = new UnitType;
            $unitType->forceFill([
                'property_id' => $property->getKey(),
                'status' => UnitTypeStatus::Draft,
                'bedroom_count' => 0,
                'bathroom_count' => 1,
                'living_room_count' => 0,
                'bed_count' => 1,
                'maximum_guests' => 1,
                'maximum_adults' => 1,
                'maximum_children' => 0,
                'maximum_infants' => 0,
                'allows_infants_on_top' => true,
                'is_entire_unit' => true,
                'smoking_allowed' => false,
                'is_featured' => false,
            ]);
            $unitType->fill($this->payload($attributes));
            $unitType->forceFill([
                'property_id' => $property->getKey(),
                'status' => UnitTypeStatus::Draft,
                'published_at' => null,
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ]);
            $this->assertValid($unitType);
            $unitType->save();

            $this->activities->record(
                activityType: 'property-booking.unit-type.created',
                description: "Unit type created: {$unitType->name}",
                actor: $actor,
                subject: $unitType,
                properties: ['property_ulid' => $property->ulid, 'code' => $unitType->code],
                severity: SystemActivitySeverity::Notice,
                source: 'property-booking-catalog',
            );

            return $unitType->refresh();
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(UnitType $unitType, array $attributes, User $actor): UnitType
    {
        return $this->database->transaction(function () use ($unitType, $attributes, $actor): UnitType {
            $unitType = UnitType::query()->lockForUpdate()->findOrFail($unitType->getKey());
            $unitType->fill($this->payload($attributes));
            $unitType->forceFill(['updated_by' => $actor->getKey()]);
            $this->assertValid($unitType);
            $changes = array_values(array_diff(array_keys($unitType->getDirty()), ['updated_by']));
            $unitType->save();

            $this->activities->record(
                activityType: 'property-booking.unit-type.updated',
                description: "Unit type updated: {$unitType->name}",
                actor: $actor,
                subject: $unitType,
                properties: ['changed_fields' => $changes],
                source: 'property-booking-catalog',
            );

            return $unitType->refresh();
        });
    }

    /** Move a unit type through its explicit public-listing lifecycle. */
    public function transition(UnitType $unitType, UnitTypeStatus $status, User $actor): UnitType
    {
        return $this->database->transaction(function () use ($unitType, $status, $actor): UnitType {
            $unitType = UnitType::query()->lockForUpdate()->findOrFail($unitType->getKey());
            $previous = $unitType->status;
            if ($previous === $status) {
                return $unitType;
            }

            $this->assertValid($unitType);
            if ($status === UnitTypeStatus::Published && $unitType->property->status === PropertyStatus::Archived) {
                throw new CatalogException('A unit type cannot be published under an archived property.');
            }
            if ($status === UnitTypeStatus::Archived && $this->hasActiveAssignments($unitType)) {
                throw new CatalogException('A unit type with active booking assignments cannot be archived.');
            }

            $unitType->forceFill([
                'status' => $status,
                'published_at' => $status === UnitTypeStatus::Published ? ($unitType->published_at ?? now()) : null,
                'updated_by' => $actor->getKey(),
            ])->save();
            $this->activities->record(
                activityType: 'property-booking.unit-type.status-changed',
                description: "Unit type status changed to {$status->label()}: {$unitType->name}",
                actor: $actor,
                subject: $unitType,
                properties: ['from' => $previous->value, 'to' => $status->value],
                severity: SystemActivitySeverity::Notice,
                source: 'property-booking-catalog',
            );

            return $unitType->refresh();
        });
    }

    /** Replace the single unit-type cover with required accessible metadata. */
    public function replaceCover(UnitType $unitType, UploadedFile $upload, string $altText, ?string $caption, User $actor): UnitType
    {
        $this->assertUpload($upload);
        $unitType = UnitType::query()->findOrFail($unitType->getKey());
        $unitType->addMedia($upload)
            ->usingName(pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME))
            ->withCustomProperties($this->mediaMetadata($altText, $caption))
            ->toMediaCollection('unit_type_cover');
        $this->recordMediaActivity($unitType, $actor, 'cover-replaced', 'Unit-type cover updated');

        return $unitType->refresh()->load('media');
    }

    /** Remove the optional unit-type cover without affecting gallery images. */
    public function removeCover(UnitType $unitType, User $actor): UnitType
    {
        $unitType = UnitType::query()->findOrFail($unitType->getKey());
        if ($unitType->getFirstMedia('unit_type_cover') === null) {
            return $unitType->loadMissing('media');
        }

        $unitType->clearMediaCollection('unit_type_cover');
        $this->recordMediaActivity($unitType, $actor, 'cover-removed', 'Unit-type cover removed');

        return $unitType->refresh()->load('media');
    }

    /**
     * Add ordered unit-type gallery images with per-image accessible metadata.
     *
     * @param  list<UploadedFile>  $uploads
     * @param  list<array{alt_text: string, caption?: string|null}>  $metadata
     */
    public function addGalleryImages(UnitType $unitType, array $uploads, array $metadata, User $actor): UnitType
    {
        if ($uploads === []) {
            return $unitType->loadMissing('media');
        }

        $unitType = UnitType::query()->findOrFail($unitType->getKey());
        $limit = max(1, (int) config('property-booking.media.unit_type_gallery_limit', 10));
        $currentCount = $unitType->getMedia('unit_type_gallery')->count();
        if ($currentCount + count($uploads) > $limit) {
            throw new CatalogException("A unit-type gallery may contain no more than {$limit} images.");
        }
        if (count($metadata) !== count($uploads)) {
            throw new CatalogException('Every unit-type gallery image requires accessible metadata.');
        }

        foreach (array_values($uploads) as $index => $upload) {
            $this->assertUpload($upload);
            $unitType->addMedia($upload)
                ->usingName(pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME))
                ->withCustomProperties($this->mediaMetadata(
                    (string) ($metadata[$index]['alt_text'] ?? ''),
                    isset($metadata[$index]['caption']) ? (string) $metadata[$index]['caption'] : null,
                ))
                ->toMediaCollection('unit_type_gallery');
        }

        $this->recordMediaActivity(
            $unitType,
            $actor,
            'gallery-images-added',
            'Unit-type gallery updated',
            ['images_added' => count($uploads), 'gallery_count' => $currentCount + count($uploads)],
        );

        return $unitType->refresh()->load('media');
    }

    /** Update accessible metadata only for media owned by this unit type. */
    public function updateMediaMetadata(UnitType $unitType, int $mediaId, string $altText, ?string $caption, User $actor): UnitType
    {
        $unitType = UnitType::query()->findOrFail($unitType->getKey());
        $media = $this->ownedMedia($unitType, $mediaId, ['unit_type_cover', 'unit_type_gallery']);
        $media->custom_properties = $this->mediaMetadata($altText, $caption);
        $media->save();
        $this->recordMediaActivity($unitType, $actor, 'media-metadata-updated', 'Unit-type image metadata updated', ['media_id' => $mediaId]);

        return $unitType->refresh()->load('media');
    }

    /** Reorder a complete gallery only when every id belongs to this unit type. */
    public function reorderGallery(UnitType $unitType, array $orderedMediaIds, User $actor): UnitType
    {
        $unitType = UnitType::query()->findOrFail($unitType->getKey());
        $existingIds = $unitType->getMedia('unit_type_gallery')->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
        $requestedIds = collect($orderedMediaIds)->map(static fn (mixed $id): int => (int) $id)->unique()->values()->all();
        $expected = $existingIds;
        sort($expected);
        $actual = $requestedIds;
        sort($actual);
        if ($actual !== $expected) {
            throw new CatalogException('The unit-type gallery order must contain each owned gallery image exactly once.');
        }

        $this->database->transaction(function () use ($unitType, $requestedIds): void {
            foreach ($requestedIds as $position => $mediaId) {
                $unitType->media()->where('collection_name', 'unit_type_gallery')->whereKey($mediaId)->update(['order_column' => $position + 1]);
            }
        });
        $this->recordMediaActivity($unitType, $actor, 'gallery-reordered', 'Unit-type gallery reordered', ['image_count' => count($requestedIds)]);

        return $unitType->refresh()->load('media');
    }

    /** Remove one owned unit-type gallery image. */
    public function removeGalleryImage(UnitType $unitType, int $mediaId, User $actor): UnitType
    {
        $unitType = UnitType::query()->findOrFail($unitType->getKey());
        $this->ownedMedia($unitType, $mediaId, ['unit_type_gallery'])->delete();
        $this->recordMediaActivity($unitType, $actor, 'gallery-image-removed', 'Unit-type gallery image removed', ['media_id' => $mediaId]);

        return $unitType->refresh()->load('media');
    }

    /** @param array<string, mixed> $attributes @return array<string, mixed> */
    private function payload(array $attributes): array
    {
        $payload = Arr::only($attributes, [
            'code', 'slug', 'name', 'short_description', 'description', 'size_square_metres',
            'bedroom_count', 'bathroom_count', 'living_room_count', 'bed_count',
            'bed_configuration', 'maximum_guests', 'maximum_adults', 'maximum_children',
            'maximum_infants', 'allows_infants_on_top', 'is_entire_unit', 'smoking_allowed',
            'is_featured', 'meta_title', 'meta_description',
        ]);
        if (array_key_exists('code', $payload)) {
            $payload['code'] = strtoupper(trim((string) $payload['code']));
        }
        if (array_key_exists('name', $payload)) {
            $payload['name'] = trim((string) $payload['name']);
        }
        if (! array_key_exists('slug', $payload) && filled($payload['name'] ?? null)) {
            $payload['slug'] = Str::slug((string) $payload['name']);
        }
        if (array_key_exists('slug', $payload)) {
            $payload['slug'] = Str::slug((string) $payload['slug']);
        }
        foreach (['short_description', 'description', 'size_square_metres', 'meta_title', 'meta_description'] as $field) {
            if (array_key_exists($field, $payload)) {
                $value = trim((string) $payload[$field]);
                $payload[$field] = $value === '' ? null : $value;
            }
        }

        return $payload;
    }

    /** Validate identity, occupancy, physical layout, and bed metadata. */
    private function assertValid(UnitType $unitType): void
    {
        if (blank($unitType->code) || blank($unitType->slug) || blank($unitType->name)) {
            throw new CatalogException('Unit types require a code, slug, and name.');
        }
        if ($unitType->maximum_guests < 1 || $unitType->maximum_adults < 1) {
            throw new CatalogException('Unit types must permit at least one guest and one adult.');
        }
        if ($unitType->maximum_guests < max($unitType->maximum_adults, $unitType->maximum_children)) {
            throw new CatalogException('Maximum guests cannot be lower than the supported adult or child capacity.');
        }
        if ($unitType->size_square_metres !== null && (float) $unitType->size_square_metres <= 0) {
            throw new CatalogException('Unit-type floor area must be greater than zero when supplied.');
        }
        if ($unitType->bed_count < 0) {
            throw new CatalogException('Unit-type bed count cannot be negative.');
        }
        $this->assertBedConfiguration($unitType->bed_configuration, $unitType->bed_count);
    }

    /** Validate the bounded public bed description and its aggregate count. */
    private function assertBedConfiguration(?array $configuration, int $bedCount): void
    {
        if ($configuration === null || $configuration === []) {
            if ($bedCount > 0) {
                throw new CatalogException('Bed configuration is required when a unit type has beds.');
            }

            return;
        }

        $total = 0;
        foreach ($configuration as $bed) {
            if (! is_array($bed)) {
                throw new CatalogException('Every bed configuration entry must contain a type and quantity.');
            }
            $type = trim((string) ($bed['type'] ?? ''));
            $quantity = (int) ($bed['quantity'] ?? 0);
            if ($type === '' || mb_strlen($type) > 80 || $quantity < 1 || $quantity > 20) {
                throw new CatalogException('Every bed configuration entry needs a bounded type and quantity between 1 and 20.');
            }
            $total += $quantity;
        }
        if ($total !== $bedCount) {
            throw new CatalogException('Bed configuration quantities must equal the unit-type bed count.');
        }
    }

    /** Determine whether exact active allocations still depend on this type. */
    private function hasActiveAssignments(UnitType $unitType): bool
    {
        return $unitType->units()->whereHas('assignments', static fn ($query) => $query->where('status', UnitAssignmentStatus::Active->value))->exists();
    }

    /** Assert that Media Library can consume the supplied upload. */
    private function assertUpload(UploadedFile $upload): void
    {
        if (! $upload->isValid()) {
            throw new CatalogException('One of the selected unit-type images could not be read.');
        }
        if (! in_array($upload->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new CatalogException('Unit-type images must be JPEG, PNG, or WebP files.');
        }
        $maximumBytes = max(1, (int) config('property-booking.media.upload_max_kilobytes', 5120)) * 1024;
        if (($upload->getSize() ?: 0) > $maximumBytes) {
            throw new CatalogException('A unit-type image exceeds the configured upload limit.');
        }
    }

    /** @return array{alt_text: string, caption: string|null} */
    private function mediaMetadata(string $altText, ?string $caption): array
    {
        $altText = trim($altText);
        $caption = $caption === null ? null : trim($caption);
        if ($altText === '' || mb_strlen($altText) > 180) {
            throw new CatalogException('Image alternative text is required and cannot exceed 180 characters.');
        }
        if ($caption !== null && mb_strlen($caption) > 320) {
            throw new CatalogException('Image captions cannot exceed 320 characters.');
        }

        return ['alt_text' => $altText, 'caption' => $caption === '' ? null : $caption];
    }

    /** Resolve media only when it belongs to this unit type and an allowed collection. */
    private function ownedMedia(UnitType $unitType, int $mediaId, array $collections): Media
    {
        $media = $unitType->media()->whereIn('collection_name', $collections)->whereKey($mediaId)->first();
        if (! $media instanceof Media) {
            throw new CatalogException('The selected unit-type image does not exist.');
        }

        return $media;
    }

    /** @param array<string, mixed> $properties */
    private function recordMediaActivity(UnitType $unitType, User $actor, string $event, string $description, array $properties = []): void
    {
        $this->activities->record(
            activityType: "property-booking.unit-type.{$event}",
            description: "{$description}: {$unitType->name}",
            actor: $actor,
            subject: $unitType,
            properties: $properties,
            source: 'property-booking-catalog',
        );
    }
}
