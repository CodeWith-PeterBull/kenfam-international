@extends($reportContext->orientation->layout())

@section('content')
    @include('commerce::reports.pdf.partials.summary')

    <section>
        <h2 class="section-title">Product catalogue</h2>
        <table>
            <thead>
                <tr>
                    <th style="width: 4%">#</th>
                    <th style="width: 26%">Product</th>
                    <th style="width: 14%">Category</th>
                    <th style="width: 11%">Status</th>
                    <th style="width: 13%" class="text-right">List price</th>
                    <th style="width: 13%" class="text-right">Selling price</th>
                    <th style="width: 8%" class="text-center">Featured</th>
                    <th style="width: 11%" class="text-right">Stock</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td class="text-center">{{ $loop->iteration }}</td>
                        <td><strong>{{ $row->name }}</strong><br><span class="muted">{{ $row->sku }}</span></td>
                        <td>{{ $row->category }}</td>
                        <td>{{ $row->status }}</td>
                        <td class="text-right">
                            @if ($row->salePrice !== null)
                                <span class="amount-was">{{ $row->listPrice }}</span>
                            @else
                                <span class="amount">{{ $row->listPrice }}</span>
                            @endif
                        </td>
                        <td class="text-right"><span class="amount-strong">{{ $row->sellingPrice }}</span></td>
                        <td class="text-center">{{ $row->featured ? 'Yes' : '—' }}</td>
                        <td class="text-right">{{ $row->stock }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center muted">No products match the selected filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
@endsection
