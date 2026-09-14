<?php

namespace Database\Seeders;

use App\Enums\UserType;
use App\Models\User;
use App\Modules\TravelTours\Database\Seeders\TravelToursAccessSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call(InstitutionDetailsSeeder::class);

        if (config('travel-tours.enabled', false)) {
            $this->call(TravelToursAccessSeeder::class);
        }

        $this->seedAccount(
            type: UserType::SystemAdministrator,
            username: 'Kenfam Administrator',
            email: 'admin@kenfam.test',
            firstName: 'Kenfam',
            lastName: 'Administrator',
            jobTitle: 'Platform administrator',
        );
        $this->seedAccount(
            type: UserType::ContentManager,
            username: 'content.manager',
            email: 'content@kenfam.test',
            firstName: 'Content',
            lastName: 'Manager',
            jobTitle: 'Content manager',
        );
        $this->seedAccount(
            type: UserType::Editor,
            username: 'kenfam.editor',
            email: 'editor@kenfam.test',
            firstName: 'Kenfam',
            lastName: 'Editor',
            jobTitle: 'Content editor',
        );
        $this->seedAccount(
            type: UserType::Viewer,
            username: 'kenfam.viewer',
            email: 'viewer@kenfam.test',
            firstName: 'Kenfam',
            lastName: 'Viewer',
            jobTitle: 'Corporate viewer',
        );
    }

    private function seedAccount(
        UserType $type,
        string $username,
        string $email,
        string $firstName,
        string $lastName,
        string $jobTitle,
    ): void {
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $username,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $user->forceFill([
            'user_type' => $type,
            'is_active' => true,
        ])->save();
        $user->assignRole($type->value);
        $user->profile()->firstOrCreate([], [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'job_title' => $jobTitle,
        ]);
    }
}
