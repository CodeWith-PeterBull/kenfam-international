<?php

/** Tour-scoped pricing rule workspace covering every plan on the tour. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Pricing\Livewire\Admin;

use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Pricing\Exceptions\InvalidRateConfiguration;
use App\Modules\TravelTours\Pricing\Livewire\Forms\PricingRuleForm;
use App\Modules\TravelTours\Pricing\Models\PricingRule;
use App\Modules\TravelTours\Pricing\Models\TourRatePlan;
use App\Modules\TravelTours\Pricing\Services\PricingRuleService;
use App\Modules\TravelTours\Scheduling\Models\TourDeparture;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Rules are created and edited per plan and only ever switched off, never deleted. */
final class PricingRuleManager extends Component
{
    public PricingRuleForm $form;

    #[Locked]
    public int $tourId;

    #[Locked]
    public ?int $editingId = null;

    public string $dialog = '';

    /** Require pricing visibility on each Livewire request. */
    public function boot(): void
    {
        Gate::authorize('viewAny', PricingRule::class);
    }

    /** Lock the manager to one tour. */
    public function mount(int $tourId): void
    {
        Tour::query()->findOrFail($tourId);
        $this->tourId = $tourId;
    }

    /**
     * Plans on the tour, for the rule form's plan selector.
     *
     * @return Collection<int, TourRatePlan>
     */
    #[Computed]
    public function plans(): Collection
    {
        return TourRatePlan::query()->where('tour_id', $this->tourId)->orderByDesc('is_default')->orderBy('name')->get();
    }

    /**
     * Upcoming departures on the tour, for departure-specific rules.
     *
     * @return Collection<int, TourDeparture>
     */
    #[Computed]
    public function departures(): Collection
    {
        return TourDeparture::query()->where('tour_id', $this->tourId)->where('starts_at', '>', now())->orderBy('starts_at')->limit(60)->get();
    }

    /**
     * Rules across every plan on the tour, by plan then priority.
     *
     * @return Collection<int, PricingRule>
     */
    #[Computed]
    public function rules(): Collection
    {
        return PricingRule::query()
            ->whereIn('rate_plan_id', $this->plans->modelKeys())
            ->with(['ratePlan', 'departure'])
            ->orderBy('rate_plan_id')
            ->orderBy('priority')
            ->orderBy('name')
            ->get();
    }

    /** Open the dialog for a new rule, optionally under a chosen plan. */
    public function openCreate(?int $planId = null): void
    {
        Gate::authorize('create', PricingRule::class);
        $this->editingId = null;
        $this->form->start($planId ?? $this->plans->first()?->getKey());
        $this->open();
    }

    /** Open the dialog for an existing rule. */
    public function openEdit(int $ruleId): void
    {
        $rule = $this->find($ruleId);
        Gate::authorize('update', $rule);
        $this->editingId = $rule->getKey();
        $this->form->fillFromRule($rule);
        $this->open();
    }

    /** Save the rule through the service. */
    public function save(PricingRuleService $service): void
    {
        $this->form->validate();

        try {
            $plan = TourRatePlan::query()->where('tour_id', $this->tourId)->findOrFail((int) $this->form->ratePlanId);
            $data = $this->form->toData();
            if ($this->editingId === null) {
                Gate::authorize('create', PricingRule::class);
                $service->create($plan, $data);
            } else {
                $rule = $this->find($this->editingId);
                Gate::authorize('update', $rule);
                if ($rule->rate_plan_id !== $plan->getKey()) {
                    throw new InvalidRateConfiguration('A rule stays on the plan it was created for.');
                }
                $service->update($rule, $data);
            }
        } catch (InvalidRateConfiguration|InvalidArgumentException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        session()->flash('success', $this->editingId === null ? 'Pricing rule created.' : 'Pricing rule updated.');
        $this->closeDialog();
    }

    /** Switch a rule on or off. */
    public function toggleActive(int $ruleId, PricingRuleService $service): void
    {
        $rule = $this->find($ruleId);
        Gate::authorize('update', $rule);
        $service->setActive($rule, ! $rule->is_active);
        unset($this->rules);
        session()->flash('success', $rule->is_active ? 'Pricing rule deactivated.' : 'Pricing rule activated.');
    }

    /** Close the dialog and forget its state. */
    public function closeDialog(): void
    {
        $this->dialog = '';
        $this->editingId = null;
        $this->resetErrorBag();
        unset($this->rules);
    }

    /** Render the workspace. */
    public function render(): View
    {
        return view('travel-tours::livewire.admin.pricing.pricing-rule-manager');
    }

    /** Resolve a rule that belongs to one of the tour's plans. */
    private function find(int $ruleId): PricingRule
    {
        return PricingRule::query()->whereIn('rate_plan_id', $this->plans->modelKeys())->findOrFail($ruleId);
    }

    /** Open the form dialog with a clean error bag. */
    private function open(): void
    {
        $this->dialog = 'form';
        $this->resetErrorBag();
    }
}
