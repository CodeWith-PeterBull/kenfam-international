@php
    $canCreate = auth()->user()->can('create', \App\Modules\TravelTours\Catalog\Models\TourCategory::class);
    $positions = $this->positions;
    $selected = $this->selectedCategory;
@endphp

<div id="travel-tour-category-manager">
    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @error('management')
        <div class="alert alert-danger" role="alert">{{ $message }}</div>
    @enderror

    <section class="card aureon-panel aureon-table-panel mb-4" aria-labelledby="travel-category-title">
        <div class="card-header d-flex align-items-center justify-content-between gap-3 flex-wrap">
            <div>
                <h3 id="travel-category-title" class="card-title mb-1">Categories</h3>
                <p class="aureon-muted mb-0">{{ number_format($this->categories->count()) }} taxonomy records, ordered as the storefront lists them</p>
            </div>
            @if ($canCreate)
                <button type="button" class="btn btn-primary" wire:click="openCreate">
                    <i class="ti ti-folder-plus me-2" aria-hidden="true"></i>Add category
                </button>
            @endif
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 travel-admin-table travel-category-table">
                <thead>
                    <tr>
                        <th scope="col">Category</th>
                        <th scope="col">Parent</th>
                        <th scope="col" class="text-center">Tours</th>
                        <th scope="col" class="text-center">Order</th>
                        <th scope="col">Visibility</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->categories as $category)
                        @php
                            $canUpdate = auth()->user()->can('update', $category);
                            $position = $positions[$category->id] ?? ['first' => true, 'last' => true];
                            $blocker = match (true) {
                                ! $category->is_active => null,
                                $category->active_children_count > 0 => 'Hide its active child categories first',
                                $category->published_tours_count > 0 => trans_choice('Assigned to :count published tour|Assigned to :count published tours', $category->published_tours_count, ['count' => $category->published_tours_count]),
                                default => null,
                            };
                        @endphp
                        <tr wire:key="travel-category-{{ $category->id }}" data-depth="{{ $category->depth }}">
                            <td>
                                <div class="travel-category-name" style="--travel-depth: {{ $category->depth }}">
                                    @if ($category->depth > 0)
                                        <i class="ti ti-corner-down-right travel-category-branch" aria-hidden="true"></i>
                                    @endif
                                    <div class="min-w-0">
                                        <strong class="d-block text-break">{{ $category->name }}</strong>
                                        <small class="aureon-muted">{{ $category->slug }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $category->parent?->name ?? 'Top level' }}</td>
                            <td class="text-center">{{ number_format($category->tours_count) }}</td>
                            <td class="text-center">
                                @if ($canUpdate)
                                    <div class="travel-admin-order" role="group" aria-label="Reorder {{ $category->name }}">
                                        <button type="button" class="btn btn-icon btn-sm btn-outline-secondary"
                                            wire:click="move({{ $category->id }}, 'up')"
                                            wire:loading.attr="disabled" wire:target="move"
                                            @disabled($position['first'])
                                            title="Move earlier" aria-label="Move {{ $category->name }} earlier">
                                            <i class="ti ti-arrow-up" aria-hidden="true"></i>
                                        </button>
                                        <button type="button" class="btn btn-icon btn-sm btn-outline-secondary"
                                            wire:click="move({{ $category->id }}, 'down')"
                                            wire:loading.attr="disabled" wire:target="move"
                                            @disabled($position['last'])
                                            title="Move later" aria-label="Move {{ $category->name }} later">
                                            <i class="ti ti-arrow-down" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                @else
                                    <span class="aureon-muted">{{ number_format($category->sort_order) }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($canUpdate)
                                    <div class="form-check form-switch travel-admin-switch">
                                        {{-- .prevent stops the browser's optimistic flip so a refused change never leaves the switch out of step with the server. --}}
                                        <input id="travel-category-active-{{ $category->id }}" type="checkbox" role="switch"
                                            class="form-check-input"
                                            wire:click.prevent="toggleActive({{ $category->id }})"
                                            wire:loading.attr="disabled" wire:target="toggleActive"
                                            @checked($category->is_active)
                                            @if ($blocker) aria-describedby="travel-category-blocker-{{ $category->id }}" @endif>
                                        <label for="travel-category-active-{{ $category->id }}" class="form-check-label">
                                            {{ $category->is_active ? 'Active' : 'Hidden' }}
                                        </label>
                                    </div>
                                    @if ($blocker)
                                        <small id="travel-category-blocker-{{ $category->id }}" class="d-block aureon-muted travel-admin-switch-hint">
                                            <i class="ti ti-lock" aria-hidden="true"></i>{{ $blocker }}
                                        </small>
                                    @endif
                                @else
                                    <span class="travel-status {{ $category->is_active ? 'travel-status--active' : 'travel-status--muted' }}">
                                        {{ $category->is_active ? 'Active' : 'Hidden' }}
                                    </span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="travel-admin-row-actions justify-content-end">
                                    <button type="button" class="btn btn-icon btn-sm btn-outline-secondary"
                                        wire:click="openDetails({{ $category->id }})"
                                        title="View category" aria-label="View {{ $category->name }}">
                                        <i class="ti ti-eye" aria-hidden="true"></i>
                                    </button>
                                    @if ($canUpdate)
                                        <button type="button" class="btn btn-icon btn-sm btn-outline-secondary"
                                            wire:click="openEdit({{ $category->id }})"
                                            title="Edit category" aria-label="Edit {{ $category->name }}">
                                            <i class="ti ti-edit" aria-hidden="true"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="travel-admin-empty">
                                    <i class="ti ti-category" aria-hidden="true"></i>
                                    <strong>No tour categories yet</strong>
                                    <span>Categories group tours for storefront discovery.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($dialog === 'form')
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true"
            aria-labelledby="travel-category-form-title" wire:keydown.escape.window="closeDialog"
            x-data x-init="$nextTick(() => $el.querySelector('#travel-category-name')?.focus())">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
                <form class="modal-content" wire:submit="save" novalidate>
                    <div class="modal-header">
                        <h3 id="travel-category-form-title" class="modal-title fs-18">{{ $selectedCategoryId ? 'Edit category' : 'Add category' }}</h3>
                        <button type="button" class="btn-close" wire:click="closeDialog" aria-label="Close category form"></button>
                    </div>
                    <div class="modal-body">
                        @error('management')
                            <div class="alert alert-danger" role="alert">{{ $message }}</div>
                        @enderror
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label for="travel-category-name" class="form-label">Name</label>
                                <input id="travel-category-name" class="form-control @error('form.name') is-invalid @enderror" wire:model="form.name" required
                                    @error('form.name') aria-invalid="true" aria-describedby="travel-category-name-error" @enderror>
                                @error('form.name')<div id="travel-category-name-error" class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label for="travel-category-order" class="form-label">Sort order</label>
                                <input id="travel-category-order" type="number" min="0" class="form-control @error('form.sortOrder') is-invalid @enderror" wire:model="form.sortOrder"
                                    @error('form.sortOrder') aria-invalid="true" aria-describedby="travel-category-order-error" @enderror>
                                @error('form.sortOrder')<div id="travel-category-order-error" class="invalid-feedback">{{ $message }}</div>@enderror
                                <small class="aureon-muted">Lower numbers list first; the arrows in the table adjust this for you.</small>
                            </div>
                            <div class="col-md-6">
                                <label for="travel-category-slug" class="form-label">URL slug</label>
                                <input id="travel-category-slug" class="form-control @error('form.slug') is-invalid @enderror" wire:model="form.slug" placeholder="Generated from name"
                                    @error('form.slug') aria-invalid="true" aria-describedby="travel-category-slug-error" @enderror>
                                @error('form.slug')<div id="travel-category-slug-error" class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label for="travel-category-parent" class="form-label">Parent category</label>
                                <select id="travel-category-parent" class="form-select @error('form.parentId') is-invalid @enderror" wire:model="form.parentId"
                                    @error('form.parentId') aria-invalid="true" aria-describedby="travel-category-parent-error" @enderror>
                                    <option value="">Top level</option>
                                    @foreach ($this->parentOptions as $option)
                                        <option value="{{ $option->id }}">{{ $option->name }}</option>
                                    @endforeach
                                </select>
                                @error('form.parentId')<div id="travel-category-parent-error" class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label for="travel-category-description" class="form-label">Description</label>
                                <textarea id="travel-category-description" rows="3" class="form-control @error('form.description') is-invalid @enderror" wire:model="form.description"
                                    @error('form.description') aria-invalid="true" aria-describedby="travel-category-description-error" @enderror></textarea>
                                @error('form.description')<div id="travel-category-description-error" class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label for="travel-category-icon" class="form-label">Icon key</label>
                                <input id="travel-category-icon" class="form-control @error('form.iconKey') is-invalid @enderror" wire:model="form.iconKey" placeholder="e.g. map-pin"
                                    @error('form.iconKey') aria-invalid="true" aria-describedby="travel-category-icon-error" @enderror>
                                @error('form.iconKey')<div id="travel-category-icon-error" class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <div class="form-check form-switch mb-2">
                                    <input id="travel-category-active" type="checkbox" role="switch" class="form-check-input" wire:model="form.isActive">
                                    <label for="travel-category-active" class="form-check-label">Active for assignment</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="travel-category-meta-title" class="form-label">SEO title</label>
                                <input id="travel-category-meta-title" class="form-control @error('form.metaTitle') is-invalid @enderror" wire:model="form.metaTitle"
                                    @error('form.metaTitle') aria-invalid="true" aria-describedby="travel-category-meta-title-error" @enderror>
                                @error('form.metaTitle')<div id="travel-category-meta-title-error" class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label for="travel-category-meta-description" class="form-label">SEO description</label>
                                <textarea id="travel-category-meta-description" rows="2" class="form-control @error('form.metaDescription') is-invalid @enderror" wire:model="form.metaDescription"
                                    @error('form.metaDescription') aria-invalid="true" aria-describedby="travel-category-meta-description-error" @enderror></textarea>
                                @error('form.metaDescription')<div id="travel-category-meta-description-error" class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" wire:click="closeDialog">Cancel</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">
                            <span wire:loading.remove wire:target="save">Save category</span>
                            <span wire:loading wire:target="save">Saving…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif

    @if ($dialog === 'details' && $selected)
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true"
            aria-labelledby="travel-category-detail-title" wire:keydown.escape.window="closeDialog"
            x-data x-init="$nextTick(() => $el.querySelector('.btn-close')?.focus())">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h3 id="travel-category-detail-title" class="modal-title fs-18">{{ $selected->name }}</h3>
                            <p class="aureon-muted fs-12 mb-0">
                                {{ $selected->parent ? 'Under '.$selected->parent->name : 'Top-level category' }}
                                &middot; {{ $selected->is_active ? 'active' : 'hidden' }}
                            </p>
                        </div>
                        <button type="button" class="btn-close" wire:click="closeDialog" aria-label="Close category details"></button>
                    </div>
                    <div class="modal-body">
                        <div class="travel-detail-grid">
                            <section aria-labelledby="travel-category-detail-identity">
                                <h4 id="travel-category-detail-identity">Identity</h4>
                                <dl class="travel-detail-list">
                                    <div><dt>URL slug</dt><dd><code>{{ $selected->slug }}</code></dd></div>
                                    <div><dt>Icon key</dt><dd>{{ $selected->icon_key ?: 'Not set' }}</dd></div>
                                    <div><dt>Sort order</dt><dd>{{ number_format($selected->sort_order) }}</dd></div>
                                    <div><dt>Created</dt><dd>{{ $selected->created_at?->format('d M Y, H:i') ?: 'Unknown' }}</dd></div>
                                    <div><dt>Updated</dt><dd>{{ $selected->updated_at?->format('d M Y, H:i') ?: 'Unknown' }}</dd></div>
                                </dl>
                            </section>
                            <section aria-labelledby="travel-category-detail-seo">
                                <h4 id="travel-category-detail-seo">Search presentation</h4>
                                <dl class="travel-detail-list">
                                    <div><dt>SEO title</dt><dd>{{ $selected->meta_title ?: 'Falls back to the name' }}</dd></div>
                                    <div><dt>SEO description</dt><dd>{{ $selected->meta_description ?: 'Not set' }}</dd></div>
                                </dl>
                            </section>
                            @if ($selected->description)
                                <section class="travel-detail-grid__wide" aria-labelledby="travel-category-detail-description">
                                    <h4 id="travel-category-detail-description">Description</h4>
                                    <p class="mb-0">{{ $selected->description }}</p>
                                </section>
                            @endif
                            <section class="travel-detail-grid__wide" aria-labelledby="travel-category-detail-children">
                                <h4 id="travel-category-detail-children">Child categories ({{ number_format($selected->children->count()) }})</h4>
                                @if ($selected->children->isEmpty())
                                    <p class="aureon-muted mb-0">No child categories.</p>
                                @else
                                    <ul class="travel-detail-chips">
                                        @foreach ($selected->children as $child)
                                            <li>
                                                <span class="travel-status {{ $child->is_active ? 'travel-status--active' : 'travel-status--muted' }}">{{ $child->name }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </section>
                            <section class="travel-detail-grid__wide" aria-labelledby="travel-category-detail-tours">
                                <h4 id="travel-category-detail-tours">Assigned tours ({{ number_format($selected->tours->count()) }})</h4>
                                @if ($selected->tours->isEmpty())
                                    <p class="aureon-muted mb-0">No tours use this category yet.</p>
                                @else
                                    <ul class="travel-detail-rows">
                                        @foreach ($selected->tours as $tour)
                                            <li>
                                                <div class="min-w-0">
                                                    <strong class="d-block text-break">{{ $tour->name }}</strong>
                                                    <small class="aureon-muted">{{ $tour->code }}@if ($tour->pivot->is_primary) &middot; primary category @endif</small>
                                                </div>
                                                <span class="travel-status {{ $tour->status === \App\Modules\TravelTours\Catalog\Enums\PublicationStatus::Published ? 'travel-status--active' : 'travel-status--muted' }}">{{ $tour->status->label() }}</span>
                                                @can('update', $tour)
                                                    <a class="btn btn-icon btn-sm btn-outline-secondary" href="{{ route('travel-tours.admin.catalog.tours.edit', $tour) }}" title="Open tour" aria-label="Open {{ $tour->name }} in the tour editor">
                                                        <i class="ti ti-external-link" aria-hidden="true"></i>
                                                    </a>
                                                @endcan
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </section>
                        </div>
                    </div>
                    <div class="modal-footer">
                        @can('update', $selected)
                            <button type="button" class="btn btn-outline-secondary" wire:click="openEdit({{ $selected->id }})">
                                <i class="ti ti-edit me-2" aria-hidden="true"></i>Edit category
                            </button>
                        @endcan
                        <button type="button" class="btn btn-primary" wire:click="closeDialog">Done</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif
</div>
