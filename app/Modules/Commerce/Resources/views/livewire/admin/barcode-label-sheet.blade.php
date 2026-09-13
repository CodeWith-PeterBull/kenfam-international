<div class="row g-3" data-barcode-sheet>
    <div class="col-xl-8">
        <div class="card mb-0">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="card-title mb-0"><i class="ti ti-barcode me-2"></i>Label print run</h5>
                @if ($this->rows !== [])
                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="clearItems">Clear all</button>
                @endif
            </div>
            <div class="card-body">
                <div class="mb-3 position-relative">
                    <label for="barcode-search" class="form-label">Add product</label>
                    <input id="barcode-search" type="search" class="form-control" wire:model.live.debounce.300ms="search" placeholder="Search by name, SKU, or barcode" autocomplete="off">
                    @if (trim($this->search) !== '' && $this->results->isNotEmpty())
                        <ul class="list-group position-absolute w-100 shadow-sm" style="z-index: 5; max-height: 260px; overflow-y: auto;">
                            @foreach ($this->results as $product)
                                <li class="list-group-item list-group-item-action d-flex align-items-center justify-content-between" role="button" wire:key="result-{{ $product->id }}" wire:click="addProduct({{ $product->id }})">
                                    <span><strong>{{ $product->name }}</strong><br><small class="aureon-muted">{{ $product->sku }} · {{ $product->barcode ?: 'No internal barcode' }}</small></span>
                                    <i class="ti ti-plus"></i>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                @error('sheet')<div class="alert alert-danger">{{ $message }}</div>@enderror

                @if ($this->rows === [])
                    <div class="text-center aureon-muted py-4"><i class="ti ti-barcode fs-24 d-block mb-2"></i>Search and add products to build a label sheet.</div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead><tr><th>Product</th><th style="width: 150px;">Encode</th><th style="width: 110px;">Labels</th><th>Preview</th><th style="width: 48px;"></th></tr></thead>
                            <tbody>
                                @foreach ($this->rows as $row)
                                    <tr wire:key="row-{{ $row['id'] }}">
                                        <td><strong>{{ $row['name'] }}</strong><br><small class="aureon-muted">{{ $row['sku'] }}</small></td>
                                        <td>
                                            <select class="form-select form-select-sm" wire:model.live="items.{{ $row['index'] }}.source">
                                                <option value="internal">Internal{{ $row['internalBarcode'] === '' ? ' (none)' : '' }}</option>
                                                <option value="manufacturer" @disabled(! $row['hasManufacturer'])>Manufacturer{{ $row['hasManufacturer'] ? '' : ' (none)' }}</option>
                                            </select>
                                        </td>
                                        <td><input type="number" min="1" max="500" class="form-control form-control-sm" wire:model.live="items.{{ $row['index'] }}.quantity"></td>
                                        <td>
                                            @if ($row['preview'])
                                                <img src="{{ $row['preview'] }}" alt="Barcode preview" style="height: 40px;"><br><small class="aureon-muted" style="letter-spacing: 1px;">{{ $row['value'] }}</small>
                                            @else
                                                <span class="badge bg-warning-subtle text-warning">No barcode</span>
                                            @endif
                                        </td>
                                        <td><button type="button" class="btn btn-sm btn-outline-danger" wire:click="removeItem({{ $row['index'] }})" aria-label="Remove"><i class="ti ti-x"></i></button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card mb-0">
            <div class="card-header"><h5 class="card-title mb-0"><i class="ti ti-settings me-2"></i>Label options</h5></div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="barcode-columns" class="form-label">Labels per row</label>
                    <select id="barcode-columns" class="form-select" wire:model.live="columns">
                        @for ($column = 1; $column <= 5; $column++)<option value="{{ $column }}">{{ $column }}</option>@endfor
                    </select>
                </div>
                <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="show-store" wire:model.live="showStoreName"><label class="form-check-label" for="show-store">Show store name</label></div>
                <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="show-name" wire:model.live="showProductName"><label class="form-check-label" for="show-name">Show product name</label></div>
                <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" id="show-price" wire:model.live="showPrice"><label class="form-check-label" for="show-price">Show price</label></div>

                <button type="button" class="btn btn-primary w-100" wire:click="generate" wire:loading.attr="disabled" @disabled($this->rows === [])>
                    <span wire:loading.remove wire:target="generate"><i class="ti ti-printer me-2"></i>Download label sheet</span>
                    <span wire:loading wire:target="generate">Preparing PDF…</span>
                </button>
                <p class="aureon-muted fs-12 mt-2 mb-0">Generates an A4 PDF of Code 128 labels ready to print and attach.</p>
            </div>
        </div>
    </div>
</div>
