@extends('travel-tours::layouts.storefront')

@section('title', 'Explore tours')
@section('meta_description', 'Search published tours by destination, travel date, tour type, and duration.')
@section('canonical', route('travel-tours.storefront.catalog.index'))
@section('page', 'catalog')

@section('content')
    <livewire:travel-tours.storefront.tour-search />
@endsection
