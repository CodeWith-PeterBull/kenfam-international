<?php

namespace Tests\Feature;

use App\Models\TwoFactorAttempt;
use App\Models\TwoFactorSession;
use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use ReflectionProperty;
use Tests\TestCase;

/**
 * TwoFactorService unit-ish coverage at the feature layer: hashed-at-rest
 * storage, verification lifecycle, and housekeeping retention.
 */
class TwoFactorServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TwoFactorService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TwoFactorService::class);
        Notification::fake();
    }

    /**
     * Generate a code for the user and return its plaintext, recovered from
     * the faked notification (the database only ever holds a keyed digest).
     */
    protected function generateCodeFor(User $user): string
    {
        $this->assertTrue($this->service->generateAndSendCode($user));

        $notification = Notification::sent($user, TwoFactorCodeNotification::class)->last();

        return (new ReflectionProperty(TwoFactorCodeNotification::class, 'code'))
            ->getValue($notification);
    }

    public function test_codes_are_stored_as_app_key_hmac_digests_never_plaintext(): void
    {
        $user = User::factory()->create();
        $code = $this->generateCodeFor($user);

        $storedHash = $user->refresh()->two_factor_code_hash;

        $this->assertNotSame($code, $storedHash);
        $this->assertSame(
            hash_hmac('sha256', $code, (string) config('app.key')),
            $storedHash,
        );
        // A bare (unkeyed) hash would be offline-brute-forceable over the
        // one-million-value code space — assert we did not ship that.
        $this->assertNotSame(hash('sha256', $code), $storedHash);
    }

    public function test_verify_code_consumes_the_challenge_exactly_once(): void
    {
        $user = User::factory()->create();
        $code = $this->generateCodeFor($user);

        $this->assertTrue($this->service->verifyCode($user, $code));
        $this->assertNull($user->refresh()->two_factor_code_hash);
        $this->assertNotNull($user->two_factor_confirmed_at);

        // Replays fail: the digest is gone, so the attempt audits as expired.
        $this->assertFalse($this->service->verifyCode($user, $code));
        $this->assertDatabaseHas('two_factor_attempts', [
            'user_id' => $user->id,
            'failure_reason' => 'expired',
        ]);
    }

    public function test_audit_rows_store_a_sha256_of_the_attempted_code(): void
    {
        $user = User::factory()->create();
        $this->generateCodeFor($user);

        $this->service->verifyCode($user, '424242');

        $this->assertDatabaseHas('two_factor_attempts', [
            'user_id' => $user->id,
            'attempted_code_hash' => hash('sha256', '424242'),
            'failure_reason' => 'invalid_code',
        ]);
    }

    public function test_cleanup_honours_the_session_expiry_and_attempt_retention(): void
    {
        $user = User::factory()->create();

        // One live and one expired verified-session row.
        foreach ([now()->addDay(), now()->subDay()] as $expiresAt) {
            TwoFactorSession::query()->create([
                'user_id' => $user->id,
                'session_id' => 'session-'.$expiresAt->timestamp,
                'ip_address' => '127.0.0.1',
                'verified_at' => now()->subDays(2),
                'expires_at' => $expiresAt,
            ]);
        }

        // One fresh and one beyond-retention audit row (retention: 90 days).
        foreach ([now(), now()->subDays(91)] as $createdAt) {
            TwoFactorAttempt::query()->create([
                'user_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'attempted_code_hash' => null,
                'success' => false,
                'failure_reason' => 'invalid_code',
                'created_at' => $createdAt,
            ]);
        }

        $this->assertSame(1, $this->service->cleanupExpiredSessions());
        $this->assertSame(1, $this->service->cleanupOldAttempts());
        $this->assertSame(1, TwoFactorSession::query()->count());
        $this->assertSame(1, TwoFactorAttempt::query()->count());
    }

    public function test_the_prune_command_runs_both_cleanups(): void
    {
        $this->artisan('two-factor:prune')
            ->expectsOutputToContain('Pruned 0 expired verified session(s)')
            ->assertSuccessful();
    }
}
