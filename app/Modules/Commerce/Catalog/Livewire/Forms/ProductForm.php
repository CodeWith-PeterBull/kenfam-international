<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Catalog\Livewire\Forms;

use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Support\ScaledDecimal;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * Validates and normalizes the complete simple-product administration form.
 */
final class ProductForm extends Form
{
    public ?Product $product = null;

    public string $name = '';

    public string $slug = '';

    public string $sku = '';

    public string $barcode = '';

    public string $manufacturerBarcode = '';

    public string $categoryId = '';

    public string $shortDescription = '';

    public string $description = '';

    public string $specificationsJson = '';

    public string $unitLabel = 'item';

    public int $minimumOrderQuantity = 1;

    public string $maximumOrderQuantity = '';

    public string $price = '';

    public string $salePrice = '';

    public string $saleStartsAt = '';

    public string $saleEndsAt = '';

    public string $costPrice = '';

    public string $taxRatePercent = '16.00';

    public bool $isTaxInclusive = true;

    public bool $trackStock = true;

    public string $weightGrams = '';

    public string $lengthMm = '';

    public string $widthMm = '';

    public string $heightMm = '';

    public bool $isFeatured = false;

    public string $metaTitle = '';

    public string $metaDescription = '';

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        $decimals = self::currencyDecimals();
        $saleEndRules = ['nullable', 'date_format:Y-m-d\TH:i'];
        if ($this->saleStartsAt !== '') {
            $saleEndRules[] = 'after:saleStartsAt';
        }

        return [
            'name' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:200', Rule::unique('products', 'slug')->ignore($this->product)],
            'sku' => ['required', 'string', 'max:80', Rule::unique('products', 'sku')->ignore($this->product)],
            'barcode' => ['nullable', 'string', 'max:80', Rule::unique('products', 'barcode')->ignore($this->product)],
            'manufacturerBarcode' => ['nullable', 'string', 'max:80', Rule::unique('products', 'manufacturer_barcode')->ignore($this->product)],
            'categoryId' => ['nullable', 'integer', Rule::exists('product_categories', 'id')],
            'shortDescription' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string', 'max:50000'],
            'specificationsJson' => ['nullable', 'json'],
            'unitLabel' => ['required', 'string', 'max:30'],
            'minimumOrderQuantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'maximumOrderQuantity' => ['nullable', 'integer', 'gte:minimumOrderQuantity', 'max:1000000'],
            'price' => ['required', 'decimal:0,'.$decimals, 'gt:0'],
            'salePrice' => ['nullable', 'decimal:0,'.$decimals, 'gt:0', 'lt:price'],
            'saleStartsAt' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'saleEndsAt' => $saleEndRules,
            'costPrice' => ['nullable', 'decimal:0,'.$decimals, 'gte:0'],
            'taxRatePercent' => ['required', 'decimal:0,2', 'between:0,100'],
            'isTaxInclusive' => ['boolean'],
            'trackStock' => ['boolean'],
            'weightGrams' => ['nullable', 'integer', 'min:0'],
            'lengthMm' => ['nullable', 'integer', 'min:0'],
            'widthMm' => ['nullable', 'integer', 'min:0'],
            'heightMm' => ['nullable', 'integer', 'min:0'],
            'isFeatured' => ['boolean'],
            'metaTitle' => ['nullable', 'string', 'max:160'],
            'metaDescription' => ['nullable', 'string', 'max:320'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'categoryId' => 'category',
            'manufacturerBarcode' => 'manufacturer barcode',
            'shortDescription' => 'short description',
            'specificationsJson' => 'specifications',
            'unitLabel' => 'unit label',
            'minimumOrderQuantity' => 'minimum order quantity',
            'maximumOrderQuantity' => 'maximum order quantity',
            'salePrice' => 'sale price',
            'saleStartsAt' => 'sale start',
            'saleEndsAt' => 'sale end',
            'costPrice' => 'cost price',
            'taxRatePercent' => 'tax rate',
            'weightGrams' => 'weight',
            'lengthMm' => 'length',
            'widthMm' => 'width',
            'heightMm' => 'height',
            'metaTitle' => 'meta title',
            'metaDescription' => 'meta description',
        ];
    }

