<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Notifications;

use App\Models\User;
use App\Modules\Commerce\Orders\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Resolves operational recipients without relying on super-user gate bypasses. */
final class CommerceNotificationRecipientResolver
{
    /** @return Collection<int, User> */
    public function staffWithPermission(string $permission): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereNotNull('email')
            ->where('email', '<>', '')
            ->where(function (Builder $query) use ($permission): void {
                $query
                    ->whereHas('permissions', static fn (Builder $permissions): Builder => $permissions->where('name', $permission))
                    ->orWhereHas('roles.permissions', static fn (Builder $permissions): Builder => $permissions->where('name', $permission));
            })
            ->orderBy('id')
            ->get();
    }

    public function orderSnapshotEmail(string $orderUlid): ?string
    {
        $email = Order::query()->where('ulid', $orderUlid)->value('customer_email');
        $email = strtolower(trim((string) $email));

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }
}
