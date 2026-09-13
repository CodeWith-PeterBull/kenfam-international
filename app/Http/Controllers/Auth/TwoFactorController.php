<?php

namespace App\Http\Controllers\Auth;

use App\Contracts\RecordsSystemActivity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyTwoFactorRequest;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Email-OTP challenge endpoints (show / verify / resend).
 *
 * Runs in the `guest` middleware group by design: the user is logged out for
 * the duration of the challenge and identified solely by the
 * `two_factor:user_id` session key stashed by AuthenticatedSessionController.
 * A missing or stale key bounces the visitor back to the login screen.
 *
 * Route-level throttles (`throttle:two-factor`, `throttle:two-factor-resend`)
 * and TwoFactorService's database-audited rate limiter together bound
 * brute-force and mail-spam attempts.
 */
class TwoFactorController extends Controller
{
    /**
     * @param  TwoFactorService  $twoFactorService  OTP lifecycle engine.
     * @param  RecordsSystemActivity  $systemActivity  Append-only audit stream.
     */
    public function __construct(
        protected TwoFactorService $twoFactorService,
        protected RecordsSystemActivity $systemActivity,
    ) {}

    /**
     * Display the challenge screen for the pending user.
     *
     * The email is masked before rendering — unlike the CSK benchmark, the
     * full address is never disclosed on a pre-authentication page.
     */
    public function show(Request $request): View|RedirectResponse
    {
        $user = $this->challengedUser($request);

        if ($user === null) {
            return $this->redirectToLogin();
        }

        return view('auth.two-factor-challenge', [
            'maskedEmail' => $this->maskEmail($user->email),
            'codeLength' => (int) config('two-factor.code.length', 6),
            'expiryMinutes' => (int) config('two-factor.code.expiry_minutes', 10),
        ]);
    }

    /**
     * Verify the submitted code and promote the challenge to a full login.
     *
     * Success sequence (order is load-bearing):
     *  1. auto-verify the email if needed — receiving the emailed code proves
     *     mailbox ownership, so users are not funnelled through a redundant
     *     email-verification loop after the challenge (CSK benchmark parity);
     *  2. log the user in, honouring the remembered remember-me choice;
     *  3. drop the challenge session keys;
     *  4. regenerate the session id (authenticated boundary), and only THEN
     *  5. record the verified session — so the stored id is the live one
     *     (fixes the CSK ordering defect that made its 24h skip inert);
     *  6. append the audit activity;
     *  7. follow the intended URL, but never onto the now-pointless
     *     email-verification notice.
     */
    public function verify(VerifyTwoFactorRequest $request): RedirectResponse
    {
        $user = $this->challengedUser($request);

        if ($user === null) {
            return $this->redirectToLogin();
        }

        if (! $this->twoFactorService->verifyCode($user, $request->validated('code'))) {
            $message = $this->twoFactorService->isRateLimited($user)
                ? __('Too many failed attempts. Try again in about :minutes minute(s).', [
                    'minutes' => $this->twoFactorService->availableInMinutes($user),
                ])
                : __('The verification code is invalid or has expired.');

            return back()->withErrors(['code' => $message]);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        Auth::login($user, (bool) $request->session()->get('two_factor:remember', false));

        $request->session()->forget(['two_factor:user_id', 'two_factor:remember']);
        $request->session()->regenerate();

        $this->twoFactorService->createVerifiedSession($user);

        // Authentication is complete only now (after the OTP), so this is the
        // correct point to stamp the last-login timestamp for 2FA accounts.
        $user->recordLogin();

        $this->systemActivity->record(
            activityType: 'auth.two_factor.challenge_passed',
            description: 'Completed the two-factor email challenge and signed in.',
            actor: $user,
            subject: $user,
        );

        $intended = $request->session()->pull('url.intended', route('dashboard', absolute: false));

        // The challenge just proved mailbox ownership, so an intended trip
        // to the email-verification notice would dead-end — reroute it.
        if ($intended === route('verification.notice')) {
            $intended = route('dashboard', absolute: false);
        }

        return redirect()->to($intended)
            ->with('status', __('Signed in successfully.'));
    }

    /**
     * Issue a fresh code to the pending user.
     *
     * Always regenerates (deviation D1: codes are hashed at rest, so the
     * original plaintext cannot be re-sent); the previous code is thereby
     * invalidated. Throttled by `two-factor-resend` to bound mail volume.
     */
    public function resend(Request $request): RedirectResponse
    {
        $user = $this->challengedUser($request);

        if ($user === null) {
            return $this->redirectToLogin();
        }

        $this->twoFactorService->resendCode($user);

        return back()->with('status', __('A new verification code has been sent to your email address.'));
    }

    /**
     * Resolve the user held by the pending challenge, if any.
     *
     * Single lookup shared by all three endpoints (the CSK benchmark
     * triplicated this resolution inline).
     */
    protected function challengedUser(Request $request): ?User
    {
        $userId = $request->session()->get('two_factor:user_id');

        return $userId === null ? null : User::query()->find($userId);
    }

    /**
     * Bounce a visitor without a pending challenge back to the login form.
     */
    protected function redirectToLogin(): RedirectResponse
    {
        return redirect()->route('login')
            ->withErrors(['email' => __('Your verification session has expired. Please sign in again.')]);
    }

    /**
     * Mask an email for pre-authentication display, e.g. `pe***@aureon.test`.
     * Keeps at most the first two characters of the local part.
     */
    protected function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);

        return Str::mask($local, '*', min(2, max(1, strlen($local) - 1))).'@'.$domain;
    }
}
