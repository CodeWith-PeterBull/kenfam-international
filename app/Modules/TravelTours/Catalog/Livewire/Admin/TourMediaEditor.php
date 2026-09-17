<?php

/** Coordinates tour-owned public imagery and attachments. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Livewire\Admin;

use App\Modules\TravelTours\Catalog\Exceptions\CatalogException;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Catalog\Services\CatalogMediaService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Delegate file mutation to the scoped catalog media service. */
final class TourMediaEditor extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $tourId;

    #[Locked]
    public ?int $editingImageId = null;

    public mixed $coverUpload = null;

    public mixed $galleryUpload = null;

    public mixed $documentUpload = null;

    public string $coverAltText = '';

    public string $coverCaption = '';

    public string $galleryAltText = '';

    public string $galleryCaption = '';

    public string $mediaAltText = '';

    public string $mediaCaption = '';

    public string $documentTitle = '';

    public string $documentDescription = '';

    public bool $galleryDialog = false;

    /** Require catalog visibility for every hydration request. */
    public function boot(): void
    {
        Gate::authorize('viewAny', Tour::class);
    }

    /** Resolve an existing tour before exposing its media workspace. */
    public function mount(int $tourId): void
    {
        $this->tourId = $tourId;
        $this->tour();
    }

    /** Suggest accessible cover text from the selected local filename. */
    public function updatedCoverUpload(): void
    {
        $this->coverAltText = $this->filenameAltText($this->coverUpload);
    }

    /** Suggest accessible gallery text from the selected local filename. */
    public function updatedGalleryUpload(): void
    {
        $this->galleryAltText = $this->filenameAltText($this->galleryUpload);
    }

    /** Replace the single cover with an accessible image. */
    public function replaceCover(CatalogMediaService $media): void
    {
        $tour = $this->tour();
        $this->validate($this->imageRules('coverUpload'));
        try {
            $media->replaceTourCover($tour, $this->coverUpload, $this->coverAltText, $this->coverCaption);
        } catch (CatalogException $exception) {
            $this->addError('media', $exception->getMessage());

            return;
        }
        $this->clearCoverInput();
        session()->flash('success', 'Tour cover saved.');
    }

    /** Remove only the tour's cover. */
    public function removeCover(CatalogMediaService $media): void
    {
        try {
            $media->removeTourCover($this->tour());
        } catch (CatalogException $exception) {
            $this->addError('media', $exception->getMessage());

            return;
        }
        session()->flash('success', 'Tour cover removed.');
    }

    /** Append one image to the bounded ordered gallery. */
    public function addGalleryImage(CatalogMediaService $media): void
    {
        abort_unless($this->galleryDialog, 404);
        $tour = $this->tour();
        $this->validate($this->imageRules('galleryUpload'));
        try {
            $media->addTourGalleryImage($tour, $this->galleryUpload, $this->galleryAltText, $this->galleryCaption);
        } catch (CatalogException $exception) {
            $this->addError('media', $exception->getMessage());

            return;
        }
        $this->clearGalleryInput();
        $this->galleryDialog = false;
        session()->flash('success', 'Gallery image added.');
    }

    /** Open image upload beside the gallery toolbar, not below a long list. */
    public function openGalleryDialog(): void
    {
        $this->tour();
        $this->clearGalleryInput();
        $this->galleryDialog = true;
    }

    /** Dismiss the transient gallery upload form. */
    public function closeGalleryDialog(): void
    {
        $this->galleryDialog = false;
        $this->clearGalleryInput();
        $this->resetValidation();
    }

    /** Open image metadata in a contextual dialog. */
    public function editImage(int $mediaId): void
    {
        $image = $this->tour()->media()->whereIn('collection_name', ['tour_cover', 'tour_gallery'])->whereKey($mediaId)->firstOrFail();
        $this->editingImageId = $image->id;
        $this->mediaAltText = (string) $image->getCustomProperty('alt_text', '');
        $this->mediaCaption = (string) $image->getCustomProperty('caption', '');
    }

    /** Save alt text and caption on the selected owned image. */
    public function saveImageMetadata(CatalogMediaService $media): void
    {
        abort_unless($this->editingImageId !== null, 404);
        $tour = $this->tour();
        $this->validate(['mediaAltText' => ['required', 'string', 'max:180'], 'mediaCaption' => ['nullable', 'string', 'max:320']]);
        try {
            $media->updateTourImage($tour, $this->editingImageId, $this->mediaAltText, $this->mediaCaption);
        } catch (CatalogException $exception) {
            $this->addError('media', $exception->getMessage());

            return;
        }
        $this->closeImageMetadata();
        session()->flash('success', 'Image text saved.');
    }

    /** Close the metadata dialog without editing the image. */
    public function closeImageMetadata(): void
    {
        $this->editingImageId = null;
        $this->mediaAltText = '';
        $this->mediaCaption = '';
        $this->resetValidation();
    }

    /** Move a gallery image by one slot. */
    public function moveGalleryImage(int $mediaId, string $direction, CatalogMediaService $media): void
    {
        abort_unless(in_array($direction, ['up', 'down'], true), 422);
        $tour = $this->tour();
        $ids = $tour->getMedia('tour_gallery')->pluck('id')->map(static fn ($id): int => (int) $id)->values()->all();
        $index = array_search($mediaId, $ids, true);
        abort_if($index === false, 404);
        $swap = $direction === 'up' ? $index - 1 : $index + 1;
        if (array_key_exists($swap, $ids)) {
            [$ids[$index], $ids[$swap]] = [$ids[$swap], $ids[$index]];
            $media->reorderTourGallery($tour, $ids);
        }
    }

    /** Remove one gallery image from the selected tour. */
    public function removeGalleryImage(int $mediaId, CatalogMediaService $media): void
    {
        try {
            $media->removeTourGalleryImage($this->tour(), $mediaId);
        } catch (CatalogException $exception) {
            $this->addError('media', $exception->getMessage());
        }
    }

    /** Add one public traveler document. */
    public function addDocument(CatalogMediaService $media): void
    {
        $tour = $this->tour();
        $this->validate([
            'documentUpload' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:'.max(1, (int) config('travel-tours.media.upload_max_kilobytes', 6144))],
            'documentTitle' => ['required', 'string', 'max:180'],
            'documentDescription' => ['nullable', 'string', 'max:500'],
        ]);
        try {
            $media->addTourDocument($tour, $this->documentUpload, $this->documentTitle, $this->documentDescription);
        } catch (CatalogException $exception) {
            $this->addError('media', $exception->getMessage());

            return;
        }
        $this->documentUpload = null;
        $this->documentTitle = '';
        $this->documentDescription = '';
        session()->flash('success', 'Tour attachment added.');
    }

    /** Remove one owned public attachment. */
    public function removeDocument(int $mediaId, CatalogMediaService $media): void
    {
        try {
            $media->removeTourDocument($this->tour(), $mediaId);
        } catch (CatalogException $exception) {
            $this->addError('media', $exception->getMessage());
        }
    }

    /** Render media from the current tour after each mutation. */
    public function render(): View
    {
        return view('travel-tours::livewire.admin.catalog.tour-media-editor', ['tour' => $this->tour()->load('media')]);
    }

    /** Resolve and authorize a writable tour. */
    private function tour(): Tour
    {
        $tour = Tour::query()->findOrFail($this->tourId);
        Gate::authorize('update', $tour);

        return $tour;
    }

    /** @return array<string, mixed> */
    private function imageRules(string $field): array
    {
        $altField = $field === 'coverUpload' ? 'coverAltText' : 'galleryAltText';
        $captionField = $field === 'coverUpload' ? 'coverCaption' : 'galleryCaption';

        return [
            $field => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.max(1, (int) config('travel-tours.media.upload_max_kilobytes', 6144))],
            $altField => ['required', 'string', 'max:180'],
            $captionField => ['nullable', 'string', 'max:320'],
        ];
    }

    /** Clear temporary image fields without removing persisted media. */
    private function clearCoverInput(): void
    {
        $this->coverUpload = null;
        $this->coverAltText = '';
        $this->coverCaption = '';
    }

    /** Clear gallery upload state while leaving cover and metadata edits alone. */
    private function clearGalleryInput(): void
    {
        $this->galleryUpload = null;
        $this->galleryAltText = '';
        $this->galleryCaption = '';
    }

    /** Convert an upload filename into editable human-readable alternative text. */
    private function filenameAltText(mixed $upload): string
    {
        if (! $upload instanceof UploadedFile) {
            return '';
        }

        return mb_substr(Str::headline(pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME)), 0, 180);
    }
}
