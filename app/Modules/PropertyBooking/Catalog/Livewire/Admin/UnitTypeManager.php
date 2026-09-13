<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Catalog\Livewire\Admin;

use App\Models\User;
use App\Modules\PropertyBooking\Catalog\Enums\AmenityScope;
use App\Modules\PropertyBooking\Catalog\Enums\UnitTypeStatus;
use App\Modules\PropertyBooking\Catalog\Exceptions\CatalogException;
use App\Modules\PropertyBooking\Catalog\Livewire\Forms\UnitTypeForm;
use App\Modules\PropertyBooking\Catalog\Models\Amenity;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Catalog\Models\UnitType;
use App\Modules\PropertyBooking\Catalog\Services\AmenityService;
use App\Modules\PropertyBooking\Catalog\Services\UnitTypeService;
use App\Modules\PropertyBooking\Support\PropertyAccessService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/** Unit-type CRUD, lifecycle, occupancy, amenity, and gallery administration. */
final class UnitTypeManager extends Component
{
    use WithFileUploads;
    use WithPagination;

    #[Url(as: 'type-q', except: '')]
    public string $search = '';

    #[Url(as: 'type-property', except: '')]
    public string $propertyFilter = '';

    #[Url(as: 'type-status', except: '')]
    public string $statusFilter = '';

    public UnitTypeForm $form;

    public string $dialog = '';

    #[Locked]
    public ?int $selectedUnitTypeId = null;

    #[Locked]
    public ?int $selectedMediaId = null;

    public mixed $coverUpload = null;

    public mixed $galleryUpload = null;

    public string $mediaAltText = '';

    public string $mediaCaption = '';

    public mixed $formCoverUpload = null;

    public string $formCoverAltText = '';

    public string $formCoverCaption = '';

    /** @var array<int, mixed> */
    public array $formGalleryUploads = [];

    /** @var array<int, string> */
    public array $formGalleryAltTexts = [];

    /** @var array<int, string> */
    public array $formGalleryCaptions = [];

    private PropertyAccessService $access;

    /** Reauthorize and restore property scope for every Livewire request. */
    public function boot(PropertyAccessService $access): void
    {
        $this->access = $access;
        Gate::authorize('viewAny', UnitType::class);
    }

    /** Reset pagination when search changes. */
    public function updatedSearch(): void
    {
        $this->resetUnitTypePage();
    }

    /** Reset pagination when property changes. */
    public function updatedPropertyFilter(): void
    {
        $this->resetUnitTypePage();
    }

    /** Reset pagination when lifecycle state changes. */
    public function updatedStatusFilter(): void
    {
        $this->resetUnitTypePage();
    }

    /** Validate and describe a pending cover selected inside the unit-type form. */
    public function updatedFormCoverUpload(): void
    {
        $this->authorizeFormMutation();
        $this->validateOnly('formCoverUpload', $this->formMediaRules());

        if ($this->formCoverUpload !== null && trim($this->formCoverAltText) === '') {
            $this->formCoverAltText = $this->defaultFormMediaAltText('cover');
        }
    }

    /** Validate pending gallery files and initialize editable metadata for each image. */
    public function updatedFormGalleryUploads(): void
    {
        $this->authorizeFormMutation();
        $this->validate($this->formGalleryUploadRules());
        $this->normalizeFormGalleryMetadata(true);
    }

    /** @return list<UnitTypeStatus> */
    #[Computed]
    public function statuses(): array
    {
        return UnitTypeStatus::cases();
    }

