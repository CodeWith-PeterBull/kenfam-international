<x-error-page
    status="500"
    title="Something went wrong"
    message="The server could not complete this request. Try the page once more, and contact the support team if the problem continues."
    illustration="500-server-error.png"
>
    <x-slot:actions>
        <button type="button" class="btn btn-primary px-4" onclick="window.location.reload()"><i class="ti ti-refresh"></i>Try again</button>
        <a href="{{ url('/') }}" class="btn btn-outline-secondary px-4"><i class="ti ti-home"></i>Home</a>
    </x-slot:actions>
</x-error-page>

