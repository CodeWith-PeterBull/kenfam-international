<?php

declare(strict_types=1);

namespace App\Modules\Commerce\PointOfSale\Livewire\Forms;

use App\Modules\Commerce\PointOfSale\Enums\ReceiptPaperWidth;
use App\Modules\Commerce\PointOfSale\Enums\ReceiptPrintMode;
use App\Modules\Commerce\PointOfSale\Models\Register;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * Validates one register configuration without owning persistence.
 */
final class RegisterForm extends Form
{
    public ?Register $register = null;

    public string $name = '';

    public string $code = '';

    public string $locationLabel = '';

    public string $description = '';

    public string $receiptPrintDriver = 'browser';

    public string $receiptPrintMode = 'manual';

    public string $receiptPaperWidth = '80';

    public string $receiptPrinterName = '';

    public bool $isActive = true;

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:40', Rule::unique('registers', 'code')->ignore($this->register?->id)],
            'locationLabel' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'receiptPrintDriver' => ['required', 'string', Rule::in(array_keys((array) config('commerce.pos.receipt_printing.drivers', [])))],
            'receiptPrintMode' => ['required', Rule::enum(ReceiptPrintMode::class)],
            'receiptPaperWidth' => ['required', Rule::in(array_map(
                static fn (ReceiptPaperWidth $width): string => (string) $width->value,
                ReceiptPaperWidth::cases(),
            ))],
            'receiptPrinterName' => ['nullable', 'string', 'max:120'],
            'isActive' => ['boolean'],
        ];
    }

    public function fillFromRegister(Register $register): void
    {
        $this->register = $register;
        $this->name = $register->name;
        $this->code = $register->code;
        $this->locationLabel = (string) $register->location_label;
        $this->description = (string) $register->description;
        $this->receiptPrintDriver = $register->receipt_print_driver;
        $this->receiptPrintMode = $register->receipt_print_mode->value;
        $this->receiptPaperWidth = (string) $register->receipt_paper_width->value;
        $this->receiptPrinterName = (string) $register->receipt_printer_name;
        $this->isActive = $register->is_active;
        $this->resetValidation();
    }

    public function resetForCreate(): void
    {
        $this->reset();
        $this->receiptPrintDriver = (string) config('commerce.pos.receipt_printing.default_driver', 'browser');
        $this->receiptPrintMode = (string) config('commerce.pos.receipt_printing.default_mode', ReceiptPrintMode::Manual->value);
        $this->receiptPaperWidth = (string) config('commerce.pos.receipt_printing.default_paper_width_mm', ReceiptPaperWidth::Roll80->value);
        $this->isActive = true;
        $this->resetValidation();
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'name' => $this->name,
            'code' => $this->code,
            'location_label' => $this->locationLabel,
            'description' => $this->description,
            'receipt_print_driver' => $this->receiptPrintDriver,
            'receipt_print_mode' => $this->receiptPrintMode,
            'receipt_paper_width' => (int) $this->receiptPaperWidth,
            'receipt_printer_name' => $this->receiptPrinterName,
            'is_active' => $this->isActive,
        ];
    }
}
