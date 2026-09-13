{{--
    Profile & account security page, rebranded onto the shared dashboard
    shell (was stock Tailwind Breeze on x-app-layout).

    Four cards, each a shared partial so future modules can reuse or extend
    them individually:
      1. Profile information  — name/email + re-verification (stock Breeze flow)
      2. Update password      — stock Breeze flow, `updatePassword` error bag
      3. Security             — Livewire self-service 2FA panel
      4. Danger zone          — account deletion behind a Bootstrap modal
--}}
@extends('layouts.dashboard-layout')

@section('title', 'Profile')

@section('content')
    <div class="page-header">
        <div class="page-title">
            <h4>My profile</h4>
            <h6>Account details, password, and sign-in security</h6>
        </div>
        <div class="page-btn">
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">
                <i class="ti ti-arrow-left me-2"></i>Dashboard
            </a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6 d-flex flex-column gap-3">
            <div class="card aureon-panel mb-0">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="ti ti-user-circle me-2"></i>Profile information</h5>
                </div>
                <div class="card-body">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="card aureon-panel mb-0">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="ti ti-key me-2"></i>Update password</h5>
                </div>
                <div class="card-body">
                    @include('profile.partials.update-password-form')
                </div>
            </div>
        </div>

        <div class="col-lg-6 d-flex flex-column gap-3">
            <div class="card aureon-panel mb-0">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="ti ti-shield-lock me-2"></i>Security — two-factor authentication</h5>
                </div>
                <div class="card-body">
                    <livewire:profile.two-factor-settings />
                </div>
            </div>

            <div class="card mb-0 border-danger">
                <div class="card-header">
                    <h5 class="card-title mb-0 text-danger"><i class="ti ti-alert-triangle me-2"></i>Danger zone</h5>
                </div>
                <div class="card-body">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
@endsection
