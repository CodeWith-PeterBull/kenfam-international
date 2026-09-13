@extends('commerce::layouts.storefront')

@section('title', 'Your cart')
@section('meta_description', 'Review products selected for your Aureon Commerce order.')
@section('nav', 'cart')

@section('content')
    <section class="commerce-page-heading">
        <div class="container-xxl">
            <nav class="commerce-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('commerce.storefront.catalog.index') }}">Shop</a><i data-lucide="chevron-right" aria-hidden="true"></i><span aria-current="page">Cart</span></nav>
            <p class="commerce-eyebrow">Aureon Commerce</p>
            <h1>Your cart</h1>
            <p>Review quantities and current server-calculated prices before checkout.</p>
        </div>
    </section>

    <section class="commerce-cart-page" aria-label="Shopping cart">
        <div class="container-xxl"><livewire:commerce.storefront.cart-manager /></div>
    </section>
@endsection
