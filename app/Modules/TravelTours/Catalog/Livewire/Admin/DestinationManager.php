<?php

/** Provides policy-authorized TravelTours destination administration. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Livewire\Admin;

use App\Models\User;
use App\Modules\TravelTours\Catalog\Enums\DestinationType;
use App\Modules\TravelTours\Catalog\Enums\PublicationStatus;
use App\Modules\TravelTours\Catalog\Exceptions\CatalogException;
use App\Modules\TravelTours\Catalog\Livewire\Forms\DestinationForm;
use App\Modules\TravelTours\Catalog\Models\Destination;
use App\Modules\TravelTours\Catalog\Services\CatalogMediaService;
use App\Modules\TravelTours\Catalog\Services\DestinationService;
use App\Modules\TravelTours\Support\LikePattern;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/** Present searchable destinations, editorial forms, and owned media controls. */
final class DestinationManager extends Component
{
    use WithFileUploads;
    use WithPagination;

    public DestinationForm $form;

    public string $search = '';

    public string $typeFilter = '';

    public string $statusFilter = '';

    public string $dialog = '';

    #[Locked]
    public ?int $selectedDestinationId = null;

    #[Locked]
    public ?int $selectedMediaId = null;

    public mixed $coverUpload = null;

    public mixed $galleryUpload = null;

    public string $coverAltText = '';

    public string $coverCaption = '';

    public string $galleryAltText = '';

    public string $galleryCaption = '';

    public string $mediaAltText = '';

    public string $mediaCaption = '';

    /** Suggest editable alt text from the selected cover filename. */
    public function updatedCoverUpload(): void
    {
        $this->coverAltText = $this->filenameAltText($this->coverUpload);
    }

    /** Suggest editable alt text from the selected gallery filename. */
    public function updatedGalleryUpload(): void
    {
        $this->galleryAltText = $this->filenameAltText($this->galleryUpload);
    }

    /** Reauthorize every Livewire request, including hydration requests. */
    public function boot(): void
    {
        Gate::authorize('viewAny', Destination::class);
    }

    /** Reset the bounded list when search input changes. */
    public function updatedSearch(): void
    {
        $this->resetPage('destinationPage');
    }

    /** Reset the bounded list when type filtering changes. */
    public function updatedTypeFilter(): void
    {
        $this->resetPage('destinationPage');
    }

    /** Reset the bounded list when status filtering changes. */
    public function updatedStatusFilter(): void
    {
        $this->resetPage('destinationPage');
    }

