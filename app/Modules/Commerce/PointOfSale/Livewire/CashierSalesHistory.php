<?php

declare(strict_types=1);

namespace App\Modules\Commerce\PointOfSale\Livewire;

use App\Models\User;
use App\Modules\Commerce\Orders\Enums\OrderChannel;
use App\Modules\Commerce\Orders\Enums\OrderStatus;
use App\Modules\Commerce\Orders\Enums\PaymentStatus;
use App\Modules\Commerce\Orders\Models\Order;
use App\Modules\Commerce\PointOfSale\Enums\TillSessionStatus;
use App\Modules\Commerce\PointOfSale\Models\TillSession;
use App\Modules\Commerce\Support\CommercePermission;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;

/**
 * Presents a read-only sales projection scoped to the authenticated POS operator.
 */
final class CashierSalesHistory extends Component
{
    use WithoutUrlPagination;
    use WithPagination;

    private const TAB_ACTIVE = 'active';

    private const TAB_HISTORY = 'history';

    /** @var array<string, string> */
    private const HISTORY_RANGES = [
        'all' => 'All sessions',
        'today' => 'Today',
        '7_days' => 'Last 7 days',
        '30_days' => 'Last 30 days',
        '90_days' => 'Last 90 days',
    ];

    #[Locked]
    public string $surface = 'dashboard';

    #[Locked]
    public string $tab = self::TAB_ACTIVE;

    public string $historyRange = 'all';

    /**
     * Accept only the two presentation contexts owned by this component.
     */
    public function mount(string $surface = 'dashboard'): void
    {
        abort_unless(in_array($surface, ['dashboard', 'terminal'], true), 422);

        $this->surface = $surface;
    }

    /**
     * Reauthorize every initial and subsequent Livewire request.
     */
    public function boot(): void
    {
        Gate::authorize(CommercePermission::ACCESS_POS);
    }

    /**
     * Display completed sales from the actor's currently open till.
     */
    public function showActiveSales(): void
    {
        $this->authorizeHistory();
        $this->tab = self::TAB_ACTIVE;
        $this->resetSalesPage();
        $this->forgetProjections();
    }

    /**
     * Display completed sales across the actor's till history.
     */
    public function showAllSales(): void
    {
        $this->authorizeHistory();
        $this->tab = self::TAB_HISTORY;
        $this->resetSalesPage();
        $this->forgetProjections();
    }

    /**
     * Canonicalize untrusted filter input before rebuilding the projection.
     */
    public function updatedHistoryRange(): void
    {
        $this->authorizeHistory();

        if (! array_key_exists($this->historyRange, self::HISTORY_RANGES)) {
            $this->historyRange = 'all';
        }

        $this->resetSalesPage();
        $this->forgetProjections();
    }

    /**
     * Explicitly refresh sales that may have been completed in another tab.
     */
    public function refreshSales(): void
    {
        $this->authorizeHistory();
        $this->forgetProjections();
    }

    /**
     * Resolve the authenticated operator's current open till, if one exists.
     */
    #[Computed]
    public function activeTill(): ?TillSession
    {
        return TillSession::query()
            ->with('register')
            ->where('opened_by', $this->actor()->getKey())
            ->where('status', TillSessionStatus::Open->value)
            ->latest('opened_at')
            ->first();
    }

    /**
     * Return the number displayed while the terminal disclosure is collapsed.
     */
    #[Computed]
    public function activeSessionSaleCount(): int
    {
        $till = $this->activeTill;

        if (! $till instanceof TillSession) {
            return 0;
        }

        return $this->actorSalesQuery()
            ->where('till_session_id', $till->getKey())
            ->count();
    }

    /**
     * Aggregate the current tab and range without converting money to decimals.
     *
     * @return array{count: int, gross_minor: int, average_minor: int}
     */
    #[Computed]
    public function summary(): array
    {
        $aggregate = $this->filteredSalesQuery()
            ->toBase()
            ->selectRaw('COUNT(*) AS sale_count, COALESCE(SUM(total_minor), 0) AS gross_minor')
            ->first();

        $count = (int) ($aggregate?->sale_count ?? 0);
        $grossMinor = (int) ($aggregate?->gross_minor ?? 0);

        return [
            'count' => $count,
            'gross_minor' => $grossMinor,
            'average_minor' => $count > 0 ? intdiv($grossMinor, $count) : 0,
        ];
    }

    /**
     * Fetch one bounded page with only relationships required by the view.
     */
    #[Computed]
    public function sales(): LengthAwarePaginator
    {
        return $this->filteredSalesQuery()
            ->with([
                'register:id,name,code',
                'tillSession:id,ulid,register_id,opened_at,closed_at',
                'payments' => static fn (HasMany $payments): HasMany => $payments
                    ->where('status', PaymentStatus::Completed->value)
                    ->oldest('id'),
            ])
            ->withCount('items')
            ->latest('placed_at')
            ->latest('id')
            ->paginate($this->perPage(), ['*'], 'cashierSalesPage');
    }

    /**
     * Expose the fixed history options to the Blade surface.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function historyRanges(): array
    {
        return self::HISTORY_RANGES;
    }

    public function render(): View
    {
        return view('commerce::livewire.pos.cashier-sales-history');
    }

    /** @return Builder<Order> */
    private function filteredSalesQuery(): Builder
    {
        $query = $this->actorSalesQuery();

        if ($this->tab === self::TAB_ACTIVE) {
            $till = $this->activeTill;

            return $till instanceof TillSession
                ? $query->where('till_session_id', $till->getKey())
                : $query->whereRaw('1 = 0');
        }

        $from = match ($this->historyRange) {
            'today' => now()->startOfDay(),
            '7_days' => now()->subDays(6)->startOfDay(),
            '30_days' => now()->subDays(29)->startOfDay(),
            '90_days' => now()->subDays(89)->startOfDay(),
            default => null,
        };

        return $query->when($from !== null, static fn (Builder $sales): Builder => $sales->where('placed_at', '>=', $from));
    }

    /** @return Builder<Order> */
    private function actorSalesQuery(): Builder
    {
        return Order::query()
            ->forChannel(OrderChannel::PointOfSale)
            ->where('status', OrderStatus::Completed->value)
            ->whereNotNull('order_number')
            ->where('cashier_id', $this->actor()->getKey());
    }

    private function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    private function authorizeHistory(): void
    {
        Gate::authorize(CommercePermission::ACCESS_POS);
    }

    private function perPage(): int
    {
        return max(5, min(25, (int) config('commerce.pos.sales_history.per_page', 8)));
    }

    private function resetSalesPage(): void
    {
        $this->resetPage(pageName: 'cashierSalesPage');
    }

    private function forgetProjections(): void
    {
        unset(
            $this->activeTill,
            $this->activeSessionSaleCount,
            $this->summary,
            $this->sales,
        );
    }
}
