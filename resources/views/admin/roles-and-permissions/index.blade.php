@extends('layouts.dashboard-layout')

@section('title', 'Roles And Permissions')

@section('content')
    <div class="page-header">
        <div class="page-title">
            <h4>Roles and permissions</h4>
            <h6>Compose access roles from the application capability catalogue</h6>
        </div>
        @can(\App\Support\CmsPermission::VIEW_USERS)
            <div class="page-btn">
                <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">
                    <i class="ti ti-users me-2"></i>User accounts
                </a>
            </div>
        @endcan
    </div>

    <livewire:admin.roles-and-permissions-manager />
@endsection
