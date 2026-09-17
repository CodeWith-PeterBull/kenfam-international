<?php

/** Exposes tour readiness, preview, and editorial state transitions. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Livewire\Admin;

use App\Models\User;
use App\Modules\TravelTours\Catalog\Exceptions\PublicationBlocked;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Catalog\Services\TourPublicationService;
use App\Modules\TravelTours\Catalog\Services\TourReadinessService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Require a real operator action for each publication transition. */
final class TourPublicationEditor extends Component
{
    #[Locked]
    public int $tourId;

    public string $publishAt = '';

    /** Reauthorize each Livewire request. */
    public function boot(): void
    {
        Gate::authorize('viewAny', Tour::class);
    }

    /** Open an existing tour. */
    public function mount(int $tourId): void
    {
        $this->tourId = $tourId;
        $this->tour();
    }

    /** Send a draft to the review queue. */
    public function submitForReview(TourPublicationService $publication): void
    {
        $this->run(fn () => $publication->submitForReview($this->tour(), $this->actor()), 'Tour submitted for review.');
    }

    /** Publish now or schedule a future local time. */
    public function publish(TourPublicationService $publication): void
    {
        $this->validate(['publishAt' => ['nullable', 'date_format:Y-m-d\TH:i']]);
        $at = $this->publishAt === '' ? null : CarbonImmutable::parse($this->publishAt, (string) config('travel-tours.defaults.timezone', 'Africa/Nairobi'))->utc();
        if ($at !== null && ! $at->isFuture()) {
            throw ValidationException::withMessages(['publishAt' => 'Choose a future time in the configured travel timezone.']);
        }
        $this->run(fn () => $publication->publish($this->tour(), $this->actor(), $at), $at ? 'Tour publication scheduled.' : 'Tour published.');
    }

    /** Remove a tour from the public catalog. */
    public function unpublish(TourPublicationService $publication): void
    {
        $this->run(fn () => $publication->unpublish($this->tour(), $this->actor()), 'Tour unpublished.');
    }

    /** Retain the record as an archived catalog item. */
    public function archive(TourPublicationService $publication): void
    {
        $this->run(fn () => $publication->archive($this->tour(), $this->actor()), 'Tour archived.');
    }

    /** Bring an archived tour back as a draft. */
    public function restoreDraft(TourPublicationService $publication): void
    {
        $this->run(fn () => $publication->restoreDraft($this->tour(), $this->actor()), 'Tour restored as a draft.');
    }

    /** Render readiness as the same reasons used by publication writes. */
    public function render(TourReadinessService $readiness): View
    {
        $tour = $this->tour();

        return view('travel-tours::livewire.admin.catalog.tour-publication-editor', [
            'tour' => $tour, 'reasons' => $readiness->reasons($tour),
        ]);
    }

    /** Resolve the tour and authorize a reader before rendering. */
    private function tour(): Tour
    {
        $tour = Tour::query()->findOrFail($this->tourId);
        Gate::authorize('view', $tour);

        return $tour;
    }

    /** Resolve the authenticated operator for audit attribution. */
    private function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    /** Convert one expected blocker into readable feedback. */
    private function run(callable $action, string $message): void
    {
        try {
            $action();
        } catch (PublicationBlocked $exception) {
            $this->addError('publication', $exception->getMessage());

            return;
        }
        $this->resetValidation();
        session()->flash('success', $message);
    }
}
