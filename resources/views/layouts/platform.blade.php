<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <title>@hasSection('title')@yield('title') | @endif Platform | {{ settings('site_name', config('app.name')) }}</title>

    @include('partials.theme-head')

    {{-- Private area: noindex (seo-checklist) --}}
    <x-seo />
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @if (settings('favicon'))
        <link rel="icon" type="image/png" href="{{ asset('storage/' . settings('favicon')) }}">
    @else
        <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('datatable')
    @stack('styles')
</head>

<body class="min-h-dvh bg-background font-sans text-foreground antialiased" x-data="appShell">
    <a href="#main" class="skip-link">Skip to content</a>

    @php($platformUser = auth('platform')->user())
    @php($menu = \App\Support\Navigation\PlatformMenu::for($platformUser, request()))
    <div class="flex min-h-dvh">
        <x-layout.sidebar :groups="$menu" :home="route('platform.dashboard')" label="Platform navigation" />

        <div class="flex min-w-0 flex-1 flex-col">
            <x-layout.header>
                <span class="hidden text-sm font-medium text-muted-foreground sm:inline">Platform administration</span>
                @if ($platformUser)
                    <x-slot:user>
                        <x-layout.user-menu :name="$platformUser->name" :email="$platformUser->email" :logout="route('platform.logout')" />
                    </x-slot:user>
                @endif
            </x-layout.header>

            <main id="main" tabindex="-1" class="mx-auto w-full max-w-screen-2xl flex-1 p-4 outline-hidden sm:p-6 lg:p-8">
                <x-ui.flash />

                @yield('content')
            </main>
        </div>
    </div>

    <x-ui.toaster />
    <x-ui.confirm-dialog />

    @stack('scripts')
</body>

</html>
