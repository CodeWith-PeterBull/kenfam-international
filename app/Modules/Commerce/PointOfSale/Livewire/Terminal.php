<?php

declare(strict_types=1);

namespace App\Modules\Commerce\PointOfSale\Livewire;

use App\Models\User;
use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Customers\Models\Customer;
use App\Modules\Commerce\Exceptions\CommerceException;
use App\Modules\Commerce\Orders\Data\OrderPlacementData;
use App\Modules\Commerce\Orders\Data\PaymentData;
use App\Modules\Commerce\Orders\Enums\OrderChannel;
use App\Modules\Commerce\Orders\Enums\OrderStatus;
use App\Modules\Commerce\Orders\Enums\PaymentMethod;
use App\Modules\Commerce\Orders\Models\Order;
use App\Modules\Commerce\Orders\Services\OrderService;
use App\Modules\Commerce\PointOfSale\Data\PosTerminalSnapshot;
use App\Modules\Commerce\PointOfSale\Enums\TillSessionStatus;
use App\Modules\Commerce\PointOfSale\Models\TillSession;
use App\Modules\Commerce\PointOfSale\Services\PosCartService;
use App\Modules\Commerce\PointOfSale\Services\PosCheckoutService;
use App\Modules\Commerce\Support\CommercePermission;
use App\Modules\Commerce\Support\ScaledDecimal;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Full cashier workspace backed by the shared Commerce transaction engine.
 */
final class Terminal extends Component
{
    public string $search = '';

    /** @var array<int|string, int> */
    public array $cart = [];

    public string $customerId = '';

    public string $customerSearch = '';

    public string $discount = '0.00';

    public string $discountReason = '';

    public string $holdNote = '';

    /** @var list<array{method: string, amount: string, tendered: string, reference: string}> */
    public array $tenders = [];

    #[Locked]
    public ?int $resumingOrderId = null;

    public function mount(): void
    {
        $this->resetTenders();
    }

    public function boot(): void
    {
        Gate::authorize(CommercePermission::ACCESS_POS);
    }

    public function updatedSearch(): void
    {
        unset($this->products);
    }

    public function updatedCustomerId(): void
    {
        unset($this->snapshot);
    }

    public function updatedDiscount(): void
    {
        unset($this->snapshot);
    }

    public function updatedDiscountReason(): void
    {
        unset($this->snapshot);
    }

    #[Computed]
    public function activeTill(): ?TillSession
    {
        return TillSession::query()
            ->with(['register', 'opener'])
            ->where('opened_by', $this->actor()->getKey())
            ->where('status', TillSessionStatus::Open->value)
            ->latest('opened_at')
            ->first();
    }

    /** @return Collection<int, Product> */
    #[Computed]
    public function products(): Collection
    {
        $search = trim($this->search);

        return Product::query()
            ->published()
            ->with(['category', 'stock', 'media'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $match = '%'.$search.'%';
                $query->where(fn (Builder $lookup): Builder => $lookup
                    ->where('name', 'like', $match)
                    ->orWhere('sku', 'like', $match)
                    ->orWhere('barcode', 'like', $match)
                    ->orWhere('manufacturer_barcode', 'like', $match));
            })
            ->when($search === '', fn (Builder $query): Builder => $query->orderByDesc('is_featured'))
            ->orderBy('name')
            ->limit(max(6, min(30, (int) config('commerce.pos.product_results', 12))))
            ->get();
    }

    /** @return Collection<int, Customer> */
    #[Computed]
    public function customers(): Collection
    {
        $search = trim($this->customerSearch);

        return Customer::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $match = '%'.$search.'%';
                $query->where(fn (Builder $customer): Builder => $customer
                    ->where('first_name', 'like', $match)
                    ->orWhere('last_name', 'like', $match)
                    ->orWhere('email', 'like', $match)
                    ->orWhere('phone', 'like', $match));
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(40)
            ->get();
    }

    #[Computed]
    public function selectedCustomerLabel(): string
    {
        return $this->selectedCustomer()?->display_name ?? 'Walk-in';
    }

    /** @return Collection<int, Order> */
    #[Computed]
    public function heldOrders(): Collection
    {
        $till = $this->activeTill;
        if (! $till instanceof TillSession) {
            return collect();
        }

        return Order::query()
            ->withCount('items')
            ->where('channel', OrderChannel::PointOfSale->value)
            ->where('status', OrderStatus::Held->value)
            ->where('till_session_id', $till->getKey())
            ->where('cashier_id', $this->actor()->getKey())
            ->oldest('created_at')
            ->get();
    }

