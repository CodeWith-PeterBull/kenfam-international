@extends('commerce::layouts.storefront')

@section('title', 'Order received')
@section('meta_description', 'Your Aureon Commerce order has been received.')
@section('nav', 'order')

@push('head')
    <meta name="robots" content="noindex, nofollow, noarchive">
@endpush

@section('content')
    <section class="commerce-order-hero commerce-order-hero--success">
        <div class="container-xxl"><span class="commerce-order-hero__icon"><i data-lucide="check" aria-hidden="true"></i></span><p class="commerce-eyebrow">Order received</p><h1>Thank you, {{ $order->customer_first_name }}.</h1><p>Your order <strong>{{ $order->order_number }}</strong> was placed successfully. A confirmation is being sent to {{ $order->customer_email }}.</p><div class="commerce-order-actions"><a class="commerce-button" href="{{ $trackingUrl }}">Track order <i data-lucide="route" aria-hidden="true"></i></a><a class="commerce-button commerce-button--secondary" href="{{ $documentUrl }}" target="_blank" rel="noopener">Order summary <i data-lucide="file-text" aria-hidden="true"></i></a></div></div>
    </section>
    <section class="commerce-public-order"><div class="container-xxl">@include('commerce::storefront.orders.partials.order-details', ['order' => $order])</div></section>
@endsection
