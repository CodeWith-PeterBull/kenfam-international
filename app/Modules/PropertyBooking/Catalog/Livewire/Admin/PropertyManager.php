<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Catalog\Livewire\Admin;

use App\Models\User;
use App\Modules\PropertyBooking\Catalog\Enums\AmenityScope;
use App\Modules\PropertyBooking\Catalog\Enums\PropertyStatus;
use App\Modules\PropertyBooking\Catalog\Exceptions\CatalogException;
use App\Modules\PropertyBooking\Catalog\Livewire\Forms\PropertyForm;
use App\Modules\PropertyBooking\Catalog\Models\Amenity;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Catalog\Models\PropertyCategory;
use App\Modules\PropertyBooking\Catalog\Services\AmenityService;
use App\Modules\PropertyBooking\Catalog\Services\PropertyService;
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

/** Property CRUD, publication, staff scope, amenities, and media administration. */
final class PropertyManager extends Component
{
    use WithFileUploads;
    use WithPagination;

    #[Url(as: 'property-q', except: '')]
    public string $search = '';

    #[Url(as: 'property-status', except: '')]
    public string $statusFilter = '';

    #[Url(as: 'property-category', except: '')]
    public string $categoryFilter = '';

    #[Url(as: 'property-per-page', except: 12)]
    public int $perPage = 12;

    public PropertyForm $form;

    public string $dialog = '';

    #[Locked]
    public ?int $selectedPropertyId = null;

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

    /** Reauthorize and restore property scoping for every Livewire request. */
    public function boot(PropertyAccessService $access): void
    {
        $this->access = $access;
        Gate::authorize('viewAny', Property::class);
    }

    /** Reset pagination when property search changes. */
    public function updatedSearch(): void
    {
        $this->resetPropertyPage();
    }

    /** Reset pagination when lifecycle filtering changes. */
    public function updatedStatusFilter(): void
    {
        $this->resetPropertyPage();
    }

    /** Reset pagination when category filtering changes. */
    public function updatedCategoryFilter(): void
    {
        $this->resetPropertyPage();
    }

