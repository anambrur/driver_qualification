{{-- Shared auth shell: brand panel (lg+) beside the form. Indexing is decided by config/seo.php (only login/register). --}}
@props([
    'title',
    'heading',
    'description' => null,
    'area' => 'tenant', // tenant | platform
    'status' => true, // false when the page renders session('status') itself
])
@php
    $siteName = settings('site_name', config('app.name'));
    $logo = settings('logo') ? asset('storage/'.settings('logo')) : null;
    $flash = array_filter([
        'success' => $status ? session('status') : null,
        'danger' => session('error'),
        'warning' => session('warning'),
    ], fn ($message) => is_string($message) && $message !== '');
    $highlights = $area === 'platform'
        ? [['shield-check', 'Staff-only access, audited'], ['building-2', 'Every company in one place'], ['life-buoy', 'Time-boxed support sessions']]
        : [['shield-check', 'Audit-ready driver qualification files'], ['bell-ring', 'Automatic expiry alerts'], ['file-chart-column', 'Compliance reports in one click']];
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <title>{{ $title }} | {{ $siteName }}</title>

    @include('partials.theme-head')

    <x-seo :title="$title.' | '.$siteName" :description="$description" />
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @if (settings('favicon'))
        <link rel="icon" type="image/png" href="{{ asset('storage/' . settings('favicon')) }}">
    @else
        <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-dvh bg-background font-sans text-foreground antialiased" data-layout="auth">
    <a href="#main" class="skip-link">Skip to content</a>

    <div class="grid min-h-dvh lg:grid-cols-2">
        <aside class="relative hidden overflow-hidden bg-linear-to-br from-brand-700 via-brand-800 to-brand-950 p-12 text-white lg:flex lg:flex-col lg:justify-between xl:p-16">
            <div class="pointer-events-none absolute -right-24 -top-24 size-96 rounded-full bg-white/5 blur-3xl" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -bottom-32 -left-16 size-96 rounded-full bg-brand-400/15 blur-3xl" aria-hidden="true"></div>

            <a href="{{ url('/') }}" class="relative inline-flex w-fit">
                <img src="{{ $logo ?? asset('images/logo/logo-dark.svg') }}" alt="{{ $siteName }}" class="h-10 w-auto max-w-56 object-contain">
            </a>

            <div class="relative max-w-md">
                @isset($aside)
                    {{ $aside }}
                @else
                    <p class="text-3xl font-semibold leading-tight tracking-tight xl:text-4xl">
                        @if ($area === 'platform')
                            Platform administration
                        @else
                            Driver qualification files, <span class="text-brand-200">always audit-ready.</span>
                        @endif
                    </p>
                    <ul class="mt-8 space-y-3">
                        @foreach ($highlights as [$icon, $text])
                            <li class="flex items-center gap-3 rounded-lg border border-white/10 bg-white/5 px-4 py-3 text-sm font-medium text-white/90">
                                <x-ui.icon :name="$icon" class="size-4 shrink-0 text-brand-200" />
                                {{ $text }}
                            </li>
                        @endforeach
                    </ul>
                @endisset
            </div>

            <p class="relative text-xs text-white/60">&copy; {{ date('Y') }} {{ $siteName }}</p>
        </aside>

        <div class="flex min-w-0 flex-col">
            <header class="flex items-center justify-between gap-4 p-4 sm:p-6">
                <a href="{{ url('/') }}" class="inline-flex lg:invisible">
                    @if ($logo)
                        <img src="{{ $logo }}" alt="{{ $siteName }}" class="h-9 w-auto max-w-48 object-contain">
                    @else
                        <img src="{{ asset('images/logo/logo.svg') }}" alt="{{ $siteName }}" class="h-9 w-auto dark:hidden">
                        <img src="{{ asset('images/logo/logo-dark.svg') }}" alt="" aria-hidden="true" class="hidden h-9 w-auto dark:block">
                    @endif
                </a>
                <x-ui.theme-toggle />
            </header>

            <main id="main" class="flex flex-1 items-start justify-center px-4 pt-6 pb-12 sm:items-center sm:px-6 sm:pt-0">
                <div class="w-full max-w-sm">
                    <h1 class="text-2xl font-semibold tracking-tight">{{ $heading }}</h1>
                    @isset($intro)
                        <p class="mt-2 text-sm text-muted-foreground">{{ $intro }}</p>
                    @endisset

                    @if ($flash)
                        <div class="mt-6 space-y-3">
                            @foreach ($flash as $tone => $message)
                                <x-ui.alert :tone="$tone">{{ $message }}</x-ui.alert>
                            @endforeach
                        </div>
                    @endif

                    <div class="mt-6">
                        {{ $slot }}
                    </div>

                    @isset($footer)
                        <div class="mt-8 text-center text-sm text-muted-foreground">{{ $footer }}</div>
                    @endisset
                </div>
            </main>
        </div>
    </div>

    @if ($area === 'tenant')
        @guest
            @include('partials.tawk-widget')
        @endguest
    @endif
</body>

</html>
