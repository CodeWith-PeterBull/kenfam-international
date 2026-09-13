<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two-factor verification attempt audit log.
 *
 * Every code-verification attempt — success or failure — is recorded here
 * (governed by `two-factor.log_attempts`). The table is doubly load-bearing:
 *
 *  1. Forensics: who attempted what, from where, and why it failed. Only a
 *     SHA-256 digest of the submitted code is stored, never plaintext.
 *  2. Rate limiting: TwoFactorService counts recent failed rows (excluding
 *     `rate_limited` block events, so the lockout cannot extend itself — a
 *     CSK benchmark defect fixed here) to enforce the sliding-window limit.
 *
 * Rows are immutable audit records: `$timestamps` is disabled on the model
 * and only `created_at` exists. Retention is enforced by the scheduled
 * `two-factor:prune` command (`two-factor.cleanup.attempt_retention_days`).
 */
return new class extends Migration
{
    /**
     * Create the two_factor_attempts table.
     */
    public function up(): void
    {
        Schema::create('two_factor_attempts', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->cascadeOnDelete()
                ->comment('Challenged user; nullable so attempts without a resolvable user can still be audited');

            $table->string('ip_address', 45)
                ->comment('Request origin IP (45 chars accommodates IPv6)');

            $table->string('attempted_code_hash', 64)
                ->nullable()
                ->comment('SHA-256 digest of the submitted code — plaintext is never persisted');

            $table->boolean('success')
                ->default(false)
                ->comment('Whether this attempt passed verification');

            $table->string('failure_reason', 50)
                ->nullable()
                ->comment('invalid_code | expired | rate_limited; null on success');

            $table->timestamp('created_at')
                ->useCurrent()
                ->comment('Attempt time; no updated_at — rows are immutable audit records');

            $table->index(['user_id', 'created_at'], 'idx_user_attempts');
            $table->index(['ip_address', 'created_at'], 'idx_ip_attempts');
            $table->index(['success', 'created_at'], 'idx_success_audit');
        });
    }

    /**
     * Drop the two_factor_attempts table.
     */
    public function down(): void
    {
        Schema::dropIfExists('two_factor_attempts');
    }
};
