<?php

namespace Database\Seeders;

use App\Enums\UserType;
use App\Models\User;
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

        $this->seedAccount(
            type: UserType::SystemAdministrator,
            username: 'Aureon Administrator',
            email: 'admin@aureon.test',
            firstName: 'Aureon',
            lastName: 'Administrator',
            jobTitle: 'Platform administrator',
        );
        $this->seedAccount(
            type: UserType::ContentManager,
            username: 'content.manager',
            email: 'content@aureon.test',
            firstName: 'Content',
            lastName: 'Manager',
            jobTitle: 'Content manager',
        );
        $this->seedAccount(
            type: UserType::Editor,
            username: 'aureon.editor',
            email: 'editor@aureon.test',
            firstName: 'Aureon',
            lastName: 'Editor',
            jobTitle: 'Content editor',
        );
        $this->seedAccount(
            type: UserType::Viewer,
            username: 'aureon.viewer',
            email: 'viewer@aureon.test',
            firstName: 'Aureon',
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
