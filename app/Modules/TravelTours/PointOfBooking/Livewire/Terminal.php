<?php

/** Full-width booking-desk terminal: shift control, assisted selection, settlement, and receipts. */

declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Livewire;

use App\Modules\TravelTours\Bookings\Enums\PaymentRecordStatus;
use App\Modules\TravelTours\Bookings\Exceptions\AvailabilityException;
use App\Modules\TravelTours\Bookings\Exceptions\DuplicateOperationConflict;
use App\Modules\TravelTours\Bookings\Exceptions\PaymentException;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Contracts\CalculatesTourQuotes;
use App\Modules\TravelTours\Contracts\ChecksDepartureAvailability;
use App\Modules\TravelTours\Customers\Exceptions\CustomerIdentityConflict;
use App\Modules\TravelTours\PointOfBooking\Data\ReceiptPrintInstructionData;
use App\Modules\TravelTours\PointOfBooking\Data\ShiftMovementData;
use App\Modules\TravelTours\PointOfBooking\Enums\ShiftMovementType;
use App\Modules\TravelTours\PointOfBooking\Exceptions\PointOfBookingException;
use App\Modules\TravelTours\PointOfBooking\Livewire\Forms\DeskSaleForm;
use App\Modules\TravelTours\PointOfBooking\Models\BookingRegister;
use App\Modules\TravelTours\PointOfBooking\Models\BookingShift;
use App\Modules\TravelTours\PointOfBooking\Services\BookingShiftService;
use App\Modules\TravelTours\PointOfBooking\Services\DeskCheckoutService;
use App\Modules\TravelTours\PointOfBooking\Services\ReceiptPrinterManager;
use App\Modules\TravelTours\Pricing\Data\TourQuoteRequest;
use App\Modules\TravelTours\Pricing\Exceptions\InvalidRateConfiguration;
use App\Modules\TravelTours\Pricing\Exceptions\PromotionNotApplicable;
use App\Modules\TravelTours\Pricing\Models\TourRatePlan;
use App\Modules\TravelTours\Scheduling\Models\TourDeparture;
use App\Modules\TravelTours\Storefront\Data\QuoteAttempt;
use App\Modules\TravelTours\Support\MoneyFormatter;
use App\Modules\TravelTours\Support\ScaledDecimal;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Every sale runs through DeskCheckoutService with a key minted when the
 * form is started, so a double click records one booking. The operator must
 * hold an open shift of their own; shift money moves only through
 * BookingShiftService. Receipt printing is an instruction handed to the
 * browser after the sale has committed and never affects the sale.
 */
final class Terminal extends Component
{
    public DeskSaleForm $form;

    #[Locked]
    public string $operationKey = '';

    #[Locked]
    public ?int $lastBookingId = null;

    public string $dialog = '';

    public string $registerId = '';

    public string $openingFloat = '0';

    public string $countedCash = '';

    public string $closingNotes = '';

    public string $movementType = 'cash_in';

    public string $movementAmount = '';

    public string $movementReason = '';

    /** Require desk access on every request. */
    public function boot(): void
    {
        Gate::authorize('viewAny', BookingShift::class);
    }

    /** Start with a clean sale. */
    public function mount(): void
    {
        $this->startSale();
    }

    /** The operator's open shift, or null when they must open one first. */
    #[Computed]
    public function shift(): ?BookingShift
    {
        return app(BookingShiftService::class)->currentFor(auth()->user());
    }

    /** Expected cash in the drawer for the open shift. */
    public function expectedCashMinor(): int
    {
        $shift = $this->shift;

        return $shift instanceof BookingShift ? app(BookingShiftService::class)->expectedCashMinor($shift) : 0;
    }

    /**
     * Registers an operator may open a shift on.
     *
     * @return Collection<int, BookingRegister>
     */
    #[Computed]
    public function registers(): Collection
    {
        return BookingRegister::query()->where('is_active', true)->orderBy('name')->get();
    }

    /**
     * Published tours that currently have bookable departures.
     *
     * @return Collection<int, Tour>
     */
    #[Computed]
    public function tours(): Collection
    {
        return Tour::query()->published()->whereHas('departures', fn ($query) => $query->bookable())->orderBy('name')->get(['id', 'name', 'code']);
    }

    /**
     * Bookable departures of the chosen tour with their plan.
     *
     * @return Collection<int, TourDeparture>
     */
    #[Computed]
    public function departures(): Collection
    {
        if ($this->form->tourId === '') {
            return new Collection;
        }

        return TourDeparture::query()->where('tour_id', (int) $this->form->tourId)->bookable()->with('ratePlan')->orderBy('starts_at')->limit(24)->get();
    }