    /** @return list<PaymentMethod> */
    #[Computed]
    public function paymentMethods(): array
    {
        $methods = collect(config('commerce.pos.payment_methods', []))
            ->map(static fn (mixed $method): ?PaymentMethod => is_string($method) ? PaymentMethod::tryFrom($method) : null)
            ->filter(static fn (?PaymentMethod $method): bool => $method !== null && $method !== PaymentMethod::CashOnDelivery)
            ->unique(static fn (PaymentMethod $method): string => $method->value)
            ->values()
            ->all();

        return $methods === [] ? [PaymentMethod::Cash] : $methods;
    }

    #[Computed]
    public function snapshot(): PosTerminalSnapshot
    {
        try {
            $discountMinor = ScaledDecimal::parseUnsigned($this->discount, $this->decimals());
        } catch (InvalidArgumentException $exception) {
            return new PosTerminalSnapshot(null, null, [$exception->getMessage()]);
        }

        return app(PosCartService::class)->snapshot(
            cart: $this->cart,
            discountMinor: $discountMinor,
            discountReason: $this->discountReason,
            customer: $this->selectedCustomer(),
            internalNote: $this->holdNote,
        );
    }

    public function lookup(): void
    {
        $this->resetErrorBag('lookup');
        $lookup = trim($this->search);
        if ($lookup === '') {
            return;
        }

        $product = Product::query()
            ->published()
            ->where(fn (Builder $query): Builder => $query
                ->where('barcode', $lookup)
                ->orWhere('manufacturer_barcode', $lookup)
                ->orWhere('sku', $lookup))
            ->first();
        if ($product instanceof Product) {
            $this->addProduct($product->id);
            $this->search = '';
            unset($this->products);

            return;
        }

        if ($this->products->isEmpty()) {
            $this->addError('lookup', 'No published product matches that barcode, SKU, or name.');
        }
    }

    public function addProduct(int $productId): void
    {
        $this->authorizeTerminal();
        $product = Product::query()->published()->with('stock')->findOrFail($productId);
        $current = (int) ($this->cart[$product->id] ?? 0);
        $maximum = $product->maximum_order_quantity ?? PHP_INT_MAX;
        if ($product->track_stock) {
            $maximum = min($maximum, (int) ($product->stock?->on_hand ?? 0));
        }

        $next = $current === 0
            ? max(1, (int) $product->minimum_order_quantity)
            : $current + 1;

        if ($next > $maximum) {
            $this->addError('cart', "{$product->name} has reached its available quantity.");

            return;
        }

        $this->cart[$product->id] = $next;
        $this->refreshCart();
        $this->beepAddToCart();
    }

    public function increase(int $productId): void
    {
        $this->addProduct($productId);
    }

    public function decrease(int $productId): void
    {
        $this->authorizeTerminal();
        $product = Product::query()->findOrFail($productId);
        $quantity = (int) ($this->cart[$productId] ?? 0) - 1;
        if ($quantity < $product->minimum_order_quantity) {
            unset($this->cart[$productId]);
        } else {
            $this->cart[$productId] = $quantity;
        }
        $this->refreshCart();
    }

    public function remove(int $productId): void
    {
        $this->authorizeTerminal();
        unset($this->cart[$productId]);
        $this->refreshCart();
    }

    public function clearSale(): void
    {
        $this->authorizeTerminal();
        $this->resetSaleState();
    }

    public function addTender(): void
    {
        $this->authorizeTerminal();
        if (count($this->tenders) >= max(1, (int) config('commerce.pos.max_tenders', 4))) {
            return;
        }
        $this->tenders[] = $this->emptyTender(PaymentMethod::Cash);
    }

    public function removeTender(int $index): void
    {
        $this->authorizeTerminal();
        if (count($this->tenders) <= 1 || ! isset($this->tenders[$index])) {
            return;
        }
        unset($this->tenders[$index]);
        $this->tenders = array_values($this->tenders);
    }

    public function applyRemaining(int $index): void
    {
        $this->authorizeTerminal();
        if (! isset($this->tenders[$index]) || $this->snapshot->calculation === null) {
            return;
        }

        $other = 0;
        foreach ($this->tenders as $tenderIndex => $tender) {
            if ($tenderIndex === $index) {
                continue;
            }
            $other += $this->parseMoney((string) ($tender['amount'] ?? '')) ?? 0;
        }
        $remaining = max(0, $this->snapshot->calculation->totalMinor - $other);
        $formatted = ScaledDecimal::formatUnsigned($remaining, $this->decimals());
        $this->tenders[$index]['amount'] = $formatted;
        if (($this->tenders[$index]['method'] ?? '') === PaymentMethod::Cash->value) {
            $this->tenders[$index]['tendered'] = $formatted;
        }
    }

