<?php

/** Public departure availability and live quoting for one published tour. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Storefront\Livewire;

use App\Modules\TravelTours\Bookings\Enums\ParticipantType;
use App\Modules\TravelTours\Bookings\Exceptions\AvailabilityException;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Contracts\CalculatesTourQuotes;
use App\Modules\TravelTours\Contracts\ChecksDepartureAvailability;
use App\Modules\TravelTours\Pricing\Data\TourQuote;
use App\Modules\TravelTours\Pricing\Exceptions\InvalidRateConfiguration;
use App\Modules\TravelTours\Pricing\Exceptions\PromotionNotApplicable;
use App\Modules\TravelTours\Pricing\Models\ParticipantRate;
use App\Modules\TravelTours\Pricing\Models\TourRatePlan;
use App\Modules\TravelTours\Scheduling\Data\DepartureAvailability;
use App\Modules\TravelTours\Scheduling\Models\TourDeparture;
use App\Modules\TravelTours\Storefront\Livewire\Forms\SelectionForm;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Let a visitor choose a bookable departure and participant mix and see the
 * authoritative server quote for it. Every figure shown here is recomputed
 * from persisted pricing on each request; nothing priced is trusted from the
 * browser. Holds and checkout build on this selection in the next milestone.
 */
final class DepartureSelector extends Component
{
    /** Upper bound on listed departures, matching the previous static list. */
    private const DEPARTURE_LIMIT = 12;

    public SelectionForm $form;

    #[Locked]
    public int $tourId;

    public ?int $departureId = null;

    /** Explanation for a selection that cannot be quoted; set while computing the quote. */
    private ?string $quoteFailure = null;

    /** Bind the selector to its tour and preselect the first departure with seats. */
    public function mount(Tour $tour): void
    {
        $this->tourId = $tour->getKey();
        $this->assertViewable();
        $first = $this->departures->first(
            fn (TourDeparture $departure): bool => $this->availability[$departure->getKey()]->availableSeats > 0,
        );
        $this->departureId = $first?->getKey();
    }

    /** Re-check publication on every later request so an unpublished tour stops quoting. */
    public function hydrate(): void
    {
        $this->assertViewable();
    }

    /**
     * Bookable departures with their public rate plans, soonest first.
     *
     * @return Collection<int, TourDeparture>
     */
    #[Computed]
    public function departures(): Collection
    {
        return TourDeparture::query()
            ->where('tour_id', $this->tourId)
            ->bookable()
            ->with('ratePlan.participantRates')
            ->orderBy('starts_at')
            ->limit(self::DEPARTURE_LIMIT)
            ->get();
    }

    /**
     * Live seat evidence for each listed departure.
     *
     * @return array<int, DepartureAvailability>
     */
    #[Computed]
    public function availability(): array
    {
        $checker = app(ChecksDepartureAvailability::class);

        return $this->departures
            ->mapWithKeys(fn (TourDeparture $departure): array => [$departure->getKey() => $checker->check($departure)])
            ->all();
    }

    /** The departure currently chosen, or null when the choice is stale or missing. */
    #[Computed]
    public function selectedDeparture(): ?TourDeparture
    {
        return $this->departures->firstWhere('id', $this->departureId);
    }

    /** The tour's default public rate plan, used when a departure has no public plan of its own. */
    #[Computed]
    public function defaultRatePlan(): ?TourRatePlan
    {
        return TourRatePlan::query()
            ->where('tour_id', $this->tourId)
            ->publiclyAvailable()
            ->with('participantRates')
            ->orderByDesc('is_default')
            ->orderBy('display_order')
            ->orderBy('id')
            ->first();
    }

    /**
     * The authoritative quote for the current selection, or null with the
     * reason exposed through quoteFailure() and the error bag.
     */
    #[Computed]
    public function quote(): ?TourQuote
    {
        $this->quoteFailure = null;
        $departure = $this->selectedDeparture;
        if (! $departure instanceof TourDeparture) {
            return null;
        }

        $plan = $this->ratePlanFor($departure);
        if (! $plan instanceof TourRatePlan) {
            $this->quoteFailure = 'Pricing for this departure is available on request.';

            return null;
        }

        try {
            $this->form->validate();
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->validator->errors());

            return null;
        }

