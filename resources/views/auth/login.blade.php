{{--
    Sign-in screen (Breeze login rebranded onto the Aureon full-page shell).

    Field names, routes, and error bags are stock Breeze — only the
    presentation moved to the DreamPOS account-page markup system. The
    benchmark's decorative social-login buttons were intentionally dropped.
--}}
@extends('layouts.full-page-layout')

@section('title', 'Sign in')

@section('content')
    <div class="account-content">
        <div class="login-wrapper bg-img">
            <div class="login-content authent-content">
                <form method="POST" action="{{ route('login') }}">
                    @csrf
                    <div class="login-userset">
                        {{-- Theme-aware brand pair: DreamPOS toggles logo-normal/logo-white via [data-theme]. --}}
                        <a href="{{ url('/') }}" class="login-logo logo-normal">
                            <img src="{{ asset('aureon/assets/brand/logo.png') }}" alt="{{ config('app.name') }}">
                        </a>
                        <a href="{{ url('/') }}" class="login-logo logo-white">
                            <img src="{{ asset('aureon/assets/brand/logo-light.png') }}" alt="{{ config('app.name') }}">
                        </a>

                        <div class="login-userheading">
                            <h3>Sign in</h3>
                            <h4 class="fs-16 aureon-auth-subtitle">Access the {{ config('app.name') }} dashboard with your email and password.</h4>
                        </div>

                        {{-- Flash status: password-reset confirmations, expired 2FA challenges, etc. --}}
                        @if (session('status'))
                            <div class="alert alert-success mb-3" role="alert">{{ session('status') }}</div>
                        @endif

                        <div class="mb-3">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input id="email" type="email" name="email" value="{{ old('email') }}"
                                    class="form-control border-end-0 @error('email') is-invalid @enderror"
                                    required autofocus autocomplete="username">
                                <span class="input-group-text border-start-0"><i class="ti ti-mail"></i></span>
                            </div>
                            @error('email')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                            <div class="pass-group">
                                <input id="password" type="password" name="password"
                                    class="pass-input form-control @error('password') is-invalid @enderror"
                                    required autocomplete="current-password">
                                <span class="ti toggle-password ti-eye-off" role="button" tabindex="0" aria-label="Toggle password visibility"></span>
                            </div>
                            @error('password')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-login authentication-check">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="custom-control custom-checkbox">
                                    <label class="checkboxs ps-4 mb-0 pb-0 line-height-1 fs-16">
                                        <input id="remember" type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}>
                                        <span class="checkmarks"></span>Remember me
                                    </label>
                                </div>
                                @if (Route::has('password.request'))
                                    <a class="aureon-auth-link fs-16" href="{{ route('password.request') }}">Forgot password?</a>
                                @endif
                            </div>
                        </div>

                        <div class="form-login">
                            <button type="submit" class="btn btn-primary w-100">Sign in</button>
                        </div>

                        @if (Route::has('register'))
                            <div class="signinform">
                                <h4>New on our platform? <a href="{{ route('register') }}" class="aureon-auth-link">Create an account</a></h4>
                            </div>
                        @endif

                        <div class="my-4 d-flex justify-content-center align-items-center copyright-text">
                            @include('layouts.partials.footer')
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