    /** Seats left on the chosen departure. */
    public function availableSeats(): ?int
    {
        $departure = $this->departures->firstWhere('id', (int) $this->form->departureId);

        return $departure instanceof TourDeparture ? app(ChecksDepartureAvailability::class)->check($departure)->availableSeats : null;
    }

    /** The live quote for the current selection, priced the same way the storefront prices it. */
    #[Computed]
    public function pricing(): QuoteAttempt
    {
        $departure = $this->departures->firstWhere('id', (int) $this->form->departureId);
        if (! $departure instanceof TourDeparture) {
            return QuoteAttempt::none();
        }
        $plan = $departure->ratePlan instanceof TourRatePlan && $departure->ratePlan->is_active
            ? $departure->ratePlan
            : TourRatePlan::query()->where('tour_id', $departure->tour_id)->where('is_active', true)->orderByDesc('is_default')->orderBy('display_order')->first();
        if (! $plan instanceof TourRatePlan) {
            return QuoteAttempt::failed('This departure has no active rate plan to price it.');
        }

        foreach (['adults', 'children', 'infants', 'promotionCode'] as $field) {
            try {
                $this->form->validateOnly($field);
            } catch (ValidationException $exception) {
                $this->setErrorBag($exception->validator->errors());

                return QuoteAttempt::none();
            }
        }

        try {
            $mix = $this->form->mix();
        } catch (InvalidArgumentException $exception) {
            return QuoteAttempt::failed($exception->getMessage());
        }

        $available = $this->availableSeats() ?? 0;
        if ($available < $mix->seats()) {
            return QuoteAttempt::failed($available === 0 ? 'This departure is fully booked.' : "Only {$available} ".($available === 1 ? 'place remains' : 'places remain').' on this departure.');
        }

        try {
            $code = strtoupper(trim($this->form->promotionCode));

            return QuoteAttempt::priced(app(CalculatesTourQuotes::class)->calculate(new TourQuoteRequest($departure->getKey(), $plan->getKey(), $mix, $code === '' ? null : $code)));
        } catch (PromotionNotApplicable $exception) {
            $this->addError('form.promotionCode', $exception->getMessage());

            return QuoteAttempt::none();
        } catch (InvalidRateConfiguration|AvailabilityException $exception) {
            return QuoteAttempt::failed($exception->getMessage());
        }
    }

    /** The booking just sold, for the receipt panel. */
    #[Computed]
    public function lastBooking(): ?TourBooking
    {
        return $this->lastBookingId === null ? null : TourBooking::query()->with(['payments', 'participants', 'register'])->find($this->lastBookingId);
    }

    /** The receipt instruction for the last sale's confirmed payment, when the register can print. */
    public function receiptInstruction(): ?ReceiptPrintInstructionData
    {
        $booking = $this->lastBooking;
        $payment = $booking?->payments->firstWhere('status', PaymentRecordStatus::Confirmed);
        $register = $booking?->register;
        if ($payment === null || ! $register instanceof BookingRegister) {
            return null;
        }

        try {
            return app(ReceiptPrinterManager::class)->instructionFor($payment, $register);
        } catch (PointOfBookingException) {
            return null;
        }
    }

    /** Reset the departure and keep traveller rows in step with the counts as the form changes. */
    public function updated(string $property): void
    {
        if ($property === 'form.tourId') {
            $this->form->departureId = '';
            unset($this->departures);
        }
        if (in_array($property, ['form.adults', 'form.children', 'form.infants'], true)) {
            $this->form->syncParticipants();
        }
        unset($this->pricing);
    }

    /** Begin a new sale with a fresh idempotency key. */
    public function startSale(): void
    {
        $this->form->start();
        $this->operationKey = (string) Str::ulid();
        $this->lastBookingId = null;
        $this->resetErrorBag();
        unset($this->pricing, $this->lastBooking);
    }

    /** Complete the sale for the operator's open shift. */
    public function sell(DeskCheckoutService $desk): void
    {
        $shift = $this->requireShift();
        if (! $shift instanceof BookingShift) {
            return;
        }
        $this->form->validate();

        try {
            $booking = $desk->sell($shift, auth()->user(), $this->form->toData($this->operationKey, (int) $shift->currency_exponent));
        } catch (PointOfBookingException|AvailabilityException|PaymentException|PromotionNotApplicable|InvalidRateConfiguration|CustomerIdentityConflict|DuplicateOperationConflict|InvalidArgumentException $exception) {
            $this->addError('desk', $exception->getMessage());

            return;
        }

        $this->lastBookingId = $booking->getKey();
        unset($this->lastBooking, $this->shift, $this->departures, $this->pricing);
        session()->flash('success', "Booking {$booking->booking_number} recorded.");
    }

    /** Open the shift dialog. */
    public function openShiftDialog(): void
    {
        Gate::authorize('open', BookingShift::class);
        $this->registerId = (string) ($this->registers->first()?->getKey() ?? '');
        $this->openingFloat = '0';
        $this->open('open-shift');
    }

