<div id="commerce-demo-data" data-commerce-demo-data>
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="status">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @error('seeding')
        <div class="alert alert-danger" role="alert">{{ $message }}</div>
    @enderror

    @php($stats = [
        ['label' => 'Products', 'value' => number_format($this->totals['products']), 'icon' => 'ti-package', 'color' => 'var(--aureon-primary)'],
        ['label' => 'Categories', 'value' => number_format($this->totals['categories']), 'icon' => 'ti-category', 'color' => 'var(--aureon-secondary)'],
        ['label' => 'Stock records', 'value' => number_format($this->totals['stocks']), 'icon' => 'ti-building-warehouse', 'color' => '#397a70'],
        ['label' => 'Orders', 'value' => number_format($this->totals['orders']), 'icon' => 'ti-receipt', 'color' => '#8a6427'],
    ])

    <section class="row" aria-label="Current Commerce data totals">
        @foreach ($stats as $stat)
            <div class="col-xl-3 col-sm-6 d-flex">
                <article class="card aureon-stat mb-4" style="--stat-color: {{ $stat['color'] }}">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="aureon-stat__icon"><i class="ti {{ $stat['icon'] }}" aria-hidden="true"></i></span>
                        <div><h3>{{ $stat['value'] }}</h3><p>{{ $stat['label'] }}</p></div>
                    </div>
                </article>
            </div>
        @endforeach
    </section>

    <form wire:submit="seed" class="card aureon-panel commerce-demo-data-panel">
        <div class="card-header">
            <h3 class="card-title mb-1">Merchant context</h3>
            <p class="aureon-muted fs-12 mb-0">Each option seeds its complete catalog, stock, customer, register, till, and sample-order graph.</p>
        </div>

        <div class="card-body">
            <fieldset>
                <legend class="visually-hidden">Choose a merchant demonstration context</legend>
                <div class="commerce-demo-context-grid">
                    @foreach ($this->contexts as $context)
                        <label class="commerce-demo-context {{ $form->context === $context['key'] ? 'is-selected' : '' }}"
                            wire:key="demo-context-{{ $context['key'] }}">
                            <input class="visually-hidden" type="radio" name="demo-context"
                                value="{{ $context['key'] }}" wire:model.live="form.context">
                            <span class="commerce-demo-context__media">
                                <img src="{{ $context['thumbnail'] }}" width="1200" height="900"
                                    loading="lazy" alt="{{ $context['name'] }} product selection preview">
                                <span class="commerce-demo-context__check" aria-hidden="true"><i class="ti ti-check"></i></span>
                            </span>
                            <span class="commerce-demo-context__body">
                                <span class="commerce-demo-context__title">{{ $context['name'] }}</span>
                                <span class="commerce-demo-context__description">{{ $context['description'] }}</span>
                                <span class="commerce-demo-context__meta">
                                    <span><i class="ti ti-package" aria-hidden="true"></i>{{ $context['products'] }} products</span>
                                    <span><i class="ti ti-category" aria-hidden="true"></i>{{ $context['categories'] }} categories</span>
                                </span>
                                <span class="commerce-demo-context__state">
                                    @if ($context['seeded_products'] === $context['products'])
                                        <span class="badge aureon-commerce-badge aureon-commerce-badge--published">Seeded</span>
                                        <small>{{ $context['active_products'] }} currently published</small>
                                    @elseif ($context['seeded_products'] > 0)
                                        <span class="badge aureon-commerce-badge aureon-commerce-badge--pending">Partial</span>
                                        <small>{{ $context['seeded_products'] }} of {{ $context['products'] }} present</small>
                                    @else
                                        <span class="badge aureon-commerce-badge aureon-commerce-badge--neutral">Not seeded</span>
                                    @endif
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('form.context')<div class="invalid-feedback d-block mt-2">{{ $message }}</div>@enderror
            </fieldset>
        </div>

        <div class="card-body border-top commerce-demo-data-options">
            <div>
                <h3 class="card-title mb-1">Existing product handling</h3>
                <p class="aureon-muted fs-12 mb-0">Keeping existing products is idempotent and remains the recommended default.</p>
            </div>
            <div class="form-check form-switch">
                <input id="archive-existing-products" class="form-check-input" type="checkbox"
                    wire:model.live="form.archiveExisting">
                <label class="form-check-label" for="archive-existing-products">Archive all existing products first</label>
            </div>

            @if ($form->archiveExisting)
                <div class="commerce-demo-data-warning" role="note">
                    <i class="ti ti-alert-triangle" aria-hidden="true"></i>
                    <div>
                        <strong>Historical rows remain intact.</strong>
                        <p>Existing products will become archived before the selected context is published. Orders, stock movements, and prior records are not deleted.</p>
                        <div class="form-check mt-2">
                            <input id="confirm-archive-products" class="form-check-input" type="checkbox"
                                wire:model="form.archiveConfirmed">
                            <label class="form-check-label" for="confirm-archive-products">I understand and want to archive the existing product catalog.</label>
                        </div>
                        @error('form.archiveConfirmed')<div class="invalid-feedback d-block">Confirm the archive action before continuing.</div>@enderror
                    </div>
                </div>
            @endif
        </div>

        @if ($lastSeededContext !== null)
            <div class="card-body border-top commerce-demo-data-result" role="status">
                <span class="commerce-demo-data-result__icon"><i class="ti ti-circle-check" aria-hidden="true"></i></span>
                <div>
                    <strong>Last completed run</strong>
                    <p>{{ $lastSeededContext }} | {{ $lastRunMode }} | <time datetime="{{ $lastRunAt }}">{{ \Illuminate\Support\Carbon::parse($lastRunAt)->diffForHumans() }}</time></p>
                    @if ($lastCommandOutput !== '')
                        <details><summary>Command output</summary><pre>{{ $lastCommandOutput }}</pre></details>
                    @endif
                </div>
            </div>
        @elseif ($lastCommandOutput !== '')
            <div class="card-body border-top commerce-demo-data-result commerce-demo-data-result--error">
                <span class="commerce-demo-data-result__icon"><i class="ti ti-alert-circle" aria-hidden="true"></i></span>
                <div><strong>Command output</strong><pre>{{ $lastCommandOutput }}</pre></div>
            </div>
        @endif

        <div class="card-footer d-flex flex-wrap align-items-center justify-content-between gap-3">
            <p class="aureon-muted fs-12 mb-0"><i class="ti ti-shield-lock me-1" aria-hidden="true"></i>Runs are serialized and recorded in System activity.</p>
            <button type="submit" class="btn btn-primary commerce-demo-data-submit"
                wire:loading.attr="disabled" wire:target="seed">
                <i class="ti ti-database-import" aria-hidden="true"></i>
                <span wire:loading.remove wire:target="seed">Seed selected context</span>
                <span wire:loading wire:target="seed">Seeding data...</span>
            </button>
        </div>
    </form>
</div>
