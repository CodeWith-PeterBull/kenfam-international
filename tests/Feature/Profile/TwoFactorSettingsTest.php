<?php

namespace Tests\Feature\Profile;

use App\Livewire\Profile\TwoFactorSettings;
use App\Models\TwoFactorSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Self-service Security panel: password-confirmed enable/disable of the
 * email-OTP second factor from the profile page.
 */
class TwoFactorSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_profile_page_embeds_the_security_panel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Security — two-factor authentication')
            ->assertSeeLivewire(TwoFactorSettings::class);
    }

    public function test_a_user_can_enable_two_factor_with_their_current_password(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(TwoFactorSettings::class)
            ->assertSet('enabled', false)
            ->set('currentPassword', 'password')
            ->call('enable')
            ->assertHasNoErrors()
            ->assertSet('enabled', true)
            // The plaintext must never linger in component state.
            ->assertSet('currentPassword', '');

        $this->assertTrue($user->refresh()->two_factor_enabled);
        $this->assertDatabaseHas('system_activities', [
            'activity_type' => 'auth.two_factor.enabled',
            'user_id' => $user->id,
        ]);
    }

    public function test_a_wrong_password_blocks_the_toggle(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(TwoFactorSettings::class)
            ->set('currentPassword', 'not-the-password')
            ->call('enable')
            ->assertHasErrors('currentPassword')
            ->assertSet('enabled', false)
            ->assertSet('currentPassword', '');

        $this->assertFalse($user->refresh()->two_factor_enabled);
    }

    public function test_disabling_clears_pending_challenge_state_and_verified_sessions(): void
    {
        $user = User::factory()->twoFactorEnabled()->create();

        // Simulate an in-flight challenge plus a verified-session row.
        $user->forceFill([
            'two_factor_code_hash' => str_repeat('a', 64),
            'two_factor_code_expires_at' => now()->addMinutes(5),
        ])->save();
        $user->twoFactorSessions()->create([
            'session_id' => 'test-session-id',
            'ip_address' => '127.0.0.1',
            'verified_at' => now(),
            'expires_at' => now()->addDay(),
        ]);

        Livewire::actingAs($user)
            ->test(TwoFactorSettings::class)
            ->assertSet('enabled', true)
            ->set('currentPassword', 'password')
            ->call('disable')
            ->assertHasNoErrors()
            ->assertSet('enabled', false);

        $user->refresh();
        $this->assertFalse($user->two_factor_enabled);
        $this->assertNull($user->two_factor_code_hash);
        $this->assertNull($user->two_factor_code_expires_at);
        $this->assertSame(0, TwoFactorSession::query()->where('user_id', $user->id)->count());
        $this->assertDatabaseHas('system_activities', [
            'activity_type' => 'auth.two_factor.disabled',
            'user_id' => $user->id,
        ]);
    }

    public function test_the_panel_explains_when_the_global_switch_is_off(): void
    {
        // phpunit.xml pins TWO_FACTOR_ENABLED=false.
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(TwoFactorSettings::class)
            ->assertSee('System-wide two-factor authentication is currently switched off');
    }
}
