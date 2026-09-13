<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Notifications;

use App\Models\User;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Support\PropertyAccessService;
use Illuminate\Support\Collection;
use Throwable;

/** Resolves verified operational recipients with explicit property scope. */
final readonly class PropertyBookingNotificationRecipientResolver
{
    /** Create the resolver with the canonical property-access service. */
    public function __construct(private PropertyAccessService $propertyAccess) {}

    /** @return Collection<int, User> */
    public function staffWithPermission(int $propertyId, string $permission): Collection
    {
        return User::query()
            ->with(['permissions', 'roles.permissions'])
            ->where('is_active', true)
            ->whereNotNull('email_verified_at')
            ->whereNotNull('email')
            ->where('email', '<>', '')
            ->orderBy('id')
            ->get()
            ->filter(function (User $user) use ($permission, $propertyId): bool {
                try {
                    $permitted = $user->isSystemAdministrator() || $user->hasPermissionTo($permission);

                    return $permitted && $this->propertyAccess->canAccess($user, $propertyId);
                } catch (Throwable) {
                    return false;
                }
            })
            ->values();
    }

    /** Resolve a validated immutable customer email snapshot. */
    public function bookingSnapshotEmail(string $bookingUlid): ?string
    {
        $email = Booking::query()->where('ulid', $bookingUlid)->value('guest_email');
        $email = strtolower(trim((string) $email));

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }
}
