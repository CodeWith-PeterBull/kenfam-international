<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verified two-factor session records — FOUNDATION table.
 *
 * One row is written each time a user passes the email-OTP challenge,
 * capturing the post-regeneration session id, origin IP, and user agent
 * with an expiry of `two-factor.session.ttl_hours`.
 *
 * IMPORTANT: the login gate does NOT consult this table yet — every login
 * is challenged while 2FA is active. The rows currently serve as an audit
 * trail and as the storage foundation for a future "remember this device"
 * feature. The CSK benchmark attempted a session-id based 24-hour skip but
 * stored the pre-regeneration id, which never matched again; Aureon fixes
 * the ordering (regenerate first, then record) and defers the skip itself
 * to a device-token design described on the TwoFactorSession model and in
 * `.docs/auth-2fa/`.
 */
return new class extends Migration
{
    /**
     * Create the two_factor_sessions table.
     */
    public function up(): void
    {
        Schema::create('two_factor_sessions', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete()
                ->comment('Owner of the verified challenge; rows cascade away with account deletion');

            $table->string('session_id', 40)
                ->comment('Laravel session id captured AFTER post-login regeneration (fixes CSK ordering defect)');

            $table->string('ip_address', 45)
                ->comment('Request origin IP at verification time (45 chars accommodates IPv6)');

            $table->text('user_agent')
                ->nullable()
                ->comment('Browser user agent at verification time; audit/forensic aid');

            $table->timestamp('verified_at')
                ->useCurrent()
                ->comment('When the OTP challenge was passed');

            $table->timestamp('expires_at')
                ->nullable()
                ->comment('Row validity horizon (verified_at + two-factor.session.ttl_hours); pruned by two-factor:prune');

            $table->timestamps();

            // Future remember-device gate lookup: match by identifier within validity.
            $table->index(['session_id', 'expires_at'], 'idx_session_verification');
            $table->index('user_id', 'idx_user_sessions');
        });
    }

    /**
     * Drop the two_factor_sessions table.
     */
    public function down(): void
    {
        Schema::dropIfExists('two_factor_sessions');
    }
};
