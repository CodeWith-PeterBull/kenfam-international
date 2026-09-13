{{--
    Two-factor email-OTP challenge screen.

    Rendered by TwoFactorController::show with:
    - $maskedEmail    partially hidden delivery address (never the full email
                      on this pre-authentication page — deviation from CSK)
    - $codeLength     configured digit count (two-factor.code.length)
    - $expiryMinutes  configured code validity (two-factor.code.expiry_minutes)

    Two independent forms: verify the code, or request a fresh one (a resend
    always regenerates — codes are hashed at rest and cannot be re-sent).
--}}
@extends('layouts.full-page-layout')

@section('title', 'Two-factor verification')

@section('content')
    <div class="account-content">
        <div class="login-wrapper bg-img">
            <div class="login-content authent-content">
                <div class="login-userset">
                    {{-- Theme-aware brand pair: DreamPOS toggles logo-normal/logo-white via [data-theme]. --}}
                    <a href="{{ url('/') }}" class="login-logo logo-normal">
                        <img src="{{ asset('aureon/assets/brand/logo.png') }}" alt="{{ config('app.name') }}">
                    </a>
                    <a href="{{ url('/') }}" class="login-logo logo-white">
                        <img src="{{ asset('aureon/assets/brand/logo-light.png') }}" alt="{{ config('app.name') }}">
                    </a>

                    <div class="login-userheading">
                        <h3>Check your email</h3>
                        <h4 class="fs-16 aureon-auth-subtitle">We sent a {{ $codeLength }}-digit verification code to <strong>{{ $maskedEmail }}</strong>.</h4>
                    </div>

                    {{-- Challenge-issued / code-resent confirmations. --}}
                    @if (session('status'))
                        <div class="alert alert-success mb-3" role="alert">{{ session('status') }}</div>
                    @endif

                    <form method="POST" action="{{ route('two-factor.verify') }}">
                        @csrf
                        <div class="mb-2">
                            <label for="code" class="form-label">Verification code <span class="text-danger">*</span></label>
                            <input id="code" type="text" name="code" value="{{ old('code') }}"
                                class="form-control aureon-otp-input @error('code') is-invalid @enderror"
                                inputmode="numeric" pattern="[0-9]*" maxlength="{{ $codeLength }}"
                                autocomplete="one-time-code" data-otp-input
                                placeholder="{{ str_repeat('•', $codeLength) }}"
                                required autofocus>
                            @error('code')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <p class="aureon-auth-meta mb-3">The code expires {{ $expiryMinutes }} minutes after it was sent.</p>

                        <div class="form-login">
                            <button type="submit" class="btn btn-primary w-100">Verify and sign in</button>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('two-factor.resend') }}" class="text-center mt-3">
                        @csrf
                        <button type="submit" class="btn btn-link aureon-auth-link p-0">Send a new code</button>
                    </form>

                    <div class="signinform">
                        <h4>Wrong account? <a href="{{ route('login') }}" class="aureon-auth-link">Back to sign in</a></h4>
                    </div>

                    <div class="alert alert-info mt-3 mb-0 fs-13" role="alert">
                        <i class="ti ti-shield-lock me-1"></i>
                        Never share this code. {{ config('app.name') }} will never ask you for it by phone, chat, or email.
                    </div>

                    <div class="my-4 d-flex justify-content-center align-items-center copyright-text">
                        @include('layouts.partials.footer')
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
