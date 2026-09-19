<?php

/** Full-width booking-desk terminal: departure search, customer and travellers, holds, and split settlement. */

declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Livewire;

use App\Models\User;
use App\Modules\TravelTours\Bookings\Data\BookingParticipantData;
use App\Modules\TravelTours\Bookings\Enums\BookingChannel;
use App\Modules\TravelTours\Bookings\Enums\BookingStatus;
use App\Modules\TravelTours\Bookings\Enums\ParticipantType;
use App\Modules\TravelTours\Bookings\Enums\PaymentMethod;
use App\Modules\TravelTours\Bookings\Exceptions\AvailabilityException;
use App\Modules\TravelTours\Bookings\Exceptions\BookingLifecycleException;
use App\Modules\TravelTours\Bookings\Exceptions\DuplicateOperationConflict;
use App\Modules\TravelTours\Bookings\Exceptions\PaymentException;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\Contracts\CalculatesTourQuotes;
use App\Modules\TravelTours\Contracts\ChecksDepartureAvailability;
use App\Modules\TravelTours\Customers\Data\TravelCustomerData;
use App\Modules\TravelTours\Customers\Exceptions\CustomerIdentityConflict;
use App\Modules\TravelTours\Customers\Models\TravelCustomer;
use App\Modules\TravelTours\Customers\Services\TravelCustomerService;
use App\Modules\TravelTours\PointOfBooking\Data\DeskSaleData;
use App\Modules\TravelTours\PointOfBooking\Data\DeskTenderData;
use App\Modules\TravelTours\PointOfBooking\Exceptions\PointOfBookingException;
use App\Modules\TravelTours\PointOfBooking\Models\BookingShift;
use App\Modules\TravelTours\PointOfBooking\Services\DeskCheckoutService;
use App\Modules\TravelTours\Pricing\Data\ParticipantMix;
use App\Modules\TravelTours\Pricing\Data\TourQuote;
use App\Modules\TravelTours\Pricing\Data\TourQuoteRequest;
use App\Modules\TravelTours\Pricing\Exceptions\InvalidRateConfiguration;
use App\Modules\TravelTours\Pricing\Exceptions\PromotionNotApplicable;
use App\Modules\TravelTours\Scheduling\Models\TourDeparture;
use App\Modules\TravelTours\Support\LikePattern;
use App\Modules\TravelTours\Support\ScaledDecimal;
use App\Modules\TravelTours\Support\TravelToursPermission;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Three panes, one shift: 01 finds a bookable departure and prices the
 * traveller mix, 02 captures the customer and travellers, 03 settles with
 * split tenders. Holds park a pending booking on the shift and are resumed
 * or discarded from the same screen. Every write goes through
 * DeskCheckoutService under a key minted per booking attempt, so a double
 * click never records two bookings.
 */
final class Terminal extends Component
{
    #[Url(as: 'shift', except: '')]
    public string $shiftUlid = '';

    public string $tourSearch = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public int $adults = 1;

    public int $children = 0;

    public int $infants = 0;

    #[Locked]
    public ?int $selectedDepartureId = null;

    public string $promotionCode = '';

    public string $customerSearch = '';

    #[Locked]
    public ?int $selectedCustomerId = null;

    public bool $showCustomerForm = false;

    public string $customerFirstName = '';

    public string $customerLastName = '';

    public string $customerEmail = '';

    public string $customerPhone = '';

    public bool $leadTravels = true;

    /** @var list<array{type: string, first_name: string, last_name: string, date_of_birth: string}> */
    public array $travellers = [];

    public string $specialRequests = '';

    /** @var list<array{method: string, amount: string, tendered: string, reference: string}> */
    public array $tenders = [];

    #[Locked]
    public ?int $resumingBookingId = null;

    #[Locked]
    public string $operationKey = '';

    /** Initialise the terminal from the operator's open shift and a forward departure window. */
    public function mount(): void
    {
        $this->authorizeTerminal();
        $shift = $this->availableShiftQuery()
            ->when($this->shiftUlid !== '', fn (Builder $query): Builder => $query->where('ulid', $this->shiftUlid))
            ->oldest('opened_at')
            ->first();
        $this->shiftUlid = $shift instanceof BookingShift ? $shift->ulid : '';
        $today = CarbonImmutable::now()->startOfDay();
        $this->dateFrom = $today->format('Y-m-d');
        $this->dateTo = $today->addMonths(6)->format('Y-m-d');
        $this->resetBookingState();
    }

