<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Catalog\Livewire\Admin;

use App\Models\User;
use App\Modules\PropertyBooking\Catalog\Enums\AmenityScope;
use App\Modules\PropertyBooking\Catalog\Exceptions\CatalogException;
use App\Modules\PropertyBooking\Catalog\Livewire\Forms\AmenityForm;
use App\Modules\PropertyBooking\Catalog\Models\Amenity;
use App\Modules\PropertyBooking\Catalog\Services\AmenityService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Searchable reusable amenity catalogue with explicit scope management. */
final class AmenityManager extends Component
{
    use WithPagination;

    #[Url(as: 'amenity-q', except: '')]
    public string $search = '';

    #[Url(as: 'amenity-scope', except: '')]
    public string $scopeFilter = '';

    #[Url(as: 'amenity-per-page', except: 15)]
    public int $perPage = 15;

    public AmenityForm $form;

    public string $dialog = '';

    #[Locked]
    public ?int $selectedAmenityId = null;

    /** Reauthorize amenity visibility for every Livewire request. */
    public function boot(): void
    {
        Gate::authorize('viewAny', Amenity::class);
    }

    /** @return list<AmenityScope> */
    #[Computed]
    public function scopes(): array
    {
        return AmenityScope::cases();
    }

    /** @return array{total: int, active: int, property: int, unit: int} */
    #[Computed]
    public function statistics(): array
    {
        return [
            'total' => Amenity::query()->count(),
            'active' => Amenity::query()->active()->count(),
            'property' => Amenity::query()->whereIn('scope', [AmenityScope::Property->value, AmenityScope::Both->value])->count(),
            'unit' => Amenity::query()->whereIn('scope', [AmenityScope::Unit->value, AmenityScope::Both->value])->count(),
        ];
    }

    /** Return the bounded, filtered amenity register. */
    #[Computed]
    public function amenities(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return Amenity::query()
            ->withCount(['properties', 'unitTypes'])
            ->when($search !== '', static function (Builder $query) use ($search): void {
                $query->where(static function (Builder $match) use ($search): void {
                    $match->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('icon_key', 'like', "%{$search}%");
                });
            })
            ->when(AmenityScope::tryFrom($this->scopeFilter), static fn (Builder $query, AmenityScope $scope): Builder => $query->where('scope', $scope->value))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($this->validPerPage(), ['*'], 'amenityPage');
    }

    /** Reset pagination when search changes. */
    public function updatedSearch(): void
    {
        $this->resetAmenityPage();
    }

    /** Reset pagination when scope changes. */
    public function updatedScopeFilter(): void
    {
        $this->resetAmenityPage();
    }

    /** Bound user-controlled page length. */
    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, [10, 15, 25, 50], true)) {
            $this->perPage = 15;
        }
        $this->resetAmenityPage();
    }

    /** Open an empty amenity form. */
    public function openCreate(): void
    {
        Gate::authorize('create', Amenity::class);
        $this->closeDialog();
        $this->form->resetForCreate();
        $this->dialog = 'form';
    }

    /** Open one amenity for editing. */
    public function openEdit(int $amenityId): void
    {
        $amenity = $this->findAmenity($amenityId);
        Gate::authorize('update', $amenity);
        $this->closeDialog();
        $this->selectedAmenityId = $amenity->getKey();
        $this->form->fillFromAmenity($amenity);
        $this->dialog = 'form';
    }

    /** Persist amenity creation or update through its service. */
    public function save(AmenityService $amenities): void
    {
        $this->resetErrorBag('management');
        $this->form->validate();

        try {
            if ($this->selectedAmenityId === null) {
                Gate::authorize('create', Amenity::class);
                $amenities->create($this->form->payload(), $this->actor());
                $message = 'Amenity created.';
            } else {
                $amenity = $this->findAmenity($this->selectedAmenityId);
                Gate::authorize('update', $amenity);
                if (! $this->form->amenity?->is($amenity)) {
                    abort(404);
                }
                $amenities->update($amenity, $this->form->payload(), $this->actor());
                $message = 'Amenity updated.';
            }
        } catch (CatalogException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        $this->closeDialog();
        session()->flash('success', $message);
        $this->refreshAmenities();
    }

    /** Toggle amenity assignment visibility. */
    public function toggleActive(int $amenityId, AmenityService $amenities): void
    {
        $amenity = $this->findAmenity($amenityId);
        Gate::authorize('update', $amenity);
        $amenities->setActive($amenity, ! $amenity->is_active, $this->actor());
        session()->flash('success', $amenity->is_active ? 'Amenity hidden.' : 'Amenity activated.');
        $this->refreshAmenities();
    }

    /** Clear all query-string filters. */
    public function clearFilters(): void
    {
        $this->search = '';
        $this->scopeFilter = '';
        $this->resetAmenityPage();
    }

    /** Close the form modal and clear validation state. */
    public function closeDialog(): void
    {
        $this->dialog = '';
        $this->selectedAmenityId = null;
        $this->form->resetForCreate();
        $this->resetValidation();
    }

    /** Render the module-owned amenity manager. */
    public function render(): View
    {
        return view('property-booking::livewire.admin.amenity-manager');
    }

    /** Resolve one amenity or fail. */
    private function findAmenity(int $amenityId): Amenity
    {
        return Amenity::query()->findOrFail($amenityId);
    }

    /** Resolve the authenticated actor for activity attribution. */
    private function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    /** Return a bounded page length. */
    private function validPerPage(): int
    {
        return in_array($this->perPage, [10, 15, 25, 50], true) ? $this->perPage : 15;
    }

    /** Reset amenity pagination and computed state. */
    private function resetAmenityPage(): void
    {
        $this->resetPage('amenityPage');
        $this->refreshAmenities();
    }

    /** Invalidate amenity-derived computed state. */
    private function refreshAmenities(): void
    {
        unset($this->amenities, $this->statistics);
    }
}
