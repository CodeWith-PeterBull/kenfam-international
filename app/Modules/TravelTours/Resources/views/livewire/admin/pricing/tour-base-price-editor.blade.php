<div class="travel-editor-form" aria-label="Tour base pricing">
    @if(session('success'))<div class="alert alert-success mx-4 mt-3 mb-0" role="status">{{ session('success') }}</div>@endif
    @error('pricing')<div class="alert alert-danger mx-4 mt-3 mb-0" role="alert">{{ $message }}</div>@enderror
    <section class="travel-editor-section" aria-labelledby="tour-base-pricing-heading">
        <div class="travel-editor-section-heading"><span>01</span><div><h4 id="tour-base-pricing-heading">Base participant prices</h4><p>Default public fares per traveler. Departure dates and offers are managed separately.</p></div></div>
        @if($plan && !$plan->is_active)<div class="alert alert-warning">The current default plan is inactive. Saving base prices will activate it.</div>@endif
        <form wire:submit="save" class="row g-3" novalidate>
            <div class="col-md-8"><label class="form-label" for="tour-rate-name">Rate name</label><input id="tour-rate-name" class="form-control @error('form.name') is-invalid @enderror" wire:model.blur="form.name" maxlength="160" @disabled(!auth()->user()->can(\App\Modules\TravelTours\Support\TravelToursPermission::MANAGE_PRICING))>@error('form.name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-4"><label class="form-label" for="tour-rate-currency">Currency (ISO)</label><input id="tour-rate-currency" class="form-control @error('form.currency') is-invalid @enderror" wire:model.blur="form.currency" maxlength="3" @disabled(!auth()->user()->can(\App\Modules\TravelTours\Support\TravelToursPermission::MANAGE_PRICING))>@error('form.currency')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            @foreach(['adult' => 'Adult', 'child' => 'Child', 'infant' => 'Infant'] as $type => $label)
                <div class="col-md-4"><label class="form-label" for="tour-rate-{{ $type }}">{{ $label }} price</label><input id="tour-rate-{{ $type }}" inputmode="decimal" class="form-control @error('form.'.$type) is-invalid @enderror" wire:model.blur="form.{{ $type }}" placeholder="{{ $type === 'adult' ? 'Required' : 'Not offered' }}" @disabled(!auth()->user()->can(\App\Modules\TravelTours\Support\TravelToursPermission::MANAGE_PRICING))>@error('form.'.$type)<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            @endforeach
            @can(\App\Modules\TravelTours\Support\TravelToursPermission::MANAGE_PRICING)<div class="col-12 d-flex justify-content-end"><button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">Save base prices</button></div>@endcan
        </form>
    </section>
</div>