        $seats = $this->form->mix()->seats();
        $available = $this->availability[$departure->getKey()]->availableSeats;
        if ($available < $seats) {
            $this->quoteFailure = $available === 0
                ? 'This departure is fully booked.'
                : "Only {$available} ".($available === 1 ? 'place remains' : 'places remain').' on this departure.';

            return null;
        }

        try {
            return app(CalculatesTourQuotes::class)->calculate($this->form->toRequest($departure->getKey(), $plan->getKey()));
        } catch (PromotionNotApplicable $exception) {
            $this->addError('form.promotionCode', $exception->getMessage());
        } catch (InvalidRateConfiguration|AvailabilityException $exception) {
            $this->quoteFailure = $exception->getMessage();
        }

        return null;
    }

    /** Return why the current selection has no quote, after the quote has been attempted. */
    public function quoteFailure(): ?string
    {
        $this->quote;

        return $this->quoteFailure;
    }

    /** Resolve the public rate plan that prices a departure, preferring its own assignment. */
    public function ratePlanFor(TourDeparture $departure): ?TourRatePlan
    {
        $assigned = $departure->ratePlan;
        if ($assigned instanceof TourRatePlan && $assigned->is_active && $assigned->is_public) {
            return $assigned;
        }

        return $this->defaultRatePlan;
    }

    /** Return today's active adult fare for a plan, or null when none is configured. */
    public function fromPriceMinor(?TourRatePlan $plan): ?int
    {
        return $this->currentRate($plan, ParticipantType::Adult)?->amount_minor;
    }

    /** Describe the age band a participant type covers on the plan that prices the selection. */
    public function ageBand(ParticipantType $type): ?string
    {
        $departure = $this->selectedDeparture;
        $plan = $departure instanceof TourDeparture ? $this->ratePlanFor($departure) : $this->defaultRatePlan;
        $rate = $this->currentRate($plan, $type);
        if (! $rate instanceof ParticipantRate || ($rate->minimum_age === null && $rate->maximum_age === null)) {
            return null;
        }
        if ($rate->maximum_age === null) {
            return $rate->minimum_age.'+';
        }
        if ($rate->minimum_age === null || $rate->minimum_age === 0) {
            return 'under '.($rate->maximum_age + 1);
        }

        return $rate->minimum_age.'–'.$rate->maximum_age;
    }

    /**
     * Render the selector. The quote is computed here, before Livewire shares
     * the error bag with the view, so validation and promotion messages raised
     * while quoting are the ones the template displays.
     */
    public function render(): View
    {
        $this->quote;

        return view('travel-tours::livewire.storefront.departure-selector');
    }

    /** Refuse to serve a tour that is neither published nor viewable by the current operator. */
    private function assertViewable(): void
    {
        if (Tour::query()->published()->whereKey($this->tourId)->exists()) {
            return;
        }

        $tour = Tour::query()->find($this->tourId);
        abort_unless($tour instanceof Tour && auth()->check() && auth()->user()->can('view', $tour), 404);
    }

    /** Pick the single rate active today for a participant type, mirroring the calculator's date window. */
    private function currentRate(?TourRatePlan $plan, ParticipantType $type): ?ParticipantRate
    {
        if (! $plan instanceof TourRatePlan) {
            return null;
        }
        $today = Carbon::now('UTC')->toDateString();
        $matching = $plan->participantRates->filter(
            fn (ParticipantRate $rate): bool => $rate->participant_type === $type
                && $rate->is_active
                && ($rate->active_from === null || $rate->active_from->toDateString() <= $today)
                && ($rate->active_until === null || $rate->active_until->toDateString() >= $today),
        );

        return $matching->count() === 1 ? $matching->first() : null;
    }
}
