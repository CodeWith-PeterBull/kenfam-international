<?php

/** Resolves the internal recipients of operational travel notices by capability. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * Staff notices go to whoever currently holds the capability the notice
 * calls for (through roles or direct grants), never to a hard-coded inbox,
 * so the recipient list follows the access catalogue without configuration.
 */
final class StaffRecipientResolver
{
    /**
     * Active users who may act on the notice.
     *
     * @return Collection<int, User>
     */
    public function holding(string $permission): Collection
    {
        try {
            return User::query()
                ->where('is_active', true)
                ->whereNotNull('email')
                ->permission($permission)
                ->orderBy('id')
                ->get();
        } catch (PermissionDoesNotExist) {
            // The catalogue has not been seeded on this install yet, so nobody holds it.
            return new Collection;
        }
    }
}
