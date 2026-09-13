<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Pricing\Livewire\Forms;

use App\Modules\PropertyBooking\Pricing\Models\RateOverride;
use App\Modules\PropertyBooking\Support\ScaledDecimal;
use Livewire\Form;

/** Validates bounded local-date rate and restriction overrides. */
final class RateOverrideForm extends Form
{
    public ?RateOverride $override = null;

    public string $startsOn = '';

    public string $endsOn = '';

    public string $rate = '';

    public bool $isClosed = false;

    public bool $closedOnArrival = false;

    public bool $closedOnDeparture = false;

    public string $minimumUnits = '';

    public string $maximumUnits = '';

    public string $reason = '';

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        $decimals = self::currencyDecimals();

        return [
            'startsOn' => ['required', 'date_format:Y-m-d'],
            'endsOn' => ['required', 'date_format:Y-m-d', 'after:startsOn'],
            'rate' => ['nullable', 'decimal:0,'.$decimals, 'gt:0'],
            'isClosed' => ['boolean'],
            'closedOnArrival' => ['boolean'],
            'closedOnDeparture' => ['boolean'],
            'minimumUnits' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'maximumUnits' => ['nullable', 'integer', 'min:1', 'gte:minimumUnits', 'max:65535'],
            'reason' => ['nullable', 'string', 'max:180'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'startsOn' => 'start date', 'endsOn' => 'end date', 'isClosed' => 'closed setting',
            'closedOnArrival' => 'closed on arrival', 'closedOnDeparture' => 'closed on departure',
            'minimumUnits' => 'minimum duration', 'maximumUnits' => 'maximum duration',
        ];
    }

    /** Populate every editable override field. */
    public function fillFromOverride(RateOverride $override): void
    {
        $this->override = $override;
        $this->startsOn = $override->starts_on->toDateString();
        $this->endsOn = $override->ends_on->toDateString();
        $this->rate = ScaledDecimal::formatUnsigned($override->rate_minor, self::currencyDecimals());
        $this->isClosed = $override->is_closed;
        $this->closedOnArrival = $override->closed_on_arrival;
        $this->closedOnDeparture = $override->closed_on_departure;
        $this->minimumUnits = self::nullableIntegerForInput($override->minimum_units);
        $this->maximumUnits = self::nullableIntegerForInput($override->maximum_units);
        $this->reason = (string) $override->reason;
    }

    /** Reset to an empty date-override form. */
    public function resetForCreate(): void
    {
        $this->reset();
        $this->override = null;
        $this->resetValidation();
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'starts_on' => $this->startsOn,
            'ends_on' => $this->endsOn,
            'rate_minor' => $this->rate === '' ? null : ScaledDecimal::parseUnsigned($this->rate, self::currencyDecimals()),
            'is_closed' => $this->isClosed,
            'closed_on_arrival' => $this->closedOnArrival,
            'closed_on_departure' => $this->closedOnDeparture,
            'minimum_units' => self::nullableInteger($this->minimumUnits),
            'maximum_units' => self::nullableInteger($this->maximumUnits),
            'reason' => self::nullableTrimmed($this->reason),
        ];
    }

    /** Resolve the bounded currency scale. */
    private static function currencyDecimals(): int
    {
        return max(0, min(6, (int) config('property-booking.defaults.currency_decimals', 2)));
    }

    /** Normalize optional text for persistence. */
    private static function nullableTrimmed(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /** Normalize nullable integer input. */
    private static function nullableInteger(string $value): ?int
    {
        return $value === '' ? null : (int) $value;
    }

    /** Render nullable integer values for text inputs. */
    private static function nullableIntegerForInput(?int $value): string
    {
        return $value === null ? '' : (string) $value;
    }
}
