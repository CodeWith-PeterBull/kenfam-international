{{-- Server-side brand colours for the storefront theme: the default (olive) palette follows config('kenfam.colors'). Include after theme.css. --}}
@php($brandColors = (array) config('kenfam.colors', []))
<style>
    :root {
        --brand-primary: {{ $brandColors['primary'] ?? '#6a753d' }};
        --brand-secondary: {{ $brandColors['secondary'] ?? '#70233a' }};
        --brand-accent: {{ $brandColors['accent'] ?? '#b28a4b' }};
        --custom-primary: {{ $brandColors['primary'] ?? '#6a753d' }};
        --custom-secondary: {{ $brandColors['secondary'] ?? '#70233a' }};
    }
</style>
