<?php

declare(strict_types=1);

namespace App\Modules\Commerce\PointOfSale\Services;

use App\Contracts\RecordsSystemActivity;
use App\Enums\SystemActivitySeverity;
use App\Models\User;
use App\Modules\Commerce\Exceptions\TillSessionException;
use App\Modules\Commerce\Orders\Enums\OrderStatus;
use App\Modules\Commerce\Orders\Enums\PaymentMethod;
use App\Modules\Commerce\Orders\Enums\PaymentStatus;
use App\Modules\Commerce\Orders\Models\Payment;
use App\Modules\Commerce\PointOfSale\Enums\TillSessionStatus;
use App\Modules\Commerce\PointOfSale\Events\TillVarianceDetected;
use App\Modules\Commerce\PointOfSale\Models\Register;
use App\Modules\Commerce\PointOfSale\Models\TillSession;
use Illuminate\Database\DatabaseManager;

/**
 * Owns register-session opening, cash reconciliation, and closing invariants.
 */
final readonly class TillService
{
    public function __construct(
        private DatabaseManager $database,
        private RecordsSystemActivity $activities,
    ) {}

    /**
     * Open one active register for one cashier with a declared cash float.
     */
    public function open(
        Register $register,
        User $cashier,
        int $openingFloatMinor,
        ?string $note = null,
    ): TillSession {
        if ($openingFloatMinor < 0) {
            throw new TillSessionException('Opening cash float cannot be negative.');
        }

        return $this->database->transaction(function () use ($register, $cashier, $openingFloatMinor, $note): TillSession {
            $cashier = User::query()->lockForUpdate()->findOrFail($cashier->getKey());
            $register = Register::query()->lockForUpdate()->findOrFail($register->getKey());
            if (! $register->is_active) {
                throw new TillSessionException('Inactive registers cannot open till sessions.');
            }

            $conflict = TillSession::query()
                ->where('status', TillSessionStatus::Open->value)
                ->where(function ($query) use ($register, $cashier): void {
                    $query->where('register_id', $register->getKey())
                        ->orWhere('opened_by', $cashier->getKey());
                })
                ->lockForUpdate()
                ->exists();
            if ($conflict) {
                throw new TillSessionException('The register or cashier already has an open till session.');
            }

            $session = new TillSession;
            $session->forceFill([
                'register_id' => $register->getKey(),
                'opened_by' => $cashier->getKey(),
                'closed_by' => null,
                'status' => TillSessionStatus::Open,
                'opening_float_minor' => $openingFloatMinor,
                'expected_cash_minor' => $openingFloatMinor,
                'counted_cash_minor' => null,
                'variance_minor' => null,
                'opening_note' => $this->note($note),
                'closing_note' => null,
                'opened_at' => now(),
                'closed_at' => null,
            ])->save();

            $this->activities->record(
                activityType: 'commerce.till.opened',
                description: "Till opened on {$register->name}",
                actor: $cashier,
                subject: $session,
                properties: [
                    'register_ulid' => $register->ulid,
                    'opening_float_minor' => $openingFloatMinor,
                ],
                severity: SystemActivitySeverity::Notice,
                source: 'commerce-pos',
            );

            return $session->refresh()->load('register');
        });
    }

    /**
     * Recompute expected cash and persist an immutable closing snapshot.
     */
    public function close(
        TillSession $session,
        User $actor,
        int $countedCashMinor,
        ?string $note = null,
    ): TillSession {
        if ($countedCashMinor < 0) {
            throw new TillSessionException('Counted cash cannot be negative.');
        }

        return $this->database->transaction(function () use ($session, $actor, $countedCashMinor, $note): TillSession {
            $session = TillSession::query()->lockForUpdate()->findOrFail($session->getKey());
            if (! $session->isOpen()) {
                throw new TillSessionException('Only an open till session can be closed.');
            }

            $heldOrders = $session->orders()->where('status', OrderStatus::Held->value)->exists();
            if ($heldOrders) {
                throw new TillSessionException('Resolve held orders before closing the till session.');
            }

            $completedCash = (int) Payment::query()
                ->whereBelongsTo($session)
                ->where('status', PaymentStatus::Completed->value)
                ->where('method', PaymentMethod::Cash->value)
                ->sum('amount_minor');
            $expected = $session->opening_float_minor + $completedCash;
            $variance = $countedCashMinor - $expected;

            $session->forceFill([
                'closed_by' => $actor->getKey(),
                'status' => TillSessionStatus::Closed,
                'expected_cash_minor' => $expected,
                'counted_cash_minor' => $countedCashMinor,
                'variance_minor' => $variance,
                'closing_note' => $this->note($note),
                'closed_at' => now(),
            ])->save();

            $this->activities->record(
                activityType: 'commerce.till.closed',
                description: "Till session closed with variance {$variance}",
                actor: $actor,
                subject: $session,
                properties: [
                    'expected_cash_minor' => $expected,
                    'counted_cash_minor' => $countedCashMinor,
                    'variance_minor' => $variance,
                ],
                severity: $variance === 0 ? SystemActivitySeverity::Notice : SystemActivitySeverity::Warning,
                source: 'commerce-pos',
            );

            $threshold = max(1, (int) config('commerce.notifications.till_variance_threshold_minor', 10_000));
            if ($variance !== 0 && abs($variance) >= $threshold) {
                TillVarianceDetected::dispatch($session->ulid, $variance, $threshold);
            }

            return $session->refresh()->load(['register', 'opener', 'closer']);
        });
    }

    private function note(?string $note): ?string
    {
        $note = $note === null ? null : trim($note);
        if ($note !== null && strlen($note) > 2000) {
            throw new TillSessionException('Till notes cannot exceed 2,000 characters.');
        }

        return $note === '' ? null : $note;
    }
}