    /** Reauthorise every Livewire request. */
    public function boot(): void
    {
        $this->authorizeTerminal();
    }

    /**
     * Open shifts this operator may work: their own, or every one for a shift manager.
     *
     * @return Collection<int, BookingShift>
     */
    #[Computed]
    public function availableShifts(): Collection
    {
        return $this->availableShiftQuery()->with(['register', 'operator'])->oldest('opened_at')->get();
    }

    /** The explicitly selected open shift. */
    #[Computed]
    public function activeShift(): ?BookingShift
    {
        if ($this->shiftUlid === '') {
            return null;
        }

        return $this->availableShiftQuery()->with(['register', 'operator'])->where('ulid', $this->shiftUlid)->first();
    }

    /**
     * Bookable departures in the window that can seat and price the current mix.
     *
     * @return Collection<int, array{departure: TourDeparture, quote: TourQuote, seats: int}>
     */
    #[Computed]
    public function availableDepartures(): Collection
    {
        $shift = $this->activeShift;
        $window = $this->window();
        $mix = $this->mix();
        if (! $shift instanceof BookingShift || $window === null || $mix === null) {
            return collect();
        }
        [$from, $to] = $window;
        $departures = TourDeparture::query()
            ->bookable()
            ->whereBetween('starts_at', [$from, $to])
            ->whereHas('tour', fn (Builder $tour): Builder => $tour->published())
            ->with(['tour.media', 'ratePlan'])
            ->when(trim($this->tourSearch) !== '', function (Builder $query): void {
                $like = LikePattern::contains($this->tourSearch);
                $query->where(fn (Builder $match): Builder => $match
                    ->whereRaw('travel_tour_departures.code '.LikePattern::CLAUSE, [$like])
                    ->orWhereHas('tour', fn (Builder $tour): Builder => $tour->whereRaw('travel_tours.name '.LikePattern::CLAUSE, [$like])->orWhereRaw('travel_tours.code '.LikePattern::CLAUSE, [$like])));
            })
            ->orderBy('starts_at')
            ->limit(max(1, min(50, (int) config('travel-tours.pob.search_results', 18))))
            ->get();
        $availability = app(ChecksDepartureAvailability::class);
        $quotes = app(CalculatesTourQuotes::class);
        $desk = app(DeskCheckoutService::class);

        return $departures->map(function (TourDeparture $departure) use ($availability, $quotes, $desk, $mix): ?array {
            $seats = $availability->check($departure)->availableSeats;
            if ($seats < $mix->seats()) {
                return null;
            }
            try {
                $quote = $quotes->calculate(new TourQuoteRequest($departure->getKey(), $desk->planFor($departure)->getKey(), $mix));
            } catch (PointOfBookingException|InvalidRateConfiguration|AvailabilityException|InvalidArgumentException) {
                return null;
            }

            return ['departure' => $departure, 'quote' => $quote, 'seats' => $seats];
        })->filter()->values();
    }

    /** The selected departure's listing entry, if it is still available. */
    #[Computed]
    public function selectedOption(): ?array
    {
        $option = $this->availableDepartures->first(fn (array $option): bool => $option['departure']->getKey() === $this->selectedDepartureId);

        return is_array($option) ? $option : null;
    }

    /** The authoritative quote for the selection with any promotion code applied. */
    #[Computed]
    public function selectedQuote(): ?TourQuote
    {
        $option = $this->selectedOption;
        if ($option === null) {
            return null;
        }
        $code = strtoupper(trim($this->promotionCode));
        if ($code === '') {
            return $option['quote'];
        }

        try {
            return app(CalculatesTourQuotes::class)->calculate(new TourQuoteRequest($option['departure']->getKey(), $option['quote']->ratePlanId, $option['quote']->participants, $code));
        } catch (PromotionNotApplicable $exception) {
            $this->addError('promotionCode', $exception->getMessage());

            return $option['quote'];
        } catch (InvalidRateConfiguration|AvailabilityException $exception) {
            $this->addError('promotionCode', $exception->getMessage());

            return null;
        }
    }

    /** The selected reusable customer profile. */
    #[Computed]
    public function selectedCustomer(): ?TravelCustomer
    {
        return $this->selectedCustomerId === null ? null : TravelCustomer::query()->find($this->selectedCustomerId);
    }

