<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#6a753d">
<title>@yield('title', 'Dashboard') | {{ config('app.name') }}</title>
<link rel="icon" type="image/png" href="{{ asset(config('kenfam.brand.favicon')) }}">
