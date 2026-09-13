<x-error-page
    status="503"
    title="Service is temporarily unavailable"
    message="Maintenance or a short service interruption is in progress. Check again shortly; your account data remains secure."
    illustration="503-maintenance.gif"
>
    <x-slot:actions>
        <button type="button" class="btn btn-primary px-4" onclick="window.location.reload()"><i class="ti ti-refresh"></i>Check again</button>
        <a href="{{ url('/') }}" class="btn btn-outline-secondary px-4"><i class="ti ti-home"></i>Home</a>
    </x-slot:actions>
</x-error-page>

