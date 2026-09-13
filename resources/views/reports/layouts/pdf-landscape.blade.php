<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $reportContext->title }}</title>
    @include('reports.partials.styles', ['orientation' => 'landscape'])
</head>
<body>
    @include('reports.partials.header')
    <main>@yield('content')</main>
    @include('reports.partials.footer')
</body>
</html>