    /**
     * Customers matching the search box by name, email, or phone.
     *
     * @return Collection<int, TravelCustomer>
     */
    #[Computed]
    public function customerResults(): Collection
    {
        $search = trim($this->customerSearch);
        if (! $this->activeShift instanceof BookingShift || mb_strlen($search) < 2) {
            return collect();
        }
        $like = LikePattern::contains($search);

        return TravelCustomer::query()
            ->where(fn (Builder $match): Builder => $match
                ->whereRaw('first_name '.LikePattern::CLAUSE, [$like])
                ->orWhereRaw('last_name '.LikePattern::CLAUSE, [$like])
                ->orWhereRaw('email '.LikePattern::CLAUSE, [$like])
                ->orWhereRaw('phone '.LikePattern::CLAUSE, [$like]))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(8)
            ->get();
    }

    /**
     * Holds parked on the active shift: pending desk bookings awaiting settlement.
     *
     * @return Collection<int, TourBooking>
     */
    #[Computed]
    public function heldBookings(): Collection
    {
        $shift = $this->activeShift;
        if (! $shift instanceof BookingShift) {
            return collect();
        }

        return TourBooking::query()
            ->where('shift_id', $shift->getKey())
            ->where('channel', BookingChannel::BookingDesk->value)
            ->where('status', BookingStatus::Pending->value)
            ->latest('placed_at')
            ->get();
    }

    /** The hold currently loaded for settlement. */
    #[Computed]
    public function resumingBooking(): ?TourBooking
    {
        return $this->resumingBookingId === null ? null : $this->heldBookings->firstWhere('id', $this->resumingBookingId);
    }

    /** @return list<PaymentMethod> */
    #[Computed]
    public function paymentMethods(): array
    {
        return collect(config('travel-tours.pob.payment_methods', []))
            ->map(static fn (mixed $method): ?PaymentMethod => is_string($method) ? PaymentMethod::tryFrom($method) : null)
            ->filter()
            ->values()
            ->all();
    }

    /** Switch to one explicitly authorised open shift. */
    public function selectShift(string $shiftUlid): void
    {
        $shift = $this->availableShiftQuery()->where('ulid', $shiftUlid)->firstOrFail();
        Gate::authorize('operate', $shift);
        $this->shiftUlid = $shift->ulid;
        $this->resetBookingState();
        $this->refreshTerminal();
    }

    /** Snap the window to departures leaving from today. */
    public function useToday(): void
    {
        $today = CarbonImmutable::now()->startOfDay();
        $this->dateFrom = $today->format('Y-m-d');
        $this->dateTo = $today->addMonths(6)->format('Y-m-d');
        $this->selectedDepartureId = null;
        $this->resumingBookingId = null;
        $this->resetTenders();
        $this->resetValidation();
        unset($this->availableDepartures, $this->selectedOption, $this->selectedQuote, $this->resumingBooking);
    }

    /** Keep traveller rows, the selection, and the quote in step with the inputs. */
    public function updated(string $property): void
    {
        if (in_array($property, ['adults', 'children', 'infants'], true)) {
            $this->adults = max(1, min(50, $this->adults));
            $this->children = max(0, min(50, $this->children));
            $this->infants = max(0, min(50, $this->infants));
            $this->syncTravellers();
        }
        if (in_array($property, ['adults', 'children', 'infants', 'dateFrom', 'dateTo', 'tourSearch'], true)) {
            $this->resumingBookingId = null;
            unset($this->availableDepartures, $this->selectedOption, $this->selectedQuote, $this->resumingBooking);
        }
        if ($property === 'promotionCode') {
            $this->resetValidation('promotionCode');
            unset($this->selectedQuote);
        }
    }

    /** Select a departure currently listed as available. */
    public function selectDeparture(int $departureId): void
    {
        abort_unless($this->availableDepartures->contains(fn (array $option): bool => $option['departure']->getKey() === $departureId), 422);
        $this->selectedDepartureId = $departureId;
        $this->resumingBookingId = null;
        $this->resetTenders();
        unset($this->selectedOption, $this->selectedQuote, $this->resumingBooking);
    }

    /** Select a customer visible in the result list. */
    public function selectCustomer(int $customerId): void
    {
        $customer = $this->customerResults->firstWhere('id', $customerId);
        abort_unless($customer instanceof TravelCustomer, 404);
        $this->selectedCustomerId = $customer->getKey();
        $this->customerSearch = '';
        $this->showCustomerForm = false;
        unset($this->selectedCustomer, $this->customerResults);
    }

