@extends('commerce::layouts.storefront')

@section('title', 'Checkout')
@section('meta_description', 'Complete your Aureon Commerce pickup or delivery order.')
@section('nav', 'checkout')

@section('content')
    <section class="commerce-page-heading">
        <div class="container-xxl">
            <nav class="commerce-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('commerce.storefront.catalog.index') }}">Shop</a><i data-lucide="chevron-right" aria-hidden="true"></i><a href="{{ route('commerce.storefront.cart.index') }}">Cart</a><i data-lucide="chevron-right" aria-hidden="true"></i><span aria-current="page">Checkout</span></nav>
            <p class="commerce-eyebrow">Secure order placement</p>
            <h1>Checkout</h1>
            <p>Confirm your contact, fulfillment, and manual payment preference.</p>
        </div>
    </section>

    <section class="commerce-checkout-page" aria-label="Checkout form">
        <div class="container-xxl"><livewire:commerce.storefront.checkout /></div>
    </section>
@endsection
