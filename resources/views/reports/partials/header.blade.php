<header class="report-header">
    @php($reportLogo = $institutionProfile->logoDataUri())
    @if ($reportLogo)
        <img class="report-logo" src="{{ $reportLogo }}" alt="{{ $institutionProfile->name }} logo">
    @endif
    <p class="institution-name">{{ strtoupper($institutionProfile->name) }}</p>
    @if ($institutionProfile->descriptor)<div class="institution-descriptor">{{ $institutionProfile->descriptor }}</div>@endif
    <div class="institution-contact">
        {{ $institutionProfile->address() }}
        @if ($institutionProfile->primaryPhone) | Tel: {{ $institutionProfile->primaryPhone }}@endif
        @if ($institutionProfile->primaryEmail) | Email: {{ $institutionProfile->primaryEmail }}@endif
        @if ($institutionProfile->website) | Web: {{ $institutionProfile->website }}@endif
    </div>
</header>
<h1 class="report-title">{{ $reportContext->title }}</h1>
@if ($reportContext->subtitle)<p class="report-subtitle">{{ $reportContext->subtitle }}</p>@endif