    /** Drop the selected customer so another can be chosen. */
    public function clearCustomer(): void
    {
        $this->selectedCustomerId = null;
        unset($this->selectedCustomer);
    }

    /** Toggle the compact new-customer form. */
    public function toggleCustomerForm(): void
    {
        Gate::authorize('create', TravelCustomer::class);
        $this->showCustomerForm = ! $this->showCustomerForm;
        $this->resetValidation();
    }

    /** Create (or resolve) and select a reusable customer profile. */
    public function createCustomer(TravelCustomerService $customers): void
    {
        Gate::authorize('create', TravelCustomer::class);
        $validated = $this->validate([
            'customerFirstName' => ['required', 'string', 'max:100'],
            'customerLastName' => ['required', 'string', 'max:100'],
            'customerEmail' => ['nullable', 'email:rfc', 'max:254'],
            'customerPhone' => ['required', 'string', 'max:40', 'regex:/^\+?[0-9 ().-]{7,30}$/'],
        ], [
            'customerPhone.required' => 'A phone number is needed to reach the customer.',
            'customerPhone.regex' => 'Enter a phone number using digits, spaces, and an optional country code.',
        ]);

        try {
            $customer = $customers->resolve(new TravelCustomerData(
                firstName: trim($validated['customerFirstName']),
                lastName: trim($validated['customerLastName']),
                email: filled($validated['customerEmail']) ? mb_strtolower(trim($validated['customerEmail'])) : null,
                phone: trim($validated['customerPhone']),
            ), $this->actor()->getKey());
        } catch (CustomerIdentityConflict $exception) {
            $this->addError('customerPhone', $exception->getMessage());

            return;
        }

        $this->selectedCustomerId = $customer->getKey();
        $this->resetCustomerForm();
        session()->flash('terminal-success', $customer->wasRecentlyCreated ? 'Customer profile created and selected.' : 'Existing customer profile selected.');
        unset($this->selectedCustomer, $this->customerResults);
    }

    /** Append one empty split-payment row up to the configured maximum. */
    public function addTender(): void
    {
        if (count($this->tenders) < $this->maximumTenders()) {
            $this->tenders[] = $this->emptyTender($this->paymentMethods[0] ?? PaymentMethod::Cash);
        }
    }

    /** Remove one split-payment row while keeping at least one. */
    public function removeTender(int $index): void
    {
        if (count($this->tenders) <= 1 || ! array_key_exists($index, $this->tenders)) {
            return;
        }
        unset($this->tenders[$index]);
        $this->tenders = array_values($this->tenders);
        $this->resetValidation();
    }

    /** Put the outstanding balance on one tender row. */
    public function applyTotal(int $index): void
    {
        $this->applyAmount($index, $this->currentBalanceMinor());
    }

    /** Put the outstanding deposit on one tender row. */
    public function applyDeposit(int $index): void
    {
        $this->applyAmount($index, $this->currentDepositMinor());
    }

    /** Park the current selection as a pending booking on the shift. */
    public function holdBooking(DeskCheckoutService $checkout): void
    {
        $shift = $this->requireShift();

        try {
            $booking = $checkout->hold($shift, $this->actor(), $this->saleData());
        } catch (PointOfBookingException|AvailabilityException|PromotionNotApplicable|InvalidRateConfiguration|CustomerIdentityConflict|DuplicateOperationConflict|InvalidArgumentException $exception) {
            $this->addError('terminal', $exception->getMessage());

            return;
        }

        $this->resetBookingState();
        session()->flash('terminal-success', "Booking {$booking->booking_number} held on this shift.");
        $this->refreshTerminal();
    }

    /** Complete a new selection or a resumed hold with exact split tenders, then open the receipt. */
    public function completeBooking(DeskCheckoutService $checkout): mixed
    {
        $shift = $this->requireShift();
        $actor = $this->actor();

        try {
            $tenders = $this->tenderData();
            $held = $this->resumingBooking;
            $booking = $held instanceof TourBooking
                ? $checkout->checkoutHeld($held, $tenders, $shift, $actor, $this->operationKey)
                : $checkout->checkout($shift, $actor, $this->saleData(), $tenders);
        } catch (PointOfBookingException|AvailabilityException|PaymentException|BookingLifecycleException|PromotionNotApplicable|InvalidRateConfiguration|CustomerIdentityConflict|DuplicateOperationConflict|InvalidArgumentException $exception) {
            $this->addError('terminal', $exception->getMessage());

            return null;
        }

        return $this->redirectRoute('travel-tours.pob.receipts.show', ['booking' => $booking->ulid, 'print' => 'checkout']);
    }