    /** @return Collection<int, Property> */
    #[Computed]
    public function propertyOptions(): Collection
    {
        return $this->access->scope(Property::query(), $this->actor(), 'property_booking_properties.id')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'currency']);
    }

    /** @return Collection<int, Amenity> */
    #[Computed]
    public function amenityOptions(): Collection
    {
        return Amenity::query()
            ->whereIn('scope', [AmenityScope::Unit->value, AmenityScope::Both->value])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /** @return array{total: int, published: int, draft: int, featured: int} */
    #[Computed]
    public function statistics(): array
    {
        return [
            'total' => $this->scopedUnitTypeQuery()->count(),
            'published' => $this->scopedUnitTypeQuery()->where('status', UnitTypeStatus::Published->value)->count(),
            'draft' => $this->scopedUnitTypeQuery()->where('status', UnitTypeStatus::Draft->value)->count(),
            'featured' => $this->scopedUnitTypeQuery()->where('is_featured', true)->count(),
        ];
    }

    /** Return the scoped and filtered unit-type register. */
    #[Computed]
    public function unitTypes(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return $this->scopedUnitTypeQuery()
            ->when($search !== '', static function (Builder $query) use ($search): void {
                $query->where(static function (Builder $match) use ($search): void {
                    $match->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when($this->propertyOptions->contains('id', (int) $this->propertyFilter), fn (Builder $query): Builder => $query->where('property_id', (int) $this->propertyFilter))
            ->when(UnitTypeStatus::tryFrom($this->statusFilter), static fn (Builder $query, UnitTypeStatus $status): Builder => $query->where('status', $status->value))
            ->with(['property', 'media'])
            ->withCount(['units', 'ratePlans'])
            ->orderByDesc('created_at')
            ->paginate(12, ['*'], 'unitTypePage');
    }

    /** Resolve the unit type currently shown by a media dialog. */
    #[Computed]
    public function mediaUnitType(): ?UnitType
    {
        return $this->selectedUnitTypeId === null ? null : $this->selectedUnitType();
    }

    /** Resolve the scoped unit type whose current media is shown inside edit CRUD. */
    #[Computed]
    public function formUnitType(): ?UnitType
    {
        return $this->selectedUnitTypeId === null ? null : $this->selectedUnitType();
    }

    /** Open an empty unit-type form. */
    public function openCreate(): void
    {
        Gate::authorize('create', UnitType::class);
        $this->closeDialog();
        $propertyId = $this->propertyOptions->contains('id', (int) $this->propertyFilter) ? (int) $this->propertyFilter : null;
        $this->form->resetForCreate($propertyId);
        $this->dialog = 'form';
    }

    /** Open one scoped unit type for editing. */
    public function openEdit(int $unitTypeId): void
    {
        $unitType = $this->findUnitType($unitTypeId);
        Gate::authorize('update', $unitType);
        $this->closeDialog();
        $this->selectedUnitTypeId = $unitType->getKey();
        $this->form->fillFromUnitType($unitType);
        $this->dialog = 'form';
    }

    /** Persist unit-type metadata and amenity assignments. */
    public function save(UnitTypeService $unitTypes, AmenityService $amenities): void
    {
        $this->resetErrorBag('management');
        $this->form->validate();
        $this->normalizeFormGalleryMetadata();
        $this->validate($this->formMediaRules());
        $actor = $this->actor();

        try {
            if ($this->selectedUnitTypeId === null) {
                Gate::authorize('create', UnitType::class);
                $property = $this->findProperty((int) $this->form->propertyId);
                $this->access->authorize($actor, $property);
                $unitType = $unitTypes->create($property, $this->form->payload(), $actor);
                $message = 'Unit type created as a draft.';
            } else {
                $unitType = $this->findUnitType($this->selectedUnitTypeId);
                Gate::authorize('update', $unitType);
                if (! $this->form->unitType?->is($unitType) || (int) $this->form->propertyId !== $unitType->property_id) {
                    abort(404);
                }
                $unitType = $unitTypes->update($unitType, $this->form->payload(), $actor);
                $message = 'Unit-type details updated.';
            }

            $amenities->syncUnitTypeAmenities($unitType, $this->integerIds($this->form->amenityIds), $actor);
            $this->persistFormMedia($unitType, $unitTypes, $actor);
        } catch (CatalogException|InvalidArgumentException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        $this->closeDialog();
        session()->flash('success', $message);
        $this->refreshUnitTypes();
    }

    /** Move a unit type through its controlled publication lifecycle. */
    public function changeStatus(int $unitTypeId, string $status, UnitTypeService $unitTypes): void
    {
        $unitType = $this->findUnitType($unitTypeId);
        Gate::authorize('update', $unitType);
        $target = UnitTypeStatus::tryFrom($status);
        abort_if($target === null, 422);

        try {
            $unitTypes->transition($unitType, $target, $this->actor());
        } catch (CatalogException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        session()->flash('success', "Unit-type status changed to {$target->label()}.");
        $this->refreshUnitTypes();
    }

    /** Open the cover/gallery workspace. */
    public function openMedia(int $unitTypeId): void
    {
        $unitType = $this->findUnitType($unitTypeId);
        Gate::authorize('update', $unitType);
        $this->closeDialog();
        $this->selectedUnitTypeId = $unitType->getKey();
        $this->dialog = 'media';
    }

    /** Replace the selected unit-type cover. */
    public function replaceCover(UnitTypeService $unitTypes): void
    {
        $unitType = $this->selectedUnitType();
        Gate::authorize('update', $unitType);
        $this->validate($this->mediaRules('coverUpload'));

        try {
            $unitTypes->replaceCover($unitType, $this->coverUpload, $this->mediaAltText, $this->mediaCaption, $this->actor());
        } catch (CatalogException $exception) {
            $this->addError('media', $exception->getMessage());

            return;
        }

        $this->clearMediaInput();
        session()->flash('success', 'Unit-type cover updated.');
        $this->refreshUnitTypes();
    }

    /** Remove the optional selected unit-type cover. */
    public function removeCover(UnitTypeService $unitTypes): void
    {
        $unitType = $this->selectedUnitType();
        Gate::authorize('update', $unitType);
        $unitTypes->removeCover($unitType, $this->actor());
        session()->flash('success', 'Unit-type cover removed.');
        $this->refreshUnitTypes();
    }

    /** Add one accessible image to the selected unit-type gallery. */
    public function addGalleryImage(UnitTypeService $unitTypes): void
    {
        $unitType = $this->selectedUnitType();
        Gate::authorize('update', $unitType);
        $this->validate($this->mediaRules('galleryUpload'));

        try {
            $unitTypes->addGalleryImages(
                $unitType,
                [$this->galleryUpload],
                [['alt_text' => $this->mediaAltText, 'caption' => $this->mediaCaption]],
                $this->actor(),
            );
        } catch (CatalogException $exception) {
            $this->addError('media', $exception->getMessage());

            return;
        }

        $this->clearMediaInput();
        session()->flash('success', 'Unit-type gallery image added.');
        $this->refreshUnitTypes();
    }

    /** Open metadata editing for one owned unit-type image. */
    public function openMediaMetadata(int $mediaId): void
    {
        $unitType = $this->selectedUnitType();
        Gate::authorize('update', $unitType);
        $media = $unitType->media()->whereIn('collection_name', ['unit_type_cover', 'unit_type_gallery'])->whereKey($mediaId)->firstOrFail();
        $this->selectedMediaId = $media->getKey();
        $this->mediaAltText = (string) $media->getCustomProperty('alt_text', '');
        $this->mediaCaption = (string) $media->getCustomProperty('caption', '');
        $this->dialog = 'metadata';
    }

    /** Persist unit-type image alternative text and caption. */
    public function saveMediaMetadata(UnitTypeService $unitTypes): void
    {
        $unitType = $this->selectedUnitType();
        Gate::authorize('update', $unitType);
        $this->validate(['mediaAltText' => ['required', 'string', 'max:180'], 'mediaCaption' => ['nullable', 'string', 'max:320']]);

        try {
            $unitTypes->updateMediaMetadata($unitType, (int) $this->selectedMediaId, $this->mediaAltText, $this->mediaCaption, $this->actor());
        } catch (CatalogException $exception) {
            $this->addError('media', $exception->getMessage());

            return;
        }

        $this->selectedMediaId = null;
        $this->clearMediaInput();
        $this->dialog = 'media';
        session()->flash('success', 'Unit-type image metadata updated.');
        $this->refreshUnitTypes();
    }

    /** Move one gallery image by one position. */
    public function moveGalleryImage(int $mediaId, string $direction, UnitTypeService $unitTypes): void
    {
        $unitType = $this->selectedUnitType();
        Gate::authorize('update', $unitType);
        abort_unless(in_array($direction, ['up', 'down'], true), 422);
        $ids = $unitType->getMedia('unit_type_gallery')->pluck('id')->map(static fn (mixed $id): int => (int) $id)->values()->all();
        $index = array_search($mediaId, $ids, true);
        if ($index === false) {
            abort(404);
        }
        $swap = $direction === 'up' ? $index - 1 : $index + 1;
        if (! array_key_exists($swap, $ids)) {
            return;
        }
        [$ids[$index], $ids[$swap]] = [$ids[$swap], $ids[$index]];
        $unitTypes->reorderGallery($unitType, $ids, $this->actor());
        $this->refreshUnitTypes();
    }

    /** Remove one image only from the selected unit-type gallery. */
    public function removeGalleryImage(int $mediaId, UnitTypeService $unitTypes): void
    {
        $unitType = $this->selectedUnitType();
        Gate::authorize('update', $unitType);

        try {
            $unitTypes->removeGalleryImage($unitType, $mediaId, $this->actor());
        } catch (CatalogException $exception) {
            $this->addError('media', $exception->getMessage());

            return;
        }

        session()->flash('success', 'Unit-type gallery image removed.');
        $this->refreshUnitTypes();
    }

    /** Return from metadata editing to the media workspace. */
    public function closeMetadata(): void
    {
        $this->selectedMediaId = null;
        $this->clearMediaInput();
        $this->dialog = 'media';
        $this->resetValidation();
    }

    /** Clear unit-type filters. */
    public function clearFilters(): void
    {
        $this->search = '';
        $this->propertyFilter = '';
        $this->statusFilter = '';
        $this->resetUnitTypePage();
    }

    /** Close any modal and clear temporary state. */
    public function closeDialog(): void
    {
        $this->dialog = '';
        $this->selectedUnitTypeId = null;
        $this->selectedMediaId = null;
        $this->clearMediaInput();
        $this->clearFormMediaInput();
        $this->form->resetForCreate();
        $this->resetValidation();
    }

    /** Render the module-owned unit-type manager. */
    public function render(): View
    {
        return view('property-booking::livewire.admin.unit-type-manager');
    }

    /** @return array<string, mixed> */
    private function mediaRules(string $field): array
    {
        $maximumKilobytes = max(1, (int) config('property-booking.media.upload_max_kilobytes', 5120));

        return [
            $field => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.$maximumKilobytes],
            'mediaAltText' => ['required', 'string', 'max:180'],
            'mediaCaption' => ['nullable', 'string', 'max:320'],
        ];
    }

    /** @return array<string, mixed> */
    private function formGalleryUploadRules(): array
    {
        $maximumKilobytes = max(1, (int) config('property-booking.media.upload_max_kilobytes', 5120));

        return [
            'formGalleryUploads' => ['array', 'max:'.$this->remainingFormGallerySlots()],
            'formGalleryUploads.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.$maximumKilobytes],
        ];
    }

    /** @return array<string, mixed> */
    private function formMediaRules(): array
    {
        $maximumKilobytes = max(1, (int) config('property-booking.media.upload_max_kilobytes', 5120));
        $coverRequired = $this->formCoverUpload !== null ? 'required' : 'nullable';

        return [
            'formCoverUpload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.$maximumKilobytes],
            'formCoverAltText' => [$coverRequired, 'string', 'max:180'],
            'formCoverCaption' => ['nullable', 'string', 'max:320'],
            ...$this->formGalleryUploadRules(),
            'formGalleryAltTexts' => ['array'],
            'formGalleryAltTexts.*' => ['required', 'string', 'max:180'],
            'formGalleryCaptions' => ['array'],
            'formGalleryCaptions.*' => ['nullable', 'string', 'max:320'],
        ];
    }

    /** Persist cover and gallery uploads selected as part of create/edit CRUD. */
    private function persistFormMedia(UnitType $unitType, UnitTypeService $unitTypes, User $actor): void
    {
        if ($this->formCoverUpload !== null) {
            $unitTypes->replaceCover(
                $unitType,
                $this->formCoverUpload,
                trim($this->formCoverAltText),
                $this->nullableTrimmed($this->formCoverCaption),
                $actor,
            );
        }

        if ($this->formGalleryUploads !== []) {
            $metadata = [];
            foreach (array_keys($this->formGalleryUploads) as $index) {
                $metadata[] = [
                    'alt_text' => trim((string) ($this->formGalleryAltTexts[$index] ?? '')),
                    'caption' => $this->nullableTrimmed((string) ($this->formGalleryCaptions[$index] ?? '')),
                ];
            }

            $unitTypes->addGalleryImages($unitType, array_values($this->formGalleryUploads), $metadata, $actor);
        }
    }

    /** Keep upload metadata keyed exactly to Livewire's current temporary files. */
    private function normalizeFormGalleryMetadata(bool $provideDefaults = false): void
    {
        $altTexts = [];
        $captions = [];

        foreach (array_keys($this->formGalleryUploads) as $index) {
            $altText = trim((string) ($this->formGalleryAltTexts[$index] ?? ''));
            $altTexts[$index] = $provideDefaults && $altText === ''
                ? $this->defaultFormMediaAltText('gallery', (int) $index)
                : $altText;
            $captions[$index] = (string) ($this->formGalleryCaptions[$index] ?? '');
        }

        $this->formGalleryAltTexts = $altTexts;
        $this->formGalleryCaptions = $captions;
    }

    /** Reauthorize a create/edit upload update before accepting temporary files. */
    private function authorizeFormMutation(): void
    {
        if ($this->selectedUnitTypeId === null) {
            Gate::authorize('create', UnitType::class);

            return;
        }

        Gate::authorize('update', $this->findUnitType($this->selectedUnitTypeId));
    }

    /** Return the number of images the current CRUD form may still append. */
    private function remainingFormGallerySlots(): int
    {
        $limit = max(1, (int) config('property-booking.media.unit_type_gallery_limit', 10));
        if ($this->selectedUnitTypeId === null) {
            return $limit;
        }

        return max(0, $limit - $this->findUnitType($this->selectedUnitTypeId)->getMedia('unit_type_gallery')->count());
    }

    /** Generate useful editable metadata when a user first selects an image. */
    private function defaultFormMediaAltText(string $kind, int $index = 0): string
    {
        $name = trim($this->form->name) !== '' ? trim($this->form->name) : 'Unit type';

        return $kind === 'cover' ? "{$name} cover image" : "{$name} gallery image ".($index + 1);
    }

    /** Normalize optional media copy before handing it to the catalog service. */
    private function nullableTrimmed(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /** Build the authorized unit-type query. */
    private function scopedUnitTypeQuery(): Builder
    {
        return $this->access->scope(UnitType::query(), $this->actor(), 'property_booking_unit_types.property_id');
    }

    /** Resolve one property only through the actor's assigned scope. */
    private function findProperty(int $propertyId): Property
    {
        return $this->access->scope(Property::query(), $this->actor(), 'property_booking_properties.id')->findOrFail($propertyId);
    }

    /** Resolve one unit type only through the actor's assigned scope. */
    private function findUnitType(int $unitTypeId): UnitType
    {
        return $this->scopedUnitTypeQuery()->with(['property', 'amenities', 'media'])->findOrFail($unitTypeId);
    }

    /** Resolve the selected unit type while retaining the media dialog. */
    private function selectedUnitType(): UnitType
    {
        abort_if($this->selectedUnitTypeId === null, 404);

        return $this->findUnitType($this->selectedUnitTypeId);
    }

    /** Resolve the authenticated actor. */
    private function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    /** @param list<int|string> $ids @return list<int> */
    private function integerIds(array $ids): array
    {
        return collect($ids)->map(static fn (mixed $id): int => (int) $id)->filter()->unique()->values()->all();
    }

    /** Reset pagination and unit-type computed state. */
    private function resetUnitTypePage(): void
    {
        $this->resetPage('unitTypePage');
        $this->refreshUnitTypes();
    }

    /** Invalidate unit-type derived computed state. */
    private function refreshUnitTypes(): void
    {
        unset($this->unitTypes, $this->statistics, $this->mediaUnitType, $this->formUnitType);
    }

    /** Clear temporary upload and metadata input. */
    private function clearMediaInput(): void
    {
        $this->coverUpload = null;
        $this->galleryUpload = null;
        $this->mediaAltText = '';
        $this->mediaCaption = '';
    }

    /** Clear temporary media selected inside the create/edit form. */
    private function clearFormMediaInput(): void
    {
        $this->formCoverUpload = null;
        $this->formCoverAltText = '';
        $this->formCoverCaption = '';
        $this->formGalleryUploads = [];
        $this->formGalleryAltTexts = [];
        $this->formGalleryCaptions = [];
    }
}
