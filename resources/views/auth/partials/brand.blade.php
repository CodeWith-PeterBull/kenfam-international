@php($authInstitution = app(\App\Contracts\ResolvesInstitutionProfile::class)->current())

<a href="{{ url('/') }}" class="login-logo logo-normal">
    <img src="{{ $authInstitution->mainLogoUrl }}" alt="{{ $authInstitution->name }}">
</a>
<a href="{{ url('/') }}" class="login-logo logo-white">
    <img src="{{ $authInstitution->lightLogoUrl ?: $authInstitution->mainLogoUrl }}" alt="{{ $authInstitution->name }}">
</a>
