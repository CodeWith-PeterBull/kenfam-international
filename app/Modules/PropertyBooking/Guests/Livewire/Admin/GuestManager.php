<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Guests\Livewire\Admin;

use App\Models\User;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Guests\Exceptions\GuestException;
use App\Modules\PropertyBooking\Guests\Livewire\Forms\GuestForm;
use App\Modules\PropertyBooking\Guests\Models\Guest;
use App\Modules\PropertyBooking\Guests\Services\GuestService;
use App\Modules\PropertyBooking\Support\PropertyAccessService;
use App\Modules\PropertyBooking\Support\PropertyBookingPermission;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Privacy-aware reusable guest directory scoped through related properties. */
final class GuestManager extends Component
{
    use WithPagination;

    #[Url(as: 'guest-q', except: '')]
    public string $search = '';

    #[Url(as: 'guest-property', except: '')]
    public string $propertyFilter = '';

    #[Url(as: 'guest-state', except: 'active')]
    public string $stateFilter = 'active';

    #[Url(as: 'guest-per-page', except: 15)]
    public int $perPage = 15;

    public GuestForm $form;

    public string $dialog = '';

    #[Locked]
    public ?int $selectedGuestId = null;

    #[Locked]
    public ?string $revealedIdentity = null;

    private PropertyAccessService $access;

    /** Reauthorize collection access and restore the scope service each request. */
    public function boot(PropertyAccessService $access): void
    {
        $this->access = $access;
        Gate::authorize('viewAny', Guest::class);
    }

    /** Reset pagination when protected guest search changes. */
    public function updatedSearch(): void
    {
        $this->resetGuestPage();
    }

    /** Validate property scope and reset pagination. */
    public function updatedPropertyFilter(): void
    {
        if (! $this->propertyOptions->contains('id', (int) $this->propertyFilter)) {
            $this->propertyFilter = '';
        }
        $this->resetGuestPage();
    }

    /** Normalize archive-state filtering. */
    public function updatedStateFilter(): void
    {
        $this->stateFilter = in_array($this->stateFilter, ['active', 'archived', 'all'], true)
            ? $this->stateFilter
            : 'active';
        $this->resetGuestPage();
    }

    /** Restrict and apply the requested page size. */
    public function updatedPerPage(): void
    {
        $this->perPage = in_array($this->perPage, [10, 15, 25, 50], true) ? $this->perPage : 15;
        $this->resetGuestPage();
    }

