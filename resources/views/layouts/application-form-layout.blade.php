<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <title>@yield('title')</title>

    {{-- Pages with @section('themed') follow light/dark/system; the 10 steps stay light until batch 4d. --}}
    @include('partials.theme-head', ['colorMode' => View::hasSection('themed') ? null : 'light'])

    {{-- Private applicant flow: noindex (seo-checklist) --}}
    <x-seo />
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @if (settings('favicon'))
        <link rel="icon" type="image/png" href="{{ asset('storage/' . settings('favicon')) }}">
    @else
        <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- The application steps still use Font Awesome icons; removed in batch 4d. --}}
    @unless (View::hasSection('themed'))
        <script src="https://kit.fontawesome.com/14c3ef5dcc.js" crossorigin="anonymous"></script>
    @endunless

    @stack('styles')
</head>

<body class="min-h-dvh bg-background font-sans text-foreground antialiased">
    <a href="#main" class="skip-link">Skip to content</a>

    <main id="main">
        @yield('content')
    </main>

    <x-ui.confirm-dialog />

    @stack('scripts')
    @include('partials.tawk-widget')
</body>

</html>
