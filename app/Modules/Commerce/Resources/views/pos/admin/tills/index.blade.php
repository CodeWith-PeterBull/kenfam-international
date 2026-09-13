@extends('layouts.dashboard-layout')

@section('title', 'Till sessions')

@section('content')
    <div class="page-header">
        <div class="add-item d-flex">
            <div class="page-title">
                <h4>Till sessions</h4>
                <h6>Open cashier sessions and reconcile physical cash at close</h6>
            </div>
        </div>
        <ul class="table-top-head">
            <li><a href="{{ route('commerce.pos.admin.registers.index') }}" title="Registers"><i class="ti ti-building-store"></i></a></li>
            <li><a href="{{ route('commerce.pos.terminal') }}" title="Open terminal"><i class="ti ti-device-desktop"></i></a></li>
        </ul>
    </div>

    <livewire:commerce.pos.admin.till-manager />
@endsection
