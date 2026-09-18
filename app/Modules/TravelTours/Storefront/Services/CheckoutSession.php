<?php

/** Session-scoped identity for anonymous holds and their idempotent operation keys. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Storefront\Services;

use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;

/**
 * A visitor has no account, so hold ownership is proven by a secret token kept
 * only in the session. Operation keys are derived from that token, a rotating
 * nonce, and the quote fingerprint: repeating the same selection returns the
 * same hold instead of reserving seats twice, while rotating the nonce after a
 * hold is consumed or expires lets the visitor start again.
 */
final readonly class CheckoutSession
{
    private const OWNER_KEY = 'travel_tours.checkout.owner';

    private const NONCE_KEY = 'travel_tours.checkout.nonce';

    /** Bind the helper to the current session store. */
    public function __construct(private Session $session) {}

    /** Return the visitor's hold owner token, creating it on first use. */
    public function ownerToken(): string
    {
        $token = $this->session->get(self::OWNER_KEY);
        if (! is_string($token) || $token === '') {
            $token = Str::random(48);
            $this->session->put(self::OWNER_KEY, $token);
        }

        return $token;
    }

    /** Derive the retry-safe hold operation key for one quote under the current nonce. */
    public function holdOperationKey(string $quoteFingerprint): string
    {
        return hash('sha256', $this->ownerToken().'|'.$this->nonce().'|'.$quoteFingerprint);
    }

    /** Start a new hold generation so the next Continue creates a fresh hold. */
    public function rotate(): void
    {
        $this->session->put(self::NONCE_KEY, (string) Str::ulid());
    }

    /** Return the current hold generation nonce, creating it on first use. */
    private function nonce(): string
    {
        $nonce = $this->session->get(self::NONCE_KEY);
        if (! is_string($nonce) || $nonce === '') {
            $nonce = (string) Str::ulid();
            $this->session->put(self::NONCE_KEY, $nonce);
        }

        return $nonce;
    }
}
