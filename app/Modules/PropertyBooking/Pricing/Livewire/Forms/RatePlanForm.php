<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Pricing\Livewire\Forms;

use App\Modules\PropertyBooking\Pricing\Enums\DepositType;
use App\Modules\PropertyBooking\Pricing\Enums\StayPricingUnit;
use App\Modules\PropertyBooking\Pricing\Models\RatePlan;
use App\Modules\PropertyBooking\Support\ScaledDecimal;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Livewire\Form;

/** Validates and normalizes complete rate-plan administration input. */
final class RatePlanForm extends Form
{
    public ?RatePlan $ratePlan = null;

    public string $unitTypeId = '';

    public string $code = '';

    public string $name = '';

    public string $description = '';

    public string $pricingUnit = 'night';

    public string $currency = 'KES';

    public string $baseRate = '';

    public int $includedAdults = 1;

    public int $includedChildren = 0;

    public string $extraAdultRate = '0.00';

    public string $extraChildRate = '0.00';

    public int $minimumUnits = 1;

    public string $maximumUnits = '';

    public int $minimumAdvanceMinutes = 0;

    public string $maximumAdvanceDays = '';

    public string $taxRatePercent = '0.00';

    public bool $isTaxInclusive = true;

    public string $depositType = 'none';

    public string $depositAmount = '';

    public string $depositRatePercent = '';

    public bool $isRefundable = true;

    public string $freeCancelBeforeMinutes = '';

    public string $cancellationTerms = '';

    public bool $isPublic = true;

    public int $sortOrder = 0;

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        $decimals = self::currencyDecimals();
        $unitTypeId = (int) $this->unitTypeId;

        return [
            'unitTypeId' => ['required', 'integer', Rule::exists('property_booking_unit_types', 'id')],
            'code' => [
                'required', 'string', 'max:40',
                Rule::unique('property_booking_rate_plans', 'code')
                    ->where(static fn (Builder $query): Builder => $query->where('unit_type_id', $unitTypeId))
                    ->ignore($this->ratePlan),
            ],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:10000'],
            'pricingUnit' => ['required', Rule::enum(StayPricingUnit::class)],
            'currency' => ['required', 'alpha', 'size:3'],
            'baseRate' => ['required', 'decimal:0,'.$decimals, 'gt:0'],
            'includedAdults' => ['required', 'integer', 'min:0', 'max:65535'],
            'includedChildren' => ['required', 'integer', 'min:0', 'max:65535'],
            'extraAdultRate' => ['required', 'decimal:0,'.$decimals, 'min:0'],
            'extraChildRate' => ['required', 'decimal:0,'.$decimals, 'min:0'],
            'minimumUnits' => ['required', 'integer', 'min:1', 'max:65535'],
            'maximumUnits' => ['nullable', 'integer', 'gte:minimumUnits', 'max:65535'],
            'minimumAdvanceMinutes' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'maximumAdvanceDays' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'taxRatePercent' => ['required', 'decimal:0,2', 'between:0,100'],
            'isTaxInclusive' => ['boolean'],
            'depositType' => ['required', Rule::enum(DepositType::class)],
            'depositAmount' => [Rule::requiredIf($this->depositType === DepositType::Fixed->value), 'nullable', 'decimal:0,'.$decimals, 'gt:0'],
            'depositRatePercent' => [Rule::requiredIf($this->depositType === DepositType::Percentage->value), 'nullable', 'decimal:0,2', 'gt:0', 'max:100'],
            'isRefundable' => ['boolean'],
            'freeCancelBeforeMinutes' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'cancellationTerms' => ['nullable', 'string', 'max:10000'],
            'isPublic' => ['boolean'],
            'sortOrder' => ['required', 'integer', 'min:0', 'max:4294967295'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'unitTypeId' => 'unit type', 'pricingUnit' => 'pricing unit',
            'baseRate' => 'base rate', 'includedAdults' => 'included adults',
            'includedChildren' => 'included children', 'extraAdultRate' => 'extra-adult rate',
            'extraChildRate' => 'extra-child rate', 'minimumUnits' => 'minimum duration',
            'maximumUnits' => 'maximum duration', 'minimumAdvanceMinutes' => 'minimum advance notice',
            'maximumAdvanceDays' => 'maximum advance days', 'taxRatePercent' => 'tax rate',
            'isTaxInclusive' => 'tax inclusive setting', 'depositType' => 'deposit type',
            'depositAmount' => 'fixed deposit', 'depositRatePercent' => 'deposit percentage',
            'isRefundable' => 'refundable setting', 'freeCancelBeforeMinutes' => 'free-cancellation cutoff',
            'cancellationTerms' => 'cancellation terms', 'isPublic' => 'public visibility',
            'sortOrder' => 'sort order',
        ];
    }

