{{-- Shared metrics strip, applied-filter line, and truncation note for Commerce list reports. --}}
<section class="report-section">
    <div class="metrics">
        @foreach ($metrics as $label => $value)
            <div class="metric"><strong>{{ $value }}</strong>{{ $label }}</div>
        @endforeach
    </div>
    @if ($reportContext->filters !== [])
        <div class="filters"><strong>Applied filters:</strong> {{ implode(' | ', $reportContext->filters) }}</div>
    @endif
    @if ($truncated)
        <div class="report-note">Showing the first {{ number_format($shown) }} of {{ number_format($total) }} records. Refine the filters to narrow this export.</div>
    @endif
</section>
