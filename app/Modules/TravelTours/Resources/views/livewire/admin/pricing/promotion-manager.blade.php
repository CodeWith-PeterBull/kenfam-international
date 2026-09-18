@use('App\Modules\TravelTours\Pricing\Enums\AdjustmentType')
@use('App\Modules\TravelTours\Pricing\Models\Promotion')
@use('App\Modules\TravelTours\Support\MoneyFormatter')
@use('App\Modules\TravelTours\Support\ScaledDecimal')
@php
    $promotions = $this->promotions;
    $canManage = auth()->user()->can('create', Promotion::class);
    $describe = static fn (Promotion $promotion): string => $promotion->adjustment_type === AdjustmentType::Percentage
        ? ScaledDecimal::formatUnsigned($promotion->adjustment_value, 2).'% off'
        : MoneyFormatter::format($promotion->adjustment_value, (string) $promotion->currency).' off';
@endphp

<div id="travel-promotion-manager">
    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @if ($dialog === '')
        @error('management')
            <div class="alert alert-danger" role="alert">{{ $message }}</div>
        @enderror
    @endif

    <section class="card aureon-panel aureon-table-panel mb-4" aria-labelledby="travel-promotions-title">
        <div class="card-header d-flex align-items-center justify-content-between gap-3 flex-wrap">
            <div>
                <h3 id="travel-promotions-title" class="card-title mb-1">Promotions</h3>
                <p class="aureon-muted mb-0">{{ number_format($promotions->total()) }} {{ Str::plural('code', $promotions->total()) }} customers can enter on the tour page</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <label class="visually-hidden" for="promotion-search">Search promotions</label>
                <input id="promotion-search" type="search" class="form-control" placeholder="Code or name" wire:model.live.debounce.400ms="search">
                @if ($canManage)
                    <button type="button" class="btn btn-primary" wire:click="openCreate"><i class="ti ti-plus me-2" aria-hidden="true"></i>Add promotion</button>
                @endif
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 travel-admin-table">
                <thead>
                    <tr>
                        <th scope="col">Promotion</th>
                        <th scope="col">Discount</th>
                        <th scope="col">Valid</th>
                        <th scope="col">Scope</th>
                        <th scope="col" class="text-center">Uses</th>
                        <th scope="col">Active</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($promotions as $promotion)
                        <tr wire:key="promotion-{{ $promotion->getKey() }}">
                            <td><strong class="d-block"><code>{{ $promotion->code }}</code></strong><small class="aureon-muted">{{ $promotion->name }}</small></td>
                            <td>{{ $describe($promotion) }}@if ($promotion->minimum_booking_minor > 0)<small class="d-block aureon-muted">from {{ MoneyFormatter::format($promotion->minimum_booking_minor, (string) ($promotion->currency ?: config('travel-tours.defaults.currency', 'KES'))) }}</small>@endif</td>
                            <td><small>{{ $promotion->valid_from?->format('d M Y') ?: 'now' }} to {{ $promotion->valid_until?->format('d M Y') ?: 'open' }}</small></td>
                            <td><small>{{ $promotion->applies_to_all_tours ? 'All tours'.($promotion->tours_count > 0 ? " except {$promotion->tours_count}" : '') : $promotion->tours_count.' '.Str::plural('tour', $promotion->tours_count) }}</small></td>
                            <td class="text-center">{{ $promotion->active_redemptions_count }}{{ $promotion->maximum_uses ? ' / '.$promotion->maximum_uses : '' }}</td>
                            <td>
                                @if ($canManage)
                                    <div class="form-check form-switch m-0">
                                        {{-- .prevent stops the optimistic flip; the state-bearing key replaces the element on re-render so a prevented click never pins the old checked state. --}}
                                        <input id="promotion-{{ $promotion->getKey() }}-active" wire:key="promotion-switch-{{ $promotion->getKey() }}-{{ $promotion->is_active ? 'on' : 'off' }}" class="form-check-input" type="checkbox" role="switch" @checked($promotion->is_active) wire:click.prevent="toggleActive({{ $promotion->getKey() }})" wire:loading.attr="disabled" wire:target="toggleActive">
                                        <label class="form-check-label visually-hidden" for="promotion-{{ $promotion->getKey() }}-active">{{ $promotion->is_active ? 'Deactivate' : 'Activate' }} {{ $promotion->code }}</label>
                                    </div>
                                @else
                                    <span class="badge {{ $promotion->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $promotion->is_active ? 'Active' : 'Inactive' }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if ($canManage)
                                    <button type="button" class="btn btn-sm btn-outline-secondary btn-icon" wire:click="openEdit({{ $promotion->getKey() }})" title="Edit {{ $promotion->code }}" aria-label="Edit {{ $promotion->code }}"><i class="ti ti-pencil" aria-hidden="true"></i></button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-5 aureon-muted">No promotions match.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($promotions->hasPages())
            <div class="card-footer">{{ $promotions->links() }}</div>
        @endif
    </section>

    @if ($dialog === 'form')
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true"
            aria-labelledby="travel-promotion-form-title" wire:keydown.escape.window="closeDialog"
            x-data x-init="$nextTick(() => $el.querySelector('#promotion-code')?.focus())">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
                <form class="modal-content" wire:submit="save" novalidate>
                    <div class="modal-header">
                        <h3 id="travel-promotion-form-title" class="modal-title fs-18">{{ $editingId ? 'Edit promotion' : 'Add promotion' }}</h3>
                        <button type="button" class="btn-close" wire:click="closeDialog" aria-label="Close promotion form"></button>
                    </div>
                    <div class="modal-body">
                        @error('management')
                            <div class="alert alert-danger" role="alert">{{ $message }}</div>
                        @enderror
                        <div class="row g-3">
                            <div class="col-sm-4">
                                <label for="promotion-code" class="form-label">Code</label>
                                <input id="promotion-code" type="text" class="form-control text-uppercase @error('form.code') is-invalid @enderror" wire:model="form.code" maxlength="80">
                                @error('form.code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-8">
                                <label for="promotion-name" class="form-label">Name shown on the quote</label>
                                <input id="promotion-name" type="text" class="form-control @error('form.name') is-invalid @enderror" wire:model="form.name" maxlength="160">
                                @error('form.name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-4">
                                <label for="promotion-adjustment-type" class="form-label">Discount type</label>
                                <select id="promotion-adjustment-type" class="form-select" wire:model.live="form.adjustmentType">
                                    <option value="percentage">Percentage</option>
                                    <option value="fixed">Fixed amount</option>
                                </select>
                            </div>
                            <div class="col-sm-4">
                                <label for="promotion-adjustment-value" class="form-label">{{ $form->adjustmentType === 'percentage' ? 'Percent off' : 'Amount off' }}</label>
                                <input id="promotion-adjustment-value" type="text" inputmode="decimal" class="form-control @error('form.adjustmentValue') is-invalid @enderror" wire:model="form.adjustmentValue" placeholder="{{ $form->adjustmentType === 'percentage' ? '10' : '0.00' }}">
                                @error('form.adjustmentValue')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-4">
                                <label for="promotion-currency" class="form-label">Currency {{ $form->adjustmentType === 'fixed' ? '' : '(fixed discounts only)' }}</label>
                                <input id="promotion-currency" type="text" class="form-control text-uppercase @error('form.currency') is-invalid @enderror" wire:model="form.currency" maxlength="3" @disabled($form->adjustmentType !== 'fixed')>
                                @error('form.currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-6">
                                <label for="promotion-valid-from" class="form-label">Valid from <span class="aureon-muted">(optional)</span></label>
                                <input id="promotion-valid-from" type="datetime-local" class="form-control @error('form.validFrom') is-invalid @enderror" wire:model="form.validFrom">
                                @error('form.validFrom')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-6">
                                <label for="promotion-valid-until" class="form-label">Valid until <span class="aureon-muted">(optional)</span></label>
                                <input id="promotion-valid-until" type="datetime-local" class="form-control @error('form.validUntil') is-invalid @enderror" wire:model="form.validUntil">
                                @error('form.validUntil')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-3">
                                <label for="promotion-min-booking" class="form-label">Minimum booking value</label>
                                <input id="promotion-min-booking" type="text" inputmode="decimal" class="form-control @error('form.minimumBooking') is-invalid @enderror" wire:model="form.minimumBooking">
                                @error('form.minimumBooking')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-3">
                                <label for="promotion-min-participants" class="form-label">Minimum travellers</label>
                                <input id="promotion-min-participants" type="number" min="1" max="1000" class="form-control @error('form.minimumParticipants') is-invalid @enderror" wire:model="form.minimumParticipants">
                                @error('form.minimumParticipants')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-3">
                                <label for="promotion-max-uses" class="form-label">Total uses <span class="aureon-muted">(blank = unlimited)</span></label>
                                <input id="promotion-max-uses" type="number" min="1" class="form-control @error('form.maximumUses') is-invalid @enderror" wire:model="form.maximumUses">
                                @error('form.maximumUses')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-3">
                                <label for="promotion-max-per-customer" class="form-label">Uses per customer <span class="aureon-muted">(blank = unlimited)</span></label>
                                <input id="promotion-max-per-customer" type="number" min="1" class="form-control @error('form.maximumUsesPerCustomer') is-invalid @enderror" wire:model="form.maximumUsesPerCustomer">
                                @error('form.maximumUsesPerCustomer')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label for="promotion-description" class="form-label">Internal note <span class="aureon-muted">(optional)</span></label>
                                <textarea id="promotion-description" rows="2" class="form-control @error('form.description') is-invalid @enderror" wire:model="form.description" maxlength="2000"></textarea>
                                @error('form.description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 d-flex flex-wrap gap-4">
                                <div class="form-check form-switch"><input id="promotion-all-tours" class="form-check-input" type="checkbox" role="switch" wire:model.live="form.appliesToAllTours"><label class="form-check-label" for="promotion-all-tours">Applies to all tours</label></div>
                                <div class="form-check form-switch"><input id="promotion-active" class="form-check-input" type="checkbox" role="switch" wire:model="form.isActive"><label class="form-check-label" for="promotion-active">Active</label></div>
                            </div>
                            <div class="col-12">
                                <label for="promotion-tours" class="form-label">{{ $form->appliesToAllTours ? 'Exclude these tours' : 'Apply to these tours' }} <span class="aureon-muted">(hold Ctrl or Cmd to choose several)</span></label>
                                <select id="promotion-tours" class="form-select" multiple size="6" wire:model="{{ $form->appliesToAllTours ? 'form.excludedTourIds' : 'form.includedTourIds' }}">
                                    @foreach ($this->tours as $tour)
                                        <option value="{{ $tour->getKey() }}">{{ $tour->name }} ({{ $tour->code }})</option>
                                    @endforeach
                                </select>
                                @error('form.includedTourIds')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" wire:click="closeDialog">Cancel</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">
                            <span wire:loading.remove wire:target="save">Save promotion</span>
                            <span wire:loading wire:target="save">Saving…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif
</div>