    /**
     * Populate editable values from an existing product.
     */
    public function fillFromProduct(Product $product): void
    {
        $this->product = $product;
        $this->name = $product->name;
        $this->slug = $product->slug;
        $this->sku = $product->sku;
        $this->barcode = (string) $product->barcode;
        $this->manufacturerBarcode = (string) $product->manufacturer_barcode;
        $this->categoryId = $product->category_id === null ? '' : (string) $product->category_id;
        $this->shortDescription = (string) $product->short_description;
        $this->description = (string) $product->description;
        $this->specificationsJson = $product->specifications === null
            ? ''
            : (string) json_encode($product->specifications, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $this->unitLabel = $product->unit_label;
        $this->minimumOrderQuantity = $product->minimum_order_quantity;
        $this->maximumOrderQuantity = $product->maximum_order_quantity === null ? '' : (string) $product->maximum_order_quantity;
        $this->price = ScaledDecimal::formatUnsigned($product->price_minor, self::currencyDecimals());
        $this->salePrice = ScaledDecimal::formatUnsigned($product->sale_price_minor, self::currencyDecimals());
        $this->saleStartsAt = $product->sale_starts_at?->format('Y-m-d\TH:i') ?? '';
        $this->saleEndsAt = $product->sale_ends_at?->format('Y-m-d\TH:i') ?? '';
        $this->costPrice = ScaledDecimal::formatUnsigned($product->cost_price_minor, self::currencyDecimals());
        $this->taxRatePercent = ScaledDecimal::formatUnsigned($product->tax_rate_bps, 2);
        $this->isTaxInclusive = $product->is_tax_inclusive;
        $this->trackStock = $product->track_stock;
        $this->weightGrams = self::nullableIntegerForInput($product->weight_grams);
        $this->lengthMm = self::nullableIntegerForInput($product->length_mm);
        $this->widthMm = self::nullableIntegerForInput($product->width_mm);
        $this->heightMm = self::nullableIntegerForInput($product->height_mm);
        $this->isFeatured = $product->is_featured;
        $this->metaTitle = (string) $product->meta_title;
        $this->metaDescription = (string) $product->meta_description;
    }

    /**
     * Reset the form to configuration-aware creation defaults.
     */
    public function resetForCreate(): void
    {
        $this->reset();
        $this->product = null;
        $this->unitLabel = 'item';
        $this->minimumOrderQuantity = 1;
        $this->price = '';
        $this->taxRatePercent = ScaledDecimal::formatUnsigned((int) config('commerce.tax.default_rate_bps', 1600), 2);
        $this->isTaxInclusive = (bool) config('commerce.tax.prices_include_tax', true);
        $this->trackStock = true;
        $this->resetValidation();
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        $decimals = self::currencyDecimals();

        return [
            'category_id' => $this->categoryId === '' ? null : (int) $this->categoryId,
            'name' => trim($this->name),
            'slug' => Str::slug($this->slug !== '' ? $this->slug : $this->name),
            'sku' => strtoupper(trim($this->sku)),
            'barcode' => trim($this->barcode),
            'manufacturer_barcode' => trim($this->manufacturerBarcode),
            'short_description' => self::nullableTrimmed($this->shortDescription),
            'description' => self::nullableTrimmed($this->description),
            'specifications' => $this->specificationsJson === '' ? null : json_decode($this->specificationsJson, true, 512, JSON_THROW_ON_ERROR),
            'unit_label' => trim($this->unitLabel),
            'minimum_order_quantity' => $this->minimumOrderQuantity,
            'maximum_order_quantity' => self::nullableInteger($this->maximumOrderQuantity),
            'price_minor' => ScaledDecimal::parseUnsigned($this->price, $decimals),
            'sale_price_minor' => $this->salePrice === '' ? null : ScaledDecimal::parseUnsigned($this->salePrice, $decimals),
            'sale_starts_at' => self::nullableTrimmed($this->saleStartsAt),
            'sale_ends_at' => self::nullableTrimmed($this->saleEndsAt),
            'cost_price_minor' => $this->costPrice === '' ? null : ScaledDecimal::parseUnsigned($this->costPrice, $decimals),
            'tax_rate_bps' => ScaledDecimal::parseUnsigned($this->taxRatePercent, 2),
            'is_tax_inclusive' => $this->isTaxInclusive,
            'track_stock' => $this->trackStock,
            'weight_grams' => self::nullableInteger($this->weightGrams),
            'length_mm' => self::nullableInteger($this->lengthMm),
            'width_mm' => self::nullableInteger($this->widthMm),
            'height_mm' => self::nullableInteger($this->heightMm),
            'is_featured' => $this->isFeatured,
            'meta_title' => self::nullableTrimmed($this->metaTitle),
            'meta_description' => self::nullableTrimmed($this->metaDescription),
        ];
    }

    private static function currencyDecimals(): int
    {
        return max(0, min(6, (int) config('commerce.currency.decimal_places', 2)));
    }

    private static function nullableTrimmed(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private static function nullableInteger(string $value): ?int
    {
        return $value === '' ? null : (int) $value;
    }

    private static function nullableIntegerForInput(?int $value): string
    {
        return $value === null ? '' : (string) $value;
    }
}
