<?php

namespace Database\Factories;

use App\Enums\UserType;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'user_type' => UserType::Viewer,
            'is_active' => true,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Mark the account as having opted in to two-factor authentication.
     *
     * Pair with config(['two-factor.enabled' => true]) in tests — the login
     * challenge only fires when both the global master switch and this
     * per-user flag are on.
     */
    public function twoFactorEnabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_enabled' => true,
        ]);
    }

    /**
     * Create a complete personal profile after the account is persisted.
     */
    public function withProfile(array $attributes = []): static
    {
        return $this->afterCreating(function (User $user) use ($attributes): void {
            UserProfile::factory()->create([
                ...$attributes,
                'user_id' => $user->id,
            ]);
        });
    }
}
