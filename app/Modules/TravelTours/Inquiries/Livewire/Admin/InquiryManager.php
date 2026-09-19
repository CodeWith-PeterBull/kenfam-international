<?php

/** Provides policy-authorized TravelTours inquiry follow-up administration. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Inquiries\Livewire\Admin;

use App\Models\User;
use App\Modules\TravelTours\Inquiries\Enums\InquiryStatus;
use App\Modules\TravelTours\Inquiries\Enums\InquiryType;
use App\Modules\TravelTours\Inquiries\Exceptions\InquiryException;
use App\Modules\TravelTours\Inquiries\Models\TourInquiry;
use App\Modules\TravelTours\Inquiries\Services\TourInquiryService;
use App\Modules\TravelTours\Support\LikePattern;
use App\Modules\TravelTours\Support\StaffRecipientResolver;
use App\Modules\TravelTours\Support\TravelToursPermission;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The follow-up queue: an enumerated, filterable list of inquiries and a
 * details dialog where an operator reads the request, owns it, schedules
 * the next follow-up, moves it through its lifecycle, and records what was
 * said. Every write reauthorizes the record and runs through the inquiry
 * service, which keeps the activity timeline.
 */
final class InquiryManager extends Component
{
    use WithPagination;

    private const PER_PAGE = 15;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: '')]
    public string $statusFilter = '';

    #[Url(as: 'type', except: '')]
    public string $typeFilter = '';

    #[Url(as: 'owner', except: '')]
    public string $ownerFilter = '';

    #[Url(as: 'due', except: '')]
    public string $followUpFilter = '';

    public string $dialog = '';

    #[Locked]
    public ?int $selectedInquiryId = null;

    public string $assigneeId = '';

    public string $status = '';

    public string $followUpAt = '';

    public string $activityType = 'note';

    public string $activityNote = '';

    /** Reauthorize every Livewire request, including hydration requests. */
    public function boot(): void
    {
        Gate::authorize('viewAny', TourInquiry::class);
    }

    /** Return to the first page whenever a filter changes. */
    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'statusFilter', 'typeFilter', 'ownerFilter', 'followUpFilter'], true)) {
            $this->resetPage();
        }
    }

    /**
     * The filtered follow-up queue, newest first.
     *
     * @return LengthAwarePaginator<int, TourInquiry>
     */
    #[Computed]
    public function inquiries(): LengthAwarePaginator
    {
        $search = trim($this->search);
        $status = InquiryStatus::tryFrom($this->statusFilter);
        $type = InquiryType::tryFrom($this->typeFilter);

        return TourInquiry::query()
            ->with(['tour', 'assignee'])
            ->when($search !== '', static fn (Builder $query) => $query->where(static fn (Builder $match) => $match
                ->whereRaw('reference '.LikePattern::CLAUSE, [LikePattern::contains($search)])
                ->orWhereRaw('contact_name '.LikePattern::CLAUSE, [LikePattern::contains($search)])
                ->orWhereRaw('contact_email '.LikePattern::CLAUSE, [LikePattern::contains($search)])
                ->orWhereRaw('contact_phone '.LikePattern::CLAUSE, [LikePattern::contains($search)])))
            ->when($status, static fn (Builder $query) => $query->where('status', $status->value))
            ->when($type, static fn (Builder $query) => $query->where('inquiry_type', $type->value))
            ->when($this->ownerFilter === 'mine', fn (Builder $query) => $query->where('assigned_to', $this->actor()->getKey()))
            ->when($this->ownerFilter === 'unassigned', static fn (Builder $query) => $query->whereNull('assigned_to'))
            ->when($this->followUpFilter === 'overdue', static fn (Builder $query) => $query->actionable()->where('follow_up_at', '<', now()))
            ->when($this->followUpFilter === 'today', static fn (Builder $query) => $query->actionable()->whereBetween('follow_up_at', [now()->startOfDay(), now()->endOfDay()]))
            ->when($this->followUpFilter === 'week', static fn (Builder $query) => $query->actionable()->whereBetween('follow_up_at', [now()->startOfDay(), now()->addDays(7)->endOfDay()]))
            ->when($this->followUpFilter === 'unscheduled', static fn (Builder $query) => $query->actionable()->whereNull('follow_up_at'))
            ->latest('created_at')
            ->paginate(self::PER_PAGE);
    }

    /** @return array{new: int, open: int, overdue: int, awaiting: int} */
    #[Computed]
    public function statistics(): array
    {
        return [
            'new' => TourInquiry::query()->where('status', InquiryStatus::New->value)->count(),
            'open' => TourInquiry::query()->actionable()->count(),
            'overdue' => TourInquiry::query()->actionable()->where('follow_up_at', '<', now())->count(),
            'awaiting' => TourInquiry::query()->where('status', InquiryStatus::AwaitingCustomer->value)->count(),
        ];
    }

    /** The inquiry open in the details dialog with its request context and timeline. */
    #[Computed]
    public function selectedInquiry(): ?TourInquiry
    {
        if ($this->dialog !== 'details' || $this->selectedInquiryId === null) {
            return null;
        }

        return TourInquiry::query()
            ->with(['tour', 'departure', 'assignee', 'customer', 'convertedBooking', 'activities' => fn ($query) => $query->with('actor')->latest('occurred_at')->latest('id')])
            ->find($this->selectedInquiryId);
    }

    /**
     * Operators who may own an inquiry.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function operators(): Collection
    {
        return app(StaffRecipientResolver::class)->holding(TravelToursPermission::MANAGE_INQUIRIES)->sortBy('name')->values();
    }

    /** @return list<InquiryStatus> */
    #[Computed]
    public function statusOptions(): array
    {
        return array_values(array_filter(InquiryStatus::cases(), static fn (InquiryStatus $status): bool => $status !== InquiryStatus::Converted));
    }

    /** Open the details dialog for anyone allowed to read the inquiry. */
    public function openDetails(int $inquiryId): void
    {
        $inquiry = TourInquiry::query()->findOrFail($inquiryId);
        Gate::authorize('view', $inquiry);
        $this->closeDialog();
        $this->selectedInquiryId = $inquiry->getKey();
        $this->assigneeId = (string) ($inquiry->assigned_to ?? '');
        $this->status = $inquiry->status->value;
        $this->followUpAt = $inquiry->follow_up_at?->format('Y-m-d\TH:i') ?? '';
        $this->dialog = 'details';
    }

    /** Take ownership of an inquiry straight from the list. */
    public function assignToMe(int $inquiryId, TourInquiryService $inquiries): void
    {
        $inquiry = TourInquiry::query()->findOrFail($inquiryId);
        Gate::authorize('update', $inquiry);

        try {
            $inquiries->assign($inquiry, $this->actor(), $this->actor());
        } catch (InquiryException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        session()->flash('success', "Inquiry {$inquiry->reference} assigned to you.");
        $this->refreshQueue();
    }

    /** Save the owner, lifecycle state, and follow-up from the details dialog. */
    public function saveFollowUp(TourInquiryService $inquiries): void
    {
        $inquiry = $this->writableInquiry();
        $this->validate([
            'assigneeId' => ['nullable', 'integer', Rule::in($this->operators->pluck('id')->all())],
            'status' => ['required', Rule::in(array_map(static fn (InquiryStatus $status): string => $status->value, $this->statusOptions))],
            'followUpAt' => ['nullable', 'date'],
        ], [
            'assigneeId.in' => 'Choose an operator who manages inquiries.',
            'followUpAt.date' => 'Enter a valid follow-up date and time.',
        ]);

        try {
            $assignee = $this->assigneeId === '' ? null : $this->operators->firstWhere('id', (int) $this->assigneeId);
            if (($assignee?->getKey()) !== $inquiry->assigned_to) {
                $inquiry = $inquiries->assign($inquiry, $assignee, $this->actor());
            }
            $target = InquiryStatus::from($this->status);
            if ($target !== $inquiry->status) {
                $inquiry = $inquiries->transition($inquiry, $target, $this->actor());
            }
            $followUp = $this->followUpAt === '' ? null : CarbonImmutable::parse($this->followUpAt, config('app.timezone'))->utc();
            if (! $target->isTerminal() && $followUp?->toIso8601String() !== $inquiry->follow_up_at?->toIso8601String()) {
                $inquiries->scheduleFollowUp($inquiry, $followUp, $this->actor());
            }
        } catch (InquiryException $exception) {
            $this->addError('management', $exception->getMessage());
            $this->refreshQueue();

            return;
        }

        session()->flash('success', "Inquiry {$inquiry->reference} updated.");
        $this->openDetails($inquiry->getKey());
        $this->refreshQueue();
    }

    /** Record what was said or done on the inquiry. */
    public function addActivity(TourInquiryService $inquiries): void
    {
        $inquiry = $this->writableInquiry();
        $this->validate([
            'activityType' => ['required', Rule::in(TourInquiryService::NOTE_TYPES)],
            'activityNote' => ['required', 'string', 'max:2000'],
        ], ['activityNote.required' => 'Write what happened before saving the activity.']);

        try {
            $inquiries->addActivity($inquiry, $this->activityType, $this->activityNote, $this->actor());
        } catch (InquiryException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        $this->activityNote = '';
        $this->resetValidation();
        unset($this->selectedInquiry);
        $this->refreshQueue();
    }

    /** Clear every filter and return to the first page. */
    public function clearFilters(): void
    {
        $this->reset('search', 'statusFilter', 'typeFilter', 'ownerFilter', 'followUpFilter');
        $this->resetPage();
    }

    /** Close the dialog and reset its transient state. */
    public function closeDialog(): void
    {
        $this->dialog = '';
        $this->selectedInquiryId = null;
        $this->assigneeId = '';
        $this->status = '';
        $this->followUpAt = '';
        $this->activityType = 'note';
        $this->activityNote = '';
        $this->resetValidation();
        unset($this->selectedInquiry);
    }

    /** Render the module-owned inquiry manager. */
    public function render(): View
    {
        return view('travel-tours::livewire.admin.inquiries.inquiry-manager');
    }

    /** Resolve the inquiry open in the dialog and authorize a write. */
    private function writableInquiry(): TourInquiry
    {
        abort_unless($this->dialog === 'details' && $this->selectedInquiryId !== null, 404);
        $inquiry = TourInquiry::query()->findOrFail($this->selectedInquiryId);
        Gate::authorize('update', $inquiry);

        return $inquiry;
    }

    /** Resolve the authenticated operator. */
    private function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    /** Drop cached projections after a write. */
    private function refreshQueue(): void
    {
        unset($this->inquiries, $this->statistics, $this->selectedInquiry);
    }
}
