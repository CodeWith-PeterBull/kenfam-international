<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Livewire\Admin;

use App\Models\User;
use App\Modules\PropertyBooking\Bookings\Enums\BookingChannel;
use App\Modules\PropertyBooking\Bookings\Enums\BookingPaymentStatus;
use App\Modules\PropertyBooking\Bookings\Enums\BookingStatus;
use App\Modules\PropertyBooking\Bookings\Enums\StayStatus;
use App\Modules\PropertyBooking\Bookings\Exceptions\BookingOperationException;
use App\Modules\PropertyBooking\Bookings\Livewire\Forms\BookingAdministrationPaymentForm;
use App\Modules\PropertyBooking\Bookings\Livewire\Forms\BookingModificationForm;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Bookings\Models\BookingPayment;
use App\Modules\PropertyBooking\Bookings\Services\BookingAdministrationPaymentService;
use App\Modules\PropertyBooking\Bookings\Services\BookingLifecycleService;
use App\Modules\PropertyBooking\Bookings\Services\BookingModificationService;
use App\Modules\PropertyBooking\Catalog\Models\AccommodationUnit;
use App\Modules\PropertyBooking\Catalog\Models\Property;
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
use Livewire\WithPagination;

/** Property-scoped booking register and explicit lifecycle administration surface. */
final class BookingManager extends Component
{
    use WithPagination;

    #[Url(as: 'booking-q', except: '')]
    public string $search = '';

    #[Url(as: 'booking-property', except: '')]
    public string $propertyFilter = '';

    #[Url(as: 'booking-channel', except: '')]
    public string $channelFilter = '';

    #[Url(as: 'booking-status', except: '')]
    public string $statusFilter = '';

    #[Url(as: 'booking-stay', except: '')]
    public string $stayFilter = '';

    #[Url(as: 'booking-payment', except: '')]
    public string $paymentFilter = '';

    #[Url(as: 'booking-per-page', except: 15)]
    public int $perPage = 15;

    public BookingModificationForm $modificationForm;

    public BookingAdministrationPaymentForm $paymentForm;

    public string $dialog = '';

    public string $actionReason = '';

    public string $overrideReason = '';

    public string $targetUnitId = '';

    #[Locked]
    public ?int $selectedBookingId = null;

    private PropertyAccessService $access;

    /** Reauthorize and restore property scope for every Livewire request. */
    public function boot(PropertyAccessService $access): void
    {
        $this->access = $access;
        Gate::authorize('viewAny', Booking::class);
    }

    /** Reset pagination when the booking search changes. */
    public function updatedSearch(): void
    {
        $this->resetBookingPage();
    }

    /** Validate property scope and reset pagination. */
    public function updatedPropertyFilter(): void
    {
        if (! $this->propertyOptions->contains('id', (int) $this->propertyFilter)) {
            $this->propertyFilter = '';
        }
        $this->resetBookingPage();
    }

    /** Normalize the booking-channel filter. */
    public function updatedChannelFilter(): void
    {
        $this->channelFilter = BookingChannel::tryFrom($this->channelFilter)?->value ?? '';
        $this->resetBookingPage();
    }

    /** Normalize the booking lifecycle filter. */
    public function updatedStatusFilter(): void
    {
        $this->statusFilter = BookingStatus::tryFrom($this->statusFilter)?->value ?? '';
        $this->resetBookingPage();
    }

    /** Normalize the guest-presence filter. */
    public function updatedStayFilter(): void
    {
        $this->stayFilter = StayStatus::tryFrom($this->stayFilter)?->value ?? '';
        $this->resetBookingPage();
    }

    /** Normalize the settlement-state filter. */
    public function updatedPaymentFilter(): void
    {
        $this->paymentFilter = BookingPaymentStatus::tryFrom($this->paymentFilter)?->value ?? '';
        $this->resetBookingPage();
    }

