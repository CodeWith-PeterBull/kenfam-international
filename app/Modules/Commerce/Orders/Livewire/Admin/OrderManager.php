<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Orders\Livewire\Admin;

use App\Contracts\RecordsSystemActivity;
use App\Enums\SystemActivitySeverity;
use App\Models\User;
use App\Modules\Commerce\Exceptions\CommerceException;
use App\Modules\Commerce\Orders\Enums\OrderChannel;
use App\Modules\Commerce\Orders\Enums\OrderPaymentStatus;
use App\Modules\Commerce\Orders\Enums\OrderStatus;
use App\Modules\Commerce\Orders\Livewire\Forms\PaymentRecordForm;
use App\Modules\Commerce\Orders\Models\Order;
use App\Modules\Commerce\Orders\Models\Payment;
use App\Modules\Commerce\Orders\Services\OrderService;
use App\Modules\Commerce\Orders\Services\PaymentService;
use App\Modules\Commerce\Reporting\Filters\OrderReportFilters;
use App\Modules\Commerce\Reporting\Reports\OrderRegisterReport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Authorized web/POS order explorer and lifecycle administration surface.
 */
final class OrderManager extends Component
{
    use WithPagination;

    #[Url(as: 'order-q', except: '')]
    public string $search = '';

    #[Url(as: 'order-channel', except: '')]
    public string $channel = '';

    #[Url(as: 'order-status', except: '')]
    public string $status = '';

    #[Url(as: 'order-payment', except: '')]
    public string $paymentStatus = '';

    #[Url(as: 'order-per-page', except: 15)]
    public int $perPage = 15;

    public PaymentRecordForm $paymentForm;

    public string $dialog = '';

    public string $cancellationReason = '';

    #[Locked]
    public ?int $selectedOrderId = null;

    public function boot(): void
    {
        Gate::authorize('viewAny', Order::class);
    }

