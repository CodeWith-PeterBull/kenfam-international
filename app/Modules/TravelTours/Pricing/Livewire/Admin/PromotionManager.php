<?php

/** Promotion code workspace across every tour. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Pricing\Livewire\Admin;

use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Pricing\Exceptions\InvalidRateConfiguration;
use App\Modules\TravelTours\Pricing\Livewire\Forms\PromotionForm;
use App\Modules\TravelTours\Pricing\Models\Promotion;
use App\Modules\TravelTours\Pricing\Services\PromotionService;
use App\Modules\TravelTours\Support\LikePattern;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Promotions are defined once, scoped to tours, and switched off rather than deleted once used. */
final class PromotionManager extends Component
{
    use WithPagination;

    private const PER_PAGE = 15;

    public PromotionForm $form;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Locked]
    public ?int $editingId = null;

    public string $dialog = '';

    /** Require pricing visibility on each Livewire request. */
    public function boot(): void
    {
        Gate::authorize('viewAny', Promotion::class);
    }

    /** Clear pagination when the search changes. */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Promotions matching the search with their usage counts.
     *
     * @return LengthAwarePaginator<int, Promotion>
     */
    #[Computed]
    public function promotions(): LengthAwarePaginator
    {
        $term = trim($this->search);

        return Promotion::query()
            ->withCount(['redemptions as active_redemptions_count' => fn ($query) => $query->whereNull('released_at'), 'tours'])
            ->when($term !== '', function ($query) use ($term): void {
                $like = LikePattern::contains($term);
                $query->where(fn ($query) => $query->whereRaw('code '.LikePattern::CLAUSE, [$like])->orWhereRaw('name '.LikePattern::CLAUSE, [$like]));
            })
            ->orderByDesc('is_active')
            ->orderBy('code')
            ->paginate(self::PER_PAGE);
    }

    /**
     * Tours available for scoping, by name.
     *
     * @return Collection<int, Tour>
     */
    #[Computed]
    public function tours(): Collection
    {
        return Tour::query()->orderBy('name')->get(['id', 'name', 'code']);
    }

    /** Open the dialog for a new promotion. */
    public function openCreate(): void
    {
        Gate::authorize('create', Promotion::class);
        $this->editingId = null;
        $this->form->start((string) config('travel-tours.defaults.currency', 'KES'));
        $this->open();
    }

    /** Open the dialog for an existing promotion. */
    public function openEdit(int $promotionId): void
    {
        $promotion = Promotion::query()->with('tours')->findOrFail($promotionId);
        Gate::authorize('update', $promotion);
        $this->editingId = $promotion->getKey();
        $this->form->fillFromPromotion($promotion);
        $this->open();
    }

    /** Save the promotion through the service. */
    public function save(PromotionService $service): void
    {
        $this->form->validate();

        try {
            $data = $this->form->toData();
            if ($this->editingId === null) {
                Gate::authorize('create', Promotion::class);
                $service->create($data);
            } else {
                $promotion = Promotion::query()->findOrFail($this->editingId);
                Gate::authorize('update', $promotion);
                $service->update($promotion, $data);
            }
        } catch (InvalidRateConfiguration|InvalidArgumentException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        session()->flash('success', $this->editingId === null ? 'Promotion created.' : 'Promotion updated.');
        $this->closeDialog();
    }

    /** Switch a promotion on or off. */
    public function toggleActive(int $promotionId, PromotionService $service): void
    {
        $promotion = Promotion::query()->findOrFail($promotionId);
        Gate::authorize('update', $promotion);
        $service->setActive($promotion, ! $promotion->is_active);
        unset($this->promotions);
        session()->flash('success', $promotion->is_active ? 'Promotion deactivated.' : 'Promotion activated.');
    }

    /** Close the dialog and forget its state. */
    public function closeDialog(): void
    {
        $this->dialog = '';
        $this->editingId = null;
        $this->resetErrorBag();
        unset($this->promotions);
    }

    /** Render the workspace. */
    public function render(): View
    {
        return view('travel-tours::livewire.admin.pricing.promotion-manager');
    }

    /** Open the form dialog with a clean error bag. */
    private function open(): void
    {
        $this->dialog = 'form';
        $this->resetErrorBag();
    }
}
