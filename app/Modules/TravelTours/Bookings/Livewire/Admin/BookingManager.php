<?php

/** Central booking workspace: list, inspect, settle, and move bookings through their lifecycle. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Bookings\Livewire\Admin;

use App\Modules\TravelTours\Bookings\Enums\BookingChannel;
use App\Modules\TravelTours\Bookings\Enums\BookingStatus;
use App\Modules\TravelTours\Bookings\Enums\PaymentRecordStatus;
use App\Modules\TravelTours\Bookings\Enums\PaymentStatus;
use App\Modules\TravelTours\Bookings\Exceptions\BookingLifecycleException;
use App\Modules\TravelTours\Bookings\Exceptions\DuplicateOperationConflict;
use App\Modules\TravelTours\Bookings\Exceptions\PaymentException;
use App\Modules\TravelTours\Bookings\Livewire\Forms\PaymentRecordForm;
use App\Modules\TravelTours\Bookings\Livewire\Forms\RefundForm;
use App\Modules\TravelTours\Bookings\Models\BookingPayment;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\Bookings\Services\BookingLifecycleService;
use App\Modules\TravelTours\Contracts\ProcessesBookingPayments;
use App\Modules\TravelTours\Support\LikePattern;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Every action is authorized against the booking policy per request and every
 * write goes through the lifecycle or payment service, which re-lock and
 * re-check the booking before changing it. Money entered here is evidence
 * awaiting confirmation; confirmation is a separately permitted step.
 */
final class BookingManager extends Component
{
    use WithPagination;

    private const PER_PAGE = 15;

    public PaymentRecordForm $payment;

