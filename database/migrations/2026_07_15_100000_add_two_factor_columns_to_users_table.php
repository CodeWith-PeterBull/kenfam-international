<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Email-OTP two-factor authentication columns for the users table.
 *
 * Adapted from the CSK backend benchmark with two deliberate deviations,
 * both documented in `.docs/auth-2fa/`:
 *
 *  1. `two_factor_enabled` keeps a schema-safe FALSE default for lean public
 *     registration and direct inserts. Managed account creation explicitly
 *     starts the administrator-facing switch at TRUE.
 *  2. The active code is stored as `two_factor_code_hash` — an APP_KEY-keyed
 *     HMAC-SHA256 digest — instead of the benchmark's plaintext
 *     `two_factor_code` column, so a database leak does not expose live codes.
 *
 * Column `->comment()` metadata is a repository documentation standard; the
 * comments are silently skipped by SQLite (local dev) and materialize on
 * MySQL/PostgreSQL in production.
 */
return new class extends Migration
{
    /**
     * Add the 2FA state columns and the challenge-lookup index.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('two_factor_enabled')
                ->default(false)
                ->after('remember_token')
                ->comment('Self-service 2FA opt-in flag; Aureon deviation — CSK benchmark defaulted true (admin-granted)');

            $table->string('two_factor_code_hash', 64)
                ->nullable()
                ->after('two_factor_enabled')
                ->comment('HMAC-SHA256 (APP_KEY-keyed) digest of the active email OTP; null when no challenge is pending');

            $table->timestamp('two_factor_code_expires_at')
                ->nullable()
                ->after('two_factor_code_hash')
                ->comment('Expiry of the active OTP (dispatch time + two-factor.code.expiry_minutes); null when no challenge is pending');

            $table->timestamp('two_factor_confirmed_at')
                ->nullable()
                ->after('two_factor_code_expires_at')
                ->comment('Timestamp of the most recent successful OTP verification; shown on the profile Security panel');

            // Supports the challenge gate's "does this user have a live code" lookup.
            $table->index(['two_factor_enabled', 'two_factor_code_expires_at'], 'idx_2fa_code_lookup');
        });
    }

    /**
     * Remove the 2FA state columns and index.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('idx_2fa_code_lookup');
            $table->dropColumn([
                'two_factor_enabled',
                'two_factor_code_hash',
                'two_factor_code_expires_at',
                'two_factor_confirmed_at',
            ]);
        });
    }
};
