<?php

namespace App\Providers;

use App\Contracts\RecordsSystemActivity;
use App\Contracts\RendersPdfReports;
use App\Contracts\ResolvesInstitutionProfile;
use App\Enums\UserType;
use App\Models\User;
use App\Services\InstitutionProfileResolver;
use App\Services\PdfReportService;
use App\Services\SystemActivityService;
use App\Support\CmsPermission;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Opcodes\LogViewer\Facades\LogViewer;
use Opcodes\LogViewer\LogFile;
use Opcodes\LogViewer\LogFolder;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(RecordsSystemActivity::class, SystemActivityService::class);
        $this->app->scoped(ResolvesInstitutionProfile::class, InstitutionProfileResolver::class);
        $this->app->bind(RendersPdfReports::class, PdfReportService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(static function (mixed $user, string $ability): ?bool {
            if (! $user instanceof User) {
                return null;
            }

            if (! $user->is_active) {
                return false;
            }

            if ($user->isSystemAdministrator() || $user->hasRole(UserType::SystemAdministrator->value)) {
                return true;
            }

            return null;
        });

        // Transport-level throttles for the two-factor challenge endpoints.
        // These back up (not replace) TwoFactorService's database-audited
        // rate limiter: `two-factor` caps verification POSTs and
        // `two-factor-resend` caps how fast OTP emails can be triggered.
        // Keyed by the pending challenge user id so an attacker cannot reset
        // the budget by rotating IPs; falls back to IP when no challenge is
        // in progress.
        RateLimiter::for('two-factor', static function (Request $request): Limit {
            return Limit::perMinute(10)->by(
                '2fa:'.($request->session()->get('two_factor:user_id') ?: $request->ip())
            );
        });

        RateLimiter::for('two-factor-resend', static function (Request $request): Limit {
            return Limit::perMinutes(5, 3)->by(
                '2fa-resend:'.($request->session()->get('two_factor:user_id') ?: $request->ip())
            );
        });

        RateLimiter::for('communication-test-mail', static function (Request $request): Limit {
            return Limit::perMinutes(10, 3)->by('communication-test-mail:'.($request->user()?->id ?: $request->ip()));
        });

        LogViewer::auth(static function ($request): bool {
            return $request->user()?->can(CmsPermission::VIEW_APPLICATION_LOGS) === true;
        });

        Gate::define('downloadLogFile', static function (mixed $user, LogFile $file): bool {
            return $user?->can(CmsPermission::VIEW_APPLICATION_LOGS) === true;
        });

        Gate::define('downloadLogFolder', static function (mixed $user, LogFolder $folder): bool {
            return $user?->can(CmsPermission::VIEW_APPLICATION_LOGS) === true;
        });

        Gate::define('deleteLogFile', static function (mixed $user, LogFile $file): bool {
            return $user?->can(CmsPermission::MANAGE_APPLICATION_LOGS) === true;
        });

        Gate::define('deleteLogFolder', static function (mixed $user, LogFolder $folder): bool {
            return $user?->can(CmsPermission::MANAGE_APPLICATION_LOGS) === true;
        });
    }
}
