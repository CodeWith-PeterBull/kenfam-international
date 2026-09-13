@props(['url'])
@php($institutionProfile = app(\App\Contracts\ResolvesInstitutionProfile::class)->current())
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; text-align: center;">
@if ($institutionProfile->mainLogoUrl)
<img src="{{ $institutionProfile->mainLogoUrl }}" class="logo" alt="{{ $institutionProfile->name }} logo" style="display: block; width: auto; max-width: 190px; max-height: 66px; margin: 0 auto 10px;">
@endif
<span style="display: block; color: #1d1d25; font-size: 18px; font-weight: 700;">{{ $institutionProfile->name }}</span>
@if ($institutionProfile->descriptor)
<span style="display: block; margin-top: 4px; color: #6d7079; font-size: 11px; font-weight: 400;">{{ $institutionProfile->descriptor }}</span>
@endif
</a>
</td>
</tr>
