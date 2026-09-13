<x-error-page
    status="403"
    title="Access is restricted"
    message="Your account is signed in, but it does not have permission to open this resource. Return to your workspace or contact an administrator."
    illustration="403-forbidden.png"
>
    <x-slot:actions>
        <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="btn btn-primary px-4"><i class="ti ti-layout-dashboard"></i>My workspace</a>
        <a href="{{ url('/') }}" class="btn btn-outline-secondary px-4"><i class="ti ti-world"></i>Public site</a>
    </x-slot:actions>
</x-error-page>

