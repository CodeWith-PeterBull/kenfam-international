@extends('layouts.dashboard-layout')

@section('title', $title)

@section('content')
    <div class="page-header">
        <div class="page-title"><h4>{{ $title }}</h4><h6>{{ $subtitle }}</h6></div>
        <div class="page-btn"><a href="{{ route($homeRoute) }}" class="btn btn-outline-secondary"><i class="ti ti-arrow-left me-2"></i>Dashboard</a></div>
    </div>

    <section class="row" aria-label="{{ $title }} overview">
        @foreach ($items as $item)
            <div class="col-xl-4 col-md-6 d-flex">
                <article class="card aureon-panel mb-4">
                    <div class="card-body">
                        <span class="aureon-muted small text-uppercase fw-semibold">{{ $item['label'] }}</span>
                        <h3 class="h5 mt-3 mb-2">{{ $item['value'] }}</h3>
                        <p class="aureon-muted mb-0">{{ $item['detail'] }}</p>
                    </div>
                </article>
            </div>
        @endforeach
    </section>
@endsection

