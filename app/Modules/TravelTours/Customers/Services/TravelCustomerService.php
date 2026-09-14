<?php

/**
 * Implements a focused TravelTours domain or application service.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Customers\Services;

use App\Modules\TravelTours\Customers\Data\TravelCustomerData;
use App\Modules\TravelTours\Customers\Enums\CustomerStatus;
use App\Modules\TravelTours\Customers\Exceptions\CustomerIdentityConflict;
use App\Modules\TravelTours\Customers\Models\TravelCustomer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/** Normalizes customer contact identity and preserves one reusable profile. */
final class TravelCustomerService
{
    /**
     * Resolve an existing contact identity or create a new customer profile.
     *
     * Existing non-empty contact data is never silently replaced by booking
     * input. Profile changes belong to a separately authorized update workflow.
     */
    public function resolve(TravelCustomerData $data, ?int $actorId = null): TravelCustomer
    {
        $email = filled($data->email) ? Str::lower(trim((string) $data->email)) : null;
        $phone = $this->normalizePhone($data->phone);
        $emailHash = $email ? $this->lookupHash($email) : null;
        $phoneHash = $phone ? $this->lookupHash($phone) : null;

        $matches = $this->matchingCustomers($emailHash, $phoneHash);
        if ($matches->count() > 1) {
            throw new CustomerIdentityConflict('The supplied email and phone belong to different customer profiles.');
        }

        $customer = $matches->first() ?? new TravelCustomer;
        $attributes = [
            'title' => $data->title,
            'first_name' => trim($data->firstName),
            'middle_name' => filled($data->middleName) ? trim((string) $data->middleName) : null,
            'last_name' => trim($data->lastName),
            'whatsapp_phone' => $this->normalizePhone($data->whatsappPhone),
            'nationality_code' => $data->nationalityCode !== null ? mb_strtoupper($data->nationalityCode) : null,
            'status' => CustomerStatus::Active,
            'updated_by' => $actorId,
        ];

        if (! $customer->exists || $customer->email_hash === null) {
            $attributes += ['email' => $email, 'email_hash' => $emailHash];
        }
        if (! $customer->exists || $customer->phone_hash === null) {
            $attributes += ['phone' => $phone, 'phone_hash' => $phoneHash];
        }
        if (! $customer->exists) {
            $attributes += ['contact_hash_version' => 1, 'created_by' => $actorId];
        }

        $customer->forceFill($attributes);
        $customer->save();

        return $customer;
    }

    /**
     * Locate all profiles matching either purpose-keyed contact hash.
     *
     * @return Collection<int, TravelCustomer>
     */
    private function matchingCustomers(?string $emailHash, ?string $phoneHash): Collection
    {
        if ($emailHash === null && $phoneHash === null) {
            return new Collection;
        }

        return TravelCustomer::query()
            ->where(function ($query) use ($emailHash, $phoneHash): void {
                $query->when($emailHash, fn ($emailQuery) => $emailQuery->where('email_hash', $emailHash))
                    ->when($phoneHash, fn ($phoneQuery) => $phoneQuery->orWhere('phone_hash', $phoneHash));
            })
            ->lockForUpdate()
            ->get();
    }

    /** Normalize phone punctuation while retaining an explicit international prefix. */
    private function normalizePhone(mixed $phone): ?string
    {
        if (blank($phone)) {
            return null;
        }
        $normalized = preg_replace('/[^0-9+]/', '', trim((string) $phone));

        return filled($normalized) ? $normalized : null;
    }

    /** Derive a purpose-specific non-reversible lookup hash. */
    private function lookupHash(string $value): string
    {
        return hash_hmac('sha256', 'travel-customer-contact:v1:'.$value, (string) config('app.key'));
    }
}
