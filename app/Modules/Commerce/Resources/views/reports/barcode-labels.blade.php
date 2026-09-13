@extends($reportContext->orientation->layout())

@section('content')
    <style>
        .barcode-sheet { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .barcode-cell { padding: 8px 4px; text-align: center; vertical-align: top; border: 1px dashed #cccccc; }
        .barcode-cell--empty { border-color: transparent; }
        .barcode-store { font-size: 8px; color: #444444; text-transform: uppercase; letter-spacing: 1px; }
        .barcode-name { font-size: 10px; font-weight: bold; color: #111111; margin: 1px 0; }
        .barcode-image { height: 40px; margin: 2px 0; }
        .barcode-value { font-size: 9px; letter-spacing: 1px; color: #111111; }
        .barcode-price { font-size: 10px; font-weight: bold; color: #111111; margin-top: 1px; }
    </style>

    <section class="report-section">
        <div class="filters"><strong>Labels:</strong> {{ $labelCount }}</div>
    </section>

    <table class="barcode-sheet">
        @foreach ($rows as $row)
            <tr>
                @foreach ($row as $cell)
                    @if ($cell === null)
                        <td class="barcode-cell barcode-cell--empty" style="width: {{ $cellWidth }}%;"></td>
                    @else
                        <td class="barcode-cell" style="width: {{ $cellWidth }}%;">
                            @if ($showStoreName)<div class="barcode-store">{{ $storeName }}</div>@endif
                            @if ($showProductName)<div class="barcode-name">{{ $cell['name'] }}</div>@endif
                            <img class="barcode-image" src="{{ $cell['barcode'] }}" alt="">
                            <div class="barcode-value">{{ $cell['value'] }}</div>
                            @if ($showPrice && $cell['priceLabel'] !== '')<div class="barcode-price">{{ $cell['priceLabel'] }}</div>@endif
                        </td>
                    @endif
                @endforeach
            </tr>
        @endforeach
    </table>
@endsection
