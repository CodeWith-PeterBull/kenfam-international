<?php

/**
 * Provides opt-in demonstration operators for local TravelTours evaluation.
 *
 * This seeder is intentionally absent from DatabaseSeeder. Adopters must call
 * it explicitly and must never treat its credentials as production accounts.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Database\Seeders;

use App\Enums\UserType;
use App\Models\User;
use App\Modules\TravelTours\Support\TravelToursRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/** Create deterministic local operators only when explicitly requested. */
final class TravelToursDemoOperatorSeeder extends Seeder
{
    /** Reconcile access first, then create one identity for every module role. */
    public function run(): void
    {
        if (! config('travel-tours.enabled', false)) {
            return;
        }

        $this->call(TravelToursAccessSeeder::class);
        $this->operator('travel.manager@example.test', 'travel.manager', 'Travel', 'Manager', TravelToursRole::MANAGER, UserType::ContentManager);
        $this->operator('booking.agent@example.test', 'booking.agent', 'Booking', 'Agent', TravelToursRole::BOOKING_AGENT, UserType::Viewer);
        $this->operator('tour.editor@example.test', 'tour.editor', 'Tour', 'Editor', TravelToursRole::TOUR_EDITOR, UserType::Editor);
    }

    /** Create or reconcile one local operator and its module role assignment. */
    private function operator(string $email, string $username, string $firstName, string $lastName, string $role, UserType $userType): void
    {
        $user = User::query()->firstOrCreate(['email' => $email], [
            'name' => $username,
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $user->forceFill(['user_type' => $userType, 'is_active' => true])->save();
        $user->syncRoles([$userType->value, $role]);
        $user->profile()->updateOrCreate([], [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'job_title' => str_replace('-', ' ', $role),
        ]);
    }
}
