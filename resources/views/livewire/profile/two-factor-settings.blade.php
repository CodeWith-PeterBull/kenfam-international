{{--
    Security card body: self-service email-OTP two-factor control.

    Presentation only — all state transitions live in
    App\Livewire\Profile\TwoFactorSettings. Receives from render():
    - $systemEnabled  global two-factor.enabled master switch
    - $confirmedAt    last successful challenge timestamp (nullable)
--}}
<div>
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <span class="badge {{ $enabled ? 'bg-success' : 'bg-secondary' }}">
                {{ $enabled ? 'Enabled' : 'Disabled' }}
            </span>
            @if ($confirmedAt)
                <span class="aureon-muted fs-13 ms-2">Last verified {{ $confirmedAt->diffForHumans() }}</span>
            @endif
        </div>
        <i class="ti ti-shield-lock fs-24 {{ $enabled ? 'text-success' : 'text-muted' }}" aria-hidden="true"></i>
    </div>

    <p class="aureon-muted">
        With two-factor authentication enabled, signing in requires a one-time
        code sent to your email address in addition to your password.
    </p>

    @unless ($systemEnabled)
        <div class="alert alert-secondary fs-13" role="alert">
            <i class="ti ti-info-circle me-1"></i>
            System-wide two-factor authentication is currently switched off.
            Your preference is saved and takes effect as soon as an
            administrator enables it.
        </div>
    @endunless

    @if ($status)
        <div class="alert alert-success" role="alert">{{ $status }}</div>
    @endif

    <div class="mb-3">
        <label for="two-factor-current-password" class="form-label">
            Confirm your password <span class="text-danger">*</span>
        </label>
        <input id="two-factor-current-password" type="password"
            wire:model="currentPassword"
            class="form-control @error('currentPassword') is-invalid @enderror"
            autocomplete="current-password"
            placeholder="Required to change this setting">
        @error('currentPassword')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    @if ($enabled)
        <button type="button" class="btn btn-outline-danger" wire:click="disable" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="disable">Disable two-factor authentication</span>
            <span wire:loading wire:target="disable">Disabling…</span>
        </button>
    @else
        <button type="button" class="btn btn-primary" wire:click="enable" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="enable">Enable two-factor authentication</span>
            <span wire:loading wire:target="enable">Enabling…</span>
        </button>
    @endif
</div>
