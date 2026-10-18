{{-- Not found. Private like every error page: SeoRobotsHeader keeps it noindex; the status stays 404. --}}
@php($siteName = settings('site_name', config('app.name')))
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <title>Page not found | {{ $siteName }}</title>

    @include('partials.theme-head')

    <x-seo :title="'Page not found | '.$siteName" />

    @if (settings('favicon'))
        <link rel="icon" type="image/png" href="{{ asset('storage/' . settings('favicon')) }}">
    @else
        <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="flex min-h-dvh flex-col bg-background font-sans text-foreground antialiased">
    <main class="flex flex-1 items-center justify-center p-6">
        <x-ui.empty-state icon="map-pin-off" title="Page not found" class="w-full max-w-md bg-card"
            description="We can’t seem to find the page you are looking for. It may have moved, or the link may be wrong.">
            <x-ui.button :href="url('/')" icon="house">Back to home</x-ui.button>
        </x-ui.empty-state>
    </main>
    <p class="pb-6 text-center text-sm text-muted-foreground">Error 404 · {{ $siteName }}</p>
</body>

</html>
