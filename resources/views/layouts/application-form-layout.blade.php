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

    {{-- Applicants hand over personal data: link the platform privacy policy --}}
    @php($privacy = \App\Models\Page::publishedUrl('privacy-policy'))
    @if ($privacy)
        <footer class="py-6 text-center text-xs text-muted-foreground">
            <a href="{{ $privacy }}" target="_blank" rel="noopener" class="underline underline-offset-4 hover:text-foreground">Privacy Policy</a>
        </footer>
    @endif

    <x-ui.toaster />
    <x-ui.confirm-dialog />

    @stack('scripts')
    @include('partials.tawk-widget')
</body>

</html>
