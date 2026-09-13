@php
    $isActiveTab = $tab === 'active';
    $summary = $this->summary;
    $sales = $this->sales;
    $activeTill = $this->activeTill;
@endphp

<div class="cashier-sales-history__content" wire:loading.class="is-loading">
    <div class="cashier-sales-history__toolbar">
        <div class="cashier-sales-history__tabs" role="tablist" aria-label="Cashier sales history">
            <button type="button"
                id="{{ $historyComponentId }}-active-tab"
                class="{{ $isActiveTab ? 'is-active' : '' }}"
                role="tab"
                aria-selected="{{ $isActiveTab ? 'true' : 'false' }}"
                aria-controls="{{ $historyComponentId }}-panel"
                wire:click="showActiveSales">
                Active session
            </button>
            <button type="button"
                id="{{ $historyComponentId }}-history-tab"
                class="{{ ! $isActiveTab ? 'is-active' : '' }}"
                role="tab"
                aria-selected="{{ ! $isActiveTab ? 'true' : 'false' }}"
                aria-controls="{{ $historyComponentId }}-panel"
                wire:click="showAllSales">
                All session sales
            </button>
        </div>

        <div class="cashier-sales-history__filters">
            @if (! $isActiveTab)
                <label for="{{ $historyComponentId }}-range">History range</label>
                <select id="{{ $historyComponentId }}-range" wire:model.live="historyRange">
                    @foreach ($this->historyRanges as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            @endif
            <button type="button" class="cashier-sales-history__refresh" wire:click="refreshSales" wire:loading.attr="disabled" title="Refresh sales" aria-label="Refresh sales">
                <i class="ti ti-refresh" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <div id="{{ $historyComponentId }}-panel"
        role="tabpanel"
        aria-labelledby="{{ $historyComponentId }}-{{ $isActiveTab ? 'active' : 'history' }}-tab">
        @if ($isActiveTab)
            <div class="cashier-sales-history__session">
                <span class="cashier-sales-history__session-icon" aria-hidden="true"><i class="ti ti-cash-register"></i></span>
                @if ($activeTill)
                    <div>
                        <strong>{{ $activeTill->register?->name ?? 'Archived register' }}</strong>
                        <span>{{ $activeTill->register?->code ?? 'Register unavailable' }} &middot; Opened {{ $activeTill->opened_at?->diffForHumans() }}</span>
                    </div>
                    <span class="cashier-sales-history__status">Open</span>
                @else
                    <div>
                        <strong>No active till</strong>
                        <span>Your completed sales remain available under all session sales.</span>
                    </div>
                @endif
            </div>
        @endif

        <dl class="cashier-sales-history__metrics" aria-label="Sales summary">
            <div>
                <dt>Transactions</dt>
                <dd>{{ number_format($summary['count']) }}</dd>
            </div>
            <div>
                <dt>Gross sales</dt>
                <dd>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($summary['gross_minor']) }}</dd>
            </div>
            <div>
                <dt>Average sale</dt>
                <dd>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($summary['average_minor']) }}</dd>
            </div>
        </dl>

        <div class="cashier-sales-history__list" aria-live="polite" wire:loading.attr="aria-busy">
            @forelse ($sales as $sale)
                @php
                    $paymentLabels = $sale->payments
                        ->map(static fn ($payment): string => $payment->method->label())
                        ->unique()
                        ->implode(' + ');
                    $customerName = trim($sale->customer_display_name);
                @endphp
                <article class="cashier-sales-history__sale">
                    <div class="cashier-sales-history__sale-heading">
                        <div>
                            <strong>{{ $sale->order_number }}</strong>
                            @if ($sale->placed_at)
                                <time datetime="{{ $sale->placed_at->toIso8601String() }}">{{ $sale->placed_at->format('d M Y, H:i') }}</time>
                            @endif
                        </div>
                        <strong class="cashier-sales-history__sale-total">{{ \App\Modules\Commerce\Support\MoneyFormatter::format($sale->total_minor) }}</strong>
                    </div>

                    <dl class="cashier-sales-history__sale-facts">
                        <div><dt>Customer</dt><dd>{{ $customerName !== '' ? $customerName : 'Walk-in customer' }}</dd></div>
                        <div><dt>Items</dt><dd>{{ number_format($sale->items_count) }}</dd></div>
                        <div><dt>Payment</dt><dd>{{ $paymentLabels !== '' ? $paymentLabels : 'Recorded sale' }}</dd></div>
                        <div><dt>Register</dt><dd>{{ $sale->register?->name ?? 'Archived register' }}</dd></div>
                    </dl>

                    <a class="cashier-sales-history__receipt"
                        href="{{ route('commerce.pos.receipts.show', $sale) }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label="Open receipt {{ $sale->order_number }} in a new tab">
                        <i class="ti ti-receipt" aria-hidden="true"></i><span>Receipt</span>
                    </a>
                </article>
            @empty
                <div class="cashier-sales-history__empty">
                    <i class="ti {{ $isActiveTab ? 'ti-receipt-off' : 'ti-history-off' }}" aria-hidden="true"></i>
                    <strong>{{ $isActiveTab ? 'No completed sales in this session' : 'No completed sales in this range' }}</strong>
                    <span>{{ $isActiveTab ? 'New transactions will appear here after checkout.' : 'Choose another history range to review earlier sessions.' }}</span>
                </div>
            @endforelse
        </div>

        @if ($sales->hasPages())
            <div class="cashier-sales-history__pagination">
                {{ $sales->links(data: ['scrollTo' => false]) }}
            </div>
        @endif
    </div>

    <span class="cashier-sales-history__loading" wire:loading role="status">Updating sales</span>
</div>
