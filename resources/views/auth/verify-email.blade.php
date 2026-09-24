{{--
    Email-verification notice (Breeze rebranded onto the Aureon shell).
    Two independent forms: resend the signed verification link, or log out.
    Note: users who complete the 2FA email challenge are auto-verified and
    never see this screen (the challenge proves mailbox ownership).
--}}
@extends('layouts.full-page-layout')

@section('title', 'Verify email')

@section('content')
    <div class="account-content">
        <div class="login-wrapper bg-img">
            <div class="login-content authent-content">
                <div class="login-userset">
                    @include('auth.partials.brand')

                    <div class="login-userheading">
                        <h3>Verify your email</h3>
                        <h4 class="fs-16 aureon-auth-subtitle">Thanks for signing up! Click the link we emailed you to verify your address. Didn't get it? We can send another.</h4>
                    </div>

                    @if (session('status') == 'verification-link-sent')
                        <div class="alert alert-success mb-3" role="alert">
                            A new verification link has been sent to the email address you provided during registration.
                        </div>
                    @endif

                    <form method="POST" action="{{ route('verification.send') }}" class="form-login">
                        @csrf
                        <button type="submit" class="btn btn-primary w-100">Resend verification email</button>
                    </form>

                    <form method="POST" action="{{ route('logout') }}" class="text-center mt-3">
                        @csrf
                        <button type="submit" class="btn btn-link aureon-auth-link p-0">Log out</button>
                    </form>

                    <div class="my-4 d-flex justify-content-center align-items-center copyright-text">
                        @include('layouts.partials.footer')
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
