<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <title>@yield('title')</title>

    @include('partials.theme-head')

    {{-- Private applicant flow: noindex (seo-checklist) --}}
    <x-seo />
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('partials.favicon')

    @vite(['resources/css/app.css', 'resources/js/app.js'])

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
