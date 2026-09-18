@use('App\Modules\TravelTours\Bookings\Enums\ParticipantType')
@use('App\Modules\TravelTours\Pricing\Models\TourRatePlan')
@use('App\Modules\TravelTours\Support\MoneyFormatter')
@use('App\Modules\TravelTours\Support\ScaledDecimal')
@php
    $plans = $this->plans;
    $canManage = auth()->user()->can('create', TourRatePlan::class);
@endphp

<div id="travel-rate-plan-manager">
    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @if ($dialog === '')
        @error('management')
            <div class="alert alert-danger" role="alert">{{ $message }}</div>
        @enderror
    @endif

    <section class="card aureon-panel aureon-table-panel mb-4" aria-labelledby="travel-rate-plans-title">
        <div class="card-header d-flex align-items-center justify-content-between gap-3 flex-wrap">
            <div>
                <h3 id="travel-rate-plans-title" class="card-title mb-1">Rate plans</h3>
                <p class="aureon-muted mb-0">{{ number_format($plans->count()) }} {{ Str::plural('plan', $plans->count()) }}; the default public plan prices departures without a plan of their own</p>
            </div>
            @if ($canManage)
                <button type="button" class="btn btn-primary" wire:click="openCreate"><i class="ti ti-plus me-2" aria-hidden="true"></i>Add rate plan</button>
            @endif
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 travel-admin-table">
                <thead>
                    <tr>
                        <th scope="col">Plan</th>
                        <th scope="col">Fares</th>
                        <th scope="col">Deposit</th>
                        <th scope="col">Usage</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($plans as $plan)
                        <tr wire:key="plan-{{ $plan->getKey() }}">
                            <td>
                                <strong class="d-block">{{ $plan->name }} @if ($plan->is_default)<span class="badge text-bg-primary ms-1">Default</span>@endif</strong>
                                <small class="aureon-muted d-block">{{ $plan->code }} &middot; {{ $plan->currency }} &middot; {{ $plan->tax_inclusive ? 'tax inclusive' : 'tax added' }}@if ($plan->tax_rate_basis_points > 0) ({{ ScaledDecimal::formatUnsigned($plan->tax_rate_basis_points, 2) }}%)@endif</small>
                                <span class="badge {{ $plan->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $plan->is_active ? 'Active' : 'Inactive' }}</span>
                                <span class="badge text-bg-light">{{ $plan->is_public ? 'Public' : 'Private' }}</span>
                            </td>
                            <td>
                                @forelse ($plan->participantRates->sortBy(fn ($rate) => $rate->participant_type->value) as $rate)
                                    <small class="d-block {{ $rate->is_active ? '' : 'aureon-muted text-decoration-line-through' }}">
                                        {{ $rate->participant_type->label() }}: {{ MoneyFormatter::format($rate->amount_minor, $plan->currency) }}
                                        @if ($rate->active_from || $rate->active_until) <span class="aureon-muted">({{ $rate->active_from?->format('d M Y') ?: 'open' }} to {{ $rate->active_until?->format('d M Y') ?: 'open' }})</span>@endif
                                    </small>
                                @empty
                                    <small class="aureon-muted">No fares yet</small>
                                @endforelse
                            </td>
                            <td>
                                {{ $plan->deposit_type === 'percentage' ? ScaledDecimal::formatUnsigned($plan->deposit_value, 2).'%' : MoneyFormatter::format($plan->deposit_value, $plan->currency) }}
                                <small class="d-block aureon-muted">balance {{ $plan->balance_due_days }} days before</small>
                            </td>
                            <td><small class="d-block">{{ $plan->departures_count }} {{ Str::plural('departure', $plan->departures_count) }}</small><small class="d-block">{{ $plan->bookings_count }} {{ Str::plural('booking', $plan->bookings_count) }}</small><small class="d-block">{{ $plan->pricing_rules_count }} {{ Str::plural('rule', $plan->pricing_rules_count) }}</small></td>
                            <td class="text-end">
                                @if ($canManage)
                                    <button type="button" class="btn btn-sm btn-outline-secondary btn-icon" wire:click="openEdit({{ $plan->getKey() }})" title="Edit {{ $plan->name }}" aria-label="Edit {{ $plan->name }}"><i class="ti ti-pencil" aria-hidden="true"></i></button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center py-5 aureon-muted">No rate plans yet. Add one to start selling this tour.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($dialog === 'form')
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true"
            aria-labelledby="travel-rate-plan-form-title" wire:keydown.escape.window="closeDialog"
            x-data x-init="$nextTick(() => $el.querySelector('#plan-code')?.focus())">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
                <form class="modal-content" wire:submit="save" novalidate>
                    <div class="modal-header">
                        <h3 id="travel-rate-plan-form-title" class="modal-title fs-18">{{ $editingId ? 'Edit rate plan' : 'Add rate plan' }}</h3>
                        <button type="button" class="btn-close" wire:click="closeDialog" aria-label="Close rate plan form"></button>
                    </div>
                    <div class="modal-body">
                        @error('management')
                            <div class="alert alert-danger" role="alert">{{ $message }}</div>
                        @enderror
                        <div class="row g-3">
                            <div class="col-sm-4">
                                <label for="plan-code" class="form-label">Code</label>
                                <input id="plan-code" type="text" class="form-control @error('form.code') is-invalid @enderror" wire:model="form.code" maxlength="60">
                                @error('form.code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-8">
                                <label for="plan-name" class="form-label">Name</label>
                                <input id="plan-name" type="text" class="form-control @error('form.name') is-invalid @enderror" wire:model="form.name" maxlength="160">
                                @error('form.name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-3">
                                <label for="plan-currency" class="form-label">Currency</label>
                                <input id="plan-currency" type="text" class="form-control text-uppercase @error('form.currency') is-invalid @enderror" wire:model="form.currency" maxlength="3">
                                @error('form.currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-3">
                                <label for="plan-tax" class="form-label">Tax rate %</label>
                                <input id="plan-tax" type="text" inputmode="decimal" class="form-control @error('form.taxRatePercent') is-invalid @enderror" wire:model="form.taxRatePercent">
                                @error('form.taxRatePercent')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-3">
                                <label for="plan-deposit-type" class="form-label">Deposit type</label>
                                <select id="plan-deposit-type" class="form-select" wire:model.live="form.depositType">
                                    <option value="percentage">Percentage of total</option>
                                    <option value="fixed">Fixed amount</option>
                                </select>
                            </div>
                            <div class="col-sm-3">
                                <label for="plan-deposit-value" class="form-label">{{ $form->depositType === 'fixed' ? 'Deposit amount' : 'Deposit %' }}</label>
                                <input id="plan-deposit-value" type="text" inputmode="decimal" class="form-control @error('form.depositValue') is-invalid @enderror" wire:model="form.depositValue">
                                @error('form.depositValue')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-3">
                                <label for="plan-balance-days" class="form-label">Balance due (days before)</label>
                                <input id="plan-balance-days" type="number" min="0" max="730" class="form-control @error('form.balanceDueDays') is-invalid @enderror" wire:model="form.balanceDueDays">
                                @error('form.balanceDueDays')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-3">
                                <label for="plan-min" class="form-label">Minimum travellers</label>
                                <input id="plan-min" type="number" min="1" max="1000" class="form-control @error('form.minimumParticipants') is-invalid @enderror" wire:model="form.minimumParticipants">
                                @error('form.minimumParticipants')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-3">
                                <label for="plan-max" class="form-label">Maximum travellers <span class="aureon-muted">(optional)</span></label>
                                <input id="plan-max" type="number" min="1" max="1000" class="form-control @error('form.maximumParticipants') is-invalid @enderror" wire:model="form.maximumParticipants">
                                @error('form.maximumParticipants')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-3">
                                <label for="plan-order" class="form-label">Display order</label>
                                <input id="plan-order" type="number" min="0" max="1000" class="form-control @error('form.displayOrder') is-invalid @enderror" wire:model="form.displayOrder">
                                @error('form.displayOrder')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label for="plan-description" class="form-label">Description <span class="aureon-muted">(optional)</span></label>
                                <textarea id="plan-description" rows="2" class="form-control @error('form.description') is-invalid @enderror" wire:model="form.description" maxlength="2000"></textarea>
                                @error('form.description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 d-flex flex-wrap gap-4">
                                <div class="form-check form-switch"><input id="plan-tax-inclusive" class="form-check-input" type="checkbox" role="switch" wire:model="form.taxInclusive"><label class="form-check-label" for="plan-tax-inclusive">Prices include tax</label></div>
                                <div class="form-check form-switch"><input id="plan-refundable" class="form-check-input" type="checkbox" role="switch" wire:model="form.isRefundable"><label class="form-check-label" for="plan-refundable">Refundable</label></div>
                                <div class="form-check form-switch"><input id="plan-active" class="form-check-input" type="checkbox" role="switch" wire:model="form.isActive"><label class="form-check-label" for="plan-active">Active</label></div>
                                <div class="form-check form-switch"><input id="plan-public" class="form-check-input" type="checkbox" role="switch" wire:model="form.isPublic"><label class="form-check-label" for="plan-public">Public on the storefront</label></div>
                                <div class="form-check form-switch"><input id="plan-default" class="form-check-input" type="checkbox" role="switch" wire:model="form.isDefault"><label class="form-check-label" for="plan-default">Default plan for this tour</label></div>
                            </div>
                        </div>

                        <hr class="my-4">
                        <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-2">
                            <div>
                                <h4 class="fs-15 mb-1">Participant fares</h4>
                                <p class="aureon-muted mb-0">Active fares of one type must not overlap in time; leave dates empty for an always-on fare.</p>
                            </div>
                            <div class="travel-admin-row-actions">
                                @foreach (ParticipantType::cases() as $type)
                                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="addRate('{{ $type->value }}')"><i class="ti ti-plus me-1" aria-hidden="true"></i>{{ $type->label() }} fare</button>
                                @endforeach
                            </div>
                        </div>
                        @error('form.rates')<div class="alert alert-danger py-2" role="alert">{{ $message }}</div>@enderror
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0 travel-rate-table">
                                <thead>
                                    <tr><th scope="col">Type</th><th scope="col">Amount ({{ strtoupper($form->currency) }})</th><th scope="col">Min age</th><th scope="col">Max age</th><th scope="col">Active from</th><th scope="col">Active until</th><th scope="col">On</th><th scope="col"><span class="visually-hidden">Remove</span></th></tr>
                                </thead>
                                <tbody>
                                    @foreach ($form->rates as $index => $rate)
                                        <tr wire:key="rate-{{ $index }}">
                                            <td>
                                                <label class="visually-hidden" for="rate-{{ $index }}-type">Fare {{ $index + 1 }} type</label>
                                                <select id="rate-{{ $index }}-type" class="form-select form-select-sm" wire:model="form.rates.{{ $index }}.type">
                                                    @foreach (ParticipantType::cases() as $type)
                                                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <label class="visually-hidden" for="rate-{{ $index }}-amount">Fare {{ $index + 1 }} amount</label>
                                                <input id="rate-{{ $index }}-amount" type="text" inputmode="decimal" class="form-control form-control-sm @error('form.rates.'.$index.'.amount') is-invalid @enderror" wire:model="form.rates.{{ $index }}.amount" placeholder="0.00">
                                                @error('form.rates.'.$index.'.amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            </td>
                                            <td><label class="visually-hidden" for="rate-{{ $index }}-min-age">Fare {{ $index + 1 }} minimum age</label><input id="rate-{{ $index }}-min-age" type="number" min="0" max="120" class="form-control form-control-sm @error('form.rates.'.$index.'.minimum_age') is-invalid @enderror" wire:model="form.rates.{{ $index }}.minimum_age"></td>
                                            <td><label class="visually-hidden" for="rate-{{ $index }}-max-age">Fare {{ $index + 1 }} maximum age</label><input id="rate-{{ $index }}-max-age" type="number" min="0" max="120" class="form-control form-control-sm @error('form.rates.'.$index.'.maximum_age') is-invalid @enderror" wire:model="form.rates.{{ $index }}.maximum_age"></td>
                                            <td><label class="visually-hidden" for="rate-{{ $index }}-from">Fare {{ $index + 1 }} active from</label><input id="rate-{{ $index }}-from" type="date" class="form-control form-control-sm @error('form.rates.'.$index.'.active_from') is-invalid @enderror" wire:model="form.rates.{{ $index }}.active_from"></td>
                                            <td><label class="visually-hidden" for="rate-{{ $index }}-until">Fare {{ $index + 1 }} active until</label><input id="rate-{{ $index }}-until" type="date" class="form-control form-control-sm @error('form.rates.'.$index.'.active_until') is-invalid @enderror" wire:model="form.rates.{{ $index }}.active_until"></td>
                                            <td><div class="form-check form-switch m-0"><input id="rate-{{ $index }}-active" class="form-check-input" type="checkbox" role="switch" wire:model="form.rates.{{ $index }}.is_active"><label class="visually-hidden" for="rate-{{ $index }}-active">Fare {{ $index + 1 }} active</label></div></td>
                                            <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger btn-icon" wire:click="removeRate({{ $index }})" title="Remove fare {{ $index + 1 }}" aria-label="Remove fare {{ $index + 1 }}"><i class="ti ti-trash" aria-hidden="true"></i></button></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" wire:click="closeDialog">Cancel</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">
                            <span wire:loading.remove wire:target="save">Save rate plan</span>
                            <span wire:loading wire:target="save">Saving…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif
</div>