    /** Load one hold into the payment pane for settlement. */
    public function resumeHold(int $bookingId): void
    {
        $booking = $this->heldBookings->firstWhere('id', $bookingId);
        abort_unless($booking instanceof TourBooking, 404);
        $this->resumingBookingId = $booking->getKey();
        $this->selectedDepartureId = null;
        $this->selectedCustomerId = $booking->customer_id;
        $this->operationKey = (string) Str::ulid();
        $this->resetTenders();
        $this->resetValidation();
        unset($this->selectedOption, $this->selectedQuote, $this->selectedCustomer, $this->resumingBooking);
    }

    /** Discard one hold, releasing its seats. */
    public function discardHold(int $bookingId, DeskCheckoutService $checkout): void
    {
        $booking = $this->heldBookings->firstWhere('id', $bookingId);
        abort_unless($booking instanceof TourBooking, 404);

        try {
            $checkout->discardHeld($booking, $this->requireShift(), $this->actor());
        } catch (PointOfBookingException|BookingLifecycleException $exception) {
            $this->addError('terminal', $exception->getMessage());

            return;
        }
        if ($this->resumingBookingId === $bookingId) {
            $this->resetBookingState();
        }
        session()->flash('terminal-success', "Hold {$booking->booking_number} discarded.");
        $this->refreshTerminal();
    }

    /** Clear the current booking while keeping the shift and search window. */
    public function resetBooking(): void
    {
        $this->resetBookingState();
        $this->refreshTerminal();
    }

    /** Render the terminal; the quote runs first so promotion messages reach the view. */
    public function render(): View
    {
        $this->selectedQuote;

        return view('travel-tours::livewire.pob.terminal');
    }

    /** Open shifts the operator may work. */
    private function availableShiftQuery(): Builder
    {
        $actor = $this->actor();
        $query = BookingShift::query()->open();
        if (! $actor->can(TravelToursPermission::MANAGE_SHIFTS)) {
            $query->where('operator_id', $actor->getKey());
        }

        return $query;
    }

    /**
     * Parse the departure window into UTC instants.
     *
     * @return array{CarbonImmutable, CarbonImmutable}|null
     */
    private function window(): ?array
    {
        try {
            $from = CarbonImmutable::createFromFormat('!Y-m-d', $this->dateFrom)?->startOfDay();
            $to = CarbonImmutable::createFromFormat('!Y-m-d', $this->dateTo)?->endOfDay();
        } catch (\Throwable) {
            return null;
        }

        return $from instanceof CarbonImmutable && $to instanceof CarbonImmutable && $to->greaterThan($from) ? [$from, $to] : null;
    }

