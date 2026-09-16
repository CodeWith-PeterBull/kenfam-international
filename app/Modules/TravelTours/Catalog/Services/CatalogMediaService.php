<?php

/**
 * Owns destination image attachment, accessible metadata, and ordering.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Services;

use App\Modules\TravelTours\Catalog\Exceptions\CatalogException;
use App\Modules\TravelTours\Catalog\Models\Destination;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** Keep destination media mutations out of Livewire and controller code. */
final readonly class CatalogMediaService
{
    /** Create the media service with its ordering transaction boundary. */
    public function __construct(private DatabaseManager $database) {}

    /** Replace the single destination cover with accessible metadata. */
    public function replaceDestinationCover(Destination $destination, UploadedFile $upload, string $altText, ?string $caption): Destination
    {
        $this->assertUpload($upload);
        $destination = Destination::query()->findOrFail($destination->getKey());
        $destination->addMedia($upload)
            ->usingName(pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME))
            ->withCustomProperties($this->metadata($altText, $caption))
            ->toMediaCollection('destination_cover');

        return $destination->refresh()->load('media');
    }

    /** Remove the optional destination cover without affecting the gallery. */
    public function removeDestinationCover(Destination $destination): Destination
    {
        $destination = Destination::query()->findOrFail($destination->getKey());
        $destination->clearMediaCollection('destination_cover');

        return $destination->refresh()->load('media');
    }

    /** Add one bounded, accessible image to the ordered destination gallery. */
    public function addDestinationGalleryImage(Destination $destination, UploadedFile $upload, string $altText, ?string $caption): Destination
    {
        $this->assertUpload($upload);
        $destination = Destination::query()->findOrFail($destination->getKey());
        $limit = max(1, (int) config('travel-tours.media.destination_gallery_limit', 12));
        if ($destination->getMedia('destination_gallery')->count() >= $limit) {
            throw new CatalogException("A destination gallery may contain no more than {$limit} images.");
        }

        $destination->addMedia($upload)
            ->usingName(pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME))
            ->withCustomProperties($this->metadata($altText, $caption))
            ->toMediaCollection('destination_gallery');

        return $destination->refresh()->load('media');
    }

    /** Update accessible metadata only for media owned by the destination. */
    public function updateDestinationMedia(Destination $destination, int $mediaId, string $altText, ?string $caption): Destination
    {
        $destination = Destination::query()->findOrFail($destination->getKey());
        $media = $this->ownedMedia($destination, $mediaId, ['destination_cover', 'destination_gallery']);
        $media->custom_properties = $this->metadata($altText, $caption);
        $media->save();

        return $destination->refresh()->load('media');
    }

    /** Reorder the complete gallery after ownership and completeness validation. */
    public function reorderDestinationGallery(Destination $destination, array $orderedMediaIds): Destination
    {
        $destination = Destination::query()->findOrFail($destination->getKey());
        $existing = $destination->getMedia('destination_gallery')->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $requested = collect($orderedMediaIds)->map(static fn ($id): int => (int) $id)->unique()->values()->all();
        $expected = $existing;
        sort($expected);
        $actual = $requested;
        sort($actual);
        if ($expected !== $actual) {
            throw new CatalogException('The destination gallery order must contain each owned image exactly once.');
        }

        $this->database->transaction(function () use ($destination, $requested): void {
            foreach ($requested as $position => $mediaId) {
                $destination->media()
                    ->where('collection_name', 'destination_gallery')
                    ->whereKey($mediaId)
                    ->update(['order_column' => $position + 1]);
            }
        });

        return $destination->refresh()->load('media');
    }

    /** Remove one gallery image only when it belongs to the destination. */
    public function removeDestinationGalleryImage(Destination $destination, int $mediaId): Destination
    {
        $destination = Destination::query()->findOrFail($destination->getKey());
        $this->ownedMedia($destination, $mediaId, ['destination_gallery'])->delete();

        return $destination->refresh()->load('media');
    }

    /** Build bounded custom properties shared by cover and gallery images. */
    private function metadata(string $altText, ?string $caption): array
    {
        $altText = trim($altText);
        $caption = trim((string) $caption);
        if ($altText === '' || mb_strlen($altText) > 180) {
            throw new CatalogException('Destination image alternative text is required and may not exceed 180 characters.');
        }
        if (mb_strlen($caption) > 320) {
            throw new CatalogException('Destination image caption may not exceed 320 characters.');
        }

        return ['alt_text' => $altText, 'caption' => $caption === '' ? null : $caption];
    }

    /** Assert that Media Library can consume the supplied upload. */
    private function assertUpload(UploadedFile $upload): void
    {
        if (! $upload->isValid()) {
            throw new CatalogException('The selected destination image could not be read.');
        }
        if (! in_array($upload->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new CatalogException('Destination images must be JPEG, PNG, or WebP files.');
        }
        $maximumBytes = max(1, (int) config('travel-tours.media.upload_max_kilobytes', 6144)) * 1024;
        if (($upload->getSize() ?: 0) > $maximumBytes) {
            throw new CatalogException('The destination image exceeds the configured upload limit.');
        }
    }

    /** Resolve media through the parent relation to reject cross-record ids. */
    private function ownedMedia(Destination $destination, int $mediaId, array $collections): Media
    {
        $media = $destination->media()->whereIn('collection_name', $collections)->whereKey($mediaId)->first();
        if (! $media instanceof Media) {
            throw new CatalogException('The selected destination image is unavailable.');
        }

        return $media;
    }
}
