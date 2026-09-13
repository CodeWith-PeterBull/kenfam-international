<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\PointOfBooking\Livewire\Forms;

use App\Modules\PropertyBooking\PointOfBooking\Enums\ReceiptPaperWidth;
use App\Modules\PropertyBooking\PointOfBooking\Enums\ReceiptPrintMode;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionRegister;
use Illuminate\Validation\Rule;
use Livewire\Form;

/** Validates one property-scoped reception register without persisting it. */
final class ReceptionRegisterForm extends Form
{
    public ?ReceptionRegister $register = null;

    public string $propertyId = '';

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
            'propertyId' => ['required', 'integer', 'exists:property_booking_properties,id'],
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required', 'string', 'max:40',
                Rule::unique('property_booking_registers', 'code')
                    ->where('property_id', $this->propertyId)
                    ->ignore($this->register?->id),
            ],
            'locationLabel' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'receiptPrintDriver' => ['required', 'string', Rule::in(array_keys((array) config('property-booking.pob.receipt_printing.drivers', [])))],
            'receiptPrintMode' => ['required', Rule::enum(ReceiptPrintMode::class)],
            'receiptPaperWidth' => ['required', Rule::in(array_map(
                static fn (ReceiptPaperWidth $width): string => (string) $width->value,
                ReceiptPaperWidth::cases(),
            ))],
            'receiptPrinterName' => ['nullable', 'string', 'max:160'],
            'isActive' => ['boolean'],
        ];
    }

    /** Hydrate the form from an existing register. */
    public function fillFromRegister(ReceptionRegister $register): void
    {
        $this->register = $register;
        $this->propertyId = (string) $register->property_id;
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

    /** Reset the form to configured module defaults. */
    public function resetForCreate(?int $propertyId = null): void
    {
        $this->reset();
        $this->propertyId = $propertyId === null ? '' : (string) $propertyId;
        $this->receiptPrintDriver = (string) config('property-booking.pob.receipt_printing.default_driver', 'browser');
        $this->receiptPrintMode = (string) config('property-booking.pob.receipt_printing.default_mode', 'manual');
        $this->receiptPaperWidth = (string) config('property-booking.pob.receipt_printing.default_paper_width_mm', 80);
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
