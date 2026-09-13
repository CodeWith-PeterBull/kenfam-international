{{--
    Password-confirmation gate (Breeze rebranded onto the Aureon shell).
    Guards sensitive areas for already-authenticated users. The CSK
    benchmark forgot to rebrand this view — deliberately covered here.
--}}
@extends('layouts.full-page-layout')

@section('title', 'Confirm password')

@section('content')
    <div class="account-content">
        <div class="login-wrapper bg-img">
            <div class="login-content authent-content">
                <form method="POST" action="{{ route('password.confirm') }}">
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
                            <h3>Confirm password</h3>
                            <h4 class="fs-16 aureon-auth-subtitle">This is a secure area of the application. Please confirm your password before continuing.</h4>
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

                        <div class="form-login">
                            <button type="submit" class="btn btn-primary w-100">Confirm</button>
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
