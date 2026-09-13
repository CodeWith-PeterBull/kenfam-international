@extends('layouts.dashboard-layout')

@section('title', 'Blank Page')

@section('content')
    <div class="page-header">
        <div class="page-title">
            <h4>Blank page</h4>
            <h6>Reusable module workspace</h6>
        </div>
        <div class="page-btn">
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">
                <i class="ti ti-arrow-left me-2"></i>Dashboard
            </a>
        </div>
    </div>

    <section class="aureon-empty-state">
        <div class="p-4">
            <span class="aureon-empty-state__icon"><i class="ti ti-file"></i></span>
            <h4 class="mb-2">Module workspace</h4>
            <p class="aureon-muted mb-0">Extend this view for focused dashboard modules while retaining the shared shell.</p>
        </div>
    </section>
@endsection
