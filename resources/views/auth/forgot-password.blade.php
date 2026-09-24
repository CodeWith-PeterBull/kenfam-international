{{--
    Forgot-password screen (Breeze rebranded onto the Aureon shell).
    Posts to the stock Breeze password.email route; only presentation changed.
--}}
@extends('layouts.full-page-layout')

@section('title', 'Forgot password')

@section('content')
    <div class="account-content">
        <div class="login-wrapper bg-img">
            <div class="login-content authent-content">
                <form method="POST" action="{{ route('password.email') }}">
                    @csrf
                    <div class="login-userset">
                        @include('auth.partials.brand')

                        <div class="login-userheading">
                            <h3>Forgot password?</h3>
                            <h4 class="fs-16 aureon-auth-subtitle">Enter your account email and we will send you a link to choose a new password.</h4>
                        </div>

                        {{-- Reset-link-sent confirmation from the password broker. --}}
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

                        <div class="form-login">
                            <button type="submit" class="btn btn-primary w-100">Email password reset link</button>
                        </div>

                        <div class="signinform">
                            <h4>Remembered it? <a href="{{ route('login') }}" class="aureon-auth-link">Back to sign in</a></h4>
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
