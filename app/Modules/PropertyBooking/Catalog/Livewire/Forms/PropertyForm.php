<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Catalog\Livewire\Forms;

use App\Modules\PropertyBooking\Catalog\Models\Property;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Form;

/** Validates and normalizes complete accommodation-property administration input. */
final class PropertyForm extends Form
{
    public ?Property $property = null;

    public string $categoryId = '';

    public string $code = '';

    public string $slug = '';

    public string $name = '';

    public string $shortDescription = '';

    public string $description = '';

    public string $houseRules = '';

    public string $cancellationSummary = '';

    public string $email = '';

    public string $phone = '';

    public string $whatsappPhone = '';

    public string $websiteUrl = '';

    public string $addressLine1 = '';

    public string $addressLine2 = '';

    public string $city = '';

    public string $region = '';

    public string $postalCode = '';

    public string $countryCode = 'KE';

    public string $latitude = '';

    public string $longitude = '';

    public string $timezone = 'Africa/Nairobi';

    public string $currency = 'KES';

    public string $checkInFrom = '';

    public string $checkInUntil = '';

    public string $checkOutFrom = '';

    public string $checkOutUntil = '';

    public int $minimumNoticeMinutes = 0;

    public string $maximumAdvanceDays = '';

    public int $turnoverMinutes = 60;

    public bool $isFeatured = false;

    public string $metaTitle = '';

    public string $metaDescription = '';

    /** @var list<int|string> */
    public array $amenityIds = [];

    /** @var list<int|string> */
    public array $assignedUserIds = [];

