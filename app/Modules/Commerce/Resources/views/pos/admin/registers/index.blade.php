@extends('layouts.dashboard-layout')

@section('title', 'POS registers')

@section('content')
    <div class="page-header">
        <div class="add-item d-flex">
            <div class="page-title">
                <h4>POS registers</h4>
                <h6>Configure the named endpoints used by cashier till sessions</h6>
            </div>
        </div>
        <ul class="table-top-head">
            <li><a href="{{ route('commerce.pos.admin.tills.index') }}" title="Till sessions"><i class="ti ti-cash-register"></i></a></li>
            <li><a href="{{ route('commerce.pos.terminal') }}" title="Open terminal"><i class="ti ti-device-desktop"></i></a></li>
        </ul>
    </div>

    <livewire:commerce.pos.admin.register-manager />
@endsection
