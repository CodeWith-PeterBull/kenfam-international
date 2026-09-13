<x-error-page
    status="419"
    title="Your secure session expired"
    message="The form token is no longer current. Refresh the session to clear temporary form state and load a fresh sign-in page."
    illustration="419-page-expired.png"
    :recover-session="true"
>
    <x-slot:actions>
        <button type="button" class="btn btn-primary px-4" data-session-recovery="{{ route('login') }}">
            <i class="ti ti-refresh"></i>Refresh and sign in
        </button>
        <a href="{{ url('/') }}" class="btn btn-outline-secondary px-4"><i class="ti ti-world"></i>Public site</a>
    </x-slot:actions>
</x-error-page>
