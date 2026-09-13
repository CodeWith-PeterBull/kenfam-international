<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Breeze session controller extended with the email-OTP two-factor gate.
 *
 * When a password login succeeds for a user who requires the second factor,
 * the user is immediately logged back out, the pending user id (and the
 * remember-me choice) is stashed in the session, an OTP is emailed, and the
 * request is redirected to the challenge screen. The session is only
 * promoted to an authenticated one by TwoFactorController::verify().
 */
class AuthenticatedSessionController extends Controller
{
    /**
     * @param  TwoFactorService  $twoFactorService  OTP lifecycle engine.
     */
    public function __construct(
        protected TwoFactorService $twoFactorService,
    ) {}

    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        /** @var User $user */
        $user = Auth::user();

        if ($this->requiresTwoFactorChallenge($user)) {
            // Hold only the pending identity: the user is a guest for the
            // duration of the challenge (the challenge routes live in the
            // `guest` middleware group), which keeps every auth-guarded
            // surface closed until the OTP is verified.
            $request->session()->put([
                'two_factor:user_id' => $user->id,
                'two_factor:remember' => $request->boolean('remember'),
            ]);

            Auth::guard('web')->logout();

            // Rotate the session id at the challenge boundary as fixation
            // hygiene; regenerate() preserves the stashed keys above. The
            // authenticated-boundary regeneration happens again on verify.
            $request->session()->regenerate();

            $this->twoFactorService->generateAndSendCode($user);

            return redirect()->route('two-factor.challenge')
                ->with('status', __('We emailed you a verification code to finish signing in.'));
        }

        $request->session()->regenerate();

        // Direct (non-2FA) sign-in is fully authenticated here. Two-factor
        // accounts are stamped later, in TwoFactorController::verify().
        $user->recordLogin();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Whether this login must pass the email-OTP challenge.
     *
     * Gate order (cheapest first, CSK benchmark parity):
     *  1. global master switch (`two-factor.enabled`) — off means never;
     *  2. break-glass exemption list (`two-factor.exempt_emails`);
     *  3. the user's self-service opt-in flag.
     *
     * Deliberately does NOT consult TwoFactorService::hasValidSession():
     * the verified-session store is a documented FOUNDATION for a future
     * remember-device feature (session ids rotate per login, so a session-id
     * match can never span logins — the defect that made the CSK benchmark's
     * 24-hour skip inert). Until the device-token design in
     * `.docs/auth-2fa/` lands, every login is challenged.
     */
    protected function requiresTwoFactorChallenge(User $user): bool
    {
        if (! config('two-factor.enabled')) {
            return false;
        }

        if (in_array($user->email, (array) config('two-factor.exempt_emails', []), true)) {
            return false;
        }

        return $user->two_factor_enabled;
    }
}
