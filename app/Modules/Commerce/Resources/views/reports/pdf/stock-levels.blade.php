@extends($reportContext->orientation->layout())

@section('content')
    @include('commerce::reports.pdf.partials.summary')

    <section>
        <h2 class="section-title">Stock levels</h2>
        <table>
            <thead>
                <tr>
                    <th style="width: 5%">#</th>
                    <th style="width: 30%">Product</th>
                    <th style="width: 18%">Category</th>
                    <th style="width: 13%" class="text-right">On hand</th>
                    <th style="width: 12%" class="text-right">Threshold</th>
                    <th style="width: 10%" class="text-right">Available</th>
                    <th style="width: 12%">State</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td class="text-center">{{ $loop->iteration }}</td>
                        <td><strong>{{ $row->name }}</strong><br><span class="muted">{{ $row->sku }}</span></td>
                        <td>{{ $row->category }}</td>
                        <td class="text-right"><span class="amount-strong">{{ number_format($row->onHand) }}</span></td>
                        <td class="text-right">{{ number_format($row->threshold) }}</td>
                        <td class="text-right">{{ number_format($row->available) }}</td>
                        <td>{{ $row->state }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center muted">No stock records match the selected filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
@endsection