    /**
     * Export the current filtered order list as a PDF register (Livewire download).
     */
    public function exportPdf(OrderRegisterReport $report, RecordsSystemActivity $activities): StreamedResponse
    {
        Gate::authorize('viewAny', Order::class);
        $actor = $this->actor();
        $filters = OrderReportFilters::fromInputs($this->search, $this->channel, $this->status, $this->paymentStatus);
        $rendered = $report->render($filters, $actor);

        $activities->record(
            activityType: 'commerce.order.report_exported',
            description: 'Order register report exported',
            actor: $actor,
            properties: ['filters' => $report->filterLabels($filters), 'pages' => $rendered->pageCount],
            severity: SystemActivitySeverity::Info,
            source: 'commerce-reports',
        );

        return response()->streamDownload(static function () use ($rendered): void {
            echo $rendered->contents;
        }, $rendered->filename, [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function updatedSearch(): void
    {
        $this->resetOrderPage();
    }

    public function updatedChannel(): void
    {
        $this->channel = OrderChannel::tryFrom($this->channel)?->value ?? '';
        $this->resetOrderPage();
    }

    public function updatedStatus(): void
    {
        $this->status = OrderStatus::tryFrom($this->status)?->value ?? '';
        $this->resetOrderPage();
    }

    public function updatedPaymentStatus(): void
    {
        $this->paymentStatus = OrderPaymentStatus::tryFrom($this->paymentStatus)?->value ?? '';
        $this->resetOrderPage();
    }

    public function updatedPerPage(): void
    {
        $this->perPage = in_array($this->perPage, [10, 15, 25, 50], true) ? $this->perPage : 15;
        $this->resetOrderPage();
    }

    /** @return array{total: int, pending: int, active: int, completed: int, revenue_minor: int} */
    #[Computed]
    public function statistics(): array
    {
        return [
            'total' => Order::query()->whereNotNull('order_number')->count(),
            'pending' => Order::query()->where('status', OrderStatus::Pending->value)->count(),
            'active' => Order::query()->whereIn('status', [OrderStatus::Confirmed->value, OrderStatus::Processing->value, OrderStatus::Ready->value])->count(),
            'completed' => Order::query()->where('status', OrderStatus::Completed->value)->count(),
            'revenue_minor' => (int) Order::query()->where('payment_status', OrderPaymentStatus::Paid->value)->sum('paid_minor'),
        ];
    }

    #[Computed]
    public function orders(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return Order::query()
            ->whereNotNull('order_number')
            ->when($search !== '', function (Builder $orders) use ($search): void {
                $orders->where(function (Builder $matches) use ($search): void {
                    $matches
                        ->where('order_number', 'like', "%{$search}%")
                        ->orWhere('customer_first_name', 'like', "%{$search}%")
                        ->orWhere('customer_last_name', 'like', "%{$search}%")
                        ->orWhere('customer_email', 'like', "%{$search}%")
                        ->orWhere('customer_phone', 'like', "%{$search}%");
                });
            })
            ->when($this->channel !== '', fn (Builder $orders): Builder => $orders->where('channel', $this->channel))
            ->when($this->status !== '', fn (Builder $orders): Builder => $orders->where('status', $this->status))
            ->when($this->paymentStatus !== '', fn (Builder $orders): Builder => $orders->where('payment_status', $this->paymentStatus))
            ->withCount('items')
            ->latest('placed_at')
            ->latest('id')
            ->paginate($this->perPage, ['*'], 'orderPage');
    }

    #[Computed]
    public function selectedOrder(): ?Order
    {
        return $this->selectedOrderId === null
            ? null
            : Order::query()->with(['items', 'payments', 'customer', 'register', 'tillSession'])->findOrFail($this->selectedOrderId);
    }

    public function openDetails(int $orderId): void
    {
        $order = $this->findOrder($orderId);
        Gate::authorize('view', $order);
        $this->selectedOrderId = $order->id;
        $this->dialog = 'details';
        unset($this->selectedOrder);
    }

    public function advanceOrder(int $orderId, string $target, OrderService $orders): void
    {
        $order = $this->findOrder($orderId);
        Gate::authorize('update', $order);
        $status = OrderStatus::tryFrom($target);
        abort_if($status === null, 422);

        try {
            $orders->transition($order, $status, $this->actor());
        } catch (CommerceException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        session()->flash('success', "Order moved to {$status->label()}.");
        $this->refreshOrders();
    }

    public function openCancellation(int $orderId): void
    {
        $order = $this->findOrder($orderId);
        Gate::authorize('cancel', $order);
        $this->selectedOrderId = $order->id;
        $this->cancellationReason = '';
        $this->dialog = 'cancel';
        unset($this->selectedOrder);
    }

    public function cancel(OrderService $orders): void
    {
        $this->validate(['cancellationReason' => ['required', 'string', 'max:255']]);
        $order = $this->selectedOrder ?? abort(404);
        Gate::authorize('cancel', $order);

        try {
            $orders->cancelWebOrder($order, $this->cancellationReason, $this->actor());
        } catch (CommerceException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        $this->closeDialog();
        session()->flash('success', 'Order cancelled and committed stock restored.');
        $this->refreshOrders();
    }

    public function openPayment(int $orderId): void
    {
        $order = $this->findOrder($orderId);
        Gate::authorize('record', Payment::class);
        abort_if($order->channel !== OrderChannel::Web || $order->balance_due_minor <= 0, 422);
        $this->selectedOrderId = $order->id;
        $this->paymentForm->fillForOrder($order);
        $this->dialog = 'payment';
        unset($this->selectedOrder);
    }

    public function recordPayment(PaymentService $payments): void
    {
        Gate::authorize('record', Payment::class);
        $this->paymentForm->validate();
        $order = $this->selectedOrder ?? abort(404);

        try {
            $payments->recordCompleted($order, $this->paymentForm->paymentData(), actor: $this->actor());
        } catch (CommerceException|InvalidArgumentException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        $this->closeDialog();
        session()->flash('success', 'Payment confirmed and order settlement updated.');
        $this->refreshOrders();
    }

    public function closeDialog(): void
    {
        $this->dialog = '';
        $this->selectedOrderId = null;
        $this->cancellationReason = '';
        $this->paymentForm->reset();
        $this->resetValidation();
        unset($this->selectedOrder);
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->channel = '';
        $this->status = '';
        $this->paymentStatus = '';
        $this->resetOrderPage();
    }

    public function render(): View
    {
        return view('commerce::livewire.admin.order-manager');
    }

    private function findOrder(int $orderId): Order
    {
        return Order::query()->findOrFail($orderId);
    }

    private function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    private function resetOrderPage(): void
    {
        $this->resetPage(pageName: 'orderPage');
    }

    private function refreshOrders(): void
    {
        unset($this->orders, $this->statistics, $this->selectedOrder);
    }
}
