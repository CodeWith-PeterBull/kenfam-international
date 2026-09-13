<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Catalog\Services;

use App\Contracts\RecordsSystemActivity;
use App\Enums\SystemActivitySeverity;
use App\Models\User;
use App\Modules\PropertyBooking\Bookings\Enums\BookingStatus;
use App\Modules\PropertyBooking\Catalog\Enums\PropertyStatus;
use App\Modules\PropertyBooking\Catalog\Exceptions\CatalogException;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Catalog\Models\PropertyCategory;
use App\Modules\PropertyBooking\PointOfBooking\Enums\ReceptionShiftStatus;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** Owns property metadata, publication, assignments, and ordered media. */
final readonly class PropertyService
{
    /** Create the property service with transaction and activity dependencies. */
    public function __construct(
        private DatabaseManager $database,
        private RecordsSystemActivity $activities,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes, User $actor): Property
    {
        return $this->database->transaction(function () use ($attributes, $actor): Property {
            $property = new Property;
            $property->forceFill([
                'status' => PropertyStatus::Draft,
                'country_code' => strtoupper((string) config('property-booking.defaults.country_code', 'KE')),
                'currency' => strtoupper((string) config('property-booking.defaults.currency', 'KES')),
                'timezone' => (string) config('property-booking.defaults.timezone', 'Africa/Nairobi'),
                'turnover_minutes' => max(0, (int) config('property-booking.defaults.turnover_minutes', 60)),
                'minimum_notice_minutes' => 0,
                'is_featured' => false,
            ]);
            $property->fill($this->payload($attributes));
            $property->forceFill([
                'status' => PropertyStatus::Draft,
                'published_at' => null,
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ]);
            $this->assertValid($property);
            $property->save();

            $this->activities->record(
                activityType: 'property-booking.property.created',
                description: "Property created: {$property->name}",
                actor: $actor,
                subject: $property,
                properties: ['code' => $property->code, 'status' => $property->status->value],
                severity: SystemActivitySeverity::Notice,
                source: 'property-booking-catalog',
            );

            return $property->refresh();
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(Property $property, array $attributes, User $actor): Property
    {
        return $this->database->transaction(function () use ($property, $attributes, $actor): Property {
            $property = Property::query()->lockForUpdate()->findOrFail($property->getKey());
            $property->fill($this->payload($attributes));
            $property->forceFill(['updated_by' => $actor->getKey()]);
            $this->assertValid($property);
            $changes = array_values(array_diff(array_keys($property->getDirty()), ['updated_by']));
            $property->save();

            $this->activities->record(
                activityType: 'property-booking.property.updated',
                description: "Property updated: {$property->name}",
                actor: $actor,
                subject: $property,
                properties: ['changed_fields' => $changes],
                source: 'property-booking-catalog',
            );

            return $property->refresh();
        });
    }

    /** Move a property through its explicit public-listing lifecycle. */
    public function transition(Property $property, PropertyStatus $status, User $actor): Property
    {
        return $this->database->transaction(function () use ($property, $status, $actor): Property {
            $property = Property::query()->lockForUpdate()->findOrFail($property->getKey());
            $previous = $property->status;
            if ($previous === $status) {
                return $property;
            }

            $this->assertValid($property);
            if ($status === PropertyStatus::Published) {
                $this->assertPublishable($property);
            }
            if ($status === PropertyStatus::Archived) {
                $this->assertArchivable($property);
            }

            $property->forceFill([
                'status' => $status,
                'published_at' => $status === PropertyStatus::Published ? ($property->published_at ?? now()) : null,
                'updated_by' => $actor->getKey(),
            ])->save();

            $this->activities->record(
                activityType: 'property-booking.property.status-changed',
                description: "Property status changed to {$status->label()}: {$property->name}",
                actor: $actor,
                subject: $property,
                properties: ['from' => $previous->value, 'to' => $status->value],
                severity: SystemActivitySeverity::Notice,
                source: 'property-booking-catalog',
            );

            return $property->refresh();
        });
    }

    /** @param list<int> $userIds */
    public function syncAssignedUsers(Property $property, array $userIds, ?int $defaultUserId, User $actor): Property
    {
        $ids = collect($userIds)->map(static fn (mixed $id): int => (int) $id)->filter()->unique()->values();
        $activeIds = User::query()->where('is_active', true)->whereKey($ids)->pluck('id');
        if ($activeIds->count() !== $ids->count()) {
            throw new CatalogException('One or more selected property users are unavailable or inactive.');
        }
        if ($defaultUserId !== null && ! $activeIds->contains($defaultUserId)) {
            throw new CatalogException('The default property user must also be assigned to the property.');
        }

        return $this->database->transaction(function () use ($property, $activeIds, $defaultUserId, $actor): Property {
            $property = Property::query()->lockForUpdate()->findOrFail($property->getKey());
            $sync = $activeIds->mapWithKeys(static fn (mixed $id): array => [
                (int) $id => ['assigned_by' => $actor->getKey(), 'is_default' => (int) $id === $defaultUserId],
            ])->all();
            $property->assignedUsers()->sync($sync);

            $this->activities->record(
                activityType: 'property-booking.property.users-synced',
                description: "Property user assignments updated: {$property->name}",
                actor: $actor,
                subject: $property,
                properties: ['assigned_user_count' => count($sync), 'default_user_id' => $defaultUserId],
                source: 'property-booking-catalog',
            );

            return $property->refresh()->load('assignedUsers');
        });
    }

    /** Replace the single property cover with required accessible metadata. */
    public function replaceCover(Property $property, UploadedFile $upload, string $altText, ?string $caption, User $actor): Property
    {
        $this->assertUpload($upload);
        $metadata = $this->mediaMetadata($altText, $caption);
        $property = Property::query()->findOrFail($property->getKey());
        $property->addMedia($upload)
            ->usingName(pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME))
            ->withCustomProperties($metadata)
            ->toMediaCollection('property_cover');

        $this->recordMediaActivity($property, $actor, 'cover-replaced', 'Property cover updated');

        return $property->refresh()->load('media');
    }

    /** Remove the optional property cover without affecting gallery images. */
    public function removeCover(Property $property, User $actor): Property
    {
        $property = Property::query()->findOrFail($property->getKey());
        if ($property->getFirstMedia('property_cover') === null) {
            return $property->loadMissing('media');
        }

        $property->clearMediaCollection('property_cover');
        $this->recordMediaActivity($property, $actor, 'cover-removed', 'Property cover removed');

        return $property->refresh()->load('media');
    }

    /**
     * Add validated ordered gallery images with per-image accessible metadata.
     *
     * @param  list<UploadedFile>  $uploads
     * @param  list<array{alt_text: string, caption?: string|null}>  $metadata
     */
    public function addGalleryImages(Property $property, array $uploads, array $metadata, User $actor): Property
    {
        if ($uploads === []) {
            return $property->loadMissing('media');
        }

        $property = Property::query()->findOrFail($property->getKey());
        $limit = max(1, (int) config('property-booking.media.property_gallery_limit', 12));
        $currentCount = $property->getMedia('property_gallery')->count();
        if ($currentCount + count($uploads) > $limit) {
            throw new CatalogException("A property gallery may contain no more than {$limit} images.");
        }
        if (count($metadata) !== count($uploads)) {
            throw new CatalogException('Every property gallery image requires accessible metadata.');
        }

        foreach (array_values($uploads) as $index => $upload) {
            $this->assertUpload($upload);
            $customProperties = $this->mediaMetadata(
                (string) ($metadata[$index]['alt_text'] ?? ''),
                isset($metadata[$index]['caption']) ? (string) $metadata[$index]['caption'] : null,
            );
            $property->addMedia($upload)
                ->usingName(pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME))
                ->withCustomProperties($customProperties)
                ->toMediaCollection('property_gallery');
        }

        $this->recordMediaActivity(
            $property,
            $actor,
            'gallery-images-added',
            'Property gallery updated',
            ['images_added' => count($uploads), 'gallery_count' => $currentCount + count($uploads)],
        );

        return $property->refresh()->load('media');
    }

    /** Update accessible metadata only for media owned by this property. */
    public function updateMediaMetadata(Property $property, int $mediaId, string $altText, ?string $caption, User $actor): Property
    {
        $property = Property::query()->findOrFail($property->getKey());
        $media = $this->ownedMedia($property, $mediaId, ['property_cover', 'property_gallery']);
        $media->custom_properties = $this->mediaMetadata($altText, $caption);
        $media->save();

        $this->recordMediaActivity($property, $actor, 'media-metadata-updated', 'Property image metadata updated', ['media_id' => $mediaId]);

        return $property->refresh()->load('media');
    }

    /** Reorder a complete gallery only when every id belongs to this property. */
    public function reorderGallery(Property $property, array $orderedMediaIds, User $actor): Property
    {
        $property = Property::query()->findOrFail($property->getKey());
        $existingIds = $property->getMedia('property_gallery')->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
        $requestedIds = collect($orderedMediaIds)->map(static fn (mixed $id): int => (int) $id)->unique()->values()->all();
        $expected = $existingIds;
        sort($expected);
        $actual = $requestedIds;
        sort($actual);
        if ($actual !== $expected) {
            throw new CatalogException('The property gallery order must contain each owned gallery image exactly once.');
        }

        $this->database->transaction(function () use ($property, $requestedIds): void {
            foreach ($requestedIds as $position => $mediaId) {
                $property->media()->where('collection_name', 'property_gallery')->whereKey($mediaId)->update(['order_column' => $position + 1]);
            }
        });
        $this->recordMediaActivity($property, $actor, 'gallery-reordered', 'Property gallery reordered', ['image_count' => count($requestedIds)]);

        return $property->refresh()->load('media');
    }

    /** Remove one owned property gallery image. */
    public function removeGalleryImage(Property $property, int $mediaId, User $actor): Property
    {
        $property = Property::query()->findOrFail($property->getKey());
        $this->ownedMedia($property, $mediaId, ['property_gallery'])->delete();
        $this->recordMediaActivity($property, $actor, 'gallery-image-removed', 'Property gallery image removed', ['media_id' => $mediaId]);

        return $property->refresh()->load('media');
    }

    /** @param array<string, mixed> $attributes @return array<string, mixed> */
    private function payload(array $attributes): array
    {
        $payload = Arr::only($attributes, [
            'category_id', 'code', 'slug', 'name', 'short_description', 'description',
            'house_rules', 'cancellation_summary', 'email', 'phone', 'whatsapp_phone',
            'website_url', 'address_line_1', 'address_line_2', 'city', 'region',
            'postal_code', 'country_code', 'latitude', 'longitude', 'timezone', 'currency',
            'check_in_from', 'check_in_until', 'check_out_from', 'check_out_until',
            'minimum_notice_minutes', 'maximum_advance_days', 'turnover_minutes',
            'is_featured', 'meta_title', 'meta_description',
        ]);

        foreach (['code', 'country_code', 'currency'] as $field) {
            if (array_key_exists($field, $payload)) {
                $payload[$field] = strtoupper(trim((string) $payload[$field]));
            }
        }
        foreach (['name', 'timezone'] as $field) {
            if (array_key_exists($field, $payload)) {
                $payload[$field] = trim((string) $payload[$field]);
            }
        }
        if (! array_key_exists('slug', $payload) && filled($payload['name'] ?? null)) {
            $payload['slug'] = Str::slug((string) $payload['name']);
        }
        if (array_key_exists('slug', $payload)) {
            $payload['slug'] = Str::slug((string) $payload['slug']);
        }
        foreach ([
            'short_description', 'description', 'house_rules', 'cancellation_summary', 'email',
            'phone', 'whatsapp_phone', 'website_url', 'address_line_1', 'address_line_2',
            'city', 'region', 'postal_code', 'check_in_from', 'check_in_until',
            'check_out_from', 'check_out_until', 'meta_title', 'meta_description',
        ] as $field) {
            if (array_key_exists($field, $payload)) {
                $value = trim((string) $payload[$field]);
                $payload[$field] = $value === '' ? null : $value;
            }
        }

        return $payload;
    }

    /** Validate identity, location, timezone, currency, and operating windows. */
    private function assertValid(Property $property): void
    {
        if (blank($property->code) || blank($property->slug) || blank($property->name)) {
            throw new CatalogException('Properties require a code, slug, and name.');
        }
        if (strlen($property->country_code) !== 2 || preg_match('/^[A-Z]{2}$/', $property->country_code) !== 1) {
            throw new CatalogException('Property country code must be an ISO alpha-2 code.');
        }
        if (strlen($property->currency) !== 3 || preg_match('/^[A-Z]{3}$/', $property->currency) !== 1) {
            throw new CatalogException('Property currency must be an ISO alpha-3 code.');
        }
        if (! in_array($property->timezone, timezone_identifiers_list(), true)) {
            throw new CatalogException('Property timezone must be a valid IANA timezone.');
        }
        if ($property->latitude !== null && ((float) $property->latitude < -90 || (float) $property->latitude > 90)) {
            throw new CatalogException('Property latitude must be between -90 and 90.');
        }
        if ($property->longitude !== null && ((float) $property->longitude < -180 || (float) $property->longitude > 180)) {
            throw new CatalogException('Property longitude must be between -180 and 180.');
        }
        $this->assertTimeWindow($property->check_in_from, $property->check_in_until, 'check-in');
        $this->assertTimeWindow($property->check_out_from, $property->check_out_until, 'check-out');
    }

    /** Require active category context when a property becomes public. */
    private function assertPublishable(Property $property): void
    {
        if ($property->category_id !== null && ! PropertyCategory::query()->active()->whereKey($property->category_id)->exists()) {
            throw new CatalogException('A property cannot be published under an inactive category.');
        }
    }

    /** Reject archive while availability or cash operations remain active. */
    private function assertArchivable(Property $property): void
    {
        $activeStatuses = collect(BookingStatus::cases())
            ->filter(static fn (BookingStatus $status): bool => $status->consumesAvailability())
            ->map(static fn (BookingStatus $status): string => $status->value)
            ->all();
        if ($property->bookings()->whereIn('status', $activeStatuses)->exists()) {
            throw new CatalogException('A property with active bookings cannot be archived.');
        }
        if ($property->shifts()->where('status', ReceptionShiftStatus::Open->value)->exists()) {
            throw new CatalogException('Close every reception shift before archiving this property.');
        }
    }

    /** Assert a same-day optional operating window is complete and ordered. */
    private function assertTimeWindow(?string $from, ?string $until, string $label): void
    {
        if (($from === null) xor ($until === null)) {
            throw new CatalogException("Property {$label} start and end times must be supplied together.");
        }
        if ($from !== null && $until !== null && $from >= $until) {
            throw new CatalogException("Property {$label} end time must be later than its start time.");
        }
    }

    /** Assert that Media Library can consume the supplied upload. */
    private function assertUpload(UploadedFile $upload): void
    {
        if (! $upload->isValid()) {
            throw new CatalogException('One of the selected property images could not be read.');
        }
        if (! in_array($upload->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new CatalogException('Property images must be JPEG, PNG, or WebP files.');
        }
        $maximumBytes = max(1, (int) config('property-booking.media.upload_max_kilobytes', 5120)) * 1024;
        if (($upload->getSize() ?: 0) > $maximumBytes) {
            throw new CatalogException('A property image exceeds the configured upload limit.');
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

    /** Resolve media only when it belongs to this property and an allowed collection. */
    private function ownedMedia(Property $property, int $mediaId, array $collections): Media
    {
        $media = $property->media()->whereIn('collection_name', $collections)->whereKey($mediaId)->first();
        if (! $media instanceof Media) {
            throw new CatalogException('The selected property image does not exist.');
        }

        return $media;
    }

    /** @param array<string, mixed> $properties */
    private function recordMediaActivity(Property $property, User $actor, string $event, string $description, array $properties = []): void
    {
        $this->activities->record(
            activityType: "property-booking.property.{$event}",
            description: "{$description}: {$property->name}",
            actor: $actor,
            subject: $property,
            properties: $properties,
            source: 'property-booking-catalog',
        );
    }
}