    /** Restrict and apply the requested page size. */
    public function updatedPerPage(): void
    {
        $this->perPage = in_array($this->perPage, [10, 15, 25, 50], true) ? $this->perPage : 15;
        $this->resetBookingPage();
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

    /** @return array{total: int, active: int, in_house: int, action: int} */
    #[Computed]
    public function statistics(): array
    {
        return [
            'total' => $this->scopedBookingQuery()->whereNotNull('booking_number')->count(),
            'active' => $this->scopedBookingQuery()->whereIn('status', [
                BookingStatus::Pending->value,
                BookingStatus::Confirmed->value,
            ])->count(),
            'in_house' => $this->scopedBookingQuery()->where('stay_status', StayStatus::CheckedIn->value)->count(),
            'action' => $this->scopedBookingQuery()
                ->where(static fn (Builder $query): Builder => $query
                    ->where('status', BookingStatus::Pending->value)
                    ->orWhere(static fn (Builder $arrival): Builder => $arrival
                        ->where('status', BookingStatus::Confirmed->value)
                        ->where('stay_status', StayStatus::Expected->value)
                        ->where('starts_at', '<=', now())))
                ->count(),
        ];
    }

    /** Return the scoped and filtered booking register. */
    #[Computed]
    public function bookings(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return $this->scopedBookingQuery()
            ->whereNotNull('booking_number')
            ->when($search !== '', static function (Builder $query) use ($search): void {
                $query->where(static function (Builder $match) use ($search): void {
                    $match->where('booking_number', 'like', "%{$search}%")
                        ->orWhere('guest_first_name', 'like', "%{$search}%")
                        ->orWhere('guest_last_name', 'like', "%{$search}%")
                        ->orWhere('guest_email', 'like', "%{$search}%")
                        ->orWhere('guest_phone', 'like', "%{$search}%");
                });
            })
            ->when($this->propertyOptions->contains('id', (int) $this->propertyFilter), fn (Builder $query): Builder => $query->where('property_id', (int) $this->propertyFilter))
            ->when(BookingChannel::tryFrom($this->channelFilter), static fn (Builder $query, BookingChannel $channel): Builder => $query->where('channel', $channel->value))
            ->when(BookingStatus::tryFrom($this->statusFilter), static fn (Builder $query, BookingStatus $status): Builder => $query->where('status', $status->value))
            ->when(StayStatus::tryFrom($this->stayFilter), static fn (Builder $query, StayStatus $status): Builder => $query->where('stay_status', $status->value))
            ->when(BookingPaymentStatus::tryFrom($this->paymentFilter), static fn (Builder $query, BookingPaymentStatus $status): Builder => $query->where('payment_status', $status->value))
            ->with(['property', 'stays.activeAssignment.unit'])
            ->withCount(['stays', 'guests', 'payments'])
            ->latest('placed_at')
            ->latest('id')
            ->paginate($this->perPage, ['*'], 'bookingPage');
    }

    /** Return the currently selected aggregate through the same property scope. */
    #[Computed]
    public function selectedBooking(): ?Booking
    {
        if ($this->selectedBookingId === null) {
            return null;
        }

        return $this->scopedBookingQuery()->with([
            'property', 'primaryGuest', 'stays.ratePlan', 'stays.activeAssignment.unit',
            'unitAssignments.unit', 'guests', 'payments.recorder.profile', 'charges',
            'register', 'receptionShift', 'receptionist.profile',
        ])->findOrFail($this->selectedBookingId);
    }

    /** Return compatible active units for an explicit move dialog. */
    #[Computed]
    public function moveTargets(): Collection
    {
        $booking = $this->selectedBooking;
        $stay = $booking?->stays->first();
        $currentUnitId = $stay?->activeAssignment?->unit_id;
        if (! $booking instanceof Booking || $stay === null || $currentUnitId === null) {
            return collect();
        }

        return AccommodationUnit::query()
            ->allocatable()
            ->where('property_id', $booking->property_id)
            ->where('unit_type_id', $stay->unit_type_id)
            ->whereKeyNot($currentUnitId)
            ->orderBy('code')
            ->get(['id', 'code', 'display_name', 'floor_label']);
    }

    /** Open the immutable booking detail panel. */
    public function openDetails(int $bookingId): void
    {
        $booking = $this->findBooking($bookingId);
        Gate::authorize('view', $booking);
        $this->prepareDialog($booking, 'details');
    }

    /** Confirm a pending booking through the lifecycle service. */
    public function confirmBooking(int $bookingId, BookingLifecycleService $lifecycle): void
    {
        $booking = $this->findBooking($bookingId);
        Gate::authorize('update', $booking);
        $this->perform('Booking confirmed.', fn (): Booking => $lifecycle->confirm($booking, $this->actor()));
    }

    /** Open the reason dialog for an eligible cancellation. */
    public function openCancellation(int $bookingId): void
    {
        $this->openReasonDialog($bookingId, 'cancel');
    }

    /** Cancel the selected expected booking. */
    public function cancelBooking(BookingLifecycleService $lifecycle): void
    {
        $this->validate(['actionReason' => ['required', 'string', 'max:255']]);
        $booking = $this->selectedOrFail();
        Gate::authorize('update', $booking);
        $this->perform(
            'Booking cancelled and allocation released.',
            fn (): Booking => $lifecycle->cancel($booking, $this->actionReason, $this->actor()),
            close: true,
        );
    }

    /** Open the reason dialog for an eligible no-show review. */
    public function openNoShow(int $bookingId): void
    {
        $this->openReasonDialog($bookingId, 'no-show');
    }

    /** Mark the selected overdue arrival as a no-show. */
    public function markNoShow(BookingLifecycleService $lifecycle): void
    {
        $this->validate(['actionReason' => ['required', 'string', 'max:255']]);
        $booking = $this->selectedOrFail();
        Gate::authorize('update', $booking);
        $this->perform(
            'Booking marked as a no-show and allocation released.',
            fn (): Booking => $lifecycle->markNoShow($booking, $this->actionReason, $this->actor()),
            close: true,
        );
    }

    /** Open the check-in dialog after action authorization. */
    public function openCheckIn(int $bookingId): void
    {
        $booking = $this->findBooking($bookingId);
        Gate::authorize('checkIn', $booking);
        $this->prepareDialog($booking, 'check-in');
    }

    /** Check in the selected booking with an optional explicit override reason. */
    public function checkIn(BookingLifecycleService $lifecycle): void
    {
        $this->validate(['overrideReason' => ['nullable', 'string', 'max:255']]);
        $booking = $this->selectedOrFail();
        Gate::authorize('checkIn', $booking);
        $this->perform(
            'Guest stay checked in.',
            fn (): Booking => $lifecycle->checkIn($booking, $this->actor(), $this->overrideReason),
            close: true,
        );
    }

    /** Open the checkout dialog after action authorization. */
    public function openCheckOut(int $bookingId): void
    {
        $booking = $this->findBooking($bookingId);
        Gate::authorize('checkOut', $booking);
        $this->prepareDialog($booking, 'check-out');
    }

    /** Check out the selected in-house booking and mark vacated units dirty. */
    public function checkOut(BookingLifecycleService $lifecycle): void
    {
        $this->validate(['overrideReason' => ['nullable', 'string', 'max:255']]);
        $booking = $this->selectedOrFail();
        Gate::authorize('checkOut', $booking);
        $this->perform(
            'Guest stay checked out and units moved to dirty.',
            fn (): Booking => $lifecycle->checkOut($booking, $this->actor(), $this->overrideReason),
            close: true,
        );
    }

    /** Open the date-modification dialog with current snapshots. */
    public function openModification(int $bookingId): void
    {
        $booking = $this->findBooking($bookingId);
        Gate::authorize('update', $booking);
        $this->prepareDialog($booking, 'modify');
        $this->modificationForm->fillFromBooking($booking);
    }

    /** Reprice and reallocate the selected interval. */
    public function modifyBooking(BookingModificationService $modifications): void
    {
        $this->modificationForm->validate();
        $booking = $this->selectedOrFail();
        Gate::authorize('update', $booking);
        $this->perform(
            'Booking dates repriced and updated.',
            fn (): Booking => $modifications->modifyInterval(
                $booking,
                $this->modificationForm->data($booking),
                $this->actor(),
            ),
            close: true,
        );
    }

    /** Open the compatible-unit move dialog. */
    public function openMove(int $bookingId): void
    {
        $booking = $this->findBooking($bookingId);
        Gate::authorize('update', $booking);
        $this->prepareDialog($booking, 'move');
    }

    /** Move the selected booking to one compatible concrete unit. */
    public function moveUnit(BookingModificationService $modifications): void
    {
        $this->validate([
            'targetUnitId' => ['required', 'integer'],
            'actionReason' => ['required', 'string', 'max:255'],
        ]);
        $booking = $this->selectedOrFail();
        Gate::authorize('update', $booking);
        $target = $this->moveTargets->firstWhere('id', (int) $this->targetUnitId);
        abort_unless($target instanceof AccommodationUnit, 422);
        $this->perform(
            'Booking moved to the selected unit.',
            fn (): Booking => $modifications->moveUnit($booking, $target, $this->actionReason, $this->actor()),
            close: true,
        );
    }

    /** Open the administration-payment dialog for an outstanding balance. */
    public function openPayment(int $bookingId): void
    {
        $booking = $this->findBooking($bookingId);
        Gate::authorize('create', BookingPayment::class);
        abort_if($booking->balance_minor <= 0 || $booking->status->isTerminal(), 422);
        $this->prepareDialog($booking, 'payment');
        $this->paymentForm->fillFromBooking($booking);
    }

    /** Record one administration payment against the selected booking. */
    public function recordPayment(BookingAdministrationPaymentService $payments): void
    {
        $this->paymentForm->validate();
        $booking = $this->selectedOrFail();
        Gate::authorize('create', BookingPayment::class);

        try {
            $payments->recordCompleted($booking, $this->paymentForm->data(), $this->actor());
        } catch (BookingOperationException|InvalidArgumentException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        $this->closeDialog();
        session()->flash('success', 'Booking payment recorded.');
        $this->refreshBookings();
    }

    /** Clear every URL-backed register filter. */
    public function clearFilters(): void
    {
        $this->search = '';
        $this->propertyFilter = '';
        $this->channelFilter = '';
        $this->statusFilter = '';
        $this->stayFilter = '';
        $this->paymentFilter = '';
        $this->resetBookingPage();
    }

    /** Close the active modal and clear all consequential input. */
    public function closeDialog(): void
    {
        $this->dialog = '';
        $this->selectedBookingId = null;
        $this->actionReason = '';
        $this->overrideReason = '';
        $this->targetUnitId = '';
        $this->modificationForm->reset();
        $this->paymentForm->reset();
        $this->resetValidation();
        unset($this->selectedBooking, $this->moveTargets);
    }

    /** Render the module-owned booking operations manager. */
    public function render(): View
    {
        return view('property-booking::livewire.admin.booking-manager');
    }

    /** Open a reason-backed mutation dialog for one scoped booking. */
    private function openReasonDialog(int $bookingId, string $dialog): void
    {
        $booking = $this->findBooking($bookingId);
        Gate::authorize('update', $booking);
        $this->prepareDialog($booking, $dialog);
    }

    /** Reset modal state and select one reloaded aggregate. */
    private function prepareDialog(Booking $booking, string $dialog): void
    {
        $this->closeDialog();
        $this->selectedBookingId = (int) $booking->getKey();
        $this->dialog = $dialog;
        unset($this->selectedBooking, $this->moveTargets);
    }

    /** Run one expected domain operation and normalize its UI error contract. */
    private function perform(string $message, callable $operation, bool $close = false): void
    {
        try {
            $operation();
        } catch (BookingOperationException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        if ($close) {
            $this->closeDialog();
        }
        session()->flash('success', $message);
        $this->refreshBookings();
    }

    /** Build the authorization-scoped booking query. */
    private function scopedBookingQuery(): Builder
    {
        return $this->access->scope(Booking::query(), $this->actor(), 'property_bookings.property_id');
    }

    /** Resolve one booking through the operator's property scope. */
    private function findBooking(int $bookingId): Booking
    {
        return $this->scopedBookingQuery()->findOrFail($bookingId);
    }

    /** Resolve the selected booking or fail without trusting browser state. */
    private function selectedOrFail(): Booking
    {
        return $this->selectedBooking ?? abort(404);
    }

    /** Resolve the authenticated actor. */
    private function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    /** Reset the named paginator and derived booking state. */
    private function resetBookingPage(): void
    {
        $this->resetPage('bookingPage');
        $this->refreshBookings();
    }

    /** Invalidate every booking-derived computed property. */
    private function refreshBookings(): void
    {
        unset($this->bookings, $this->statistics, $this->selectedBooking, $this->moveTargets);
    }
}
