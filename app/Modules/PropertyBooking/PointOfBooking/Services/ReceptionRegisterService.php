<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\PointOfBooking\Services;

use App\Contracts\RecordsSystemActivity;
use App\Enums\SystemActivitySeverity;
use App\Models\User;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\PointOfBooking\Enums\ReceiptPaperWidth;
use App\Modules\PropertyBooking\PointOfBooking\Enums\ReceiptPrintMode;
use App\Modules\PropertyBooking\PointOfBooking\Enums\ReceptionShiftStatus;
use App\Modules\PropertyBooking\PointOfBooking\Exceptions\ReceptionRegisterException;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionRegister;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Arr;

/** Owns normalized property-scoped register configuration and retirement. */
final readonly class ReceptionRegisterService
{
    /** Create the register service with transactional collaborators. */
    public function __construct(
        private DatabaseManager $database,
        private RecordsSystemActivity $activities,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function create(Property $property, array $attributes, User $actor): ReceptionRegister
    {
        return $this->database->transaction(function () use ($property, $attributes, $actor): ReceptionRegister {
            $property = Property::query()->lockForUpdate()->findOrFail($property->getKey());
            $register = new ReceptionRegister;
            $register->fill($this->payload($attributes));
            $register->forceFill([
                'property_id' => $property->getKey(),
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ]);
            $this->assertValid($register);
            $register->save();
            $this->record('created', $register, $actor);

            return $register->refresh()->load('property');
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(ReceptionRegister $register, array $attributes, User $actor): ReceptionRegister
    {
        return $this->database->transaction(function () use ($register, $attributes, $actor): ReceptionRegister {
            $register = ReceptionRegister::query()->lockForUpdate()->findOrFail($register->getKey());
            $register->fill($this->payload($attributes));
            $register->forceFill(['updated_by' => $actor->getKey()]);
            $this->assertValid($register);
            $changedFields = array_values(array_diff(array_keys($register->getDirty()), ['updated_by']));
            $register->save();
            $this->record('updated', $register, $actor, ['changed_fields' => $changedFields]);

            return $register->refresh()->load('property');
        });
    }

    /** Activate or retire a register while retaining its financial history. */
    public function setActive(ReceptionRegister $register, bool $active, User $actor): ReceptionRegister
    {
        return $this->database->transaction(function () use ($register, $active, $actor): ReceptionRegister {
            $register = ReceptionRegister::query()->lockForUpdate()->findOrFail($register->getKey());
            if (! $active && $register->shifts()
                ->where('status', ReceptionShiftStatus::Open->value)
                ->lockForUpdate()
                ->exists()) {
                throw new ReceptionRegisterException('Close the open reception shift before deactivating this register.');
            }

            $register->forceFill(['is_active' => $active, 'updated_by' => $actor->getKey()])->save();
            $this->record($active ? 'activated' : 'deactivated', $register, $actor);

            return $register->refresh();
        });
    }

    /** @param array<string, mixed> $attributes */
    private function payload(array $attributes): array
    {
        $payload = Arr::only($attributes, [
            'name', 'code', 'location_label', 'description', 'receipt_print_driver',
            'receipt_print_mode', 'receipt_paper_width', 'receipt_printer_name', 'is_active',
        ]);
        foreach (['name', 'code', 'location_label', 'description', 'receipt_print_driver', 'receipt_print_mode', 'receipt_printer_name'] as $field) {
            if (array_key_exists($field, $payload) && is_string($payload[$field])) {
                $value = trim($payload[$field]);
                $payload[$field] = $value === '' ? null : $value;
            }
        }
        if (isset($payload['code'])) {
            $payload['code'] = strtoupper((string) $payload['code']);
        }

        $driver = $payload['receipt_print_driver'] ?? config('property-booking.pob.receipt_printing.default_driver', 'browser');
        $mode = $payload['receipt_print_mode'] ?? config('property-booking.pob.receipt_printing.default_mode', 'manual');
        $width = $payload['receipt_paper_width'] ?? config('property-booking.pob.receipt_printing.default_paper_width_mm', 80);
        $mode = $mode instanceof ReceiptPrintMode ? $mode : ReceiptPrintMode::tryFrom((string) $mode);
        $width = $width instanceof ReceiptPaperWidth ? $width : ReceiptPaperWidth::tryFrom((int) $width);
        $drivers = array_keys((array) config('property-booking.pob.receipt_printing.drivers', []));
        if (! is_string($driver) || ! in_array($driver, $drivers, true)) {
            throw new ReceptionRegisterException('The selected receipt printer driver is unavailable.');
        }
        if (! $mode instanceof ReceiptPrintMode || ! $width instanceof ReceiptPaperWidth) {
            throw new ReceptionRegisterException('The receipt print mode or paper width is invalid.');
        }

        $payload['receipt_print_driver'] = $driver;
        $payload['receipt_print_mode'] = $mode->value;
        $payload['receipt_paper_width'] = $width->value;
        $payload['is_active'] = (bool) ($payload['is_active'] ?? true);

        return $payload;
    }

    /** Reject incomplete, duplicate, or cross-property register identities. */
    private function assertValid(ReceptionRegister $register): void
    {
        if (blank($register->name) || blank($register->code)) {
            throw new ReceptionRegisterException('Reception registers require a name and operational code.');
        }
        if (ReceptionRegister::query()
            ->where('property_id', $register->property_id)
            ->where('code', $register->code)
            ->when($register->exists, fn ($query) => $query->whereKeyNot($register->getKey()))
            ->exists()) {
            throw new ReceptionRegisterException('That register code is already in use at this property.');
        }
    }

    /** @param array<string, mixed> $properties */
    private function record(string $action, ReceptionRegister $register, User $actor, array $properties = []): void
    {
        $this->activities->record(
            activityType: "property-booking.register.{$action}",
            description: "Reception register {$action}: {$register->name}",
            actor: $actor,
            subject: $register,
            properties: [
                'register_ulid' => $register->ulid,
                'property_id' => $register->property_id,
                'code' => $register->code,
                'is_active' => $register->is_active,
                ...$properties,
            ],
            severity: SystemActivitySeverity::Notice,
            source: 'property-booking-pob',
        );
    }
}
