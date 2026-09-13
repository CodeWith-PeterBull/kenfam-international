<?php

namespace Tests\Feature\Auth;

use App\Models\TwoFactorAttempt;
use App\Models\TwoFactorSession;
use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use ReflectionProperty;
use Tests\TestCase;

/**
 * Challenge verification: code entry, failure modes, rate limiting, resend,
 * and the session/audit side effects of a successful second factor.
 */
class TwoFactorVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['two-factor.enabled' => true]);
        Notification::fake();
    }

    /**
     * Log the user in up to the challenge and return the plaintext OTP,
     * recovered from the faked notification via reflection (storage only
     * ever holds the keyed digest).
     */
    protected function issueChallenge(User $user): string
    {
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('two-factor.challenge'));

        $notification = Notification::sent($user, TwoFactorCodeNotification::class)->last();

        return (new ReflectionProperty(TwoFactorCodeNotification::class, 'code'))
            ->getValue($notification);
    }

    public function test_a_valid_code_completes_the_login(): void
    {
        $user = User::factory()->twoFactorEnabled()->create();
        $code = $this->issueChallenge($user);

        $response = $this->post(route('two-factor.verify'), ['code' => $code]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);

        // Challenge state is consumed and the confirmation stamped.
        $user->refresh();
        $this->assertNull($user->two_factor_code_hash);
        $this->assertNull($user->two_factor_code_expires_at);
        $this->assertNotNull($user->two_factor_confirmed_at);

        // Session keys are dropped and the success is audited.
        $response->assertSessionMissing('two_factor:user_id');
        $this->assertSame(1, TwoFactorAttempt::query()->where('success', true)->count());
        $this->assertDatabaseHas('system_activities', [
            'activity_type' => 'auth.two_factor.challenge_passed',
            'user_id' => $user->id,
        ]);
    }

    public function test_completing_the_challenge_stamps_the_last_login_timestamp(): void
    {
        $user = User::factory()->twoFactorEnabled()->create(['last_login_at' => null]);
        $code = $this->issueChallenge($user);

        // Not stamped by the password step that issued the challenge...
        $this->assertNull($user->refresh()->last_login_at);

        $this->post(route('two-factor.verify'), ['code' => $code]);

        // ...only once the second factor is verified.
        $this->assertNotNull($user->refresh()->last_login_at);
        $this->assertTrue($user->last_login_at->isToday());
    }

    public function test_the_verified_session_row_records_the_post_login_session_id(): void
    {
        $user = User::factory()->twoFactorEnabled()->create();
        $code = $this->issueChallenge($user);

        $this->post(route('two-factor.verify'), ['code' => $code]);

        // Regenerate-then-record ordering (fixes the CSK benchmark defect):
        // the stored id must be the LIVE session id after login.
        $sessionRecord = TwoFactorSession::query()->sole();
        $this->assertSame(session()->getId(), $sessionRecord->session_id);
        $this->assertTrue($sessionRecord->expires_at->isFuture());
    }

    public function test_a_successful_challenge_auto_verifies_an_unverified_email(): void
    {
        Event::fake([Verified::class]);

        $user = User::factory()->twoFactorEnabled()->unverified()->create();
        $code = $this->issueChallenge($user);

        $this->post(route('two-factor.verify'), ['code' => $code]);

        // Receiving the emailed code proves mailbox ownership.
        $this->assertTrue($user->refresh()->hasVerifiedEmail());
        Event::assertDispatched(Verified::class);
    }

    public function test_an_invalid_code_is_rejected_and_audited(): void
    {
        $user = User::factory()->twoFactorEnabled()->create();
        $code = $this->issueChallenge($user);

        $wrong = $code === '000000' ? '111111' : '000000';
        $response = $this->post(route('two-factor.verify'), ['code' => $wrong]);

        $response->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->assertDatabaseHas('two_factor_attempts', [
            'user_id' => $user->id,
            'success' => false,
            'failure_reason' => 'invalid_code',
        ]);
    }

    public function test_an_expired_code_is_rejected(): void
    {
        $user = User::factory()->twoFactorEnabled()->create();
        $code = $this->issueChallenge($user);

        $this->travel(11)->minutes();

        $this->post(route('two-factor.verify'), ['code' => $code])
            ->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->assertDatabaseHas('two_factor_attempts', [
            'user_id' => $user->id,
            'failure_reason' => 'expired',
        ]);
    }

    public function test_five_failures_lock_out_even_the_correct_code(): void
    {
        $user = User::factory()->twoFactorEnabled()->create();
        $code = $this->issueChallenge($user);
        $wrong = $code === '000000' ? '111111' : '000000';

        foreach (range(1, 5) as $ignored) {
            $this->post(route('two-factor.verify'), ['code' => $wrong]);
        }

        // The correct code is now refused and the block itself is audited...
        $this->post(route('two-factor.verify'), ['code' => $code])
            ->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->assertDatabaseHas('two_factor_attempts', [
            'user_id' => $user->id,
            'failure_reason' => 'rate_limited',
        ]);

        // ...but block events do not extend the window (D4): once the five
        // genuine failures age past the 15-minute decay, the lockout lifts.
        // The original code has expired by then, so request a fresh one.
        $this->travel(16)->minutes();
        $newCode = $this->refreshChallengeCode($user);

        $this->post(route('two-factor.verify'), ['code' => $newCode])
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
    }

    public function test_resend_generates_a_fresh_code_and_invalidates_the_old_one(): void
    {
        $user = User::factory()->twoFactorEnabled()->create();
        $oldCode = $this->issueChallenge($user);
        $oldHash = $user->refresh()->two_factor_code_hash;

        $this->post(route('two-factor.resend'))->assertRedirect();

        // A second notification carries a new code; the stored digest rotated.
        $this->assertSame(2, Notification::sent($user, TwoFactorCodeNotification::class)->count());
        $this->assertNotSame($oldHash, $user->refresh()->two_factor_code_hash);

        // The superseded code no longer verifies.
        $this->post(route('two-factor.verify'), ['code' => $oldCode])
            ->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_verification_without_a_pending_challenge_bounces_to_login(): void
    {
        $this->post(route('two-factor.verify'), ['code' => '123456'])
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_the_verify_endpoint_is_throttled_at_the_transport_level(): void
    {
        $user = User::factory()->twoFactorEnabled()->create();
        $this->issueChallenge($user);

        // Named limiter `two-factor`: 10 requests per minute per pending user.
        foreach (range(1, 10) as $ignored) {
            $this->post(route('two-factor.verify'), ['code' => '000000'])
                ->assertRedirect();
        }

        $this->post(route('two-factor.verify'), ['code' => '000000'])
            ->assertStatus(429);
    }

    /**
     * Ask the resend endpoint for a fresh code mid-challenge and return its
     * plaintext (again recovered from the faked notification).
     */
    protected function refreshChallengeCode(User $user): string
    {
        $this->post(route('two-factor.resend'));

        $notification = Notification::sent($user, TwoFactorCodeNotification::class)->last();

        return (new ReflectionProperty(TwoFactorCodeNotification::class, 'code'))
            ->getValue($notification);
    }
}
