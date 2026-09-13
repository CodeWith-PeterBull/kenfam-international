@extends($reportContext->orientation->layout())

@section('content')
    @include('commerce::reports.pdf.partials.summary')

    <section>
        <h2 class="section-title">Stock movement history</h2>
        <table>
            <thead>
                <tr>
                    <th style="width: 4%">#</th>
                    <th style="width: 14%">Date</th>
                    <th style="width: 26%">Product</th>
                    <th style="width: 15%">Type</th>
                    <th style="width: 10%" class="text-right">Change</th>
                    <th style="width: 10%" class="text-right">Balance</th>
                    <th style="width: 12%">Reference</th>
                    <th style="width: 9%">By</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td class="text-center">{{ $loop->iteration }}</td>
                        <td>{{ $row->occurredAt }}</td>
                        <td><strong>{{ $row->product }}</strong><br><span class="muted">{{ $row->sku }}</span></td>
                        <td>{{ $row->type }}</td>
                        <td class="text-right"><span class="amount-strong">{{ $row->quantityDelta }}</span></td>
                        <td class="text-right">{{ number_format($row->balanceAfter) }}</td>
                        <td>{{ $row->reference }}</td>
                        <td>{{ $row->actor }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center muted">No stock movements match the selected filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
@endsection
