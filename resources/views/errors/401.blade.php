<x-error-page
    status="401"
    title="Authentication required"
    message="Your session does not include the credentials required for this page. Sign in again or return to the public website."
    illustration="401-unauthorized.png"
>
    <x-slot:actions>
        <a href="{{ route('login') }}" class="btn btn-primary px-4"><i class="ti ti-login"></i>Sign in</a>
        <a href="{{ url('/') }}" class="btn btn-outline-secondary px-4"><i class="ti ti-home"></i>Public site</a>
    </x-slot:actions>
</x-error-page>

