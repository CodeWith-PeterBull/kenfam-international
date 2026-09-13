<?php

namespace App\Console\Commands;

use App\Services\TwoFactorService;
use Illuminate\Console\Command;

/**
 * Housekeeping for the two-factor authentication subsystem.
 *
 * Deletes verified-session rows past their validity horizon and audit
 * attempts older than the configured retention window
 * (`two-factor.cleanup.attempt_retention_days`, default 90 days).
 *
 * Scheduled daily in routes/console.php — the CSK benchmark shipped the
 * cleanup methods but never scheduled them (defect fixed here). Safe to run
 * manually at any time: `php artisan two-factor:prune`.
 */
class PruneTwoFactorData extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'two-factor:prune';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete expired two-factor verified sessions and audit attempts past the retention window';

    /**
     * Run both cleanup passes and report the deleted row counts.
     */
    public function handle(TwoFactorService $twoFactorService): int
    {
        $sessions = $twoFactorService->cleanupExpiredSessions();
        $attempts = $twoFactorService->cleanupOldAttempts();

        $this->info("Pruned {$sessions} expired verified session(s) and {$attempts} aged attempt record(s).");

        return self::SUCCESS;
    }
}
