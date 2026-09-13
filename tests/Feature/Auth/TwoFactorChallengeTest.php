<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Login-gate behaviour: when the email-OTP challenge is (and is not) issued.
 *
 * The challenge requires BOTH the global `two-factor.enabled` switch (kept
 * off in phpunit.xml, enabled per-test) and the user's own opt-in flag
 * (via the UserFactory twoFactorEnabled state).
 */
class TwoFactorChallengeTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_two_factor_redirects_to_the_challenge_as_a_guest(): void
    {
        config(['two-factor.enabled' => true]);
        Notification::fake();

        $user = User::factory()->twoFactorEnabled()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '1',
        ]);

        // The user is parked as a guest with only the pending identity stashed.
        $response->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
        $response->assertSessionHas('two_factor:user_id', $user->id);
        $response->assertSessionHas('two_factor:remember', true);

        // A code was persisted (hashed) and dispatched by mail.
        $user->refresh();
        $this->assertNotNull($user->two_factor_code_hash);
        $this->assertTrue($user->two_factor_code_expires_at->isFuture());
        Notification::assertSentTo($user, TwoFactorCodeNotification::class);

        // Authentication is NOT complete at the password step: last-login is
        // only stamped once the OTP challenge itself is passed.
        $this->assertNull($user->last_login_at);
    }

    public function test_login_bypasses_the_challenge_when_the_global_switch_is_off(): void
    {
        // phpunit.xml pins TWO_FACTOR_ENABLED=false; assert the default holds.
        Notification::fake();

        $user = User::factory()->twoFactorEnabled()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
        Notification::assertNothingSent();
    }

    public function test_login_bypasses_the_challenge_for_users_who_have_not_opted_in(): void
    {
        config(['two-factor.enabled' => true]);
        Notification::fake();

        // Factory default: two_factor_enabled = false (self-service opt-in).
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
        Notification::assertNothingSent();
    }

    public function test_login_bypasses_the_challenge_for_exempt_emails(): void
    {
        config(['two-factor.enabled' => true]);
        Notification::fake();

        $user = User::factory()->twoFactorEnabled()->create();
        config(['two-factor.exempt_emails' => [$user->email]]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
        Notification::assertNothingSent();
    }

    public function test_the_challenge_screen_renders_for_a_pending_challenge(): void
    {
        config(['two-factor.enabled' => true]);
        Notification::fake();

        $user = User::factory()->twoFactorEnabled()->create(['email' => 'peter@aureon.test']);

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $response = $this->get(route('two-factor.challenge'));

        $response->assertOk();
        $response->assertSee('Check your email');
        // The delivery address is masked, never disclosed in full pre-auth.
        $response->assertSee('pe***@aureon.test');
        $response->assertDontSee('peter@aureon.test');
    }

    public function test_the_challenge_screen_bounces_visitors_without_a_pending_challenge(): void
    {
        $this->get(route('two-factor.challenge'))
            ->assertRedirect(route('login'));
    }
}
