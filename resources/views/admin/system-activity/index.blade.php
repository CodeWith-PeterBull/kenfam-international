@extends('layouts.dashboard-layout')

@section('title', 'System Activity')

@section('content')
    <div class="page-header">
        <div class="page-title">
            <h4>System activity</h4>
            <h6>Structured CMS audit trail</h6>
        </div>
        @can(\App\Support\CmsPermission::VIEW_APPLICATION_LOGS)
            @if (config('log-viewer.enabled'))
                <div class="page-btn">
                    <a href="{{ url(config('log-viewer.route_path', 'logs')) }}" class="btn btn-outline-secondary">
                        <i class="ti ti-file-search me-2"></i>Application logs
                    </a>
                </div>
            @endif
        @endcan
    </div>

    <livewire:admin.system-activity-index />
@endsection
