@extends('layouts.dashboard-layout')

@section('title', 'User Management')

@section('content')
    <div class="page-header">
        <div class="page-title">
            <h4>User management</h4>
            <h6>Accounts, personal profiles, status, and role assignments</h6>
        </div>
    </div>

    <livewire:admin.user-management />
@endsection