    public string $defaultUserId = '';

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'categoryId' => ['nullable', 'integer', Rule::exists('property_booking_categories', 'id')],
            'code' => ['required', 'string', 'max:40', Rule::unique('property_booking_properties', 'code')->ignore($this->property)],
            'slug' => ['nullable', 'string', 'max:180', Rule::unique('property_booking_properties', 'slug')->ignore($this->property)],
            'name' => ['required', 'string', 'max:180'],
            'shortDescription' => ['nullable', 'string', 'max:320'],
            'description' => ['nullable', 'string', 'max:50000'],
            'houseRules' => ['nullable', 'string', 'max:50000'],
            'cancellationSummary' => ['nullable', 'string', 'max:10000'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'whatsappPhone' => ['nullable', 'string', 'max:40'],
            'websiteUrl' => ['nullable', 'url:http,https', 'max:2048'],
            'addressLine1' => ['nullable', 'string', 'max:180'],
            'addressLine2' => ['nullable', 'string', 'max:180'],
            'city' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'postalCode' => ['nullable', 'string', 'max:30'],
            'countryCode' => ['required', 'alpha', 'size:2'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'decimal:0,7'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'decimal:0,7'],
            'timezone' => ['required', 'timezone:all', 'max:64'],
            'currency' => ['required', 'alpha', 'size:3'],
            'checkInFrom' => ['nullable', 'date_format:H:i', 'required_with:checkInUntil'],
            'checkInUntil' => ['nullable', 'date_format:H:i', 'required_with:checkInFrom', 'after:checkInFrom'],
            'checkOutFrom' => ['nullable', 'date_format:H:i', 'required_with:checkOutUntil'],
            'checkOutUntil' => ['nullable', 'date_format:H:i', 'required_with:checkOutFrom', 'after:checkOutFrom'],
            'minimumNoticeMinutes' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'maximumAdvanceDays' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'turnoverMinutes' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'isFeatured' => ['boolean'],
            'metaTitle' => ['nullable', 'string', 'max:160'],
            'metaDescription' => ['nullable', 'string', 'max:320'],
            'amenityIds' => ['array'],
            'amenityIds.*' => ['integer', 'distinct', Rule::exists('property_booking_amenities', 'id')],
            'assignedUserIds' => ['array'],
            'assignedUserIds.*' => ['integer', 'distinct', Rule::exists('users', 'id')],
            'defaultUserId' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'categoryId' => 'category', 'shortDescription' => 'short description',
            'houseRules' => 'house rules', 'cancellationSummary' => 'cancellation summary',
            'whatsappPhone' => 'WhatsApp phone', 'websiteUrl' => 'website URL',
            'addressLine1' => 'address line 1', 'addressLine2' => 'address line 2',
            'postalCode' => 'postal code', 'countryCode' => 'country code',
            'checkInFrom' => 'check-in start', 'checkInUntil' => 'check-in end',
            'checkOutFrom' => 'check-out start', 'checkOutUntil' => 'check-out end',
            'minimumNoticeMinutes' => 'minimum notice', 'maximumAdvanceDays' => 'maximum advance days',
            'turnoverMinutes' => 'turnover buffer', 'metaTitle' => 'meta title',
            'metaDescription' => 'meta description', 'amenityIds' => 'amenities',
            'assignedUserIds' => 'assigned users', 'defaultUserId' => 'default user',
        ];
    }

    /** Populate every editable property field and association selector. */
    public function fillFromProperty(Property $property): void
    {
        $property->loadMissing(['amenities', 'assignedUsers']);
        $this->property = $property;
        $this->categoryId = $property->category_id === null ? '' : (string) $property->category_id;
        $this->code = $property->code;
        $this->slug = $property->slug;
        $this->name = $property->name;
        $this->shortDescription = (string) $property->short_description;
        $this->description = (string) $property->description;
        $this->houseRules = (string) $property->house_rules;
        $this->cancellationSummary = (string) $property->cancellation_summary;
        $this->email = (string) $property->email;
        $this->phone = (string) $property->phone;
        $this->whatsappPhone = (string) $property->whatsapp_phone;
        $this->websiteUrl = (string) $property->website_url;
        $this->addressLine1 = (string) $property->address_line_1;
        $this->addressLine2 = (string) $property->address_line_2;
        $this->city = (string) $property->city;
        $this->region = (string) $property->region;
        $this->postalCode = (string) $property->postal_code;
        $this->countryCode = $property->country_code;
        $this->latitude = (string) $property->latitude;
        $this->longitude = (string) $property->longitude;
        $this->timezone = $property->timezone;
        $this->currency = $property->currency;
        $this->checkInFrom = self::shortTime($property->check_in_from);
        $this->checkInUntil = self::shortTime($property->check_in_until);
        $this->checkOutFrom = self::shortTime($property->check_out_from);
        $this->checkOutUntil = self::shortTime($property->check_out_until);
        $this->minimumNoticeMinutes = $property->minimum_notice_minutes;
        $this->maximumAdvanceDays = self::nullableIntegerForInput($property->maximum_advance_days);
        $this->turnoverMinutes = $property->turnover_minutes;
        $this->isFeatured = $property->is_featured;
        $this->metaTitle = (string) $property->meta_title;
        $this->metaDescription = (string) $property->meta_description;
        $this->amenityIds = $property->amenities->pluck('id')->all();
        $this->assignedUserIds = $property->assignedUsers->pluck('id')->all();
        $default = $property->assignedUsers->first(static fn ($user): bool => (bool) $user->pivot->is_default);
        $this->defaultUserId = $default === null ? '' : (string) $default->getKey();
    }

    /** Reset to configuration-aware property creation defaults. */
    public function resetForCreate(): void
    {
        $this->reset();
        $this->property = null;
        $this->countryCode = strtoupper((string) config('property-booking.defaults.country_code', 'KE'));
        $this->currency = strtoupper((string) config('property-booking.defaults.currency', 'KES'));
        $this->timezone = (string) config('property-booking.defaults.timezone', 'Africa/Nairobi');
        $this->turnoverMinutes = max(0, (int) config('property-booking.defaults.turnover_minutes', 60));
        $this->minimumNoticeMinutes = 0;
        $this->isFeatured = false;
        $this->amenityIds = [];
        $this->assignedUserIds = [];
        $this->resetValidation();
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'category_id' => $this->categoryId === '' ? null : (int) $this->categoryId,
            'code' => strtoupper(trim($this->code)),
            'slug' => Str::slug($this->slug !== '' ? $this->slug : $this->name),
            'name' => trim($this->name),
            'short_description' => self::nullableTrimmed($this->shortDescription),
            'description' => self::nullableTrimmed($this->description),
            'house_rules' => self::nullableTrimmed($this->houseRules),
            'cancellation_summary' => self::nullableTrimmed($this->cancellationSummary),
            'email' => self::nullableTrimmed($this->email),
            'phone' => self::nullableTrimmed($this->phone),
            'whatsapp_phone' => self::nullableTrimmed($this->whatsappPhone),
            'website_url' => self::nullableTrimmed($this->websiteUrl),
            'address_line_1' => self::nullableTrimmed($this->addressLine1),
            'address_line_2' => self::nullableTrimmed($this->addressLine2),
            'city' => self::nullableTrimmed($this->city),
            'region' => self::nullableTrimmed($this->region),
            'postal_code' => self::nullableTrimmed($this->postalCode),
            'country_code' => strtoupper(trim($this->countryCode)),
            'latitude' => self::nullableTrimmed($this->latitude),
            'longitude' => self::nullableTrimmed($this->longitude),
            'timezone' => trim($this->timezone),
            'currency' => strtoupper(trim($this->currency)),
            'check_in_from' => self::nullableTrimmed($this->checkInFrom),
            'check_in_until' => self::nullableTrimmed($this->checkInUntil),
            'check_out_from' => self::nullableTrimmed($this->checkOutFrom),
            'check_out_until' => self::nullableTrimmed($this->checkOutUntil),
            'minimum_notice_minutes' => $this->minimumNoticeMinutes,
            'maximum_advance_days' => self::nullableInteger($this->maximumAdvanceDays),
            'turnover_minutes' => $this->turnoverMinutes,
            'is_featured' => $this->isFeatured,
            'meta_title' => self::nullableTrimmed($this->metaTitle),
            'meta_description' => self::nullableTrimmed($this->metaDescription),
        ];
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

    /** Render database time values as browser time-control values. */
    private static function shortTime(?string $value): string
    {
        return $value === null ? '' : substr($value, 0, 5);
    }
}
