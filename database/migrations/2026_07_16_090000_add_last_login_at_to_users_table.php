<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records when each user most recently completed an authenticated login.
 *
 * The column is server-controlled (never mass assignable) and stamped by
 * {@see User::recordLogin()} only after authentication is fully
 * established — for two-factor accounts that means after the OTP challenge
 * passes, not at the password step. It powers "last seen" columns in user
 * lists and dormant-account checks without querying the system activity log.
 *
 * The `->comment()` metadata is a repository documentation standard; it is a
 * silent no-op on SQLite (local dev) and materializes on MySQL/PostgreSQL.
 */
return new class extends Migration
{
    /**
     * Add the last-login timestamp column.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('last_login_at')
                ->nullable()
                ->after('two_factor_confirmed_at')
                ->comment('Timestamp of the most recent completed login (post-2FA when 2FA is active); null until first sign-in');
        });
    }

    /**
     * Remove the last-login timestamp column.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('last_login_at');
        });
    }
};
