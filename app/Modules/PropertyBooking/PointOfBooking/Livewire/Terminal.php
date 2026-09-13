<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\PointOfBooking\Livewire;

use App\Models\User;
use App\Modules\PropertyBooking\Bookings\Enums\BookingPaymentMethod;
use App\Modules\PropertyBooking\Bookings\Enums\BookingStatus;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Guests\Models\Guest;
use App\Modules\PropertyBooking\Guests\Services\GuestService;
use App\Modules\PropertyBooking\PointOfBooking\Data\PobTenderData;
use App\Modules\PropertyBooking\PointOfBooking\Exceptions\PointOfBookingException;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionShift;
use App\Modules\PropertyBooking\PointOfBooking\Services\PobCheckoutService;
use App\Modules\PropertyBooking\Pricing\Data\BookingQuote;
use App\Modules\PropertyBooking\Pricing\Enums\AdvanceNoticePolicy;
use App\Modules\PropertyBooking\Pricing\Enums\RatePlanStatus;
use App\Modules\PropertyBooking\Pricing\Models\RatePlan;
use App\Modules\PropertyBooking\Pricing\Services\BookingQuoteService;
use App\Modules\PropertyBooking\Support\PropertyAccessService;
use App\Modules\PropertyBooking\Support\PropertyBookingPermission;
use App\Modules\PropertyBooking\Support\ScaledDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Full-width availability, guest, hold, payment, and completion workspace. */
final class Terminal extends Component
{
    #[Url(as: 'shift', except: '')]
    public string $shiftUlid = '';

    public string $arrivalDate = '';

    public string $arrivalTime = '14:00';

    public string $departureDate = '';

    public string $departureTime = '10:00';

    public int $adults = 1;

    public int $children = 0;

    public int $infants = 0;

    public string $rateSearch = '';

    #[Locked]
    public ?int $selectedRatePlanId = null;

    public string $guestSearch = '';

    #[Locked]
    public ?int $selectedGuestId = null;

    public bool $showGuestForm = false;

    public string $guestFirstName = '';

    public string $guestMiddleName = '';

    public string $guestLastName = '';

    public string $guestEmail = '';

    public string $guestPhone = '';

    public string $guestCountryCode = 'KE';

    public string $guestIdentityType = '';

    public string $guestIdentityNumber = '';

    public string $specialRequests = '';

    public string $internalNote = '';

    /** @var list<array{method:string,amount:string,tendered:string,reference:string}> */
    public array $tenders = [];

    #[Locked]
    public ?int $resumingBookingId = null;

    /** Initialize the terminal from the operator's explicit open shift. */
    public function mount(): void
    {
        $this->authorizeTerminal();
        $shift = $this->availableShiftQuery()
            ->when($this->shiftUlid !== '', fn (Builder $query): Builder => $query->where('ulid', $this->shiftUlid))
            ->oldest('opened_at')
            ->first();
        if ($shift instanceof ReceptionShift) {
            $this->shiftUlid = $shift->ulid;
            $this->setDefaultInterval($shift);
        } else {
            $today = CarbonImmutable::now()->startOfDay();
            $this->arrivalDate = $today->format('Y-m-d');
            $this->departureDate = $today->addDay()->format('Y-m-d');
        }
        $this->resetTenders();
    }

    /** Reauthorize every Livewire request. */
    public function boot(): void
    {
        $this->authorizeTerminal();
    }

    /** @return Collection<int, ReceptionShift> */
    #[Computed]
    public function availableShifts(): Collection
    {
        return $this->availableShiftQuery()
            ->with(['property', 'register', 'receptionist.profile'])
            ->oldest('opened_at')
            ->get();
    }

    /** Resolve the explicitly selected open shift. */
    #[Computed]
    public function activeShift(): ?ReceptionShift
    {
        if ($this->shiftUlid === '') {
            return null;
        }

        return $this->availableShiftQuery()
            ->with(['property', 'register', 'receptionist.profile'])
            ->where('ulid', $this->shiftUlid)
            ->first();
    }

