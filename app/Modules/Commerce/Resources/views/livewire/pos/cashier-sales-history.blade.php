@php($historyComponentId = 'cashier-sales-'.$this->getId())

<div class="cashier-sales-history cashier-sales-history--{{ $surface }}" data-cashier-sales-history data-surface="{{ $surface }}">
    @if ($surface === 'terminal')
        <details class="cashier-sales-history__disclosure" wire:ignore.self>
            <summary>
                <span><i class="ti ti-history" aria-hidden="true"></i>My sales</span>
                <strong>{{ number_format($this->activeSessionSaleCount) }} this till</strong>
            </summary>
            <div class="cashier-sales-history__terminal-content">
                @include('commerce::livewire.pos.partials.cashier-sales-history-content', ['historyComponentId' => $historyComponentId])
            </div>
        </details>
    @else
        <section class="card aureon-panel cashier-sales-history__panel mb-4" aria-labelledby="{{ $historyComponentId }}-title">
            <div class="card-header cashier-sales-history__heading">
                <div>
                    <h3 id="{{ $historyComponentId }}-title" class="card-title mb-1">My sales</h3>
                    <p class="mb-0">Completed point-of-sale transactions assigned to your account</p>
                </div>
                <span class="cashier-sales-history__heading-icon" aria-hidden="true"><i class="ti ti-receipt-2"></i></span>
            </div>
            <div class="card-body">
                @include('commerce::livewire.pos.partials.cashier-sales-history-content', ['historyComponentId' => $historyComponentId])
            </div>
        </section>
    @endif
</div>
