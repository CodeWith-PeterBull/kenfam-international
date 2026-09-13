@extends('commerce::layouts.storefront')

@section('title', 'Track '.$order->order_number)
@section('meta_description', 'Track the current fulfillment and payment state of your Aureon Commerce order.')
@section('nav', 'order')

@push('head')
    <meta name="robots" content="noindex, nofollow, noarchive">
@endpush

@section('content')
    @php
        $steps = [\App\Modules\Commerce\Orders\Enums\OrderStatus::Pending, \App\Modules\Commerce\Orders\Enums\OrderStatus::Confirmed, \App\Modules\Commerce\Orders\Enums\OrderStatus::Processing, \App\Modules\Commerce\Orders\Enums\OrderStatus::Ready, \App\Modules\Commerce\Orders\Enums\OrderStatus::Completed];
        $current = array_search($order->status, $steps, true);
    @endphp
    <section class="commerce-order-hero {{ $order->status === \App\Modules\Commerce\Orders\Enums\OrderStatus::Cancelled ? 'commerce-order-hero--cancelled' : '' }}">
        <div class="container-xxl"><p class="commerce-eyebrow">Order tracking</p><h1>{{ $order->order_number }}</h1><p>Placed {{ $order->placed_at?->format('d M Y, H:i') }} for {{ \App\Modules\Commerce\Support\MoneyFormatter::format($order->total_minor) }}.</p><a class="commerce-button commerce-button--secondary" href="{{ $documentUrl }}" target="_blank" rel="noopener">Printable summary <i data-lucide="file-text" aria-hidden="true"></i></a></div>
    </section>
    <section class="commerce-tracking" aria-labelledby="tracking-progress-title"><div class="container-xxl"><p class="commerce-eyebrow">Current status</p><h2 id="tracking-progress-title">{{ $order->status->label() }}</h2>
        @if ($order->status === \App\Modules\Commerce\Orders\Enums\OrderStatus::Cancelled)
            <div class="commerce-notice commerce-notice--error"><strong>This order was cancelled.</strong>@if ($order->cancellation_reason)<span>{{ $order->cancellation_reason }}</span>@endif</div>
        @else
            <ol class="commerce-tracking-steps">@foreach ($steps as $index => $step)<li class="{{ $current !== false && $index <= $current ? 'complete' : '' }} {{ $step === $order->status ? 'current' : '' }}"><span>{{ $index + 1 }}</span><div><strong>{{ $step->label() }}</strong><small>{{ $step === $order->status ? 'Current state' : ($current !== false && $index < $current ? 'Completed' : 'Pending') }}</small></div></li>@endforeach</ol>
        @endif
    </div></section>
    <section class="commerce-public-order"><div class="container-xxl">@include('commerce::storefront.orders.partials.order-details', ['order' => $order])</div></section>
@endsection