    /** Bound user-controlled page length. */
    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, [6, 12, 24, 48], true)) {
            $this->perPage = 12;
        }
        $this->resetPropertyPage();
    }

    /** Validate incoming cover files immediately. */
    public function updatedCoverUpload(): void
    {
        Gate::authorize('create', Property::class);
        $this->validate($this->coverRules(false));
    }

    /** Validate incoming gallery files immediately. */
    public function updatedGalleryUpload(): void
    {
        Gate::authorize('create', Property::class);
        $this->validate($this->galleryRules(false));
    }

    /** Validate and describe a pending cover selected inside the property form. */
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

    /** @return list<PropertyStatus> */
    #[Computed]
    public function statuses(): array
    {
        return PropertyStatus::cases();
    }

    /** @return Collection<int, PropertyCategory> */
    #[Computed]
    public function categories(): Collection
    {
        return PropertyCategory::query()->orderBy('sort_order')->orderBy('name')->get();
    }

    /** @return Collection<int, Amenity> */
    #[Computed]
    public function amenityOptions(): Collection
    {
        return Amenity::query()
            ->whereIn('scope', [AmenityScope::Property->value, AmenityScope::Both->value])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, User> */
    #[Computed]
    public function userOptions(): Collection
    {
        return User::query()->where('is_active', true)->orderBy('name')->orderBy('email')->get(['id', 'name', 'email']);
    }

    /** @return array{total: int, published: int, draft: int, featured: int} */
    #[Computed]
    public function statistics(): array
    {
        return [
            'total' => $this->scopedPropertyQuery()->count(),
            'published' => $this->scopedPropertyQuery()->where('status', PropertyStatus::Published->value)->count(),
            'draft' => $this->scopedPropertyQuery()->where('status', PropertyStatus::Draft->value)->count(),
            'featured' => $this->scopedPropertyQuery()->where('is_featured', true)->count(),
        ];
    }

    /** Return the authorized, filtered property register. */
    #[Computed]
    public function properties(): LengthAwarePaginator
    {
        return $this->filteredProperties()
            ->with(['category', 'media'])
            ->withCount(['unitTypes', 'units', 'ratePlans'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($this->validPerPage(), ['*'], 'propertyPage');
    }

    /** Resolve the property currently shown by a media dialog. */
    #[Computed]
    public function mediaProperty(): ?Property
    {
        return $this->selectedPropertyId === null ? null : $this->selectedProperty();
    }

    /** Resolve the scoped property whose current media is shown inside edit CRUD. */
    #[Computed]
    public function formProperty(): ?Property
    {
        return $this->selectedPropertyId === null ? null : $this->selectedProperty();
    }

    /** Open an empty property form. */
    public function openCreate(): void
    {
        Gate::authorize('create', Property::class);
        $this->closeDialog();
        $this->form->resetForCreate();
        $this->dialog = 'form';
    }

    /** Open one authorized property for editing. */
    public function openEdit(int $propertyId): void
    {
        $property = $this->findProperty($propertyId);
        Gate::authorize('update', $property);
        $this->closeDialog();
        $this->selectedPropertyId = $property->getKey();
        $this->form->fillFromProperty($property);
        $this->dialog = 'form';
    }

    /** Persist property details and validated associations through services. */
    public function save(PropertyService $properties, AmenityService $amenities): void
    {
        $this->resetErrorBag('management');
        $this->form->validate();
        $this->normalizeFormGalleryMetadata();
        $this->validate($this->formMediaRules());
        $actor = $this->actor();

        try {
            if ($this->selectedPropertyId === null) {
                Gate::authorize('create', Property::class);
                $property = $properties->create($this->form->payload(), $actor);
                $message = 'Property created as a draft.';
            } else {
                $property = $this->findProperty($this->selectedPropertyId);
                Gate::authorize('update', $property);
                if (! $this->form->property?->is($property)) {
                    abort(404);
                }
                $property = $properties->update($property, $this->form->payload(), $actor);
                $message = 'Property details updated.';
            }

            $amenities->syncPropertyAmenities($property, $this->integerIds($this->form->amenityIds), $actor);
            $properties->syncAssignedUsers(
                $property,
                $this->integerIds($this->form->assignedUserIds),
                $this->form->defaultUserId === '' ? null : (int) $this->form->defaultUserId,
                $actor,
            );
            $this->persistFormMedia($property, $properties, $actor);
        } catch (CatalogException|InvalidArgumentException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        $this->closeDialog();
        session()->flash('success', $message);
        $this->refreshProperties();
    }

    /** Move a property through its controlled lifecycle. */
    public function changeStatus(int $propertyId, string $status, PropertyService $properties): void
    {
        $property = $this->findProperty($propertyId);
        Gate::authorize('update', $property);
        $target = PropertyStatus::tryFrom($status);
        abort_if($target === null, 422);

        try {
            $properties->transition($property, $target, $this->actor());
        } catch (CatalogException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        session()->flash('success', "Property status changed to {$target->label()}.");
        $this->refreshProperties();
    }

    /** Open the ordered cover/gallery workspace for one property. */
    public function openMedia(int $propertyId): void
    {
        $property = $this->findProperty($propertyId);
        Gate::authorize('update', $property);
        $this->closeDialog();
        $this->selectedPropertyId = $property->getKey();
        $this->dialog = 'media';
    }

    /** Replace the selected property's cover image. */
    public function replaceCover(PropertyService $properties): void
    {
        $property = $this->selectedProperty();
        Gate::authorize('update', $property);
        $this->validate($this->coverRules(true));

        try {
            $properties->replaceCover($property, $this->coverUpload, $this->mediaAltText, $this->mediaCaption, $this->actor());
        } catch (CatalogException $exception) {
            $this->addError('media', $exception->getMessage());

            return;
        }

        $this->clearMediaInput();
        session()->flash('success', 'Property cover updated.');
        $this->refreshProperties();
    }

    /** Remove the selected property's cover image. */
    public function removeCover(PropertyService $properties): void
    {
        $property = $this->selectedProperty();
        Gate::authorize('update', $property);
        $properties->removeCover($property, $this->actor());
        session()->flash('success', 'Property cover removed.');
        $this->refreshProperties();
    }

    /** Add one accessible image to the selected property's ordered gallery. */
    public function addGalleryImage(PropertyService $properties): void
    {
        $property = $this->selectedProperty();
        Gate::authorize('update', $property);
        $this->validate($this->galleryRules(true));

        try {
            $properties->addGalleryImages(
                $property,
                [$this->galleryUpload],
                [['alt_text' => $this->mediaAltText, 'caption' => $this->mediaCaption]],
                $this->actor(),
            );
        } catch (CatalogException $exception) {
            $this->addError('media', $exception->getMessage());

            return;
        }

        $this->clearMediaInput();
        session()->flash('success', 'Property gallery image added.');
        $this->refreshProperties();
    }

    /** Open accessible metadata editing for one owned image. */
    public function openMediaMetadata(int $mediaId): void
    {
        $property = $this->selectedProperty();
        Gate::authorize('update', $property);
        $media = $property->media()->whereIn('collection_name', ['property_cover', 'property_gallery'])->whereKey($mediaId)->firstOrFail();
        $this->selectedMediaId = $media->getKey();
        $this->mediaAltText = (string) $media->getCustomProperty('alt_text', '');
        $this->mediaCaption = (string) $media->getCustomProperty('caption', '');
        $this->dialog = 'metadata';
    }

    /** Persist image alternative text and caption without replacing the file. */
    public function saveMediaMetadata(PropertyService $properties): void
    {
        $property = $this->selectedProperty();
        Gate::authorize('update', $property);
        $this->validate([
            'mediaAltText' => ['required', 'string', 'max:180'],
            'mediaCaption' => ['nullable', 'string', 'max:320'],
        ]);

        try {
            $properties->updateMediaMetadata($property, (int) $this->selectedMediaId, $this->mediaAltText, $this->mediaCaption, $this->actor());
        } catch (CatalogException $exception) {
            $this->addError('media', $exception->getMessage());

            return;
        }

        $this->selectedMediaId = null;
        $this->clearMediaInput();
        $this->dialog = 'media';
        session()->flash('success', 'Property image metadata updated.');
        $this->refreshProperties();
    }

    /** Move one gallery image by one position without accepting foreign ids. */
    public function moveGalleryImage(int $mediaId, string $direction, PropertyService $properties): void
    {
        $property = $this->selectedProperty();
        Gate::authorize('update', $property);
        abort_unless(in_array($direction, ['up', 'down'], true), 422);
        $ids = $property->getMedia('property_gallery')->pluck('id')->map(static fn (mixed $id): int => (int) $id)->values()->all();
        $index = array_search($mediaId, $ids, true);
        if ($index === false) {
            abort(404);
        }
        $swap = $direction === 'up' ? $index - 1 : $index + 1;
        if (! array_key_exists($swap, $ids)) {
            return;
        }
        [$ids[$index], $ids[$swap]] = [$ids[$swap], $ids[$index]];
        $properties->reorderGallery($property, $ids, $this->actor());
        $this->refreshProperties();
    }

    /** Remove one image only from the selected property's gallery. */
    public function removeGalleryImage(int $mediaId, PropertyService $properties): void
    {
        $property = $this->selectedProperty();
        Gate::authorize('update', $property);

        try {
            $properties->removeGalleryImage($property, $mediaId, $this->actor());
        } catch (CatalogException $exception) {
            $this->addError('media', $exception->getMessage());

            return;
        }

        session()->flash('success', 'Property gallery image removed.');
        $this->refreshProperties();
    }

    /** Return from metadata editing to the media workspace. */
    public function closeMetadata(): void
    {
        $this->selectedMediaId = null;
        $this->clearMediaInput();
        $this->dialog = 'media';
        $this->resetValidation();
    }

    /** Clear all property filters. */
    public function clearFilters(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->categoryFilter = '';
        $this->resetPropertyPage();
    }

    /** Close any modal and clear temporary form/media state. */
    public function closeDialog(): void
    {
        $this->dialog = '';
        $this->selectedPropertyId = null;
        $this->selectedMediaId = null;
        $this->clearMediaInput();
        $this->clearFormMediaInput();
        $this->form->resetForCreate();
        $this->resetValidation();
    }

    /** Render the module-owned property manager. */
    public function render(): View
    {
        return view('property-booking::livewire.admin.property-manager');
    }

    /** @return array<string, mixed> */
    private function coverRules(bool $required): array
    {
        $maximumKilobytes = max(1, (int) config('property-booking.media.upload_max_kilobytes', 5120));

        return [
            'coverUpload' => [$required ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.$maximumKilobytes],
            'mediaAltText' => [$required ? 'required' : 'nullable', 'string', 'max:180'],
            'mediaCaption' => ['nullable', 'string', 'max:320'],
        ];
    }

    /** @return array<string, mixed> */
    private function galleryRules(bool $required): array
    {
        $maximumKilobytes = max(1, (int) config('property-booking.media.upload_max_kilobytes', 5120));

        return [
            'galleryUpload' => [$required ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.$maximumKilobytes],
            'mediaAltText' => [$required ? 'required' : 'nullable', 'string', 'max:180'],
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
    private function persistFormMedia(Property $property, PropertyService $properties, User $actor): void
    {
        if ($this->formCoverUpload !== null) {
            $properties->replaceCover(
                $property,
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

            $properties->addGalleryImages($property, array_values($this->formGalleryUploads), $metadata, $actor);
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
        if ($this->selectedPropertyId === null) {
            Gate::authorize('create', Property::class);

            return;
        }

        Gate::authorize('update', $this->findProperty($this->selectedPropertyId));
    }

    /** Return the number of images the current CRUD form may still append. */
    private function remainingFormGallerySlots(): int
    {
        $limit = max(1, (int) config('property-booking.media.property_gallery_limit', 12));
        if ($this->selectedPropertyId === null) {
            return $limit;
        }

        return max(0, $limit - $this->findProperty($this->selectedPropertyId)->getMedia('property_gallery')->count());
    }

    /** Generate useful editable metadata when a user first selects an image. */
    private function defaultFormMediaAltText(string $kind, int $index = 0): string
    {
        $name = trim($this->form->name) !== '' ? trim($this->form->name) : 'Property';

        return $kind === 'cover' ? "{$name} cover image" : "{$name} gallery image ".($index + 1);
    }

    /** Normalize optional media copy before handing it to the catalog service. */
    private function nullableTrimmed(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /** Build the assigned-property query for the current actor. */
    private function scopedPropertyQuery(): Builder
    {
        return $this->access->scope(Property::query(), $this->actor(), 'property_booking_properties.id');
    }

    /** Apply bounded property filters after authorization scoping. */
    private function filteredProperties(): Builder
    {
        $search = trim($this->search);

        return $this->scopedPropertyQuery()
            ->when($search !== '', static function (Builder $query) use ($search): void {
                $query->where(static function (Builder $match) use ($search): void {
                    $match->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('region', 'like', "%{$search}%");
                });
            })
            ->when(PropertyStatus::tryFrom($this->statusFilter), static fn (Builder $query, PropertyStatus $status): Builder => $query->where('status', $status->value))
            ->when($this->categories->contains('id', (int) $this->categoryFilter), fn (Builder $query): Builder => $query->where('category_id', (int) $this->categoryFilter));
    }

    /** Resolve one property only through the actor's scoped query. */
    private function findProperty(int $propertyId): Property
    {
        return $this->scopedPropertyQuery()
            ->with(['category', 'amenities', 'assignedUsers', 'media'])
            ->findOrFail($propertyId);
    }

    /** Resolve the selected property while retaining the current media dialog. */
    private function selectedProperty(): Property
    {
        abort_if($this->selectedPropertyId === null, 404);

        return $this->findProperty($this->selectedPropertyId);
    }

    /** Resolve the authenticated actor for activity attribution and query scope. */
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

    /** Return a bounded property-card page length. */
    private function validPerPage(): int
    {
        return in_array($this->perPage, [6, 12, 24, 48], true) ? $this->perPage : 12;
    }

    /** Reset pagination and all property-derived computed state. */
    private function resetPropertyPage(): void
    {
        $this->resetPage('propertyPage');
        $this->refreshProperties();
    }

    /** Invalidate property-derived computed state after a write. */
    private function refreshProperties(): void
    {
        unset($this->properties, $this->statistics, $this->mediaProperty, $this->formProperty);
    }

    /** Clear transient media inputs without changing the selected property. */
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