    #[Computed]
    /** @return Collection<int, Destination> */
    public function parentOptions(): Collection
    {
        return Destination::query()
            ->where('is_active', true)
            ->when($this->selectedDestinationId, fn (Builder $query) => $query->whereKeyNot($this->selectedDestinationId))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    /** Resolve the selected destination with media for its edit workspace. */
    public function selectedDestination(): ?Destination
    {
        return $this->selectedDestinationId === null
            ? null
            : Destination::query()->with(['parent', 'media'])->findOrFail($this->selectedDestinationId);
    }

    /** Open a fresh destination form only for catalog editors. */
    public function openCreate(): void
    {
        Gate::authorize('create', Destination::class);
        $this->closeDialog();
        $this->dialog = 'form';
    }

    /** Load one authorized destination for metadata editing. */
    public function openEdit(int $destinationId): void
    {
        $destination = Destination::query()->findOrFail($destinationId);
        Gate::authorize('update', $destination);
        $this->closeDialog();
        $this->selectedDestinationId = $destination->getKey();
        $this->form->fillFromDestination($destination);
        $this->dialog = 'form';
    }

    /** Persist validated editorial fields without changing public state. */
    public function save(DestinationService $destinations): void
    {
        abort_unless($this->dialog === 'form', 404);
        $this->form->validate();

        try {
            if ($this->selectedDestinationId === null) {
                Gate::authorize('create', Destination::class);
                $destinations->create($this->form->toData(), $this->actor());
                $message = 'Destination created as an editorial item.';
            } else {
                $destination = Destination::query()->findOrFail($this->selectedDestinationId);
                Gate::authorize('update', $destination);
                $destinations->update($destination, $this->form->toData(), $this->actor());
                $message = 'Destination updated.';
            }
        } catch (CatalogException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        $this->closeDialog();
        session()->flash('success', $message);
    }

    /** Toggle assignment availability without crossing publication rules. */
    public function toggleActive(int $destinationId, DestinationService $destinations): void
    {
        $destination = Destination::query()->findOrFail($destinationId);
        Gate::authorize('update', $destination);

        try {
            $destinations->setActive($destination, ! $destination->is_active, $this->actor());
        } catch (CatalogException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        session()->flash('success', $destination->is_active ? 'Destination hidden.' : 'Destination activated.');
    }

    /** Open image controls only for a writable destination. */
    public function openMedia(int $destinationId): void
    {
        $destination = Destination::query()->findOrFail($destinationId);
        Gate::authorize('update', $destination);
        $this->closeDialog();
        $this->selectedDestinationId = $destination->getKey();
        $this->dialog = 'media';
    }

    /** Replace the cover through the media service. */
    public function replaceCover(CatalogMediaService $media): void
    {
        $destination = $this->writableDestination();
        $this->validate($this->uploadRules('coverUpload'));

        try {
            $media->replaceDestinationCover($destination, $this->coverUpload, $this->coverAltText, $this->coverCaption);
        } catch (CatalogException $exception) {
            $this->addError('media', $exception->getMessage());

            return;
        }

        $this->resetCoverInput();
        unset($this->selectedDestination);
        session()->flash('success', 'Destination cover updated.');
    }

    /** Add one accessible gallery image through the media service. */
    public function addGalleryImage(CatalogMediaService $media): void
    {
        $destination = $this->writableDestination();
        $this->validate($this->uploadRules('galleryUpload'));

        try {
            $media->addDestinationGalleryImage($destination, $this->galleryUpload, $this->galleryAltText, $this->galleryCaption);
        } catch (CatalogException $exception) {
            $this->addError('media', $exception->getMessage());

            return;
        }

        $this->resetGalleryInput();
        unset($this->selectedDestination);
        session()->flash('success', 'Destination gallery image added.');
    }

    /** Remove the selected destination's cover. */
    public function removeCover(CatalogMediaService $media): void
    {
        $media->removeDestinationCover($this->writableDestination());
        unset($this->selectedDestination);
        session()->flash('success', 'Destination cover removed.');
    }

    /** Remove one image only from the selected destination's gallery. */
    public function removeGalleryImage(int $mediaId, CatalogMediaService $media): void
    {
        try {
            $media->removeDestinationGalleryImage($this->writableDestination(), $mediaId);
        } catch (CatalogException $exception) {
            $this->addError('media', $exception->getMessage());

            return;
        }

        unset($this->selectedDestination);
        session()->flash('success', 'Destination gallery image removed.');
    }

    /** Open accessible metadata editing for one image owned by the selected destination. */
    public function openMediaMetadata(int $mediaId): void
    {
        $destination = $this->writableDestination();
        $image = $destination->media()
            ->whereIn('collection_name', ['destination_cover', 'destination_gallery'])
            ->whereKey($mediaId)
            ->firstOrFail();
        $this->selectedMediaId = $image->getKey();
        $this->mediaAltText = (string) $image->getCustomProperty('alt_text', '');
        $this->mediaCaption = (string) $image->getCustomProperty('caption', '');
        $this->dialog = 'metadata';
    }

    /** Save accessible metadata through the destination media service. */
    public function saveMediaMetadata(CatalogMediaService $media): void
    {
        abort_unless($this->selectedMediaId !== null, 404);
        $destination = $this->selectedDestinationId === null
            ? abort(404)
            : Destination::query()->with('media')->findOrFail($this->selectedDestinationId);
        Gate::authorize('update', $destination);
        $this->validate([
            'mediaAltText' => ['required', 'string', 'max:180'],
            'mediaCaption' => ['nullable', 'string', 'max:320'],
        ]);

        try {
            $media->updateDestinationMedia($destination, $this->selectedMediaId, $this->mediaAltText, $this->mediaCaption);
        } catch (CatalogException $exception) {
            $this->addError('media', $exception->getMessage());

            return;
        }

        $this->selectedMediaId = null;
        $this->resetMediaInput();
        $this->dialog = 'media';
        unset($this->selectedDestination);
        session()->flash('success', 'Destination image metadata updated.');
    }

    /** Return to the selected destination's media workspace. */
    public function closeMetadata(): void
    {
        $this->selectedMediaId = null;
        $this->resetMediaInput();
        $this->dialog = 'media';
        $this->resetValidation();
    }

    /** Move one owned gallery image by one position. */
    public function moveGalleryImage(int $mediaId, string $direction, CatalogMediaService $media): void
    {
        $destination = $this->writableDestination();
        abort_unless(in_array($direction, ['up', 'down'], true), 422);
        $ids = $destination->getMedia('destination_gallery')->pluck('id')->map(static fn ($id): int => (int) $id)->values()->all();
        $index = array_search($mediaId, $ids, true);
        abort_if($index === false, 404);
        $swap = $direction === 'up' ? $index - 1 : $index + 1;
        if (! array_key_exists($swap, $ids)) {
            return;
        }

        [$ids[$index], $ids[$swap]] = [$ids[$swap], $ids[$index]];
        $media->reorderDestinationGallery($destination, $ids);
        unset($this->selectedDestination);
    }

    /** Clear all list filters and return to the first page. */
    public function clearFilters(): void
    {
        $this->search = '';
        $this->typeFilter = '';
        $this->statusFilter = '';
        $this->resetPage('destinationPage');
    }

    /** Close the current editor and discard temporary uploads. */
    public function closeDialog(): void
    {
        $this->dialog = '';
        $this->selectedDestinationId = null;
        $this->selectedMediaId = null;
        $this->form->resetForCreate();
        $this->resetMediaInput();
        $this->resetValidation();
        unset($this->selectedDestination, $this->parentOptions);
    }

    /** Render a bounded, filtered destination page. */
    public function render(): View
    {
        $search = trim($this->search);
        $type = DestinationType::tryFrom($this->typeFilter);
        $status = PublicationStatus::tryFrom($this->statusFilter);
        $destinations = Destination::query()
            ->with(['parent', 'media'])
            ->withCount(['children', 'tours'])
            ->when($search !== '', static fn (Builder $query) => $query->where(static fn (Builder $match) => $match
                ->whereRaw('name '.LikePattern::CLAUSE, [LikePattern::contains($search)])
                ->orWhereRaw('code '.LikePattern::CLAUSE, [LikePattern::contains($search)])
                ->orWhereRaw('country_code '.LikePattern::CLAUSE, [LikePattern::contains($search)])))
            ->when($type, static fn (Builder $query) => $query->where('type', $type->value))
            ->when($status, static fn (Builder $query) => $query->where('status', $status->value))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(12, pageName: 'destinationPage');

        return view('travel-tours::livewire.admin.catalog.destination-manager', compact('destinations'));
    }

    /** Resolve the current destination and authorize a media mutation. */
    private function writableDestination(): Destination
    {
        abort_unless($this->dialog === 'media' && $this->selectedDestinationId !== null, 404);
        $destination = Destination::query()->with('media')->findOrFail($this->selectedDestinationId);
        Gate::authorize('update', $destination);

        return $destination;
    }

    /** Resolve the authenticated editor for actor attribution. */
    private function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    /** @return array<string, mixed> */
    private function uploadRules(string $field): array
    {
        $maximumKilobytes = max(1, (int) config('travel-tours.media.upload_max_kilobytes', 6144));
        $altField = $field === 'coverUpload' ? 'coverAltText' : 'galleryAltText';
        $captionField = $field === 'coverUpload' ? 'coverCaption' : 'galleryCaption';

        return [
            $field => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.$maximumKilobytes],
            $altField => ['required', 'string', 'max:180'],
            $captionField => ['nullable', 'string', 'max:320'],
        ];
    }

    /** Clear transient upload fields while keeping the media dialog open. */
    private function resetMediaInput(): void
    {
        $this->resetCoverInput();
        $this->resetGalleryInput();
        $this->mediaAltText = '';
        $this->mediaCaption = '';
    }

    /** Clear only the cover draft after a successful upload. */
    private function resetCoverInput(): void
    {
        $this->coverUpload = null;
        $this->coverAltText = '';
        $this->coverCaption = '';
    }

    /** Clear only the gallery draft after a successful upload. */
    private function resetGalleryInput(): void
    {
        $this->galleryUpload = null;
        $this->galleryAltText = '';
        $this->galleryCaption = '';
    }

    /** Derive an editable, human-readable alt suggestion from a local filename. */
    private function filenameAltText(mixed $upload): string
    {
        if (! $upload instanceof UploadedFile) {
            return '';
        }

        return mb_substr(Str::headline(pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME)), 0, 180);
    }
}
