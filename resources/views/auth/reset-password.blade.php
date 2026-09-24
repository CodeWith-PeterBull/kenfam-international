{{--
    Password-reset screen (Breeze rebranded onto the Aureon shell).
    Receives $request from NewPasswordController::create (stock Breeze
    contract: hidden token from the route, email prefilled from the link).
--}}
@extends('layouts.full-page-layout')

@section('title', 'Reset password')

@section('content')
    <div class="account-content">
        <div class="login-wrapper bg-img">
            <div class="login-content authent-content">
                <form method="POST" action="{{ route('password.store') }}">
                    @csrf

                    {{-- Password reset token from the signed email link. --}}
                    <input type="hidden" name="token" value="{{ $request->route('token') }}">

                    <div class="login-userset">
                        @include('auth.partials.brand')

                        <div class="login-userheading">
                            <h3>Reset password</h3>
                            <h4 class="fs-16 aureon-auth-subtitle">Choose a new password for your {{ config('app.name') }} account.</h4>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}"
                                    class="form-control border-end-0 @error('email') is-invalid @enderror"
                                    required autofocus autocomplete="username">
                                <span class="input-group-text border-start-0"><i class="ti ti-mail"></i></span>
                            </div>
                            @error('email')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">New password <span class="text-danger">*</span></label>
                            <div class="pass-group">
                                <input id="password" type="password" name="password"
                                    class="pass-input form-control @error('password') is-invalid @enderror"
                                    required autocomplete="new-password">
                                <span class="ti toggle-password ti-eye-off" role="button" tabindex="0" aria-label="Toggle password visibility"></span>
                            </div>
                            @error('password')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label">Confirm new password <span class="text-danger">*</span></label>
                            <div class="pass-group">
                                <input id="password_confirmation" type="password" name="password_confirmation"
                                    class="pass-input form-control @error('password_confirmation') is-invalid @enderror"
                                    required autocomplete="new-password">
                                <span class="ti toggle-password ti-eye-off" role="button" tabindex="0" aria-label="Toggle password visibility"></span>
                            </div>
                            @error('password_confirmation')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-login">
                            <button type="submit" class="btn btn-primary w-100">Reset password</button>
                        </div>

                        <div class="my-4 d-flex justify-content-center align-items-center copyright-text">
                            @include('layouts.partials.footer')
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
