{{--
    Registration screen (Breeze register rebranded onto the Aureon shell).

    Keeps the stock Breeze single `name` field as the account username.
    Personal names are completed from the authenticated profile page. New
    accounts are auto-assigned the `viewer` user type and role.
--}}
@extends('layouts.full-page-layout')

@section('title', 'Create account')

@section('content')
    <div class="account-content">
        <div class="login-wrapper bg-img">
            <div class="login-content authent-content">
                <form method="POST" action="{{ route('register') }}">
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
                            <h3>Create account</h3>
                            <h4 class="fs-16 aureon-auth-subtitle">Register for a {{ config('app.name') }} account.</h4>
                        </div>

                        <div class="mb-3">
                            <label for="name" class="form-label">Username <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input id="name" type="text" name="name" value="{{ old('name') }}"
                                    class="form-control border-end-0 @error('name') is-invalid @enderror"
                                    required autofocus autocomplete="username">
                                <span class="input-group-text border-start-0"><i class="ti ti-user"></i></span>
                            </div>
                            @error('name')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input id="email" type="email" name="email" value="{{ old('email') }}"
                                    class="form-control border-end-0 @error('email') is-invalid @enderror"
                                    required autocomplete="username">
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
                                    required autocomplete="new-password">
                                <span class="ti toggle-password ti-eye-off" role="button" tabindex="0" aria-label="Toggle password visibility"></span>
                            </div>
                            @error('password')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label">Confirm password <span class="text-danger">*</span></label>
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
                            <button type="submit" class="btn btn-primary w-100">Create account</button>
                        </div>

                        <div class="signinform">
                            <h4>Already registered? <a href="{{ route('login') }}" class="aureon-auth-link">Sign in</a></h4>
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
