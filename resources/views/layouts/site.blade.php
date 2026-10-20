<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php($siteName = settings('site_name', config('app.name')))
    <title>@yield('title') | {{ $siteName }}</title>

    @include('partials.theme-head')

    {{-- Public marketing pages: indexable through config/seo.php. Sections arrive escaped; x-seo escapes again. --}}
    @php($section = fn (string $name) => html_entity_decode(trim($__env->yieldContent($name)), ENT_QUOTES | ENT_HTML5) ?: null)
    <x-seo :title="$section('title').' | '.$siteName" :description="$section('description')" :image="$section('image')"
        :type="$section('og_type') ?? 'website'" :schema="$schema ?? null" />
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('partials.favicon')

    @php($gaId = settings('google_analytics_id', config('app.ga_measurement_id')))
    @if (! empty($gaId))
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', @json($gaId));
        </script>
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="flex min-h-dvh flex-col bg-background font-sans text-foreground antialiased">
    <a href="#main" class="skip-link">Skip to content</a>

    <x-site.header />

    <main id="main" tabindex="-1" class="flex-1 outline-hidden">
        @yield('content')
    </main>

    <x-site.footer />

    <x-ui.toaster />
    @include('partials.tawk-widget')
</body>

</html>
