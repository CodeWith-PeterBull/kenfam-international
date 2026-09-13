{{--
    Danger-zone card body (Bootstrap rewrite of the Breeze partial).
    Stock Breeze flow: DELETE profile.destroy, password confirmed into the
    `userDeletion` error bag. The Alpine x-modal was replaced with a
    Bootstrap modal (the dashboard shell ships bootstrap.bundle, not Alpine);
    a small pushed script reopens the modal when validation fails so the
    error is never hidden behind a closed dialog.
--}}
<p class="aureon-muted">
    Once your account is deleted, all of its resources and data will be
    permanently deleted. Before deleting your account, please download any
    data or information that you wish to retain.
</p>

<button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#delete-account-modal">
    Delete account
</button>

<div class="modal fade" id="delete-account-modal" tabindex="-1" aria-labelledby="delete-account-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="{{ route('profile.destroy') }}">
                @csrf
                @method('delete')

                <div class="modal-header">
                    <h5 class="modal-title" id="delete-account-modal-label">Are you sure you want to delete your account?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <p class="aureon-muted">
                        This action is permanent. Please enter your password to
                        confirm you would like to permanently delete your account.
                    </p>

                    <label for="delete-account-password" class="form-label">Password <span class="text-danger">*</span></label>
                    <input id="delete-account-password" type="password" name="password"
                        class="form-control @error('password', 'userDeletion') is-invalid @enderror"
                        autocomplete="current-password" placeholder="Password">
                    @error('password', 'userDeletion')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete account</button>
                </div>
            </form>
        </div>
    </div>
</div>

@if ($errors->userDeletion->isNotEmpty())
    @push('scripts')
        <script>
            // Re-open the confirmation modal after a failed password check so
            // the validation error is visible without another click.
            document.addEventListener('DOMContentLoaded', () => {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('delete-account-modal')).show();
            });
        </script>
    @endpush
@endif
