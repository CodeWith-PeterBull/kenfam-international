@extends($reportContext->orientation->layout())

@section('content')
    @include('commerce::reports.pdf.partials.summary')

    <section>
        <h2 class="section-title">Order register</h2>
        <table>
            <thead>
                <tr>
                    <th style="width: 4%">#</th>
                    <th style="width: 16%">Order</th>
                    <th style="width: 19%">Customer</th>
                    <th style="width: 10%">Channel</th>
                    <th style="width: 11%">Status</th>
                    <th style="width: 11%">Payment</th>
                    <th style="width: 6%" class="text-center">Items</th>
                    <th style="width: 12%" class="text-right">Total</th>
                    <th style="width: 11%" class="text-right">Balance</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td class="text-center">{{ $loop->iteration }}</td>
                        <td><strong>{{ $row->orderNumber }}</strong><br><span class="muted">{{ $row->placedAt }}</span></td>
                        <td>{{ $row->customer }}</td>
                        <td>{{ $row->channel }}</td>
                        <td>{{ $row->status }}</td>
                        <td>{{ $row->paymentStatus }}</td>
                        <td class="text-center">{{ $row->itemCount }}</td>
                        <td class="text-right"><span class="amount-strong">{{ $row->total }}</span></td>
                        <td class="text-right"><span class="amount">{{ $row->balance }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center muted">No orders match the selected filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
@endsection
