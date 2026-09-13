<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Customers\Services;

use App\Contracts\RecordsSystemActivity;
use App\Enums\SystemActivitySeverity;
use App\Models\User;
use App\Modules\Commerce\Customers\Models\Customer;
use App\Modules\Commerce\Exceptions\CustomerException;
use App\Modules\Commerce\Orders\Data\CustomerSnapshotData;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Arr;

/**
 * Owns reusable customer writes while keeping order snapshots independent.
 */
final readonly class CustomerService
{
    public function __construct(
        private DatabaseManager $database,
        private RecordsSystemActivity $activities,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes, User $actor): Customer
    {
        return $this->database->transaction(function () use ($attributes, $actor): Customer {
            $customer = new Customer;
            $customer->fill($this->payload($attributes));
            $this->assertValid($customer);
            $customer->forceFill([
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ])->save();

            $this->activities->record(
                activityType: 'commerce.customer.created',
                description: "Customer created: {$customer->display_name}",
                actor: $actor,
                subject: $customer,
                properties: ['has_account' => $customer->user_id !== null],
                severity: SystemActivitySeverity::Notice,
                source: 'commerce-customers',
            );

            return $customer->refresh();
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(Customer $customer, array $attributes, User $actor): Customer
    {
        return $this->database->transaction(function () use ($customer, $attributes, $actor): Customer {
            $customer = Customer::query()->lockForUpdate()->findOrFail($customer->getKey());
            $customer->fill($this->payload($attributes));
            $this->assertValid($customer);
            $customer->forceFill(['updated_by' => $actor->getKey()]);
            $changes = array_values(array_diff(array_keys($customer->getDirty()), ['updated_by']));
            $customer->save();

            $this->activities->record(
                activityType: 'commerce.customer.updated',
                description: "Customer updated: {$customer->display_name}",
                actor: $actor,
                subject: $customer,
                properties: ['changed_fields' => $changes],
                source: 'commerce-customers',
            );

            return $customer->refresh();
        });
    }

    public function delete(Customer $customer, User $actor): void
    {
        $this->database->transaction(function () use ($customer, $actor): void {
            $customer = Customer::query()->lockForUpdate()->findOrFail($customer->getKey());

            $this->activities->record(
                activityType: 'commerce.customer.archived',
                description: "Customer archived: {$customer->display_name}",
                actor: $actor,
                subject: $customer,
                properties: ['orders_count' => $customer->orders()->count()],
                severity: SystemActivitySeverity::Notice,
                source: 'commerce-customers',
            );

            $customer->delete();
        });
    }

    /**
     * Restore an archived reusable customer without altering order snapshots.
     */
    public function restore(Customer $customer, User $actor): Customer
    {
        return $this->database->transaction(function () use ($customer, $actor): Customer {
            $customer = Customer::withTrashed()->lockForUpdate()->findOrFail($customer->getKey());
            if ($customer->trashed()) {
                $customer->restore();
            }
            $customer->forceFill(['updated_by' => $actor->getKey()])->save();

            $this->activities->record(
                activityType: 'commerce.customer.restored',
                description: "Customer restored: {$customer->display_name}",
                actor: $actor,
                subject: $customer,
                properties: ['orders_count' => $customer->orders()->count()],
                severity: SystemActivitySeverity::Notice,
                source: 'commerce-customers',
            );

            return $customer->refresh();
        });
    }

    /**
     * Resolve a reusable storefront customer without weakening order snapshots.
     *
     * Guest matching requires the same name, normalized email, and phone so
     * shared household or organization contact details are not merged. An
     * authenticated account takes precedence and may claim an exact guest
     * record created before registration.
     */
    public function resolveForCheckout(CustomerSnapshotData $snapshot, ?User $account = null): Customer
    {
        return $this->database->transaction(function () use ($snapshot, $account): Customer {
            $customer = $account instanceof User
                ? Customer::withTrashed()
                    ->where('user_id', $account->getKey())
                    ->lockForUpdate()
                    ->first()
                : null;

            $customer ??= $this->matchingGuest($snapshot);
            $customer ??= new Customer;

            if ($customer->trashed()) {
                $customer->restore();
            }

            $customer->fill([
                'user_id' => $account?->getKey(),
                'first_name' => $snapshot->firstName,
                'last_name' => $snapshot->lastName,
                'company' => $snapshot->company,
                'tax_identifier' => $snapshot->taxIdentifier,
                'email' => $snapshot->email,
                'phone' => $snapshot->phone,
                'address_line_1' => $snapshot->addressLine1,
                'address_line_2' => $snapshot->addressLine2,
                'city' => $snapshot->city,
                'region' => $snapshot->region,
                'postal_code' => $snapshot->postalCode,
                'country_code' => $snapshot->countryCode,
            ]);
            $this->assertValid($customer);
            $customer->save();

            return $customer->refresh();
        });
    }

    /** @return array<string, mixed> */
    private function payload(array $attributes): array
    {
        $payload = Arr::only($attributes, [
            'user_id', 'first_name', 'last_name', 'company', 'tax_identifier',
            'email', 'phone', 'address_line_1', 'address_line_2', 'city', 'region',
            'postal_code', 'country_code',
        ]);

        foreach ($payload as $key => $value) {
            if (is_string($value)) {
                $value = trim($value);
                $payload[$key] = $value === '' ? null : $value;
            }
        }

        if (filled($payload['email'] ?? null)) {
            $payload['email'] = strtolower((string) $payload['email']);
        }
        if (filled($payload['country_code'] ?? null)) {
            $payload['country_code'] = strtoupper((string) $payload['country_code']);
        }

        return $payload;
    }

    private function assertValid(Customer $customer): void
    {
        if (blank($customer->first_name) || blank($customer->last_name)) {
            throw new CustomerException('Customers require a first and last name.');
        }

        if (strlen((string) $customer->country_code) !== 2) {
            throw new CustomerException('Customer country codes must contain two letters.');
        }
    }

    /**
     * Match only an active, unlinked guest with an exact identity tuple.
     */
    private function matchingGuest(CustomerSnapshotData $snapshot): ?Customer
    {
        if ($snapshot->email === null || $snapshot->phone === null) {
            return null;
        }

        return Customer::query()
            ->whereNull('user_id')
            ->where('first_name', $snapshot->firstName)
            ->where('last_name', $snapshot->lastName)
            ->where('email', $snapshot->email)
            ->where('phone', $snapshot->phone)
            ->lockForUpdate()
            ->first();
    }
}
