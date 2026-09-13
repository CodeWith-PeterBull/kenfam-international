@extends('layouts.dashboard-layout')

@section('title', 'Dashboard')

@section('content')
    <div class="page-header">
        <div class="page-title">
            <h4>Dashboard</h4>
            <h6>Corporate content and operations at a glance</h6>
        </div>
        <div class="page-btn">
            <a href="{{ route('admin.blank') }}" class="btn btn-primary">
                <i class="ti ti-plus me-2"></i>Open workspace
            </a>
        </div>
    </div>

    <section class="aureon-welcome mb-4" aria-labelledby="dashboard-welcome-title">
        <div class="position-relative z-1">
            <p class="mb-2 text-uppercase fw-semibold small">Aureon administration</p>
            <h2 id="dashboard-welcome-title" class="text-white mb-2">Welcome back, {{ auth()->user()->name }}</h2>
            <p>Monitor publishing, events, team activity, media, and operating spend from one focused workspace.</p>
        </div>
    </section>

    <section class="row" aria-label="Platform statistics">
        @foreach ($stats as $stat)
            <div class="col-xxl-3 col-xl-4 col-sm-6 d-flex">
                <article class="card aureon-stat mb-4" style="--stat-color: {{ $stat['color'] }}">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="aureon-stat__icon"><i class="ti {{ $stat['icon'] }}"></i></span>
                        <div>
                            <h3>{{ number_format($stat['value']) }}</h3>
                            <p>{{ $stat['label'] }}</p>
                        </div>
                    </div>
                </article>
            </div>
        @endforeach
    </section>

    <div class="row">
        <div class="col-xl-7 d-flex">
            <section class="card aureon-panel mb-4" aria-labelledby="recent-updates-title">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h3 id="recent-updates-title" class="card-title mb-0">Recent content updates</h3>
                    <span class="badge bg-light text-dark">{{ count($recentUpdates) }} updates</span>
                </div>
                <div class="card-body">
                    @foreach ($recentUpdates as $update)
                        <div class="aureon-list-row">
                            <div>
                                <h6 class="mb-1">{{ $update['title'] }}</h6>
                                <p class="aureon-muted mb-0">{{ $update['meta'] }}</p>
                            </div>
                            <small class="aureon-muted text-nowrap">{{ $update['time'] }}</small>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        <div class="col-xl-5 d-flex">
            <section class="card aureon-panel mb-4" aria-labelledby="events-title">
                <div class="card-header">
                    <h3 id="events-title" class="card-title mb-0">Upcoming events</h3>
                </div>
                <div class="card-body">
                    @foreach ($upcomingEvents as $event)
                        <div class="aureon-list-row">
                            <div class="d-flex gap-3">
                                <span class="badge bg-primary align-self-start">{{ $event['date'] }}</span>
                                <div>
                                    <h6 class="mb-1">{{ $event['title'] }}</h6>
                                    <p class="aureon-muted mb-0"><i class="ti ti-map-pin me-1"></i>{{ $event['location'] }}</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-4 d-flex">
            <section class="card aureon-panel mb-4" aria-labelledby="budget-title">
                <div class="card-header">
                    <h3 id="budget-title" class="card-title mb-0">Expenditure snapshot</h3>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-end mb-3">
                        <div>
                            <p class="aureon-muted mb-1">Spent</p>
                            <h4 class="mb-0">KES {{ number_format($budget['spent']) }}</h4>
                        </div>
                        <span class="fw-semibold">{{ $budget['percentage'] }}%</span>
                    </div>
                    <div class="progress aureon-progress mb-3" role="progressbar" aria-label="Budget used" aria-valuenow="{{ $budget['percentage'] }}" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar" style="width: {{ $budget['percentage'] }}%"></div>
                    </div>
                    <p class="aureon-muted mb-0">Allocated: KES {{ number_format($budget['allocated']) }}</p>
                </div>
            </section>
        </div>

        <div class="col-xl-4 d-flex">
            <section class="card aureon-panel mb-4" aria-labelledby="approvals-title">
                <div class="card-header">
                    <h3 id="approvals-title" class="card-title mb-0">Pending approvals</h3>
                </div>
                <div class="card-body">
                    @foreach ($approvals as $approval)
                        <div class="aureon-list-row align-items-center">
                            <span><span class="aureon-status-dot"></span>{{ $approval['label'] }}</span>
                            <span class="badge bg-light text-dark">{{ $approval['count'] }}</span>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        <div class="col-xl-4 d-flex">
            <section class="card aureon-panel mb-4" aria-labelledby="health-title">
                <div class="card-header">
                    <h3 id="health-title" class="card-title mb-0">System health</h3>
                </div>
                <div class="card-body">
                    <div class="aureon-list-row align-items-center">
                        <span>Application</span><span class="badge bg-success">Operational</span>
                    </div>
                    <div class="aureon-list-row align-items-center">
                        <span>Queue</span><span class="badge bg-success">Ready</span>
                    </div>
                    <div class="aureon-list-row align-items-center">
                        <span>Environment</span><span class="badge bg-light text-dark text-capitalize">{{ app()->environment() }}</span>
                    </div>
                </div>
            </section>
        </div>
    </div>
@endsection