    /** Populate every editable rate-plan field. */
    public function fillFromRatePlan(RatePlan $ratePlan): void
    {
        $this->ratePlan = $ratePlan;
        $this->unitTypeId = (string) $ratePlan->unit_type_id;
        $this->code = $ratePlan->code;
        $this->name = $ratePlan->name;
        $this->description = (string) $ratePlan->description;
        $this->pricingUnit = $ratePlan->pricing_unit->value;
        $this->currency = $ratePlan->currency;
        $this->baseRate = ScaledDecimal::formatUnsigned($ratePlan->base_rate_minor, self::currencyDecimals());
        $this->includedAdults = $ratePlan->included_adults;
        $this->includedChildren = $ratePlan->included_children;
        $this->extraAdultRate = ScaledDecimal::formatUnsigned($ratePlan->extra_adult_minor, self::currencyDecimals());
        $this->extraChildRate = ScaledDecimal::formatUnsigned($ratePlan->extra_child_minor, self::currencyDecimals());
        $this->minimumUnits = $ratePlan->minimum_units;
        $this->maximumUnits = self::nullableIntegerForInput($ratePlan->maximum_units);
        $this->minimumAdvanceMinutes = $ratePlan->minimum_advance_minutes;
        $this->maximumAdvanceDays = self::nullableIntegerForInput($ratePlan->maximum_advance_days);
        $this->taxRatePercent = ScaledDecimal::formatUnsigned($ratePlan->tax_rate_bps, 2);
        $this->isTaxInclusive = $ratePlan->is_tax_inclusive;
        $this->depositType = $ratePlan->deposit_type->value;
        $this->depositAmount = ScaledDecimal::formatUnsigned($ratePlan->deposit_amount_minor, self::currencyDecimals());
        $this->depositRatePercent = ScaledDecimal::formatUnsigned($ratePlan->deposit_rate_bps, 2);
        $this->isRefundable = $ratePlan->is_refundable;
        $this->freeCancelBeforeMinutes = self::nullableIntegerForInput($ratePlan->free_cancel_before_minutes);
        $this->cancellationTerms = (string) $ratePlan->cancellation_terms;
        $this->isPublic = $ratePlan->is_public;
        $this->sortOrder = $ratePlan->sort_order;
    }

    /** Reset to configuration-aware rate creation defaults. */
    public function resetForCreate(?int $unitTypeId = null, ?string $currency = null): void
    {
        $this->reset();
        $this->ratePlan = null;
        $this->unitTypeId = $unitTypeId === null ? '' : (string) $unitTypeId;
        $this->pricingUnit = StayPricingUnit::Night->value;
        $this->currency = strtoupper($currency ?? (string) config('property-booking.defaults.currency', 'KES'));
        $this->includedAdults = 1;
        $this->extraAdultRate = ScaledDecimal::formatUnsigned(0, self::currencyDecimals());
        $this->extraChildRate = ScaledDecimal::formatUnsigned(0, self::currencyDecimals());
        $this->minimumUnits = 1;
        $this->taxRatePercent = ScaledDecimal::formatUnsigned((int) config('property-booking.defaults.tax_rate_bps', 0), 2);
        $this->isTaxInclusive = (bool) config('property-booking.defaults.prices_include_tax', true);
        $this->depositType = DepositType::None->value;
        $this->isRefundable = true;
        $this->isPublic = true;
        $this->sortOrder = 0;
        $this->resetValidation();
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        $decimals = self::currencyDecimals();
        $depositType = DepositType::from($this->depositType);

        return [
            'code' => strtoupper(trim($this->code)),
            'name' => trim($this->name),
            'description' => self::nullableTrimmed($this->description),
            'pricing_unit' => $this->pricingUnit,
            'currency' => strtoupper(trim($this->currency)),
            'base_rate_minor' => ScaledDecimal::parseUnsigned($this->baseRate, $decimals),
            'included_adults' => $this->includedAdults,
            'included_children' => $this->includedChildren,
            'extra_adult_minor' => ScaledDecimal::parseUnsigned($this->extraAdultRate, $decimals),
            'extra_child_minor' => ScaledDecimal::parseUnsigned($this->extraChildRate, $decimals),
            'minimum_units' => $this->minimumUnits,
            'maximum_units' => self::nullableInteger($this->maximumUnits),
            'minimum_advance_minutes' => $this->minimumAdvanceMinutes,
            'maximum_advance_days' => self::nullableInteger($this->maximumAdvanceDays),
            'tax_rate_bps' => ScaledDecimal::parseUnsigned($this->taxRatePercent, 2),
            'is_tax_inclusive' => $this->isTaxInclusive,
            'deposit_type' => $depositType->value,
            'deposit_amount_minor' => $depositType === DepositType::Fixed
                ? ScaledDecimal::parseUnsigned($this->depositAmount, $decimals)
                : null,
            'deposit_rate_bps' => $depositType === DepositType::Percentage
                ? ScaledDecimal::parseUnsigned($this->depositRatePercent, 2)
                : null,
            'is_refundable' => $this->isRefundable,
            'free_cancel_before_minutes' => self::nullableInteger($this->freeCancelBeforeMinutes),
            'cancellation_terms' => self::nullableTrimmed($this->cancellationTerms),
            'is_public' => $this->isPublic,
            'sort_order' => $this->sortOrder,
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
