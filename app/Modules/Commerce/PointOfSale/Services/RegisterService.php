<?php

declare(strict_types=1);

namespace App\Modules\Commerce\PointOfSale\Services;

use App\Contracts\RecordsSystemActivity;
use App\Enums\SystemActivitySeverity;
use App\Models\User;
use App\Modules\Commerce\Exceptions\RegisterException;
use App\Modules\Commerce\PointOfSale\Enums\ReceiptPaperWidth;
use App\Modules\Commerce\PointOfSale\Enums\ReceiptPrintMode;
use App\Modules\Commerce\PointOfSale\Enums\TillSessionStatus;
use App\Modules\Commerce\PointOfSale\Models\Register;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Arr;

/**
 * Owns normalized register configuration and active-state transitions.
 */
final readonly class RegisterService
{
    public function __construct(
        private DatabaseManager $database,
        private RecordsSystemActivity $activities,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes, User $actor): Register
    {
        return $this->database->transaction(function () use ($attributes, $actor): Register {
            $register = new Register;
            $register->fill($this->payload($attributes));
            $this->assertValid($register);
            $register->save();

            $this->record('created', $register, $actor);

            return $register->refresh();
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(Register $register, array $attributes, User $actor): Register
    {
        return $this->database->transaction(function () use ($register, $attributes, $actor): Register {
            $register = Register::query()->lockForUpdate()->findOrFail($register->getKey());
            $register->fill($this->payload($attributes));
            $this->assertValid($register);
            $changedFields = array_keys($register->getDirty());
            $register->save();

            $this->record('updated', $register, $actor, ['changed_fields' => $changedFields]);

            return $register->refresh();
        });
    }

    /**
     * Activate or deactivate a register while preserving transactional history.
     */
    public function setActive(Register $register, bool $active, User $actor): Register
    {
        return $this->database->transaction(function () use ($register, $active, $actor): Register {
            $register = Register::query()->lockForUpdate()->findOrFail($register->getKey());
            if (! $active && $register->tillSessions()
                ->where('status', TillSessionStatus::Open->value)
                ->lockForUpdate()
                ->exists()) {
                throw new RegisterException('Close the open till session before deactivating this register.');
            }

            $register->forceFill(['is_active' => $active])->save();
            $this->record($active ? 'activated' : 'deactivated', $register, $actor);

            return $register->refresh();
        });
    }

    /** @param array<string, mixed> $attributes */
    private function payload(array $attributes): array
    {
        $payload = Arr::only($attributes, [
            'name',
            'code',
            'location_label',
            'description',
            'receipt_print_driver',
            'receipt_print_mode',
            'receipt_paper_width',
            'receipt_printer_name',
            'is_active',
        ]);
        foreach (['name', 'code', 'location_label', 'description', 'receipt_print_driver', 'receipt_print_mode', 'receipt_printer_name'] as $field) {
            if (! array_key_exists($field, $payload) || ! is_string($payload[$field])) {
                continue;
            }

            $value = trim($payload[$field]);
            $payload[$field] = $value === '' ? null : $value;
        }

        if (isset($payload['code'])) {
            $payload['code'] = strtoupper((string) $payload['code']);
        }
        $driver = $payload['receipt_print_driver']
            ?? (string) config('commerce.pos.receipt_printing.default_driver', 'browser');
        $mode = $payload['receipt_print_mode']
            ?? (string) config('commerce.pos.receipt_printing.default_mode', ReceiptPrintMode::Manual->value);
        $width = $payload['receipt_paper_width']
            ?? (int) config('commerce.pos.receipt_printing.default_paper_width_mm', ReceiptPaperWidth::Roll80->value);
        $mode = $mode instanceof ReceiptPrintMode ? $mode : ReceiptPrintMode::tryFrom((string) $mode);
        $width = $width instanceof ReceiptPaperWidth ? $width : ReceiptPaperWidth::tryFrom((int) $width);
        $drivers = array_keys((array) config('commerce.pos.receipt_printing.drivers', []));
        if (! is_string($driver) || ! in_array($driver, $drivers, true)) {
            throw new RegisterException('The selected receipt printer driver is unavailable.');
        }
        if (! $mode instanceof ReceiptPrintMode || ! $width instanceof ReceiptPaperWidth) {
            throw new RegisterException('The receipt print mode or paper width is invalid.');
        }
        $payload['receipt_print_driver'] = $driver;
        $payload['receipt_print_mode'] = $mode->value;
        $payload['receipt_paper_width'] = $width->value;
        $payload['is_active'] = (bool) ($payload['is_active'] ?? true);

        return $payload;
    }

    private function assertValid(Register $register): void
    {
        if (blank($register->name) || blank($register->code)) {
            throw new RegisterException('Registers require a name and operational code.');
        }

        $drivers = array_keys((array) config('commerce.pos.receipt_printing.drivers', []));
        if (! in_array($register->receipt_print_driver, $drivers, true)) {
            throw new RegisterException('The selected receipt printer driver is unavailable.');
        }

        if (! $register->receipt_print_mode instanceof ReceiptPrintMode
            || ! $register->receipt_paper_width instanceof ReceiptPaperWidth) {
            throw new RegisterException('The receipt print mode or paper width is invalid.');
        }

        $duplicate = Register::query()
            ->where('code', $register->code)
            ->when($register->exists, fn ($query) => $query->whereKeyNot($register->getKey()))
            ->exists();
        if ($duplicate) {
            throw new RegisterException('That register code is already in use.');
        }
    }

    /** @param array<string, mixed> $properties */
    private function record(string $action, Register $register, User $actor, array $properties = []): void
    {
        $this->activities->record(
            activityType: "commerce.register.{$action}",
            description: "Register {$action}: {$register->name}",
            actor: $actor,
            subject: $register,
            properties: [
                'register_ulid' => $register->ulid,
                'code' => $register->code,
                'is_active' => $register->is_active,
                ...$properties,
            ],
            severity: SystemActivitySeverity::Notice,
            source: 'commerce-pos',
        );
    }
}
