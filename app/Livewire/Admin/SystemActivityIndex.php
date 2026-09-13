<?php

namespace App\Livewire\Admin;

use App\Enums\SystemActivitySeverity;
use App\Models\SystemActivity;
use App\Models\User;
use App\Support\CmsPermission;
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

class SystemActivityIndex extends Component
{
    use WithPagination;

    /** @var list<int> */
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'type', except: '')]
    public string $activityType = '';

    #[Url(as: 'severity', except: '')]
    public string $severity = '';

    #[Url(as: 'actor', except: '')]
    public string $actorId = '';

    #[Url(as: 'from', except: '')]
    public string $dateFrom = '';

    #[Url(as: 'to', except: '')]
    public string $dateTo = '';

    #[Url(as: 'per-page', except: 25)]
    public int $perPage = 25;

    #[Url(as: 'direction', except: 'desc')]
    public string $sortDirection = 'desc';

    public bool $showDetails = false;

    #[Locked]
    public ?int $selectedActivityId = null;

    /**
     * Livewire invokes boot for the initial request and every subsequent update.
     */
    public function boot(): void
    {
        Gate::authorize(CmsPermission::VIEW_SYSTEM_ACTIVITIES);
    }

    public function updatedSearch(): void
    {
        $this->resetActivityPage();
    }

    public function updatedActivityType(): void
    {
        $this->resetActivityPage();
    }

    public function updatedSeverity(): void
    {
        $this->resetActivityPage();
    }

    public function updatedActorId(): void
    {
        $this->resetActivityPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetActivityPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetActivityPage();
    }

    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, self::PER_PAGE_OPTIONS, true)) {
            $this->perPage = 25;
        }

        $this->resetActivityPage();
    }

    public function toggleSortDirection(): void
    {
        $this->sortDirection = $this->resolvedSortDirection() === 'desc' ? 'asc' : 'desc';
        $this->resetActivityPage();
    }

    public function openDetails(int $activityId): void
    {
        SystemActivity::query()->findOrFail($activityId);

        $this->selectedActivityId = $activityId;
        $this->showDetails = true;
        unset($this->selectedActivity);
    }

    public function closeDetails(): void
    {
        $this->showDetails = false;
        $this->selectedActivityId = null;
        unset($this->selectedActivity);
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->activityType = '';
        $this->severity = '';
        $this->actorId = '';
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->sortDirection = 'desc';

        $this->resetActivityPage();
    }

    public function refreshActivities(): void
    {
        unset(
            $this->activities,
            $this->statistics,
            $this->activityTypes,
            $this->activeActors,
            $this->selectedActivity,
        );
    }

    /**
     * Summary values follow the current filters so cards and rows stay consistent.
     *
     * @return array{total: int, today: int, actors: int, attention: int}
     */
    #[Computed]
    public function statistics(): array
    {
        $query = $this->filteredQuery();

        return [
            'total' => (clone $query)->count(),
            'today' => (clone $query)->whereDate('created_at', today())->count(),
            'actors' => (clone $query)->whereNotNull('user_id')->distinct()->count('user_id'),
            'attention' => (clone $query)->whereIn('severity', [
                SystemActivitySeverity::Warning->value,
                SystemActivitySeverity::Error->value,
                SystemActivitySeverity::Critical->value,
            ])->count(),
        ];
    }

    /** @return Collection<int, string> */
    #[Computed]
    public function activityTypes(): Collection
    {
        return SystemActivity::query()
            ->distinct()
            ->orderBy('activity_type')
            ->pluck('activity_type');
    }

    /** @return Collection<int, User> */
    #[Computed]
    public function activeActors(): Collection
    {
        return User::query()
            ->whereHas('systemActivities')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    /** @return list<SystemActivitySeverity> */
    #[Computed]
    public function severities(): array
    {
        return SystemActivitySeverity::cases();
    }

    #[Computed]
    public function activities(): LengthAwarePaginator
    {
        return $this->filteredQuery()
            ->with('actor:id,name,email')
            ->orderBy('created_at', $this->resolvedSortDirection())
            ->orderBy('id', $this->resolvedSortDirection())
            ->paginate($this->resolvedPerPage());
    }

    #[Computed]
    public function selectedActivity(): ?SystemActivity
    {
        if ($this->selectedActivityId === null) {
            return null;
        }

        return SystemActivity::query()
            ->with(['actor:id,name,email', 'subject'])
            ->find($this->selectedActivityId);
    }

    public function render(): View
    {
        return view('livewire.admin.system-activity-index');
    }

    private function filteredQuery(): Builder
    {
        return SystemActivity::query()
            ->search($this->search)
            ->ofType($this->activityType)
            ->ofSeverity($this->resolvedSeverity())
            ->byActor($this->resolvedActorId())
            ->occurredBetween(
                $this->resolvedDate($this->dateFrom),
                $this->resolvedDate($this->dateTo),
            );
    }

    private function resetActivityPage(): void
    {
        $this->resetPage();
        $this->refreshActivities();
    }

    private function resolvedPerPage(): int
    {
        return in_array($this->perPage, self::PER_PAGE_OPTIONS, true) ? $this->perPage : 25;
    }

    private function resolvedSortDirection(): string
    {
        return $this->sortDirection === 'asc' ? 'asc' : 'desc';
    }

    private function resolvedSeverity(): ?string
    {
        return SystemActivitySeverity::tryFrom($this->severity)?->value;
    }

    private function resolvedActorId(): ?int
    {
        return ctype_digit($this->actorId) && (int) $this->actorId > 0
            ? (int) $this->actorId
            : null;
    }

    private function resolvedDate(string $date): ?string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts) !== 1) {
            return null;
        }

        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]) ? $date : null;
    }
}
