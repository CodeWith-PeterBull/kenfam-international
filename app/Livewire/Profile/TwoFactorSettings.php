<?php

namespace App\Livewire\Profile;

use App\Contracts\RecordsSystemActivity;
use App\Enums\SystemActivitySeverity;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Self-service two-factor authentication panel (profile "Security" card).
 *
 * Lets the authenticated user opt in to — or out of — the email-OTP second
 * factor. This is the Aureon deviation from the CSK benchmark, where 2FA
 * was admin-granted with no user-facing control. Aureon supports both the
 * administrator-managed account preference and this user-owned control.
 *
 * Threat model / design notes:
 *  - Every toggle requires the CURRENT password (`current_password:web`
 *    rule) so a hijacked but unlocked session cannot silently weaken or
 *    reconfigure account security.
 *  - Disabling clears any pending OTP digest/expiry and hard-deletes the
 *    user's verified-session foundation rows, leaving no dormant state.
 *  - Both transitions are appended to the system activity stream.
 *  - The user's preference is stored regardless of the global
 *    `two-factor.enabled` master switch; the panel explains that challenges
 *    only occur while the system flag is on (see config/two-factor.php).
 *
 * Class-based Livewire component (see `.docs/auth-2fa/`): security logic
 * stays in a docblocked, Pint-covered PHP class with a stable FQCN for
 * Livewire::test(), while the Blade file remains presentation-only.
 */
class TwoFactorSettings extends Component
{
    /**
     * Mirrors the user's `two_factor_enabled` flag for the toggle UI.
     */
    public bool $enabled = false;

    /**
     * Current password, required for every state transition; never persisted
     * and cleared after each attempt.
     */
    public string $currentPassword = '';

    /**
     * Inline confirmation message rendered after a successful transition.
     */
    public ?string $status = null;

    /**
     * Seed the toggle state from the authenticated user.
     */
    public function mount(): void
    {
        $this->enabled = (bool) $this->user()->two_factor_enabled;
    }

    /**
     * Opt the user in to the email-OTP challenge.
     */
    public function enable(RecordsSystemActivity $systemActivity): void
    {
        $user = $this->confirmCurrentPassword();

        // forceFill: the 2FA columns are intentionally not mass assignable.
        $user->forceFill(['two_factor_enabled' => true])->save();

        $systemActivity->record(
            activityType: 'auth.two_factor.enabled',
            description: 'Enabled two-factor authentication from the profile security panel.',
            actor: $user,
            subject: $user,
            severity: SystemActivitySeverity::Notice,
        );

        $this->enabled = true;
        $this->status = __('Two-factor authentication is now enabled for your account.');
    }

    /**
     * Opt the user out and dismantle any in-flight challenge state:
     * pending OTP digest/expiry and all verified-session foundation rows.
     */
    public function disable(TwoFactorService $twoFactorService, RecordsSystemActivity $systemActivity): void
    {
        $user = $this->confirmCurrentPassword();

        $user->forceFill([
            'two_factor_enabled' => false,
            'two_factor_code_hash' => null,
            'two_factor_code_expires_at' => null,
        ])->save();

        $twoFactorService->invalidateUserSessions($user);

        $systemActivity->record(
            activityType: 'auth.two_factor.disabled',
            description: 'Disabled two-factor authentication from the profile security panel.',
            actor: $user,
            subject: $user,
            severity: SystemActivitySeverity::Notice,
        );

        $this->enabled = false;
        $this->status = __('Two-factor authentication has been disabled for your account.');
    }

    /**
     * Render the Security card with the ambient system state.
     */
    public function render(): View
    {
        return view('livewire.profile.two-factor-settings', [
            'systemEnabled' => (bool) config('two-factor.enabled'),
            'confirmedAt' => $this->user()->two_factor_confirmed_at,
        ]);
    }

    /**
     * Validate the re-entered password against the `web` guard's user and
     * hand the user back; the field is cleared on both outcomes so the
     * plaintext never lingers in component state.
     */
    protected function confirmCurrentPassword(): User
    {
        $this->status = null;

        try {
            $this->validate(
                rules: ['currentPassword' => ['required', 'current_password:web']],
                attributes: ['currentPassword' => __('current password')],
            );
        } finally {
            $this->currentPassword = '';
        }

        return $this->user();
    }

    /**
     * The authenticated owner of this panel.
     */
    protected function user(): User
    {
        /** @var User */
        return Auth::user();
    }
}