    /** The traveller mix as the shared pricing input, or null when it cannot price. */
    private function mix(): ?ParticipantMix
    {
        try {
            return new ParticipantMix($this->adults, $this->children, $this->infants);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /** Resolve and authorise the selected open shift. */
    private function requireShift(): BookingShift
    {
        $shift = $this->activeShift;
        abort_unless($shift instanceof BookingShift, 422);
        Gate::authorize('operate', $shift);

        return $shift;
    }

    /** Validate the booking pane and build the typed sale; the service prices everything again. */
    private function saleData(): DeskSaleData
    {
        $option = $this->selectedOption;
        if ($option === null) {
            throw new PointOfBookingException('Select an available departure first.');
        }
        $customer = $this->selectedCustomer;
        if (! $customer instanceof TravelCustomer) {
            throw new PointOfBookingException('Select or create a customer before continuing.');
        }
        $this->validate($this->travellerRules(), $this->travellerMessages());

        $participants = [];
        foreach ($this->travellers as $index => $traveller) {
            $usesCustomer = $index === 0 && $this->leadTravels;
            $participants[] = new BookingParticipantData(
                type: ParticipantType::from($traveller['type']),
                firstName: trim($usesCustomer ? $customer->first_name : $traveller['first_name']),
                lastName: trim($usesCustomer ? $customer->last_name : $traveller['last_name']),
                dateOfBirth: filled($traveller['date_of_birth']) ? CarbonImmutable::parse($traveller['date_of_birth']) : null,
                email: $usesCustomer ? $customer->email : null,
                phone: $usesCustomer ? $customer->phone : null,
                isLead: $index === 0,
            );
        }
        $code = strtoupper(trim($this->promotionCode));

        return new DeskSaleData(
            operationKey: $this->operationKey,
            departureId: $option['departure']->getKey(),
            mix: $option['quote']->participants,
            customer: new TravelCustomerData(firstName: $customer->first_name, lastName: $customer->last_name, email: $customer->email, phone: $customer->phone),
            participants: $participants,
            promotionCode: $code === '' ? null : $code,
            specialRequests: trim($this->specialRequests) === '' ? null : trim($this->specialRequests),
        );
    }

    /** @return array<string, list<string>> */
    private function travellerRules(): array
    {
        $rules = ['travellers' => ['required', 'array', 'min:1'], 'leadTravels' => ['boolean'], 'specialRequests' => ['nullable', 'string', 'max:1000']];
        foreach ($this->travellers as $index => $traveller) {
            $named = ! ($index === 0 && $this->leadTravels);
            $minor = in_array($traveller['type'] ?? '', [ParticipantType::Child->value, ParticipantType::Infant->value], true);
            $rules["travellers.{$index}.first_name"] = $named ? ['required', 'string', 'max:80'] : ['nullable', 'string', 'max:80'];
            $rules["travellers.{$index}.last_name"] = $named ? ['required', 'string', 'max:80'] : ['nullable', 'string', 'max:80'];
            $rules["travellers.{$index}.date_of_birth"] = [$minor ? 'required' : 'nullable', 'date', 'before:today'];
        }

        return $rules;
    }

    /** @return array<string, string> */
    private function travellerMessages(): array
    {
        $messages = [];
        foreach (array_keys($this->travellers) as $index) {
            $number = $index + 1;
            $messages["travellers.{$index}.first_name.required"] = "Enter traveller {$number}'s first name.";
            $messages["travellers.{$index}.last_name.required"] = "Enter traveller {$number}'s last name.";
            $messages["travellers.{$index}.date_of_birth.required"] = "Enter traveller {$number}'s date of birth so the fare can be confirmed.";
            $messages["travellers.{$index}.date_of_birth.before"] = "Traveller {$number}'s date of birth must be in the past.";
        }

        return $messages;
    }

    /**
     * Validate the payment rows and convert them into immutable tenders.
     *
     * @return list<DeskTenderData>
     */
    private function tenderData(): array
    {
        $methods = array_map(static fn (PaymentMethod $method): string => $method->value, $this->paymentMethods);
        $decimals = $this->decimals();
        foreach ($this->tenders as $index => $tender) {
            // Only cash carries an amount handed over; clear anything a method switch left behind.
            if (($tender['method'] ?? null) !== PaymentMethod::Cash->value) {
                $this->tenders[$index]['tendered'] = '';
            } else {
                $this->tenders[$index]['reference'] = '';
            }
        }
        $this->validate([
            'tenders' => ['required', 'array', 'min:1', 'max:'.$this->maximumTenders()],
            'tenders.*.method' => ['required', Rule::in($methods)],
            'tenders.*.amount' => ['required', 'regex:/^\d{1,12}(\.\d{1,'.$decimals.'})?$/'],
            'tenders.*.tendered' => ['nullable', 'regex:/^\d{1,12}(\.\d{1,'.$decimals.'})?$/'],
            'tenders.*.reference' => ['nullable', 'string', 'max:120'],
        ], [
            'tenders.*.amount.required' => 'Enter the amount applied.',
            'tenders.*.amount.regex' => 'Enter the amount as a plain decimal, for example 12500.00.',
            'tenders.*.tendered.regex' => 'Enter the cash tendered as a plain decimal.',
        ]);

        return collect($this->tenders)->map(function (array $tender) use ($decimals): DeskTenderData {
            $method = PaymentMethod::from($tender['method']);
            $amount = ScaledDecimal::toMinor($tender['amount'], $decimals);
            $tendered = trim($tender['tendered']) === '' ? null : ScaledDecimal::toMinor($tender['tendered'], $decimals);

            return new DeskTenderData(
                method: $method,
                amountMinor: $amount,
                tenderedMinor: $method === PaymentMethod::Cash ? ($tendered ?? $amount) : null,
                reference: trim($tender['reference']) === '' ? null : trim($tender['reference']),
            );
        })->all();
    }

    /** Put one exact amount on a tender row, mirroring it into cash tendered. */
    private function applyAmount(int $index, int $amountMinor): void
    {
        if (! array_key_exists($index, $this->tenders)) {
            return;
        }
        $this->tenders[$index]['amount'] = ScaledDecimal::formatUnsigned($amountMinor, $this->decimals());
        if ($this->tenders[$index]['method'] === PaymentMethod::Cash->value) {
            $this->tenders[$index]['tendered'] = $this->tenders[$index]['amount'];
        }
    }

    /** Outstanding balance of the resumed hold or the selected quote. */
    private function currentBalanceMinor(): int
    {
        $held = $this->resumingBooking;

        return $held instanceof TourBooking ? (int) $held->balance_minor : ($this->selectedQuote?->totalMinor ?? 0);
    }

    /** Outstanding deposit of the resumed hold or the selected quote. */
    private function currentDepositMinor(): int
    {
        $held = $this->resumingBooking;

        return $held instanceof TourBooking
            ? max((int) $held->deposit_required_minor - (int) $held->paid_minor, 0)
            : ($this->selectedQuote?->depositMinor ?? 0);
    }

    /** Keep one traveller row per counted seat, preserving what was already typed. */
    private function syncTravellers(): void
    {
        $wanted = [];
        foreach ([[ParticipantType::Adult, $this->adults], [ParticipantType::Child, $this->children], [ParticipantType::Infant, $this->infants]] as [$type, $count]) {
            for ($i = 0; $i < max(0, $count); $i++) {
                $wanted[] = $type->value;
            }
        }
        $existing = $this->travellers;
        $rows = [];
        foreach ($wanted as $type) {
            $match = null;
            foreach ($existing as $key => $row) {
                if (($row['type'] ?? null) === $type) {
                    $match = $row;
                    unset($existing[$key]);
                    break;
                }
            }
            $rows[] = $match ?? ['type' => $type, 'first_name' => '', 'last_name' => '', 'date_of_birth' => ''];
        }
        $this->travellers = $rows;
    }

    /** Reset the new-customer fields after persistence or cancellation. */
    private function resetCustomerForm(): void
    {
        $this->showCustomerForm = false;
        $this->customerFirstName = '';
        $this->customerLastName = '';
        $this->customerEmail = '';
        $this->customerPhone = '';
        $this->resetValidation();
    }

    /** Reset booking-specific state while retaining the search window and shift. */
    private function resetBookingState(): void
    {
        $this->selectedDepartureId = null;
        $this->selectedCustomerId = null;
        $this->resumingBookingId = null;
        $this->customerSearch = '';
        $this->promotionCode = '';
        $this->specialRequests = '';
        $this->leadTravels = true;
        $this->travellers = [];
        $this->operationKey = (string) Str::ulid();
        $this->syncTravellers();
        $this->resetCustomerForm();
        $this->resetTenders();
        $this->resetValidation();
    }

    /** Restore one empty default tender row. */
    private function resetTenders(): void
    {
        $this->tenders = [$this->emptyTender($this->paymentMethods[0] ?? PaymentMethod::Cash)];
    }

    /** @return array{method: string, amount: string, tendered: string, reference: string} */
    private function emptyTender(PaymentMethod $method): array
    {
        return ['method' => $method->value, 'amount' => '', 'tendered' => '', 'reference' => ''];
    }

    /** Forget every computed projection after a mutation. */
    private function refreshTerminal(): void
    {
        unset(
            $this->activeShift,
            $this->availableShifts,
            $this->availableDepartures,
            $this->selectedOption,
            $this->selectedQuote,
            $this->selectedCustomer,
            $this->customerResults,
            $this->heldBookings,
            $this->resumingBooking,
        );
    }

    /** Enforce the desk capability at every request boundary. */
    private function authorizeTerminal(): void
    {
        Gate::authorize(TravelToursPermission::ACCESS_POB);
    }

    /** Resolve the authenticated operator. */
    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    /** The bounded number of split tenders one booking may carry. */
    private function maximumTenders(): int
    {
        return max(1, min(10, (int) config('travel-tours.pob.maximum_tenders', 4)));
    }

    /** Bounded module currency precision. */
    private function decimals(): int
    {
        return max(0, min(6, (int) config('travel-tours.defaults.currency_decimals', 2)));
    }
}
