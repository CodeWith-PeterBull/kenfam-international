<?php

namespace Tests\Feature\Auth;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_new_registrations_are_assigned_the_viewer_role(): void
    {
        // Deliberately unseeded: RegisteredUserController uses
        // Role::findOrCreate so self-registration works (and stays
        // least-privileged) even before RoleSeeder has run.
        $this->post('/register', [
            'name' => 'Role Contract User',
            'email' => 'role-contract@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::query()->where('email', 'role-contract@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('viewer'));
        $this->assertSame(['viewer'], $user->getRoleNames()->all());
        $this->assertSame(UserType::Viewer, $user->user_type);
        $this->assertTrue($user->is_active);
    }
}
