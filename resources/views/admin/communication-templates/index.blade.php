@extends('layouts.dashboard-layout')

@section('title', 'Communication Templates')

@section('content')
    <div class="page-header">
        <div class="page-title">
            <h4>Communication templates</h4>
            <h6>Institution-aware mail notifications and PDF report foundations</h6>
        </div>
        @can(\App\Support\CmsPermission::VIEW_INSTITUTION_DETAILS)
            <div class="page-btn">
                <a href="{{ route('admin.institution-details.index') }}" class="btn btn-outline-secondary">
                    <i class="ti ti-building me-2"></i>Institution details
                </a>
            </div>
        @endcan
    </div>

    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif

    <section class="card aureon-panel mb-4">
        <div class="card-header">
            <div>
                <h5 class="card-title mb-1">Mail notification</h5>
                <p class="aureon-muted mb-0">Shared Markdown presentation used by notification mail.</p>
            </div>
            <a href="{{ route('admin.communication-templates.mail-preview') }}" class="btn btn-icon btn-outline-secondary" target="_blank" rel="noopener" title="Open mail preview" aria-label="Open mail preview">
                <i class="ti ti-external-link"></i>
            </a>
        </div>
        <div class="card-body aureon-communication-grid">
            <div>
                @can(\App\Support\CmsPermission::SEND_TEST_NOTIFICATIONS)
                    <form method="POST" action="{{ route('admin.communication-templates.test-mail') }}" class="aureon-test-mail-form">
                        @csrf
                        <label for="test-email" class="form-label">Test recipient</label>
                        <div class="input-group">
                            <input id="test-email" name="email" type="email" value="{{ old('email', auth()->user()->email) }}" class="form-control @error('email') is-invalid @enderror" required>
                            <button class="btn btn-primary" type="submit">
                                <i class="ti ti-send me-2"></i>Send test
                            </button>
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </form>
                @endcan
                <dl class="aureon-template-facts mt-4 mb-0">
                    <div><dt>Source</dt><dd>Laravel Markdown notifications</dd></div>
                    <div><dt>Branding</dt><dd>Current institution details</dd></div>
                    <div><dt>Transport</dt><dd>Environment mail configuration</dd></div>
                </dl>
            </div>
            <iframe class="aureon-mail-preview" src="{{ route('admin.communication-templates.mail-preview') }}" title="Mail notification template preview" loading="lazy"></iframe>
        </div>
    </section>

    <div class="row g-4">
        @foreach (\App\Enums\ReportOrientation::cases() as $orientation)
            <div class="col-xl-6">
                <section class="card aureon-panel h-100">
                    <div class="card-header">
                        <div>
                            <h5 class="card-title mb-1">{{ $orientation->label() }} report</h5>
                            <p class="aureon-muted mb-0">A4, two-pass page totals, and institution identity.</p>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="{{ route('admin.communication-templates.report-preview', $orientation) }}" class="btn btn-icon btn-outline-secondary" target="_blank" rel="noopener" title="Open {{ strtolower($orientation->label()) }} preview" aria-label="Open {{ strtolower($orientation->label()) }} preview">
                                <i class="ti ti-external-link"></i>
                            </a>
                            <a href="{{ route('admin.communication-templates.report-download', $orientation) }}" class="btn btn-icon btn-primary" title="Download {{ strtolower($orientation->label()) }} sample" aria-label="Download {{ strtolower($orientation->label()) }} sample">
                                <i class="ti ti-download"></i>
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <iframe class="aureon-report-preview aureon-report-preview--{{ $orientation->value }}" src="{{ route('admin.communication-templates.report-preview', $orientation) }}" title="{{ $orientation->label() }} PDF report template preview" loading="lazy"></iframe>
                    </div>
                </section>
            </div>
        @endforeach
    </div>
@endsection
