<?php

/**
 * Owns destination image attachment, accessible metadata, and ordering.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Services;

use App\Modules\TravelTours\Catalog\Enums\PublicationStatus;
use App\Modules\TravelTours\Catalog\Exceptions\CatalogException;
use App\Modules\TravelTours\Catalog\Models\Destination;
use App\Modules\TravelTours\Catalog\Models\Tour;
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

    /** Replace the tour cover while retaining only the newly supplied image. */
    public function replaceTourCover(Tour $tour, UploadedFile $upload, string $altText, ?string $caption): Tour
    {
        $this->assertImageUpload($upload, 'Tour');
        $tour = Tour::query()->findOrFail($tour->getKey());
        $tour->addMedia($upload)->usingName(pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME))
            ->withCustomProperties($this->imageMetadata($altText, $caption, 'Tour'))->toMediaCollection('tour_cover');

        return $tour->refresh()->load('media');
    }

    /** Remove the optional tour cover without touching gallery or documents. */
    public function removeTourCover(Tour $tour): Tour
    {
        $tour = Tour::query()->findOrFail($tour->getKey());
        if ($tour->status === PublicationStatus::Published) {
            throw new CatalogException('Unpublish this tour before removing its cover, or replace the cover directly.');
        }
        $tour->clearMediaCollection('tour_cover');

        return $tour->refresh()->load('media');
    }

    /** Add one bounded, accessible image to the tour gallery. */
    public function addTourGalleryImage(Tour $tour, UploadedFile $upload, string $altText, ?string $caption): Tour
    {
        $this->assertImageUpload($upload, 'Tour');
        $tour = Tour::query()->findOrFail($tour->getKey());
        $limit = max(1, (int) config('travel-tours.media.tour_gallery_limit', 18));
        if ($tour->getMedia('tour_gallery')->count() >= $limit) {
            throw new CatalogException("A tour gallery may contain no more than {$limit} images.");
        }
        $tour->addMedia($upload)->usingName(pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME))
            ->withCustomProperties($this->imageMetadata($altText, $caption, 'Tour'))->toMediaCollection('tour_gallery');

        return $tour->refresh()->load('media');
    }

    /** Update metadata only when the image belongs to the supplied tour. */
    public function updateTourImage(Tour $tour, int $mediaId, string $altText, ?string $caption): Tour
    {
        $tour = Tour::query()->findOrFail($tour->getKey());
        $media = $this->ownedMedia($tour, $mediaId, ['tour_cover', 'tour_gallery'], 'tour image');
        $media->custom_properties = $this->imageMetadata($altText, $caption, 'Tour');
        $media->save();

        return $tour->refresh()->load('media');
    }

    /** Store the complete deterministic order for tour gallery images. */
    public function reorderTourGallery(Tour $tour, array $orderedMediaIds): Tour
    {
        return $this->reorderGallery($tour, 'tour_gallery', $orderedMediaIds, 'tour');
    }

    /** Remove one gallery image only from its owning tour. */
    public function removeTourGalleryImage(Tour $tour, int $mediaId): Tour
    {
        $tour = Tour::query()->findOrFail($tour->getKey());
        $this->ownedMedia($tour, $mediaId, ['tour_gallery'], 'tour gallery image')->delete();

        return $tour->refresh()->load('media');
    }

    /** Attach one public traveler document with a purposeful title. */
    public function addTourDocument(Tour $tour, UploadedFile $upload, string $title, ?string $description): Tour
    {
        $this->assertDocumentUpload($upload);
        $title = trim($title);
        $description = trim((string) $description);
        if ($title === '' || mb_strlen($title) > 180 || mb_strlen($description) > 500) {
            throw new CatalogException('A tour attachment needs a title of at most 180 characters and an optional description of at most 500 characters.');
        }
        $tour = Tour::query()->findOrFail($tour->getKey());
        $limit = max(1, (int) config('travel-tours.media.tour_document_limit', 12));
        if ($tour->getMedia('tour_documents')->count() >= $limit) {
            throw new CatalogException("A tour may expose no more than {$limit} public attachments.");
        }
        $tour->addMedia($upload)->usingName($title)->withCustomProperties([
            'title' => $title, 'description' => $description === '' ? null : $description,
        ])->toMediaCollection('tour_documents');

        return $tour->refresh()->load('media');
    }

    /** Remove one public attachment only from its owning tour. */
    public function removeTourDocument(Tour $tour, int $mediaId): Tour
    {
        $tour = Tour::query()->findOrFail($tour->getKey());
        $this->ownedMedia($tour, $mediaId, ['tour_documents'], 'tour attachment')->delete();

        return $tour->refresh()->load('media');
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

    /** Validate one public catalog image using the configured upload ceiling. */
    private function assertImageUpload(UploadedFile $upload, string $label): void
    {
        if (! $upload->isValid() || ! in_array($upload->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new CatalogException("{$label} images must be readable JPEG, PNG, or WebP files.");
        }
        $this->assertUploadSize($upload, $label.' image');
    }

    /** Validate one public PDF or Word attachment using the configured ceiling. */
    private function assertDocumentUpload(UploadedFile $upload): void
    {
        if (! $upload->isValid() || ! in_array($upload->getMimeType(), [
            'application/pdf', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ], true)) {
            throw new CatalogException('Tour attachments must be readable PDF, DOC, or DOCX files.');
        }
        $this->assertUploadSize($upload, 'Tour attachment');
    }

    /** Enforce the shared module upload size limit. */
    private function assertUploadSize(UploadedFile $upload, string $label): void
    {
        $maximumBytes = max(1, (int) config('travel-tours.media.upload_max_kilobytes', 6144)) * 1024;
        if (($upload->getSize() ?: 0) > $maximumBytes) {
            throw new CatalogException("{$label} exceeds the configured upload limit.");
        }
    }

    /** Build bounded accessible metadata for one catalog image. */
    private function imageMetadata(string $altText, ?string $caption, string $label): array
    {
        $altText = trim($altText);
        $caption = trim((string) $caption);
        if ($altText === '' || mb_strlen($altText) > 180 || mb_strlen($caption) > 320) {
            throw new CatalogException("{$label} image alternative text is required (180 characters maximum); captions may not exceed 320 characters.");
        }

        return ['alt_text' => $altText, 'caption' => $caption === '' ? null : $caption];
    }

    /** Reorder a complete owned gallery after set-equivalence validation. */
    private function reorderGallery(Destination|Tour $owner, string $collection, array $orderedMediaIds, string $label): Destination|Tour
    {
        $owner = $owner::query()->findOrFail($owner->getKey());
        $existing = $owner->getMedia($collection)->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $requested = collect($orderedMediaIds)->map(static fn ($id): int => (int) $id)->unique()->values()->all();
        $expected = $existing;
        sort($expected);
        $actual = $requested;
        sort($actual);
        if ($expected !== $actual) {
            throw new CatalogException("The {$label} gallery order must contain each owned image exactly once.");
        }
        $this->database->transaction(function () use ($owner, $collection, $requested): void {
            foreach ($requested as $position => $mediaId) {
                $owner->media()->where('collection_name', $collection)->whereKey($mediaId)->update(['order_column' => $position + 1]);
            }
        });

        return $owner->refresh()->load('media');
    }

    /** Resolve media through the parent relation to reject cross-record ids. */
    private function ownedMedia(Destination|Tour $destination, int $mediaId, array $collections, string $label = 'destination image'): Media
    {
        $media = $destination->media()->whereIn('collection_name', $collections)->whereKey($mediaId)->first();
        if (! $media instanceof Media) {
            throw new CatalogException("The selected {$label} is unavailable.");
        }

        return $media;
    }
}