    /** @return Collection<int, array{rate:RatePlan,quote:BookingQuote}> */
    #[Computed]
    public function availableRates(): Collection
    {
        $shift = $this->activeShift;
        $interval = $this->interval($shift);
        if (! $shift instanceof ReceptionShift || $interval === null) {
            return collect();
        }

        [$startsAt, $endsAt] = $interval;
        $rates = RatePlan::query()
            ->where('property_id', $shift->property_id)
            ->where('status', RatePlanStatus::Active->value)
            ->with(['property', 'unitType.media'])
            ->when(trim($this->rateSearch) !== '', function (Builder $query): void {
                $search = '%'.trim($this->rateSearch).'%';
                $query->where(fn (Builder $match): Builder => $match
                    ->where('name', 'like', $search)
                    ->orWhere('code', 'like', $search)
                    ->orWhereHas('unitType', fn (Builder $type): Builder => $type
                        ->where('name', 'like', $search)->orWhere('code', 'like', $search)));
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(max(1, min(50, (int) config('property-booking.pob.search_results', 18))))
            ->get();
        $quotes = app(BookingQuoteService::class);

        return $rates->map(function (RatePlan $rate) use ($quotes, $startsAt, $endsAt): ?array {
            try {
                return [
                    'rate' => $rate,
                    'quote' => $quotes->quote(
                        $rate,
                        $startsAt,
                        $endsAt,
                        $this->adults,
                        $this->children,
                        $this->infants,
                        $this->advanceNoticePolicy(),
                    ),
                ];
            } catch (\Throwable) {
                return null;
            }
        })->filter()->values();
    }

    /** Return the currently selected fresh quote, if still available. */
    #[Computed]
    public function selectedQuote(): ?BookingQuote
    {
        $selection = $this->availableRates->first(
            fn (array $option): bool => $option['rate']->getKey() === $this->selectedRatePlanId,
        );

        return is_array($selection) ? $selection['quote'] : null;
    }

    /** Return the selected reusable guest. */
    #[Computed]
    public function selectedGuest(): ?Guest
    {
        return $this->selectedGuestId === null ? null : Guest::query()->find($this->selectedGuestId);
    }

    /** @return Collection<int, Guest> */
    #[Computed]
    public function guestResults(): Collection
    {
        $shift = $this->activeShift;
        $search = trim($this->guestSearch);
        if (! $shift instanceof ReceptionShift || strlen($search) < 2) {
            return collect();
        }
        $pattern = '%'.$search.'%';
        $query = Guest::query()
            ->where(fn (Builder $match): Builder => $match
                ->where('first_name', 'like', $pattern)
                ->orWhere('middle_name', 'like', $pattern)
                ->orWhere('last_name', 'like', $pattern)
                ->orWhere('email', 'like', $pattern)
                ->orWhere('phone', 'like', $pattern));
        if (! app(PropertyAccessService::class)->hasGlobalAccess($this->actor())) {
            $query->whereHas('primaryBookings', fn (Builder $booking): Builder => $booking->where('property_id', $shift->property_id));
        }

        return $query->orderBy('first_name')->orderBy('last_name')->limit(8)->get();
    }

    /** @return Collection<int, Booking> */
    #[Computed]
    public function heldBookings(): Collection
    {
        $shift = $this->activeShift;
        if (! $shift instanceof ReceptionShift) {
            return collect();
        }

        return Booking::query()
            ->where('reception_shift_id', $shift->getKey())
            ->where('status', BookingStatus::Held->value)
            ->with(['primaryGuest', 'stays.ratePlan', 'unitAssignments.unit'])
            ->latest('created_at')
            ->get();
    }

    /** Return the hold currently loaded into the terminal. */
    #[Computed]
    public function resumingBooking(): ?Booking
    {
        return $this->resumingBookingId === null
            ? null
            : $this->heldBookings->firstWhere('id', $this->resumingBookingId);
    }

    /** @return list<BookingPaymentMethod> */
    #[Computed]
    public function paymentMethods(): array
    {
        return collect(config('property-booking.pob.payment_methods', []))
            ->map(static fn (mixed $method): ?BookingPaymentMethod => is_string($method) ? BookingPaymentMethod::tryFrom($method) : null)
            ->filter()
            ->values()
            ->all();
    }

    /** Return configured identity document choices for reception workflows. */
    #[Computed]
    public function identityTypes(): array
    {
        return collect(config('property-booking.identity.types', []))
            ->filter(static fn (mixed $label, mixed $value): bool => is_string($value) && $value !== '' && is_string($label) && $label !== '')
            ->all();
    }

    /** Determine whether every POB booking requires a protected identity record. */
    #[Computed]
    public function guestIdentityRequired(): bool
    {
        return (bool) config('property-booking.pob.guest_identity_required', true);
    }

    /** Determine whether the selected guest satisfies the configured POB identity policy. */
    #[Computed]
    public function guestIdentitySatisfied(): bool
    {
        $guest = $this->selectedGuest;

        return $guest instanceof Guest
            && (! $this->guestIdentityRequired || filled($guest->identity_number_hash));
    }

    /** Switch to one explicitly authorized open shift. */
    public function selectShift(string $shiftUlid): void
    {
        $shift = $this->availableShiftQuery()->where('ulid', $shiftUlid)->firstOrFail();
        Gate::authorize('operate', $shift);
        $this->shiftUlid = $shift->ulid;
        $this->setDefaultInterval($shift);
        $this->resetBookingState();
        unset($this->availableShifts, $this->activeShift);
    }

    /** Prefill a walk-in stay from the current property-local minute until next-day checkout. */
    public function useCurrentArrival(): void
    {
        $shift = $this->requireShift();
        $localNow = CarbonImmutable::now($shift->property->timezone);

        $this->arrivalDate = $localNow->format('Y-m-d');
        $this->arrivalTime = $localNow->format('H:i');
        $this->departureDate = $localNow->addDay()->format('Y-m-d');
        $this->departureTime = substr((string) ($shift->property->check_out_until ?: '10:00'), 0, 5);
        $this->selectedRatePlanId = null;
        $this->resumingBookingId = null;
        $this->resetTenders();
        $this->resetValidation();
        unset($this->availableRates, $this->selectedQuote, $this->resumingBooking);
    }

    /** Select a currently available rate option. */
    public function selectRate(int $ratePlanId): void
    {
        abort_unless($this->availableRates->contains(fn (array $option): bool => $option['rate']->getKey() === $ratePlanId), 422);
        $this->selectedRatePlanId = $ratePlanId;
        $this->resumingBookingId = null;
        $this->resetTenders();
        unset($this->selectedQuote, $this->resumingBooking);
    }

    /** Select a guest already visible in the property-scoped result set. */
    public function selectGuest(int $guestId): void
    {
        $guest = $this->guestResults->firstWhere('id', $guestId);
        abort_unless($guest instanceof Guest, 404);
        $this->selectedGuestId = $guest->getKey();
        $this->guestSearch = '';
        $this->showGuestForm = false;
        $this->guestIdentityType = '';
        $this->guestIdentityNumber = '';
        unset($this->selectedGuest, $this->guestResults, $this->guestIdentitySatisfied);
    }

    /** Toggle the compact guest creation form. */
    public function toggleGuestForm(): void
    {
        Gate::authorize('create', Guest::class);
        $this->showGuestForm = ! $this->showGuestForm;
        $this->resetValidation();
    }

    /** Create and select a reusable guest through the protected guest service. */
    public function createGuest(GuestService $guests): void
    {
        Gate::authorize('create', Guest::class);
        $identityPresence = $this->guestIdentityRequired ? 'required' : 'nullable';
        $validated = $this->validate([
            'guestFirstName' => ['required', 'string', 'max:100'],
            'guestMiddleName' => ['nullable', 'string', 'max:100'],
            'guestLastName' => ['required', 'string', 'max:100'],
            'guestEmail' => ['nullable', 'email:rfc', 'max:255', 'required_without:guestPhone'],
            'guestPhone' => ['nullable', 'string', 'max:40', 'required_without:guestEmail'],
            'guestCountryCode' => ['required', 'string', 'size:2'],
            'guestIdentityType' => [$identityPresence, 'string', Rule::in(array_keys($this->identityTypes)), 'required_with:guestIdentityNumber'],
            'guestIdentityNumber' => [$identityPresence, 'string', 'max:180', 'required_with:guestIdentityType'],
        ]);
        $attributes = [
            'first_name' => $validated['guestFirstName'],
            'middle_name' => $validated['guestMiddleName'],
            'last_name' => $validated['guestLastName'],
            'email' => $validated['guestEmail'],
            'phone' => $validated['guestPhone'],
            'country_code' => strtoupper($validated['guestCountryCode']),
        ];
        if (filled($validated['guestIdentityNumber'] ?? null)) {
            $attributes['identity_type'] = $validated['guestIdentityType'];
            $attributes['identity_number'] = $validated['guestIdentityNumber'];
        }
        $guest = $guests->create($attributes, $this->actor());
        $this->selectedGuestId = $guest->getKey();
        $this->resetGuestForm();
        session()->flash('terminal-success', 'Guest profile created and selected.');
        unset($this->selectedGuest, $this->guestResults, $this->guestIdentitySatisfied);
    }

    /** Add protected identity to a selected legacy guest without exposing stored values. */
    public function recordSelectedGuestIdentity(GuestService $guests): void
    {
        $guest = $this->selectedGuest;
        abort_unless($guest instanceof Guest, 422);
        Gate::authorize('update', $guest);
        $validated = $this->validate([
            'guestIdentityType' => ['required', 'string', Rule::in(array_keys($this->identityTypes))],
            'guestIdentityNumber' => ['required', 'string', 'max:180'],
        ]);

        $guests->update($guest, [
            'identity_type' => $validated['guestIdentityType'],
            'identity_number' => $validated['guestIdentityNumber'],
        ], $this->actor());
        $this->guestIdentityType = '';
        $this->guestIdentityNumber = '';
        session()->flash('terminal-success', 'Guest identity recorded securely.');
        unset($this->selectedGuest, $this->guestIdentitySatisfied);
        $this->resetValidation(['guestIdentityType', 'guestIdentityNumber']);
    }

    /** Append one empty split-payment row up to the configured maximum. */
    public function addTender(): void
    {
        $maximum = max(1, min(10, (int) config('property-booking.pob.maximum_tenders', 4)));
        if (count($this->tenders) < $maximum) {
            $this->tenders[] = $this->emptyTender($this->paymentMethods[0] ?? BookingPaymentMethod::Cash);
        }
    }

    /** Remove one split-payment row while retaining at least one tender. */
    public function removeTender(int $index): void
    {
        if (count($this->tenders) <= 1 || ! array_key_exists($index, $this->tenders)) {
            return;
        }
        unset($this->tenders[$index]);
        $this->tenders = array_values($this->tenders);
        $this->resetValidation();
    }

    /** Apply the current booking total to one tender row. */
    public function applyTotal(int $index): void
    {
        if (! array_key_exists($index, $this->tenders)) {
            return;
        }
        $this->tenders[$index]['amount'] = ScaledDecimal::formatUnsigned($this->currentTotalMinor(), $this->decimals());
        if ($this->tenders[$index]['method'] === BookingPaymentMethod::Cash->value) {
            $this->tenders[$index]['tendered'] = $this->tenders[$index]['amount'];
        }
    }

    /** Persist the current selection as an owned, expiring hold. */
    public function holdBooking(PobCheckoutService $checkout): void
    {
        $shift = $this->requireShift();
        $quote = $this->requireQuote();
        $guest = $this->requireGuest();
        $rate = RatePlan::query()->findOrFail($quote->ratePlanId);

        try {
            $booking = $checkout->hold($quote, $rate, $guest, $shift, $this->actor(), $this->specialRequests, $this->internalNote);
        } catch (PointOfBookingException $exception) {
            $this->addError('terminal', $exception->getMessage());

            return;
        }

        $this->resetBookingState();
        session()->flash('terminal-success', "Booking {$booking->booking_number} held.");
        $this->refreshTerminal();
    }

    /** Complete a new selection or repriced hold with exact split tender. */
    public function completeBooking(PobCheckoutService $checkout): mixed
    {
        $shift = $this->requireShift();
        $actor = $this->actor();

        try {
            $tenders = $this->tenderData();
            if ($this->resumingBooking instanceof Booking) {
                $booking = $checkout->checkoutHeld($this->resumingBooking, $tenders, $shift, $actor);
            } else {
                $quote = $this->requireQuote();
                $booking = $checkout->checkout(
                    $quote,
                    RatePlan::query()->findOrFail($quote->ratePlanId),
                    $this->requireGuest(),
                    $tenders,
                    $shift,
                    $actor,
                    $this->specialRequests,
                    $this->internalNote,
                );
            }
        } catch (PointOfBookingException|InvalidArgumentException $exception) {
            $this->addError('terminal', $exception->getMessage());

            return null;
        }

        return $this->redirectRoute('property-booking.pob.receipts.show', [
            'booking' => $booking->ulid,
            'print' => 'checkout',
        ]);
    }

    /** Load one owned hold into the terminal for repricing and payment. */
    public function resumeHold(int $bookingId): void
    {
        $booking = $this->heldBookings->firstWhere('id', $bookingId);
        abort_unless($booking instanceof Booking, 404);
        $stay = $booking->stays->first();
        abort_unless($stay !== null, 422);
        $this->resumingBookingId = $booking->getKey();
        $this->selectedGuestId = $booking->primary_guest_id;
        $this->selectedRatePlanId = $stay->rate_plan_id;
        $this->arrivalDate = $booking->starts_at->timezone($booking->property_timezone)->format('Y-m-d');
        $this->arrivalTime = $booking->starts_at->timezone($booking->property_timezone)->format('H:i');
        $this->departureDate = $booking->ends_at->timezone($booking->property_timezone)->format('Y-m-d');
        $this->departureTime = $booking->ends_at->timezone($booking->property_timezone)->format('H:i');
        $this->adults = $booking->adult_count;
        $this->children = $booking->child_count;
        $this->infants = $booking->infant_count;
        $this->specialRequests = (string) $booking->special_requests;
        $this->internalNote = (string) $booking->internal_note;
        $this->resetTenders();
        unset($this->selectedGuest, $this->selectedQuote, $this->resumingBooking);
    }

    /** Discard one owned hold and release its concrete unit. */
    public function discardHold(int $bookingId, PobCheckoutService $checkout): void
    {
        $booking = $this->heldBookings->firstWhere('id', $bookingId);
        abort_unless($booking instanceof Booking, 404);

        try {
            $checkout->discardHeld($booking, $this->requireShift(), $this->actor());
        } catch (PointOfBookingException $exception) {
            $this->addError('terminal', $exception->getMessage());

            return;
        }
        if ($this->resumingBookingId === $bookingId) {
            $this->resetBookingState();
        }
        session()->flash('terminal-success', "Hold {$booking->booking_number} discarded.");
        $this->refreshTerminal();
    }

    /** Clear the current booking while preserving shift and interval filters. */
    public function resetBooking(): void
    {
        $this->resetBookingState();
        $this->refreshTerminal();
    }

    /** Render the module-owned terminal component. */
    public function render(): View
    {
        return view('property-booking::livewire.pob.terminal');
    }

    /** Return all open shifts the current operator may explicitly operate. */
    private function availableShiftQuery(): Builder
    {
        $actor = $this->actor();
        $query = app(PropertyAccessService::class)->scope(
            ReceptionShift::query()->open(),
            $actor,
            'property_booking_shifts.property_id',
        );
        if (! $actor->can(PropertyBookingPermission::MANAGE_SHIFTS)) {
            $query->where('receptionist_id', $actor->getKey());
        }

        return $query;
    }

    /** Parse the local terminal interval into authoritative UTC instants. */
    private function interval(?ReceptionShift $shift): ?array
    {
        if (! $shift instanceof ReceptionShift) {
            return null;
        }
        try {
            $startsAt = CarbonImmutable::createFromFormat('!Y-m-d H:i', "{$this->arrivalDate} {$this->arrivalTime}", $shift->property->timezone)?->utc();
            $endsAt = CarbonImmutable::createFromFormat('!Y-m-d H:i', "{$this->departureDate} {$this->departureTime}", $shift->property->timezone)?->utc();
        } catch (\Throwable) {
            return null;
        }

        return $startsAt instanceof CarbonImmutable && $endsAt instanceof CarbonImmutable && $endsAt->greaterThan($startsAt)
            ? [$startsAt, $endsAt]
            : null;
    }

    /** Resolve and authorize the selected open shift. */
    private function requireShift(): ReceptionShift
    {
        $shift = $this->activeShift;
        abort_unless($shift instanceof ReceptionShift, 422);
        Gate::authorize('operate', $shift);

        return $shift;
    }

    /** Resolve a current authoritative quote. */
    private function requireQuote(): BookingQuote
    {
        $quote = $this->selectedQuote;
        if (! $quote instanceof BookingQuote) {
            throw new PointOfBookingException('Select a currently available accommodation rate.');
        }

        return $quote;
    }

    /** Resolve the selected reusable guest. */
    private function requireGuest(): Guest
    {
        $guest = $this->selectedGuest;
        if (! $guest instanceof Guest) {
            throw new PointOfBookingException('Select or create a guest before continuing.');
        }
        if ($this->guestIdentityRequired && blank($guest->identity_number_hash)) {
            throw new PointOfBookingException('Record the guest ID or passport before continuing with this booking.');
        }

        return $guest;
    }

    /** Convert validated decimal payment rows into immutable tender data. */
    private function tenderData(): array
    {
        $methods = array_map(static fn (BookingPaymentMethod $method): string => $method->value, $this->paymentMethods);
        $this->validate([
            'tenders' => ['required', 'array', 'min:1', 'max:'.max(1, (int) config('property-booking.pob.maximum_tenders', 4))],
            'tenders.*.method' => ['required', Rule::in($methods)],
            'tenders.*.amount' => ['required', 'decimal:0,'.$this->decimals(), 'gt:0'],
            'tenders.*.tendered' => ['nullable', 'decimal:0,'.$this->decimals(), 'gte:0'],
            'tenders.*.reference' => ['nullable', 'string', 'max:160'],
        ]);

        return collect($this->tenders)->map(function (array $tender): PobTenderData {
            $method = BookingPaymentMethod::from($tender['method']);
            $amount = ScaledDecimal::parseUnsigned($tender['amount'], $this->decimals());
            $tendered = trim($tender['tendered']) === '' ? null : ScaledDecimal::parseUnsigned($tender['tendered'], $this->decimals());

            return new PobTenderData(
                method: $method,
                amountMinor: $amount,
                tenderedMinor: $method === BookingPaymentMethod::Cash ? ($tendered ?? $amount) : null,
                reference: trim($tender['reference']) ?: null,
            );
        })->all();
    }

    /** Return the selected quote or resumed hold total. */
    private function currentTotalMinor(): int
    {
        return $this->resumingBooking?->total_minor ?? $this->selectedQuote?->calculation->totalMinor ?? 0;
    }

    /** Apply property check-in defaults to a future valid interval. */
    private function setDefaultInterval(ReceptionShift $shift): void
    {
        $local = CarbonImmutable::now($shift->property->timezone)->addDay()->startOfDay();
        $this->arrivalDate = $local->format('Y-m-d');
        $this->departureDate = $local->addDay()->format('Y-m-d');
        $this->arrivalTime = substr((string) ($shift->property->check_in_from ?: '14:00'), 0, 5);
        $this->departureTime = substr((string) ($shift->property->check_out_until ?: '10:00'), 0, 5);
    }

    /** Reset guest creation fields after successful persistence or cancellation. */
    private function resetGuestForm(): void
    {
        $this->showGuestForm = false;
        $this->guestFirstName = '';
        $this->guestMiddleName = '';
        $this->guestLastName = '';
        $this->guestEmail = '';
        $this->guestPhone = '';
        $this->guestCountryCode = (string) config('property-booking.defaults.country_code', 'KE');
        $this->guestIdentityType = '';
        $this->guestIdentityNumber = '';
        $this->resetValidation();
    }

    /** Reset booking-specific state while retaining availability search context. */
    private function resetBookingState(): void
    {
        $this->selectedRatePlanId = null;
        $this->selectedGuestId = null;
        $this->resumingBookingId = null;
        $this->guestSearch = '';
        $this->specialRequests = '';
        $this->internalNote = '';
        $this->resetGuestForm();
        $this->resetTenders();
        $this->resetValidation();
    }

    /** Restore one empty default tender row. */
    private function resetTenders(): void
    {
        $this->tenders = [$this->emptyTender($this->paymentMethods[0] ?? BookingPaymentMethod::Cash)];
    }

    /** @return array{method:string,amount:string,tendered:string,reference:string} */
    private function emptyTender(BookingPaymentMethod $method): array
    {
        return ['method' => $method->value, 'amount' => '', 'tendered' => '', 'reference' => ''];
    }

    /** Forget all terminal computed projections after mutation. */
    private function refreshTerminal(): void
    {
        unset(
            $this->activeShift,
            $this->availableShifts,
            $this->availableRates,
            $this->selectedQuote,
            $this->selectedGuest,
            $this->guestResults,
            $this->guestIdentitySatisfied,
            $this->heldBookings,
            $this->resumingBooking,
        );
    }

    /** Enforce the module capability at every request boundary. */
    private function authorizeTerminal(): void
    {
        Gate::authorize(PropertyBookingPermission::ACCESS_POB);
    }

    /** Resolve the authenticated operator. */
    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    /** Return bounded property-booking currency precision. */
    private function decimals(): int
    {
        return max(0, min(6, (int) config('property-booking.defaults.currency_decimals', 2)));
    }

    /** Resolve the configured lead-time policy for authenticated onsite bookings. */
    private function advanceNoticePolicy(): AdvanceNoticePolicy
    {
        return (bool) config('property-booking.pob.enforce_advance_notice', false)
            ? AdvanceNoticePolicy::Enforce
            : AdvanceNoticePolicy::WaiveForOnSiteBooking;
    }
}