    public RefundForm $refund;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: '')]
    public string $statusFilter = '';

    #[Url(as: 'payment', except: '')]
    public string $paymentFilter = '';

    #[Url(as: 'channel', except: '')]
    public string $channelFilter = '';

    #[Url(as: 'attention', except: '')]
    public string $attentionFilter = '';

    #[Locked]
    public ?int $selectedId = null;

    #[Locked]
    public ?int $paymentId = null;

    /** Retry-safe key minted when a money dialog opens so a double submit records once. */
    #[Locked]
    public string $operationKey = '';

    public string $dialog = '';

    public string $reason = '';

    /** Require booking visibility on each Livewire request. */
    public function boot(): void
    {
        Gate::authorize('viewAny', TourBooking::class);
    }

    /** Clear pagination when a filter changes. */
    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'statusFilter', 'paymentFilter', 'channelFilter', 'attentionFilter'], true)) {
            $this->resetPage();
        }
    }

    /** Clear every filter and return to the first page. */
    public function clearFilters(): void
    {
        $this->reset('search', 'statusFilter', 'paymentFilter', 'channelFilter', 'attentionFilter');
        $this->resetPage();
    }

    /**
     * Bookings matching the filters, newest first.
     *
     * @return LengthAwarePaginator<int, TourBooking>
     */
    #[Computed]
    public function bookings(): LengthAwarePaginator
    {
        $term = trim($this->search);

        return TourBooking::query()
            ->with(['customer', 'departure'])
            ->when($term !== '', function ($query) use ($term): void {
                $like = LikePattern::contains($term);
                $query->where(function ($query) use ($like): void {
                    $query->whereRaw('booking_number '.LikePattern::CLAUSE, [$like])
                        ->orWhereRaw('customer_name_snapshot '.LikePattern::CLAUSE, [$like])
                        ->orWhereRaw('customer_email_snapshot '.LikePattern::CLAUSE, [$like])
                        ->orWhereRaw('tour_name_snapshot '.LikePattern::CLAUSE, [$like]);
                });
            })
            ->when(BookingStatus::tryFrom($this->statusFilter), fn ($query, BookingStatus $status) => $query->where('status', $status->value))
            ->when(PaymentStatus::tryFrom($this->paymentFilter), fn ($query, PaymentStatus $status) => $query->where('payment_status', $status->value))
            ->when(BookingChannel::tryFrom($this->channelFilter), fn ($query, BookingChannel $channel) => $query->where('channel', $channel->value))
            ->when($this->attentionFilter === 'payments', fn ($query) => $query->whereHas('payments', fn ($payment) => $payment->where('status', PaymentRecordStatus::Pending->value)))
            ->when($this->attentionFilter === 'bookings', fn ($query) => $query->where('status', BookingStatus::Pending->value))
            ->when($this->attentionFilter === 'balance', fn ($query) => $query->where('status', BookingStatus::Confirmed->value)->whereColumn('paid_minor', '<', 'total_minor'))
            ->when($this->attentionFilter === 'departing', fn ($query) => $query->where('status', BookingStatus::Confirmed->value)->whereBetween('departure_starts_at_snapshot', [now(), now()->addDays(30)]))
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE);
    }

    /**
     * Bookings by status, and the number waiting on someone here.
     *
     * @return array{statuses: array<string, int>, awaiting_payment_confirmation: int, awaiting_booking_confirmation: int}
     */
    #[Computed]
    public function statusCounts(): array
    {
        $counts = TourBooking::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'statuses' => collect(BookingStatus::cases())->mapWithKeys(fn (BookingStatus $status): array => [$status->value => (int) ($counts[$status->value] ?? 0)])->all(),
            'awaiting_payment_confirmation' => BookingPayment::query()->where('status', PaymentRecordStatus::Pending->value)->count(),
            'awaiting_booking_confirmation' => (int) ($counts[BookingStatus::Pending->value] ?? 0),
        ];
    }

    /** The booking open in a dialog with everything the detail view shows. */
    #[Computed]
    public function selected(): ?TourBooking
    {
        if ($this->selectedId === null) {
            return null;
        }

        return TourBooking::query()
            ->with([
                'customer', 'departure.tour', 'participants', 'priceLines',
                'payments' => fn ($query) => $query->with('receiver')->orderByDesc('id'),
                'refunds' => fn ($query) => $query->orderByDesc('id'),
                'statusHistory' => fn ($query) => $query->with('actor')->orderByDesc('changed_at')->orderByDesc('id'),
            ])
            ->find($this->selectedId);
    }

    /** Open the read-only detail dialog. */
    public function openDetails(int $bookingId): void
    {
        $booking = $this->find($bookingId);
        Gate::authorize('view', $booking);
        $this->open('details', $booking);
    }

    /** Open the payment evidence dialog with a fresh operation key. */
    public function openPayment(int $bookingId): void
    {
        $booking = $this->find($bookingId);
        Gate::authorize('recordPayment', $booking);
        $this->payment->start($booking);
        $this->operationKey = 'manual:'.Str::ulid();
        $this->open('payment', $booking);
    }

    /** Record payment evidence awaiting confirmation. */
    public function recordPayment(ProcessesBookingPayments $payments): void
    {
        $booking = $this->find((int) $this->selectedId);
        Gate::authorize('recordPayment', $booking);
        $this->payment->validate();

        try {
            $payments->recordPending($booking, $this->payment->toData($booking, $this->operationKey, $this->actor()));
        } catch (PaymentException|DuplicateOperationConflict|InvalidArgumentException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        $this->finish('Payment recorded and awaiting confirmation.', 'details');
    }

    /** Confirm recorded payment evidence and settle the booking. */
    public function confirmPayment(int $paymentId, ProcessesBookingPayments $payments): void
    {
        $payment = $this->findPayment($paymentId);
        Gate::authorize('confirmPayment', $payment->booking);

        try {
            $payments->confirm($payment, $this->actor());
        } catch (PaymentException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        $this->finish('Payment confirmed.', 'details');
    }

    /** Open the rejection dialog for one pending payment. */
    public function openReject(int $paymentId): void
    {
        $payment = $this->findPayment($paymentId);
        Gate::authorize('confirmPayment', $payment->booking);
        $this->paymentId = $payment->getKey();
        $this->reason = '';
        $this->open('reject', $payment->booking);
    }

    /** Reject recorded payment evidence with a reason. */
    public function rejectPayment(ProcessesBookingPayments $payments): void
    {
        $payment = $this->findPayment((int) $this->paymentId);
        Gate::authorize('confirmPayment', $payment->booking);
        $this->validate(['reason' => ['required', 'string', 'min:5', 'max:500']], ['reason.required' => 'Explain why the payment is rejected.', 'reason.min' => 'Give a fuller reason.']);

        try {
            $payments->reject($payment, $this->actor(), $this->reason);
        } catch (PaymentException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        $this->finish('Payment rejected.', 'details');
    }

    /** Open the refund dialog with a fresh operation key. */
    public function openRefund(int $bookingId): void
    {
        $booking = $this->find($bookingId);
        Gate::authorize('refund', $booking);
        $this->refund->start();
        $this->operationKey = 'refund:'.Str::ulid();
        $this->open('refund', $booking);
    }

    /** Record a processed refund against confirmed money. */
    public function recordRefund(ProcessesBookingPayments $payments): void
    {
        $booking = $this->find((int) $this->selectedId);
        Gate::authorize('refund', $booking);
        $this->refund->validate();

        try {
            $payments->refund($booking, $this->refund->toData($booking, $this->operationKey, $this->actor()));
        } catch (PaymentException|DuplicateOperationConflict|InvalidArgumentException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        $this->finish('Refund recorded.', 'details');
    }

    /** Confirm a pending booking. */
    public function confirmBooking(int $bookingId, BookingLifecycleService $lifecycle): void
    {
        $booking = $this->find($bookingId);
        Gate::authorize('update', $booking);

        try {
            $lifecycle->confirm($booking, $this->actor());
        } catch (BookingLifecycleException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        $this->finish('Booking confirmed.', $this->dialog === 'details' ? 'details' : '', $booking);
    }

    /** Open the cancellation dialog. */
    public function openCancel(int $bookingId): void
    {
        $booking = $this->find($bookingId);
        Gate::authorize('update', $booking);
        $this->reason = '';
        $this->open('cancel', $booking);
    }

    /** Cancel the selected booking with a reason. */
    public function cancelBooking(BookingLifecycleService $lifecycle): void
    {
        $booking = $this->find((int) $this->selectedId);
        Gate::authorize('update', $booking);
        $this->validate(['reason' => ['required', 'string', 'min:5', 'max:500']], ['reason.required' => 'Explain why the booking is cancelled.', 'reason.min' => 'Give a fuller reason.']);

        try {
            $lifecycle->cancel($booking, $this->actor(), $this->reason);
        } catch (BookingLifecycleException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        $this->finish('Booking cancelled.', 'details');
    }

    /** Mark a confirmed booking as completed after travel. */
    public function completeBooking(int $bookingId, BookingLifecycleService $lifecycle): void
    {
        $booking = $this->find($bookingId);
        Gate::authorize('update', $booking);

        try {
            $lifecycle->complete($booking, $this->actor());
        } catch (BookingLifecycleException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        $this->finish('Booking completed.', $this->dialog === 'details' ? 'details' : '', $booking);
    }

    /** Close any dialog and forget its transient input. */
    public function closeDialog(): void
    {
        $this->dialog = '';
        $this->selectedId = null;
        $this->paymentId = null;
        $this->reason = '';
        $this->resetErrorBag();
        unset($this->selected);
    }

    /** Render the workspace with fresh counts and the open dialog. */
    public function render(): View
    {
        return view('travel-tours::livewire.admin.bookings.booking-manager', [
            'statuses' => BookingStatus::cases(),
            'paymentStatuses' => PaymentStatus::cases(),
            'channels' => BookingChannel::cases(),
            'pendingRecordStatus' => PaymentRecordStatus::Pending,
        ]);
    }

    /** Resolve a booking or fail loudly; ids come from the rendered table only. */
    private function find(int $bookingId): TourBooking
    {
        return TourBooking::query()->findOrFail($bookingId);
    }

    /** Resolve a payment that belongs to the selected booking. */
    private function findPayment(int $paymentId): BookingPayment
    {
        return BookingPayment::query()->with('booking')->where('booking_id', $this->selectedId)->findOrFail($paymentId);
    }

    /** Select a booking, open a dialog, and drop stale messages. */
    private function open(string $dialog, TourBooking $booking): void
    {
        $this->selectedId = $booking->getKey();
        $this->dialog = $dialog;
        $this->resetErrorBag();
        unset($this->selected);
    }

    /** Flash the outcome, refresh the selection, and return to the requested dialog. */
    private function finish(string $message, string $dialog, ?TourBooking $booking = null): void
    {
        if ($booking instanceof TourBooking && $dialog !== '') {
            $this->selectedId = $booking->getKey();
        }
        $this->dialog = $dialog;
        $this->reason = '';
        $this->paymentId = null;
        session()->flash('success', $message);
        unset($this->selected, $this->bookings);
    }

    /** Identify the acting user for audit columns. */
    private function actor(): ?int
    {
        return auth()->id();
    }
}
