{{--
    Update-password card body (Bootstrap rewrite of the Breeze partial).
    Stock Breeze flow: PUT password.update, validated into the
    `updatePassword` error bag by PasswordController.
--}}
<p class="aureon-muted">Use a long, random password to keep your account secure.</p>

<form method="post" action="{{ route('password.update') }}">
    @csrf
    @method('put')

    <div class="mb-3">
        <label for="update_password_current_password" class="form-label">Current password <span class="text-danger">*</span></label>
        <input id="update_password_current_password" type="password" name="current_password"
            class="form-control @error('current_password', 'updatePassword') is-invalid @enderror"
            autocomplete="current-password">
        @error('current_password', 'updatePassword')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3">
        <label for="update_password_password" class="form-label">New password <span class="text-danger">*</span></label>
        <input id="update_password_password" type="password" name="password"
            class="form-control @error('password', 'updatePassword') is-invalid @enderror"
            autocomplete="new-password">
        @error('password', 'updatePassword')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3">
        <label for="update_password_password_confirmation" class="form-label">Confirm new password <span class="text-danger">*</span></label>
        <input id="update_password_password_confirmation" type="password" name="password_confirmation"
            class="form-control @error('password_confirmation', 'updatePassword') is-invalid @enderror"
            autocomplete="new-password">
        @error('password_confirmation', 'updatePassword')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    <div class="d-flex align-items-center gap-3">
        <button type="submit" class="btn btn-primary">Save</button>

        @if (session('status') === 'password-updated')
            <span class="text-success fs-13"><i class="ti ti-check me-1"></i>Saved.</span>
        @endif
    </div>
</form>
