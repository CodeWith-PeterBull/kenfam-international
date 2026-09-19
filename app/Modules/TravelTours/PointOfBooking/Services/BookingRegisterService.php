<?php

/**
 * Owns booking-desk register configuration and retirement.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Services;

use App\Models\User;
use App\Modules\TravelTours\PointOfBooking\Enums\ReceiptPaperWidth;
use App\Modules\TravelTours\PointOfBooking\Enums\ReceiptPrintMode;
use App\Modules\TravelTours\PointOfBooking\Exceptions\PointOfBookingException;
use App\Modules\TravelTours\PointOfBooking\Models\BookingRegister;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Arr;

/**
 * Registers are the physical desks shifts run on. They are switched off
 * rather than deleted so every shift and receipt keeps its endpoint, and a
 * register with an open shift cannot be retired underneath the operator.
 */
final readonly class BookingRegisterService
{
    /** Create the register service with its transaction boundary. */
    public function __construct(private DatabaseManager $database) {}

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes, User $actor): BookingRegister
    {
        return $this->database->transaction(function () use ($attributes, $actor): BookingRegister {
            $register = new BookingRegister;
            $register->forceFill($this->payload($attributes) + ['created_by' => $actor->getKey(), 'updated_by' => $actor->getKey()]);
            $this->assertValid($register);
            $register->save();

            return $register->refresh();
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(BookingRegister $register, array $attributes, User $actor): BookingRegister
    {
        return $this->database->transaction(function () use ($register, $attributes, $actor): BookingRegister {
            $register = BookingRegister::query()->lockForUpdate()->findOrFail($register->getKey());
            $payload = $this->payload($attributes);
            if (array_key_exists('is_active', $payload) && ! $payload['is_active'] && $register->shifts()->open()->lockForUpdate()->exists()) {
                throw new PointOfBookingException('Close the open shift on this register before deactivating it.');
            }
            $register->forceFill($payload + ['updated_by' => $actor->getKey()]);
            $this->assertValid($register);
            $register->save();

            return $register->refresh();
        });
    }

    /** Activate or retire a register while retaining its financial history. */
    public function setActive(BookingRegister $register, bool $active, User $actor): BookingRegister
    {
        return $this->database->transaction(function () use ($register, $active, $actor): BookingRegister {
            $register = BookingRegister::query()->lockForUpdate()->findOrFail($register->getKey());
            if (! $active && $register->shifts()->open()->lockForUpdate()->exists()) {
                throw new PointOfBookingException('Close the open shift on this register before deactivating it.');
            }
            $register->forceFill(['is_active' => $active, 'updated_by' => $actor->getKey()])->save();

            return $register->refresh();
        });
    }

    /**
     * Normalise the operator's input into column values.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function payload(array $attributes): array
    {
        $payload = Arr::only($attributes, ['name', 'code', 'location', 'receipt_printer_driver', 'receipt_print_mode', 'receipt_paper_width_mm', 'receipt_printer_name', 'is_active']);
        foreach (['name', 'code', 'location', 'receipt_printer_driver', 'receipt_printer_name'] as $field) {
            if (array_key_exists($field, $payload) && is_string($payload[$field])) {
                $value = trim($payload[$field]);
                $payload[$field] = $value === '' ? null : $value;
            }
        }
        if (isset($payload['code'])) {
            $payload['code'] = strtoupper((string) $payload['code']);
        }

        $driver = $payload['receipt_printer_driver'] ?? config('travel-tours.pob.receipt_printing.default_driver', 'browser');
        $mode = $payload['receipt_print_mode'] ?? config('travel-tours.pob.receipt_printing.default_mode', 'manual');
        $width = $payload['receipt_paper_width_mm'] ?? config('travel-tours.pob.receipt_printing.default_paper_width_mm', 80);
        $mode = $mode instanceof ReceiptPrintMode ? $mode : ReceiptPrintMode::tryFrom((string) $mode);
        $width = $width instanceof ReceiptPaperWidth ? $width : ReceiptPaperWidth::tryFrom((int) $width);
        if (! is_string($driver) || ! array_key_exists($driver, (array) config('travel-tours.pob.receipt_printing.drivers', []))) {
            throw new PointOfBookingException('The selected receipt printer driver is unavailable.');
        }
        if (! $mode instanceof ReceiptPrintMode || ! $width instanceof ReceiptPaperWidth) {
            throw new PointOfBookingException('The receipt print mode or paper width is invalid.');
        }
        unset($payload['receipt_print_mode']);
        $payload['receipt_printer_driver'] = $driver;
        $payload['automatic_receipt_print'] = $mode->isAutomatic();
        $payload['receipt_paper_width_mm'] = $width->value;
        $payload['is_active'] = (bool) ($payload['is_active'] ?? true);

        return $payload;
    }

    /** Reject incomplete or duplicate register identities. */
    private function assertValid(BookingRegister $register): void
    {
        if (blank($register->name) || blank($register->code)) {
            throw new PointOfBookingException('Registers require a name and an operational code.');
        }
        if (BookingRegister::query()->where('code', $register->code)->when($register->exists, fn ($query) => $query->whereKeyNot($register->getKey()))->exists()) {
            throw new PointOfBookingException('Another register already uses this code.');
        }
    }
}
