<x-error-page
    status="404"
    title="We could not find that page"
    message="The address may be outdated, the page may have moved, or the requested module is not available."
    illustration="404-not-found.png"
>
    <x-slot:actions>
        <a href="{{ auth()->check() ? route('dashboard') : url('/') }}" class="btn btn-primary px-4"><i class="ti ti-home"></i>{{ auth()->check() ? 'My workspace' : 'Home' }}</a>
        <button type="button" class="btn btn-outline-secondary px-4" onclick="window.history.back()"><i class="ti ti-arrow-left"></i>Go back</button>
    </x-slot:actions>
</x-error-page>

