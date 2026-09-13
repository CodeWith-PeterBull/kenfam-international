<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Guests\Services;

use App\Contracts\RecordsSystemActivity;
use App\Enums\SystemActivitySeverity;
use App\Models\User;
use App\Modules\PropertyBooking\Guests\Data\GuestProfileData;
use App\Modules\PropertyBooking\Guests\Exceptions\GuestException;
use App\Modules\PropertyBooking\Guests\Models\Guest;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Arr;

/** Owns guest writes, normalization, encryption, and exact identity lookup. */
final readonly class GuestService
{
    /** Create the guest service with its required dependencies. */
    public function __construct(
        private DatabaseManager $database,
        private Encrypter $encrypter,
        private RecordsSystemActivity $activities,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes, ?User $actor = null): Guest
    {
        return $this->database->transaction(function () use ($attributes, $actor): Guest {
            $guest = new Guest;
            $guest->fill($this->payload($attributes));
            $this->applyIdentity($guest, $attributes);
            $this->assertValid($guest);
            $guest->forceFill(['created_by' => $actor?->getKey(), 'updated_by' => $actor?->getKey()])->save();

            $this->activities->record(
                activityType: 'property-booking.guest.created',
                description: "Booking guest created: {$guest->fullName()}",
                actor: $actor,
                subject: $guest,
                properties: ['has_identity' => $guest->identity_number_hash !== null, 'has_account' => $guest->user_id !== null],
                severity: SystemActivitySeverity::Notice,
                source: 'property-booking-guests',
            );

            return $guest->refresh();
        });
    }

    /** Resolve or create a reusable guest from public-safe checkout input. */
    public function resolveForCheckout(GuestProfileData $data, ?User $account = null): Guest
    {
        return $this->database->transaction(function () use ($data, $account): Guest {
            $email = strtolower(trim($data->email));
            $phone = trim($data->phone);
            $accountId = $account instanceof User && strtolower($account->email) === $email
                ? (int) $account->getKey()
                : null;

            $guest = Guest::withTrashed()
                ->when(
                    $accountId !== null,
                    static fn ($query) => $query->where(static fn ($identity) => $identity
                        ->where('user_id', $accountId)
                        ->orWhere(static fn ($contact) => $contact->whereNull('user_id')->where('email', $email)->where('phone', $phone))),
                    static fn ($query) => $query->whereNull('user_id')->where('email', $email)->where('phone', $phone),
                )
                ->lockForUpdate()
                ->first();

            $created = ! $guest instanceof Guest;
            $guest ??= new Guest;
            if ($guest->trashed()) {
                $guest->restore();
            }
            $attributes = $data->toAttributes($accountId);
            if ($guest->exists
                && $guest->identity_number_hash !== null
                && array_key_exists('identity_number', $attributes)) {
                $identityMatches = hash_equals(
                    $guest->identity_number_hash,
                    $this->identityHash((string) $attributes['identity_number']),
                ) && $guest->identity_type === strtolower(trim((string) ($attributes['identity_type'] ?? '')));
                if (! $identityMatches) {
                    throw new GuestException('The supplied identity does not match the existing guest profile.');
                }
                unset($attributes['identity_type'], $attributes['identity_number']);
            }
            $guest->fill($this->payload($attributes));
            $this->applyIdentity($guest, $attributes);
            $this->assertValid($guest);
            $guest->forceFill([
                'created_by' => $guest->exists ? $guest->created_by : null,
                'updated_by' => null,
            ])->save();

            $this->activities->record(
                activityType: $created ? 'property-booking.guest.created' : 'property-booking.guest.resolved',
                description: $created ? 'Booking guest created through storefront checkout' : 'Existing booking guest resolved for storefront checkout',
                actor: $account,
                subject: $guest,
                properties: [
                    'has_account' => $guest->user_id !== null,
                    'has_identity' => $guest->identity_number_hash !== null,
                    'source' => 'storefront',
                ],
                severity: SystemActivitySeverity::Notice,
                source: 'property-booking-storefront',
            );

            return $guest->refresh();
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(Guest $guest, array $attributes, User $actor): Guest
    {
        return $this->database->transaction(function () use ($guest, $attributes, $actor): Guest {
            $guest = Guest::query()->lockForUpdate()->findOrFail($guest->getKey());
            $guest->fill($this->payload($attributes));
            $this->applyIdentity($guest, $attributes);
            $this->assertValid($guest);
            $guest->forceFill(['updated_by' => $actor->getKey()]);
            $changes = array_values(array_diff(array_keys($guest->getDirty()), [
                'updated_by', 'identity_number_ciphertext', 'identity_number_hash',
            ]));
            if (array_key_exists('identity_number', $attributes)) {
                $changes[] = 'identity_number';
            }
            $guest->save();

            $this->activities->record(
                activityType: 'property-booking.guest.updated',
                description: "Booking guest updated: {$guest->fullName()}",
                actor: $actor,
                subject: $guest,
                properties: ['changed_fields' => array_values(array_unique($changes))],
                source: 'property-booking-guests',
            );

            return $guest->refresh();
        });
    }

    /** Find a guest by a normalized protected identity fingerprint. */
    public function findByIdentity(string $type, string $number): ?Guest
    {
        return Guest::query()
            ->where('identity_type', strtolower(trim($type)))
            ->where('identity_number_hash', $this->identityHash($number))
            ->first();
    }

    /** Soft archive a guest while retaining booking and financial snapshots. */
    public function archive(Guest $guest, User $actor): void
    {
        $this->database->transaction(function () use ($guest, $actor): void {
            $guest = Guest::query()->lockForUpdate()->findOrFail($guest->getKey());
            $this->activities->record(
                activityType: 'property-booking.guest.archived',
                description: "Booking guest archived: {$guest->fullName()}",
                actor: $actor,
                subject: $guest,
                properties: ['booking_assignments_count' => $guest->bookingAssignments()->count()],
                severity: SystemActivitySeverity::Notice,
                source: 'property-booking-guests',
            );
            $guest->delete();
        });
    }

    /** Restore an archived guest without changing immutable booking snapshots. */
    public function restore(Guest $guest, User $actor): Guest
    {
        return $this->database->transaction(function () use ($guest, $actor): Guest {
            $guest = Guest::withTrashed()->lockForUpdate()->findOrFail($guest->getKey());
            if ($guest->trashed()) {
                $guest->restore();
            }
            $guest->forceFill(['updated_by' => $actor->getKey()])->save();
            $this->activities->record(
                activityType: 'property-booking.guest.restored',
                description: "Booking guest restored: {$guest->fullName()}",
                actor: $actor,
                subject: $guest,
                properties: ['booking_assignments_count' => $guest->bookingAssignments()->count()],
                severity: SystemActivitySeverity::Notice,
                source: 'property-booking-guests',
            );

            return $guest->refresh();
        });
    }

    /** Decrypt identity only for an already-authorized operational caller. */
    public function revealIdentity(Guest $guest): ?string
    {
        return $guest->identity_number_ciphertext === null
            ? null
            : $this->encrypter->decryptString($guest->identity_number_ciphertext);
    }

    /** @param array<string, mixed> $attributes */
    private function payload(array $attributes): array
    {
        $payload = Arr::only($attributes, [
            'user_id', 'title', 'first_name', 'middle_name', 'last_name', 'email', 'phone',
            'alternate_phone', 'date_of_birth', 'nationality_country_code', 'identity_type',
            'identity_country_code', 'address_line_1', 'address_line_2', 'city', 'region',
            'postal_code', 'country_code', 'emergency_contact_name', 'emergency_contact_phone', 'note',
        ]);

        foreach ($payload as $key => $value) {
            if (is_string($value)) {
                $payload[$key] = trim($value) === '' ? null : trim($value);
            }
        }

        foreach (['nationality_country_code', 'identity_country_code', 'country_code'] as $key) {
            if (filled($payload[$key] ?? null)) {
                $payload[$key] = strtoupper((string) $payload[$key]);
            }
        }
        if (filled($payload['email'] ?? null)) {
            $payload['email'] = strtolower((string) $payload['email']);
        }
        if (filled($payload['identity_type'] ?? null)) {
            $payload['identity_type'] = strtolower((string) $payload['identity_type']);
        }

        return $payload;
    }

    /** @param array<string, mixed> $attributes */
    private function applyIdentity(Guest $guest, array $attributes): void
    {
        if (! array_key_exists('identity_number', $attributes)) {
            return;
        }

        $number = trim((string) ($attributes['identity_number'] ?? ''));
        if ($number === '') {
            $guest->forceFill(['identity_number_ciphertext' => null, 'identity_number_hash' => null]);

            return;
        }

        if (blank($guest->identity_type)) {
            throw new GuestException('An identity type is required when an identity number is supplied.');
        }

        $guest->forceFill([
            'identity_number_ciphertext' => $this->encrypter->encryptString($number),
            'identity_number_hash' => $this->identityHash($number),
        ]);
    }

    /** Create the keyed identity fingerprint used for exact lookup. */
    private function identityHash(string $number): string
    {
        $normalized = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($number))) ?? '';
        if ($normalized === '') {
            throw new GuestException('The identity number must contain letters or digits.');
        }

        $key = (string) config('property-booking.identity.hash_key', '');
        $key = $key !== '' ? $key : (string) config('app.key');
        if ($key === '') {
            throw new GuestException('Guest identity hashing requires an application or dedicated identity key.');
        }

        return hash_hmac('sha256', $normalized, $key);
    }

    /** Validate the normalized domain input. */
    private function assertValid(Guest $guest): void
    {
        if (blank($guest->first_name) || blank($guest->last_name)) {
            throw new GuestException('Guests require a first and last name.');
        }
        if ($guest->email !== null && filter_var($guest->email, FILTER_VALIDATE_EMAIL) === false) {
            throw new GuestException('The guest email address is invalid.');
        }
        if ($guest->date_of_birth?->isFuture() === true) {
            throw new GuestException('The guest date of birth cannot be in the future.');
        }

        foreach (['nationality_country_code', 'identity_country_code', 'country_code'] as $key) {
            $value = $guest->getAttribute($key);
            if ($value !== null && strlen((string) $value) !== 2) {
                throw new GuestException('Guest country codes must contain two letters.');
            }
        }
    }
}
