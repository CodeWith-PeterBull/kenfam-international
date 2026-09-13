@extends($reportContext->orientation->layout())

@section('content')
    <section class="report-section">
        <div class="metrics">
            <div class="metric"><strong>{{ count($records) }}</strong>Total records</div>
            <div class="metric"><strong>{{ collect($records)->where('status', 'Published')->count() }}</strong>Published</div>
            <div class="metric"><strong>{{ collect($records)->where('status', 'Review')->count() }}</strong>In review</div>
            <div class="metric"><strong>{{ $orientation === 'landscape' ? 'Extended' : 'Condensed' }}</strong>View</div>
        </div>
        @if ($reportContext->filters !== [])
            <div class="filters"><strong>Applied filters:</strong> {{ implode(' | ', $reportContext->filters) }}</div>
        @endif
    </section>

    <section>
        <h2 class="section-title">Representative CMS register</h2>
        <table>
            <thead>
                <tr>
                    <th style="width: 5%">#</th>
                    <th style="width: 24%">Record</th>
                    <th style="width: 16%">Module</th>
                    <th style="width: 14%">Status</th>
                    @if ($orientation === 'landscape')
                        <th style="width: 20%">Owner</th>
                        <th style="width: 12%">Updated</th>
                    @else
                        <th style="width: 22%">Updated by</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($records as $record)
                    <tr>
                        <td class="text-center">{{ $loop->iteration }}</td>
                        <td><strong>{{ $record['title'] }}</strong><br>{{ $record['reference'] }}</td>
                        <td>{{ $record['module'] }}</td>
                        <td>{{ $record['status'] }}</td>
                        @if ($orientation === 'landscape')
                            <td>{{ $record['owner'] }}</td>
                            <td>{{ $record['updated'] }}</td>
                        @else
                            <td>{{ $record['owner'] }}<br>{{ $record['updated'] }}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
@endsection
