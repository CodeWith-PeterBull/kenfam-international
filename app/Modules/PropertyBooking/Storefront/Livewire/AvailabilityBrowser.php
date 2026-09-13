<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Storefront\Livewire;

use App\Modules\PropertyBooking\Catalog\Models\PropertyCategory;
use App\Modules\PropertyBooking\Storefront\Exceptions\StorefrontException;
use App\Modules\PropertyBooking\Storefront\Livewire\Forms\StaySearchForm;
use App\Modules\PropertyBooking\Storefront\Services\StayDiscoveryService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/** Public availability search and property-result workspace. */
final class AvailabilityBrowser extends Component
{
    public StaySearchForm $form;

    public int $revision = 0;

    /** Initialize safe defaults and bounded URL-provided discovery values. */
    public function mount(): void
    {
        $this->form->fillDefaults();
        $this->form->arrivalDate = $this->queryDate('arrival', $this->form->arrivalDate);
        $this->form->departureDate = $this->queryDate('departure', $this->form->departureDate);
        $this->form->arrivalTime = $this->queryTime('arrival_time', $this->form->arrivalTime);
        $this->form->departureTime = $this->queryTime('departure_time', $this->form->departureTime);
        $this->form->category = $this->query('category', '');
        $this->form->location = $this->query('location', '');
        $this->form->adults = $this->queryInteger('adults', 1, 1, 20);
        $this->form->children = $this->queryInteger('children', 0, 0, 20);
        $this->form->infants = $this->queryInteger('infants', 0, 0, 10);
    }

    /** Validate and refresh current availability results. */
    public function search(): void
    {
        $this->form->validate();
        $this->revision++;
        unset($this->results);
    }

    /** Reset filters while preserving useful default stay dates. */
    public function clearFilters(): void
    {
        $this->form->reset();
        $this->form->fillDefaults();
        $this->revision++;
        $this->resetValidation();
        unset($this->results, $this->categories);
    }

    /** Return active categories used by the public filter. */
    #[Computed]
    public function categories(): Collection
    {
        return PropertyCategory::query()->active()->whereHas('properties', static fn ($query) => $query->published())->orderBy('sort_order')->orderBy('name')->get();
    }

    /** Return true current availability results for the validated/default form. */
    #[Computed]
    public function results(): Collection
    {
        try {
            return app(StayDiscoveryService::class)->search($this->form->toData());
        } catch (StorefrontException $exception) {
            $this->addError('availability', $exception->getMessage());

            return collect();
        }
    }

    /** Build bounded query parameters for property/detail links. */
    public function queryParameters(): array
    {
        return array_filter([
            'arrival' => $this->form->arrivalDate,
            'departure' => $this->form->departureDate,
            'arrival_time' => $this->form->arrivalTime,
            'departure_time' => $this->form->departureTime,
            'adults' => $this->form->adults,
            'children' => $this->form->children,
            'infants' => $this->form->infants,
            'category' => trim($this->form->category) ?: null,
            'location' => trim($this->form->location) ?: null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /** Render the module-owned public availability workspace. */
    public function render(): View
    {
        return view('property-booking::livewire.storefront.availability-browser', [
            'results' => $this->results,
        ]);
    }

    /** Read a short scalar query value. */
    private function query(string $key, string $default): string
    {
        $value = request()->query($key);

        return is_string($value) && strlen($value) <= 180 ? trim($value) : $default;
    }

    /** Read one strict ISO date query value. */
    private function queryDate(string $key, string $default): string
    {
        $value = $this->query($key, '');
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date instanceof \DateTimeImmutable && $date->format('Y-m-d') === $value ? $value : $default;
    }

    /** Read one strict 24-hour time query value. */
    private function queryTime(string $key, string $default): string
    {
        $value = $this->query($key, '');
        $time = \DateTimeImmutable::createFromFormat('!H:i', $value);

        return $time instanceof \DateTimeImmutable && $time->format('H:i') === $value ? $value : $default;
    }

    /** Read a bounded integer query value. */
    private function queryInteger(string $key, int $default, int $minimum, int $maximum): int
    {
        $value = filter_var(request()->query($key), FILTER_VALIDATE_INT);

        return $value !== false && $value >= $minimum && $value <= $maximum ? $value : $default;
    }
}