    public function hold(OrderService $orders): void
    {
        $this->authorizeTerminal();
        $snapshot = $this->validatedSnapshot();
        if (! $snapshot instanceof PosTerminalSnapshot) {
            return;
        }

        try {
            $orders->holdPointOfSaleOrder(
                data: $snapshot->placement,
                session: $this->requireTill(),
                cashier: $this->actor(),
                customer: $this->selectedCustomer(),
            );
        } catch (CommerceException $exception) {
            $this->addError('terminal', $exception->getMessage());

            return;
        }

        $this->resetSaleState();
        session()->flash('pos-success', 'Sale held for later.');
        unset($this->heldOrders);
    }

    public function resume(int $orderId): void
    {
        $this->authorizeTerminal();
        $order = $this->ownedHold($orderId)->load('items');
        $this->cart = $order->items->mapWithKeys(fn ($item): array => [$item->product_id => $item->quantity])->all();
        $this->customerId = $order->customer_id === null ? '' : (string) $order->customer_id;
        $this->discount = ScaledDecimal::formatUnsigned($order->discount_minor, $this->decimals());
        $this->discountReason = (string) $order->discount_reason;
        $this->holdNote = (string) $order->internal_note;
        $this->resumingOrderId = $order->id;
        $this->resetTenders();
        $this->refreshCart();
    }

    public function discard(int $orderId, OrderService $orders): void
    {
        $this->authorizeTerminal();
        $order = $this->ownedHold($orderId);

        try {
            $orders->discardHeldPointOfSaleOrder($order, $this->requireTill(), $this->actor());
        } catch (CommerceException $exception) {
            $this->addError('terminal', $exception->getMessage());

            return;
        }

        if ($this->resumingOrderId === $order->id) {
            $this->resetSaleState();
        }
        session()->flash('pos-success', 'Held sale discarded.');
        unset($this->heldOrders);
    }

    public function checkout(PosCheckoutService $checkout): void
    {
        $this->authorizeTerminal();
        $snapshot = $this->validatedSnapshot();
        if (! $snapshot instanceof PosTerminalSnapshot || $snapshot->calculation === null || $snapshot->placement === null) {
            return;
        }

        $payments = $this->validatedPayments($snapshot->calculation->totalMinor);
        if ($payments === null) {
            return;
        }

        $placement = new OrderPlacementData(
            items: $snapshot->placement->items,
            customer: $snapshot->placement->customer,
            fulfillmentType: $snapshot->placement->fulfillmentType,
            preferredPaymentMethod: $payments[0]->method,
            discountMinor: $snapshot->placement->discountMinor,
            discountReason: $snapshot->placement->discountReason,
            internalNote: $snapshot->placement->internalNote,
        );

        try {
            $till = $this->requireTill();
            $actor = $this->actor();
            $customer = $this->selectedCustomer();
            $order = $this->resumingOrderId === null
                ? $checkout->checkout($placement, $payments, $till, $actor, $customer)
                : $checkout->checkoutHeld($this->ownedHold($this->resumingOrderId), $placement, $payments, $till, $actor, $customer);
        } catch (CommerceException $exception) {
            $this->addError('terminal', $exception->getMessage());

            return;
        }

        $this->resetSaleState();
        $this->redirectRoute('commerce.pos.receipts.show', [
            'order' => $order,
            'print' => 'checkout',
        ]);
    }

    public function tenderTotalMinor(): int
    {
        return array_sum(array_map(fn (array $tender): int => $this->parseMoney((string) ($tender['amount'] ?? '')) ?? 0, $this->tenders));
    }

    public function cashChangeMinor(): int
    {
        $change = 0;
        foreach ($this->tenders as $tender) {
            if (($tender['method'] ?? '') !== PaymentMethod::Cash->value) {
                continue;
            }
            $amount = $this->parseMoney((string) ($tender['amount'] ?? '')) ?? 0;
            $tendered = $this->parseMoney((string) ($tender['tendered'] ?? '')) ?? $amount;
            $change += max(0, $tendered - $amount);
        }

        return $change;
    }

    public function render(): View
    {
        return view('commerce::livewire.pos.terminal');
    }

    private function validatedSnapshot(): ?PosTerminalSnapshot
    {
        $this->resetErrorBag('terminal');
        $this->validate([
            'discountReason' => ['nullable', 'string', 'max:500'],
            'holdNote' => ['nullable', 'string', 'max:2000'],
        ]);
        $snapshot = $this->snapshot;
        if (! $this->activeTill instanceof TillSession) {
            $this->addError('terminal', 'Your cashier account does not have an open till session.');

            return null;
        }
        if (! $snapshot->canTransact()) {
            $this->addError('terminal', $snapshot->issues[0] ?? 'Add at least one valid product.');

            return null;
        }
        if ($snapshot->calculation?->discountMinor > 0 && blank($this->discountReason)) {
            $this->addError('discountReason', 'Enter a reason for the fixed discount.');

            return null;
        }

        return $snapshot;
    }

