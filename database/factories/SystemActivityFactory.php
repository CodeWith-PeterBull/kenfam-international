<?php

namespace Database\Factories;

use App\Enums\SystemActivitySeverity;
use App\Models\SystemActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SystemActivity>
 */
class SystemActivityFactory extends Factory
{
    protected $model = SystemActivity::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'batch_uuid' => null,
            'user_id' => null,
            'activity_type' => fake()->randomElement([
                'content.created',
                'content.updated',
                'event.published',
                'user.login',
            ]),
            'severity' => SystemActivitySeverity::Info,
            'source' => 'application',
            'description' => fake()->sentence(),
            'subject_type' => null,
            'subject_id' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'request_method' => 'GET',
            'route_name' => 'admin.dashboard',
            'request_url' => fake()->url(),
            'properties' => ['fixture' => true],
            'created_at' => fake()->dateTimeBetween('-30 days'),
        ];
    }

    public function forActor(User $actor): static
    {
        return $this->state(fn (): array => ['user_id' => $actor->getKey()]);
    }

    public function withSeverity(SystemActivitySeverity $severity): static
    {
        return $this->state(fn (): array => ['severity' => $severity]);
    }
}
