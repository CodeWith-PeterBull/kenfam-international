@use('App\Modules\TravelTours\Pricing\Enums\AdjustmentType')
@use('App\Modules\TravelTours\Pricing\Enums\PricingRuleType')
@use('App\Modules\TravelTours\Pricing\Models\PricingRule')
@use('App\Modules\TravelTours\Support\MoneyFormatter')
@use('App\Modules\TravelTours\Support\ScaledDecimal')
@php
    $rules = $this->rules;
    $plans = $this->plans;
    $canManage = auth()->user()->can('create', PricingRule::class);
    $describe = static fn (PricingRule $rule): string => match ($rule->adjustment_type) {
        AdjustmentType::Percentage => ($rule->adjustment_value < 0 ? '−' : '+').ScaledDecimal::formatUnsigned(abs($rule->adjustment_value), 2).'%',
        AdjustmentType::Fixed => ($rule->adjustment_value < 0 ? '−' : '+').MoneyFormatter::format(abs($rule->adjustment_value), $rule->ratePlan->currency),
        AdjustmentType::Override => 'Override to '.MoneyFormatter::format($rule->adjustment_value, $rule->ratePlan->currency),
    };
@endphp

<div id="travel-pricing-rule-manager">
    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @if ($dialog === '')
        @error('management')
            <div class="alert alert-danger" role="alert">{{ $message }}</div>
        @enderror
    @endif

    <section class="card aureon-panel aureon-table-panel mb-4" aria-labelledby="travel-pricing-rules-title">
        <div class="card-header d-flex align-items-center justify-content-between gap-3 flex-wrap">
            <div>
                <h3 id="travel-pricing-rules-title" class="card-title mb-1">Pricing rules</h3>
                <p class="aureon-muted mb-0">Seasonal, group, and early-bird adjustments applied in priority order; a non-stackable rule stops the chain</p>
            </div>
            @if ($canManage && $plans->isNotEmpty())
                <button type="button" class="btn btn-primary" wire:click="openCreate"><i class="ti ti-plus me-2" aria-hidden="true"></i>Add rule</button>
            @endif
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 travel-admin-table">
                <thead>
                    <tr>
                        <th scope="col">Rule</th>
                        <th scope="col">Plan</th>
                        <th scope="col">Adjustment</th>
                        <th scope="col">Applies when</th>
                        <th scope="col" class="text-center">Priority</th>
                        <th scope="col">Active</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rules as $rule)
                        <tr wire:key="rule-{{ $rule->getKey() }}">
                            <td><strong class="d-block">{{ $rule->name }}</strong><small class="aureon-muted">{{ $rule->rule_type->label() }}@if ($rule->is_stackable) &middot; stackable @endif</small></td>
                            <td>{{ $rule->ratePlan->name }}</td>
                            <td>{{ $describe($rule) }}</td>
                            <td>
                                <small class="d-block">
                                    @if ($rule->departure) Departure {{ $rule->departure->code }} @endif
                                    @if ($rule->travel_starts_on || $rule->travel_ends_on) Travel {{ $rule->travel_starts_on?->format('d M Y') ?: 'any' }} to {{ $rule->travel_ends_on?->format('d M Y') ?: 'any' }}. @endif
                                    @if ($rule->sales_start_at || $rule->sales_end_at) Sold {{ $rule->sales_start_at?->format('d M Y') ?: 'any' }} to {{ $rule->sales_end_at?->format('d M Y') ?: 'any' }}. @endif
                                    @if ($rule->minimum_participants) {{ $rule->minimum_participants }}+ travellers. @endif
                                    @if ($rule->minimum_advance_days) Booked {{ $rule->minimum_advance_days }}+ days ahead. @endif
                                </small>
                            </td>
                            <td class="text-center">{{ $rule->priority }}</td>
                            <td>
                                @if ($canManage)
                                    <div class="form-check form-switch m-0">
                                        {{-- .prevent stops the optimistic flip; the state-bearing key replaces the element on re-render so a prevented click never pins the old checked state. --}}
                                        <input id="rule-{{ $rule->getKey() }}-active" wire:key="rule-switch-{{ $rule->getKey() }}-{{ $rule->is_active ? 'on' : 'off' }}" class="form-check-input" type="checkbox" role="switch" @checked($rule->is_active) wire:click.prevent="toggleActive({{ $rule->getKey() }})" wire:loading.attr="disabled" wire:target="toggleActive">
                                        <label class="form-check-label visually-hidden" for="rule-{{ $rule->getKey() }}-active">{{ $rule->is_active ? 'Deactivate' : 'Activate' }} {{ $rule->name }}</label>
                                    </div>
                                @else
                                    <span class="badge {{ $rule->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $rule->is_active ? 'Active' : 'Inactive' }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if ($canManage)
                                    <button type="button" class="btn btn-sm btn-outline-secondary btn-icon" wire:click="openEdit({{ $rule->getKey() }})" title="Edit {{ $rule->name }}" aria-label="Edit {{ $rule->name }}"><i class="ti ti-pencil" aria-hidden="true"></i></button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-5 aureon-muted">{{ $plans->isEmpty() ? 'Add a rate plan before defining rules.' : 'No pricing rules yet.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($dialog === 'form')
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true"
            aria-labelledby="travel-pricing-rule-form-title" wire:keydown.escape.window="closeDialog"
            x-data x-init="$nextTick(() => $el.querySelector('#rule-name')?.focus())">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
                <form class="modal-content" wire:submit="save" novalidate>
                    <div class="modal-header">
                        <h3 id="travel-pricing-rule-form-title" class="modal-title fs-18">{{ $editingId ? 'Edit pricing rule' : 'Add pricing rule' }}</h3>
                        <button type="button" class="btn-close" wire:click="closeDialog" aria-label="Close pricing rule form"></button>
                    </div>
                    <div class="modal-body">
                        @error('management')
                            <div class="alert alert-danger" role="alert">{{ $message }}</div>
                        @enderror
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label for="rule-plan" class="form-label">Rate plan</label>
                                <select id="rule-plan" class="form-select @error('form.ratePlanId') is-invalid @enderror" wire:model="form.ratePlanId" @disabled($editingId !== null)>
                                    <option value="">Choose a plan</option>
                                    @foreach ($plans as $plan)
                                        <option value="{{ $plan->getKey() }}">{{ $plan->name }} ({{ $plan->currency }})</option>
                                    @endforeach
                                </select>
                                @error('form.ratePlanId')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-6">
                                <label for="rule-name" class="form-label">Name</label>
                                <input id="rule-name" type="text" class="form-control @error('form.name') is-invalid @enderror" wire:model="form.name" maxlength="160">
                                @error('form.name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-4">
                                <label for="rule-type" class="form-label">Rule type</label>
                                <select id="rule-type" class="form-select" wire:model="form.ruleType">
                                    @foreach (PricingRuleType::cases() as $type)
                                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-4">
                                <label for="rule-adjustment-type" class="form-label">Adjustment</label>
                                <select id="rule-adjustment-type" class="form-select" wire:model.live="form.adjustmentType">
                                    <option value="percentage">Percentage of fares</option>
                                    <option value="fixed">Fixed amount</option>
                                    <option value="override">Override fare total</option>
                                </select>
                            </div>
                            <div class="col-sm-4">
                                <label for="rule-adjustment-value" class="form-label">{{ $form->adjustmentType === 'percentage' ? 'Percent (negative for a discount)' : ($form->adjustmentType === 'fixed' ? 'Amount (negative for a discount)' : 'New fare total') }}</label>
                                <input id="rule-adjustment-value" type="text" inputmode="decimal" class="form-control @error('form.adjustmentValue') is-invalid @enderror" wire:model="form.adjustmentValue" placeholder="{{ $form->adjustmentType === 'percentage' ? '-10' : '0.00' }}">
                                @error('form.adjustmentValue')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-6">
                                <label for="rule-departure" class="form-label">Only for departure <span class="aureon-muted">(optional)</span></label>
                                <select id="rule-departure" class="form-select @error('form.departureId') is-invalid @enderror" wire:model="form.departureId">
                                    <option value="">Every departure</option>
                                    @foreach ($this->departures as $departure)
                                        <option value="{{ $departure->getKey() }}">{{ $departure->code }} &middot; {{ $departure->starts_at->timezone($departure->timezone)->format('d M Y') }}</option>
                                    @endforeach
                                </select>
                                @error('form.departureId')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-3">
                                <label for="rule-priority" class="form-label">Priority</label>
                                <input id="rule-priority" type="number" min="0" max="10000" class="form-control @error('form.priority') is-invalid @enderror" wire:model="form.priority">
                                @error('form.priority')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-3 d-flex flex-column justify-content-end gap-2">
                                <div class="form-check form-switch"><input id="rule-stackable" class="form-check-input" type="checkbox" role="switch" wire:model="form.isStackable"><label class="form-check-label" for="rule-stackable">Stackable</label></div>
                                <div class="form-check form-switch"><input id="rule-active" class="form-check-input" type="checkbox" role="switch" wire:model="form.isActive"><label class="form-check-label" for="rule-active">Active</label></div>
                            </div>
                            <div class="col-sm-3">
                                <label for="rule-travel-from" class="form-label">Travel from</label>
                                <input id="rule-travel-from" type="date" class="form-control @error('form.travelStartsOn') is-invalid @enderror" wire:model="form.travelStartsOn">
                                @error('form.travelStartsOn')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-3">
                                <label for="rule-travel-until" class="form-label">Travel until</label>
                                <input id="rule-travel-until" type="date" class="form-control @error('form.travelEndsOn') is-invalid @enderror" wire:model="form.travelEndsOn">
                                @error('form.travelEndsOn')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-3">
                                <label for="rule-sales-from" class="form-label">Sold from</label>
                                <input id="rule-sales-from" type="datetime-local" class="form-control @error('form.salesStartAt') is-invalid @enderror" wire:model="form.salesStartAt">
                                @error('form.salesStartAt')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-3">
                                <label for="rule-sales-until" class="form-label">Sold until</label>
                                <input id="rule-sales-until" type="datetime-local" class="form-control @error('form.salesEndAt') is-invalid @enderror" wire:model="form.salesEndAt">
                                @error('form.salesEndAt')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-6">
                                <label for="rule-min-participants" class="form-label">Minimum travellers <span class="aureon-muted">(optional)</span></label>
                                <input id="rule-min-participants" type="number" min="1" max="1000" class="form-control @error('form.minimumParticipants') is-invalid @enderror" wire:model="form.minimumParticipants">
                                @error('form.minimumParticipants')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-6">
                                <label for="rule-advance-days" class="form-label">Booked at least (days ahead) <span class="aureon-muted">(optional)</span></label>
                                <input id="rule-advance-days" type="number" min="0" max="730" class="form-control @error('form.minimumAdvanceDays') is-invalid @enderror" wire:model="form.minimumAdvanceDays">
                                @error('form.minimumAdvanceDays')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" wire:click="closeDialog">Cancel</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">
                            <span wire:loading.remove wire:target="save">Save rule</span>
                            <span wire:loading wire:target="save">Saving…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif
</div>