    /** Open a shift on the chosen register with the counted float. */
    public function openShift(BookingShiftService $shifts): void
    {
        Gate::authorize('open', BookingShift::class);
        $this->validate(['registerId' => ['required', 'integer'], 'openingFloat' => ['required', 'regex:/^\d{1,12}(\.\d{1,2})?$/']], ['registerId.required' => 'Choose a register.', 'openingFloat.regex' => 'Enter the float as a plain decimal.']);

        try {
            $register = BookingRegister::query()->findOrFail((int) $this->registerId);
            $shifts->open(auth()->user(), $register, ScaledDecimal::toMinor($this->openingFloat, (int) config('travel-tours.defaults.currency_decimals', 2)));
        } catch (PointOfBookingException|InvalidArgumentException $exception) {
            $this->addError('desk', $exception->getMessage());

            return;
        }

        $this->finish('Shift opened.');
    }

    /** Open the cash movement dialog. */
    public function openMovementDialog(): void
    {
        if (! $this->requireShift() instanceof BookingShift) {
            return;
        }
        $this->movementType = 'cash_in';
        $this->movementAmount = '';
        $this->movementReason = '';
        $this->open('movement');
    }

    /** Declare cash in or out. */
    public function recordMovement(BookingShiftService $shifts): void
    {
        $shift = $this->requireShift();
        if (! $shift instanceof BookingShift) {
            return;
        }
        $this->validate(['movementType' => ['required', 'in:cash_in,cash_out'], 'movementAmount' => ['required', 'regex:/^\d{1,12}(\.\d{1,2})?$/'], 'movementReason' => ['required', 'string', 'min:3', 'max:500']], ['movementAmount.required' => 'Enter the amount.', 'movementAmount.regex' => 'Enter the amount as a plain decimal.', 'movementReason.required' => 'Say why the cash moved.']);

        try {
            $shifts->recordMovement($shift, new ShiftMovementData('movement:'.Str::ulid(), ShiftMovementType::from($this->movementType), ScaledDecimal::toMinor($this->movementAmount, (int) $shift->currency_exponent), $this->movementReason), (int) auth()->id());
        } catch (PointOfBookingException|InvalidArgumentException $exception) {
            $this->addError('desk', $exception->getMessage());

            return;
        }

        $this->finish('Cash movement recorded.');
    }

    /** Open the close-shift dialog. */
    public function openCloseDialog(): void
    {
        if (! $this->requireShift() instanceof BookingShift) {
            return;
        }
        $this->countedCash = '';
        $this->closingNotes = '';
        $this->open('close-shift');
    }

    /** Close the shift against the counted cash. */
    public function closeShift(BookingShiftService $shifts): void
    {
        $shift = $this->requireShift();
        if (! $shift instanceof BookingShift) {
            return;
        }
        $this->validate(['countedCash' => ['required', 'regex:/^\d{1,12}(\.\d{1,2})?$/'], 'closingNotes' => ['nullable', 'string', 'max:1000']], ['countedCash.required' => 'Count the cash in the drawer and enter it.', 'countedCash.regex' => 'Enter the counted cash as a plain decimal.']);

        try {
            $closed = $shifts->close($shift, ScaledDecimal::toMinor($this->countedCash, (int) $shift->currency_exponent), $this->closingNotes, (int) auth()->id());
        } catch (PointOfBookingException|InvalidArgumentException $exception) {
            $this->addError('desk', $exception->getMessage());

            return;
        }

        $variance = MoneyFormatter::format((int) $closed->variance_minor, $closed->currency, (int) $closed->currency_exponent);
        $this->finish($closed->variance_minor === 0 ? 'Shift closed with no variance.' : "Shift closed with a variance of {$variance}.");
    }

    /** Close any dialog. */
    public function closeDialog(): void
    {
        $this->dialog = '';
        $this->resetErrorBag();
    }

    /** Render the terminal; pricing runs first so its messages reach the view. */
    public function render(): View
    {
        $this->pricing;

        return view('travel-tours::livewire.pob.terminal');
    }

    /** The operator's open shift, or null after recording the refusal. */
    private function requireShift(): ?BookingShift
    {
        $shift = $this->shift;
        if (! $shift instanceof BookingShift) {
            $this->addError('desk', 'Open a shift before taking a booking.');
        }

        return $shift;
    }

    /** Open a dialog with a clean error bag. */
    private function open(string $dialog): void
    {
        $this->dialog = $dialog;
        $this->resetErrorBag();
    }

    /** Flash the outcome and refresh shift-dependent state. */
    private function finish(string $message): void
    {
        $this->dialog = '';
        session()->flash('success', $message);
        unset($this->shift);
    }
}
