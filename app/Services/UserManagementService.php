<?php

namespace App\Services;

use App\Contracts\RecordsSystemActivity;
use App\Enums\SystemActivitySeverity;
use App\Enums\UserType;
use App\Models\User;
use DomainException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;

final readonly class UserManagementService
{
    public function __construct(
        private DatabaseManager $database,
        private RecordsSystemActivity $activities,
        private TwoFactorService $twoFactor,
    ) {}

    /**
     * @param  array<string, mixed>  $accountAttributes
     * @param  array<string, mixed>  $profileAttributes
     * @param  list<string>  $additionalRoles
     */
    public function create(
        array $accountAttributes,
        array $profileAttributes,
        array $additionalRoles,
        ?UploadedFile $profilePhoto,
        User $actor,
    ): User {
        return $this->database->transaction(function () use ($accountAttributes, $profileAttributes, $additionalRoles, $profilePhoto, $actor): User {
            $type = $this->resolveUserType($accountAttributes['user_type'] ?? null);
            $user = new User($this->accountPayload($accountAttributes, $type));
            $user->forceFill([
                'two_factor_enabled' => (bool) ($accountAttributes['two_factor_enabled'] ?? true),
            ])->save();
            $profile = $user->profile()->create($this->profilePayload($profileAttributes));
            $user->setRelation('profile', $profile);

            $roleNames = $this->syncAccountRoles($user, $type, $additionalRoles);
            $photoUpdated = $this->replaceProfilePhoto($user, $profilePhoto, false);

            $this->activities->record(
                activityType: 'user.created',
                description: "User account created for {$user->display_name}",
                actor: $actor,
                subject: $user,
                properties: [
                    'user_type' => $type->value,
                    'two_factor_enabled' => $user->two_factor_enabled,
                    'roles' => $roleNames,
                    'profile_photo_added' => $photoUpdated,
                ],
                severity: SystemActivitySeverity::Notice,
                source: 'user-management',
            );

            return $user->refresh()->load(['profile', 'roles', 'media']);
        });
    }

    /**
     * @param  array<string, mixed>  $accountAttributes
     * @param  array<string, mixed>  $profileAttributes
     * @param  list<string>  $additionalRoles
     */
    public function update(
        User $user,
        array $accountAttributes,
        array $profileAttributes,
        array $additionalRoles,
        ?UploadedFile $profilePhoto,
        bool $removeProfilePhoto,
        User $actor,
    ): User {
        return $this->database->transaction(function () use ($user, $accountAttributes, $profileAttributes, $additionalRoles, $profilePhoto, $removeProfilePhoto, $actor): User {
            $type = $this->resolveUserType($accountAttributes['user_type'] ?? null);
            $isActive = (bool) ($accountAttributes['is_active'] ?? true);
            $twoFactorEnabled = (bool) ($accountAttributes['two_factor_enabled'] ?? $user->two_factor_enabled);
            $this->assertManagedAccountChangeIsSafe($user, $actor, $type, $isActive);

            $user->fill($this->accountPayload($accountAttributes, $type));
            $user->forceFill(['two_factor_enabled' => $twoFactorEnabled]);
            if (! $twoFactorEnabled) {
                $user->forceFill([
                    'two_factor_code_hash' => null,
                    'two_factor_code_expires_at' => null,
                ]);
            }
            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }
            $accountChanges = array_values(array_diff(array_keys($user->getDirty()), ['password']));
            $user->save();

            if (! $twoFactorEnabled) {
                $this->twoFactor->invalidateUserSessions($user);
            }

            $profile = $user->profile()->firstOrNew();
            $profile->fill($this->profilePayload($profileAttributes));
            $profileChanges = array_keys($profile->getDirty());
            $profile->save();
            $user->setRelation('profile', $profile);

            $roleNames = $this->syncAccountRoles($user, $type, $additionalRoles);
            $photoUpdated = $this->replaceProfilePhoto($user, $profilePhoto, $removeProfilePhoto);

            $this->activities->record(
                activityType: 'user.updated',
                description: "User account updated for {$user->display_name}",
                actor: $actor,
                subject: $user,
                properties: [
                    'account_fields' => $accountChanges,
                    'two_factor_enabled' => $user->two_factor_enabled,
                    'profile_fields' => $profileChanges,
                    'roles' => $roleNames,
                    'profile_photo_updated' => $photoUpdated,
                ],
                severity: SystemActivitySeverity::Notice,
                source: 'user-management',
            );

            return $user->refresh()->load(['profile', 'roles', 'media']);
        });
    }

    /**
     * Update the profile fields a signed-in user is allowed to own.
     *
     * @param  array<string, mixed>  $accountAttributes
     * @param  array<string, mixed>  $profileAttributes
     */
    public function updateOwnProfile(
        User $user,
        array $accountAttributes,
        array $profileAttributes,
        ?UploadedFile $profilePhoto,
        bool $removeProfilePhoto,
    ): User {
        return $this->database->transaction(function () use ($user, $accountAttributes, $profileAttributes, $profilePhoto, $removeProfilePhoto): User {
            $user->fill(Arr::only($accountAttributes, ['name', 'email']));
            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }
            $accountChanges = array_keys($user->getDirty());
            $user->save();

            $profile = $user->profile()->firstOrNew();
            $profile->fill($this->profilePayload($profileAttributes));
            $profileChanges = array_keys($profile->getDirty());
            $profile->save();
            $user->setRelation('profile', $profile);

            $photoUpdated = $this->replaceProfilePhoto($user, $profilePhoto, $removeProfilePhoto);

            $this->activities->record(
                activityType: 'user.profile_updated',
                description: "Profile updated by {$user->display_name}",
                actor: $user,
                subject: $user,
                properties: [
                    'account_fields' => $accountChanges,
                    'profile_fields' => $profileChanges,
                    'profile_photo_updated' => $photoUpdated,
                ],
                source: 'profile',
            );

            return $user->refresh()->load(['profile', 'roles', 'media']);
        });
    }

    public function setActive(User $user, bool $active, User $actor): User
    {
        return $this->database->transaction(function () use ($user, $active, $actor): User {
            $this->assertManagedAccountChangeIsSafe($user, $actor, $user->user_type, $active);

            if ($user->is_active === $active) {
                return $user;
            }

            $user->forceFill(['is_active' => $active])->save();

            $this->activities->record(
                activityType: $active ? 'user.activated' : 'user.deactivated',
                description: sprintf('User account %s for %s', $active ? 'activated' : 'deactivated', $user->display_name),
                actor: $actor,
                subject: $user,
                properties: ['is_active' => $active],
                severity: SystemActivitySeverity::Notice,
                source: 'user-management',
            );

            return $user->refresh();
        });
    }

    public function deleteManagedUser(User $user, User $actor): void
    {
        if ($user->is($actor)) {
            throw new DomainException('You cannot delete your own account from user management.');
        }

        $this->deleteAccount($user, $actor);
    }

    public function deleteOwnAccount(User $user): void
    {
        $this->deleteAccount($user, $user);
    }

    /**
     * Validate the irreversible self-service boundary before logout mutates
     * the remember token. The deletion method repeats this check in-transaction.
     */
    public function assertOwnAccountDeletionIsSafe(User $user): void
    {
        $this->assertLastAdministratorRemains($user, false, null);
    }

    private function deleteAccount(User $user, User $actor): void
    {
        $this->database->transaction(function () use ($user, $actor): void {
            $this->assertLastAdministratorRemains($user, false, null);
            $displayName = $user->display_name;

            $this->activities->record(
                activityType: 'user.deleted',
                description: "User account deleted for {$displayName}",
                actor: $actor,
                subject: $user,
                properties: ['email' => $user->email, 'user_type' => $user->user_type->value],
                severity: SystemActivitySeverity::Warning,
                source: $user->is($actor) ? 'profile' : 'user-management',
            );

            $user->delete();
        });
    }

    /** @return array<string, mixed> */
    private function accountPayload(array $attributes, UserType $type): array
    {
        $payload = Arr::only($attributes, ['name', 'email', 'password', 'is_active']);
        $payload['name'] = trim((string) ($payload['name'] ?? ''));
        $payload['email'] = Str::lower(trim((string) ($payload['email'] ?? '')));
        $payload['user_type'] = $type;
        $payload['is_active'] = (bool) ($payload['is_active'] ?? true);

        if (blank($payload['password'] ?? null)) {
            unset($payload['password']);
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    private function profilePayload(array $attributes): array
    {
        $payload = Arr::only($attributes, [
            'first_name',
            'middle_name',
            'last_name',
            'date_of_birth',
            'identification_type',
            'identification_number',
            'phone',
            'job_title',
            'bio',
        ]);

        foreach ($payload as $key => $value) {
            if (is_string($value)) {
                $value = trim($value);
                $payload[$key] = $value === '' ? null : $value;
            }
        }

        return $payload;
    }

    /** @param list<string> $additionalRoles
     * @return list<string>
     */
    private function syncAccountRoles(User $user, UserType $type, array $additionalRoles): array
    {
        $additionalRoles = array_values(array_unique(array_filter(array_map(
            static fn (mixed $role): string => trim((string) $role),
            $additionalRoles,
        ))));

        if (array_intersect($additionalRoles, UserType::values()) !== []) {
            throw new InvalidArgumentException('Base roles are selected through the user type field.');
        }

        $availableAdditionalRoles = Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $additionalRoles)
            ->pluck('name')
            ->all();

        if (count($availableAdditionalRoles) !== count($additionalRoles)) {
            throw new InvalidArgumentException('One or more selected roles are unavailable.');
        }

        Role::findOrCreate($type->value, 'web');
        $roleNames = array_values(array_unique([$type->value, ...$availableAdditionalRoles]));
        $user->syncRoles($roleNames);

        return $roleNames;
    }

    private function replaceProfilePhoto(User $user, ?UploadedFile $profilePhoto, bool $remove): bool
    {
        if (! $profilePhoto && ! $remove) {
            return false;
        }

        $user->clearMediaCollection('profile_photo');

        if ($profilePhoto) {
            $extension = strtolower($profilePhoto->guessExtension() ?: 'jpg');
            $user->addMedia($profilePhoto)
                ->usingFileName(Str::uuid().'.'.$extension)
                ->toMediaCollection('profile_photo');
        }

        return true;
    }

    private function resolveUserType(mixed $value): UserType
    {
        if ($value instanceof UserType) {
            return $value;
        }

        return UserType::tryFrom((string) $value)
            ?? throw new InvalidArgumentException('A valid user type is required.');
    }

    private function assertManagedAccountChangeIsSafe(User $user, User $actor, UserType $type, bool $isActive): void
    {
        if ($user->is($actor) && ! $isActive) {
            throw new DomainException('You cannot deactivate your own account.');
        }

        $this->assertLastAdministratorRemains($user, $isActive, $type);
    }

    private function assertLastAdministratorRemains(User $user, bool $isActive, ?UserType $type): void
    {
        if (! $user->is_active || (! $user->isSystemAdministrator() && ! $user->hasRole(UserType::SystemAdministrator->value))) {
            return;
        }

        $remainsAdministrator = $isActive && $type === UserType::SystemAdministrator;
        if ($remainsAdministrator) {
            return;
        }

        $otherActiveAdministrators = User::query()
            ->whereKeyNot($user->getKey())
            ->where('is_active', true)
            ->where('user_type', UserType::SystemAdministrator->value)
            ->whereHas('roles', fn ($query) => $query->where('name', UserType::SystemAdministrator->value))
            ->count();

        if ($otherActiveAdministrators === 0) {
            throw new DomainException('The last active system administrator cannot be deactivated, demoted, or deleted.');
        }
    }
}