    /** @return list<PaymentData>|null */
    private function validatedPayments(int $totalMinor): ?array
    {
        $methods = array_map(static fn (PaymentMethod $method): string => $method->value, $this->paymentMethods);
        $this->validate([
            'tenders' => ['required', 'array', 'min:1', 'max:'.max(1, (int) config('commerce.pos.max_tenders', 4))],
            'tenders.*.method' => ['required', Rule::in($methods)],
            'tenders.*.amount' => ['required', 'decimal:0,'.$this->decimals(), 'gt:0'],
            'tenders.*.tendered' => ['nullable', 'decimal:0,'.$this->decimals(), 'gte:0'],
            'tenders.*.reference' => ['nullable', 'string', 'max:120'],
        ]);

        $payments = [];
        $applied = 0;
        foreach ($this->tenders as $index => $tender) {
            $method = PaymentMethod::from($tender['method']);
            $amount = $this->parseMoney($tender['amount']);
            $tendered = $this->parseMoney($tender['tendered']);
            $reference = trim($tender['reference']);
            if ($amount === null) {
                $this->addError("tenders.{$index}.amount", 'Enter a valid payment amount.');

                return null;
            }
            if ($method !== PaymentMethod::Cash && $reference === '') {
                $this->addError("tenders.{$index}.reference", 'Enter the non-cash payment reference.');

                return null;
            }

            $payments[] = new PaymentData(
                method: $method,
                amountMinor: $amount,
                tenderedMinor: $method === PaymentMethod::Cash ? ($tendered ?? $amount) : $amount,
                reference: $reference === '' ? null : $reference,
            );
            $applied += $amount;
        }

        if ($applied !== $totalMinor) {
            $this->addError('payments', 'Applied payments must equal the sale total exactly.');

            return null;
        }

        return $payments;
    }

    private function selectedCustomer(): ?Customer
    {
        return $this->customerId === '' ? null : Customer::query()->findOrFail((int) $this->customerId);
    }

    private function ownedHold(int $orderId): Order
    {
        $till = $this->requireTill();

        return Order::query()
            ->where('channel', OrderChannel::PointOfSale->value)
            ->where('status', OrderStatus::Held->value)
            ->where('till_session_id', $till->getKey())
            ->where('cashier_id', $this->actor()->getKey())
            ->findOrFail($orderId);
    }

    private function requireTill(): TillSession
    {
        $till = $this->activeTill;
        abort_unless($till instanceof TillSession, 422);

        return $till;
    }

    private function resetSaleState(): void
    {
        $this->cart = [];
        $this->customerId = '';
        $this->discount = ScaledDecimal::formatUnsigned(0, $this->decimals());
        $this->discountReason = '';
        $this->holdNote = '';
        $this->resumingOrderId = null;
        $this->resetTenders();
        $this->resetValidation();
        $this->refreshCart();
    }

    private function resetTenders(): void
    {
        $method = $this->paymentMethods[0] ?? PaymentMethod::Cash;
        $this->tenders = [$this->emptyTender($method)];
    }

    /** @return array{method: string, amount: string, tendered: string, reference: string} */
    private function emptyTender(PaymentMethod $method): array
    {
        return ['method' => $method->value, 'amount' => '', 'tendered' => '', 'reference' => ''];
    }

    private function parseMoney(string $value): ?int
    {
        if (trim($value) === '') {
            return null;
        }

        try {
            return ScaledDecimal::parseUnsigned($value, $this->decimals());
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function refreshCart(): void
    {
        ksort($this->cart);
        unset($this->snapshot);
    }

    /**
     * Ask the browser to play the cashier add-to-cart confirmation beep.
     *
     * The sound is a browser-only affordance; the server merely signals a
     * successful add when the point-of-sale sound feedback is enabled.
     */
    private function beepAddToCart(): void
    {
        if ((bool) config('commerce.pos.sound.add_to_cart_enabled', true)) {
            $this->dispatch('commerce-pos-cart-added');
        }
    }

    private function authorizeTerminal(): void
    {
        Gate::authorize(CommercePermission::ACCESS_POS);
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function decimals(): int
    {
        return max(0, min(6, (int) config('commerce.currency.decimal_places', 2)));
    }
}