    /** @return Collection<int, Property> */
    #[Computed]
    public function propertyOptions(): Collection
    {
        return $this->access
            ->scope(Property::query(), $this->actor(), 'property_booking_properties.id')
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    /** @return array{active: int, booked: int, profiles: int, archived: int} */
    #[Computed]
    public function statistics(): array
    {
        return [
            'active' => $this->scopedGuestQuery()->count(),
            'booked' => $this->scopedGuestQuery()->whereHas('bookingAssignments')->count(),
            'profiles' => $this->scopedGuestQuery()->whereDoesntHave('bookingAssignments')->count(),
            'archived' => $this->scopedGuestQuery(true)->onlyTrashed()->count(),
        ];
    }

    /** Return the scoped guest register without protected identity values. */
    #[Computed]
    public function guests(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return $this->scopedGuestQuery(true)
            ->when($this->stateFilter === 'active', static fn (Builder $query): Builder => $query->withoutTrashed())
            ->when($this->stateFilter === 'archived', static fn (Builder $query): Builder => $query->onlyTrashed())
            ->when($search !== '', static function (Builder $query) use ($search): void {
                $query->where(static function (Builder $match) use ($search): void {
                    $match->where('first_name', 'like', "%{$search}%")
                        ->orWhere('middle_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when(
                $this->propertyOptions->contains('id', (int) $this->propertyFilter),
                fn (Builder $query): Builder => $query->whereHas(
                    'bookingAssignments.booking',
                    fn (Builder $booking): Builder => $booking->where('property_id', (int) $this->propertyFilter),
                ),
            )
            ->withCount(['bookingAssignments', 'primaryBookings'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate($this->perPage, ['*'], 'guestPage');
    }

    /** Return the selected guest with only operational relationships. */
    #[Computed]
    public function selectedGuest(): ?Guest
    {
        if ($this->selectedGuestId === null) {
            return null;
        }

        return $this->scopedGuestQuery(true)
            ->withCount(['bookingAssignments', 'primaryBookings'])
            ->findOrFail($this->selectedGuestId);
    }

    /** Open an empty profile form. */
    public function openCreate(): void
    {
        Gate::authorize('create', Guest::class);
        $this->closeDialog();
        $this->form->resetForCreate();
        $this->dialog = 'form';
    }

    /** Open one scoped profile for editing without decrypting identity. */
    public function openEdit(int $guestId): void
    {
        $guest = $this->findGuest($guestId);
        Gate::authorize('update', $guest);
        $this->closeDialog();
        $this->selectedGuestId = (int) $guest->getKey();
        $this->form->fillFromGuest($guest);
        $this->dialog = 'form';
    }

    /** Open one privacy-safe profile detail panel. */
    public function openDetails(int $guestId): void
    {
        $guest = $this->findGuest($guestId);
        Gate::authorize('view', $guest);
        $this->closeDialog();
        $this->selectedGuestId = (int) $guest->getKey();
        $this->dialog = 'details';
    }

    /** Persist profile creation or update through the encryption-owning service. */
    public function save(GuestService $guests): void
    {
        $this->form->validate();
        $actor = $this->actor();

        try {
            if ($this->selectedGuestId === null) {
                Gate::authorize('create', Guest::class);
                $guests->create($this->form->payload(), $actor);
                $message = 'Guest profile created.';
            } else {
                $guest = $this->findGuest($this->selectedGuestId);
                Gate::authorize('update', $guest);
                $guests->update($guest, $this->form->payload(false), $actor);
                $message = 'Guest profile updated.';
            }
        } catch (GuestException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        $this->closeDialog();
        session()->flash('success', $message);
        $this->refreshGuests();
    }

    /** Reveal protected identity only after an explicit authorized action. */
    public function revealIdentity(GuestService $guests): void
    {
        $guest = $this->selectedOrFail();
        Gate::authorize('update', $guest);
        $this->revealedIdentity = $guests->revealIdentity($guest);
    }

    /** Archive a scoped guest while preserving historical booking snapshots. */
    public function archive(int $guestId, GuestService $guests): void
    {
        $guest = $this->findGuest($guestId);
        Gate::authorize('delete', $guest);
        $guests->archive($guest, $this->actor());
        session()->flash('success', 'Guest profile archived.');
        $this->refreshGuests();
    }

    /** Restore one archived guest profile through the domain service. */
    public function restore(int $guestId, GuestService $guests): void
    {
        $guest = $this->findGuest($guestId);
        Gate::authorize('restore', $guest);
        $guests->restore($guest, $this->actor());
        session()->flash('success', 'Guest profile restored.');
        $this->refreshGuests();
    }

    /** Clear every URL-backed directory filter. */
    public function clearFilters(): void
    {
        $this->search = '';
        $this->propertyFilter = '';
        $this->stateFilter = 'active';
        $this->resetGuestPage();
    }

    /** Close any guest panel and erase sensitive rendered state. */
    public function closeDialog(): void
    {
        $this->dialog = '';
        $this->selectedGuestId = null;
        $this->revealedIdentity = null;
        $this->form->resetForCreate();
        $this->resetValidation();
        unset($this->selectedGuest);
    }

    /** Render the guest administration component. */
    public function render(): View
    {
        return view('property-booking::livewire.admin.guest-manager');
    }

    /** Build the guest query allowed by global or assigned-property access. */
    private function scopedGuestQuery(bool $withTrashed = false): Builder
    {
        $query = $withTrashed ? Guest::withTrashed() : Guest::query();
        $actor = $this->actor();
        if ($this->access->hasGlobalAccess($actor)) {
            return $query;
        }

        $propertyIds = $this->access->assignedPropertyIds($actor);

        return $query->where(function (Builder $scope) use ($actor, $propertyIds): void {
            $scope->whereHas(
                'bookingAssignments.booking',
                static fn (Builder $booking): Builder => $booking->whereIn('property_id', $propertyIds),
            );
            if ($actor->can(PropertyBookingPermission::MANAGE_GUESTS)) {
                $scope->orWhere(static fn (Builder $own): Builder => $own
                    ->where('created_by', $actor->getKey())
                    ->whereDoesntHave('bookingAssignments'));
            }
        });
    }

    /** Resolve one guest through the current operator scope. */
    private function findGuest(int $guestId): Guest
    {
        return $this->scopedGuestQuery(true)->findOrFail($guestId);
    }

    /** Resolve the currently selected guest or abort. */
    private function selectedOrFail(): Guest
    {
        $guest = $this->selectedGuest;
        abort_unless($guest instanceof Guest, 404);

        return $guest;
    }

    /** Resolve the authenticated operator. */
    private function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    /** Reset guest pagination and computed query state. */
    private function resetGuestPage(): void
    {
        $this->resetPage('guestPage');
        $this->refreshGuests();
    }

    /** Invalidate guest-related computed projections. */
    private function refreshGuests(): void
    {
        unset($this->guests, $this->statistics, $this->selectedGuest);
    }
}
