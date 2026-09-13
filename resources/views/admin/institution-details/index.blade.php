@extends('layouts.dashboard-layout')

@section('title', 'Institution Details')

@section('content')
    <div class="page-header">
        <div class="page-title">
            <h4>Institution details</h4>
            <h6>Identity and contact information shared by communications</h6>
        </div>
        @can(\App\Support\CmsPermission::PREVIEW_COMMUNICATION_TEMPLATES)
            <div class="page-btn">
                <a href="{{ route('admin.communication-templates.index') }}" class="btn btn-outline-secondary">
                    <i class="ti ti-template me-2"></i>Preview templates
                </a>
            </div>
        @endcan
    </div>

    <livewire:admin.institution-details-editor />
@endsection
