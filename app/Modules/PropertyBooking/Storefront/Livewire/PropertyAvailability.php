<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Storefront\Livewire;

use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Catalog\Models\UnitType;
use App\Modules\PropertyBooking\Storefront\Data\BookingSelectionData;
use App\Modules\PropertyBooking\Storefront\Data\PropertySearchResult;
use App\Modules\PropertyBooking\Storefront\Exceptions\StorefrontException;
use App\Modules\PropertyBooking\Storefront\Livewire\Forms\StaySearchForm;
use App\Modules\PropertyBooking\Storefront\Services\BookingSelectionSession;
use App\Modules\PropertyBooking\Storefront\Services\StayDiscoveryService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Availability and selection panel for one public property or unit type. */
final class PropertyAvailability extends Component
{
    #[Locked]
    public int $propertyId;

    #[Locked]
    public ?int $unitTypeId = null;

    public StaySearchForm $form;

    /** Initialize a scoped property context and current URL search values. */
    public function mount(int $propertyId, ?int $unitTypeId = null): void
    {
        $this->propertyId = $propertyId;
        $this->unitTypeId = $unitTypeId;
        $this->form->fillDefaults();
        $this->form->arrivalDate = $this->queryDate('arrival', $this->form->arrivalDate);
        $this->form->departureDate = $this->queryDate('departure', $this->form->departureDate);
        $this->form->arrivalTime = $this->queryTime('arrival_time', $this->form->arrivalTime);
        $this->form->departureTime = $this->queryTime('departure_time', $this->form->departureTime);
        $this->form->adults = $this->queryInteger('adults', 1, 1, 20);
        $this->form->children = $this->queryInteger('children', 0, 0, 20);
        $this->form->infants = $this->queryInteger('infants', 0, 0, 10);
    }

    /** Validate and refresh this property's current options. */
    public function search(): void
    {
        $this->form->validate();
        unset($this->availability);
    }

    /** Store one current rate selection and continue to guest review. */
    public function select(string $ratePlanUlid, BookingSelectionSession $selection): void
    {
        $this->form->validate();
        $option = collect($this->availability?->options ?? [])->first(
            static fn ($candidate): bool => hash_equals($candidate->ratePlan->ulid, $ratePlanUlid),
        );
        if ($option === null) {
            $this->addError('selection', 'That rate is no longer available for the selected stay.');

            return;
        }

        $selection->select(BookingSelectionData::fromQuote($option->quote));
        $this->redirectRoute('property-booking.storefront.selection.index');
    }

    /** Return a published property and its current available rate options. */
    #[Computed]
    public function availability(): ?PropertySearchResult
    {
        $property = Property::query()->published()->findOrFail($this->propertyId);
        $unitType = $this->unitTypeId === null
            ? null
            : UnitType::query()->published()->where('property_id', $property->getKey())->findOrFail($this->unitTypeId);

        try {
            return app(StayDiscoveryService::class)->result($property, $this->form->toData(), $unitType);
        } catch (StorefrontException $exception) {
            $this->addError('availability', $exception->getMessage());

            return null;
        }
    }

    /** Render the scoped availability and selection workspace. */
    public function render(): View
    {
        return view('property-booking::livewire.storefront.property-availability', [
            'availability' => $this->availability,
        ]);
    }

    /** Read one bounded scalar query value. */
    private function query(string $key, string $default): string
    {
        $value = request()->query($key);

        return is_string($value) && strlen($value) <= 20 ? trim($value) : $default;
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

    /** Read one bounded integer query value. */
    private function queryInteger(string $key, int $default, int $minimum, int $maximum): int
    {
        $value = filter_var(request()->query($key), FILTER_VALIDATE_INT);

        return $value !== false && $value >= $minimum && $value <= $maximum ? $value : $default;
    }
}
