<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />

    <!-- Dynamic Title -->
    <title>
        @hasSection('title')
            @yield('title') | {{ settings('site_name', 'Application') }}
        @else
            {{ settings('meta_title', settings('site_name', 'Laravel Application')) }}
        @endif
    </title>

    @include('partials.theme-head')

    <x-seo />

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Primary Meta Tags -->
    <meta name="title" content="{{ View::hasSection('meta_title') ? View::getSection('meta_title') : settings('meta_title', settings('site_name')) }}">
    <meta name="description" content="{{ View::hasSection('meta_description') ? View::getSection('meta_description') : settings('meta_description') }}">
    <meta name="keywords" content="{{ View::hasSection('meta_keywords') ? View::getSection('meta_keywords') : settings('meta_keywords') }}">
    <meta name="author" content="{{ settings('site_name') }}">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="{{ View::hasSection('meta_title') ? View::getSection('meta_title') : settings('meta_title', settings('site_name')) }}">
    <meta property="og:description" content="{{ View::hasSection('meta_description') ? View::getSection('meta_description') : settings('meta_description') }}">
    @if(settings('logo'))
        <meta property="og:image" content="{{ asset('storage/' . settings('logo')) }}">
    @endif

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="{{ url()->current() }}">
    <meta property="twitter:title" content="{{ View::hasSection('meta_title') ? View::getSection('meta_title') : settings('meta_title', settings('site_name')) }}">
    <meta property="twitter:description" content="{{ View::hasSection('meta_description') ? View::getSection('meta_description') : settings('meta_description') }}">
    @if(settings('logo'))
        <meta property="twitter:image" content="{{ asset('storage/' . settings('logo')) }}">
    @endif

    <!-- Favicon -->
    @if(settings('favicon'))
        <link rel="icon" type="image/png" href="{{ asset('storage/' . settings('favicon')) }}">
        <link rel="apple-touch-icon" href="{{ asset('storage/' . settings('favicon')) }}">
    @else
        <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    @endif

    <!-- Page Specific Meta Data -->
    @stack('meta')

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('datatable')
    <script src="https://kit.fontawesome.com/14c3ef5dcc.js" crossorigin="anonymous" defer></script>

    <!-- Custom Page Styles -->
    @stack('styles')

    <!-- Google Analytics -->
    @php
        $gaId = settings('google_analytics_id', config('app.ga_measurement_id'));
    @endphp
    @if(!empty($gaId))
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', @json($gaId));
        </script>
    @endif
</head>

<body class="min-h-dvh bg-background font-sans text-foreground antialiased" x-data="appShell">
    <a href="#main" class="skip-link">Skip to content</a>

    @php($authUser = auth()->user())
    @php($menu = \App\Support\Navigation\CompanyMenu::for($authUser, request()))
    <div class="flex min-h-dvh">
        <x-layout.sidebar :groups="$menu" :home="route('dashboard')" label="Company navigation" />

        <div class="flex min-w-0 flex-1 flex-col">
            <x-layout.header>
                @if ($authUser)
                    <x-slot:user>
                        <x-layout.user-menu :name="$authUser->name" :email="$authUser->email" :logout="route('logout')">
                            <a role="menuitem" href="{{ route('profile.edit') }}">{{ svg('lucide-user-round', '', ['aria-hidden' => 'true']) }} Profile</a>
                            @if ($authUser->company_id)
                                <a role="menuitem" href="{{ route('admin.settings.company.edit', $authUser->company_id) }}">{{ svg('lucide-building-2', '', ['aria-hidden' => 'true']) }} Company settings</a>
                            @endif
                            <a role="menuitem" href="{{ route('billing.index') }}">{{ svg('lucide-credit-card', '', ['aria-hidden' => 'true']) }} Billing</a>
                        </x-layout.user-menu>
                    </x-slot:user>
                @endif
            </x-layout.header>

            <x-support-session-banner />
            <x-subscription-alert />

            <main id="main" tabindex="-1" class="mx-auto w-full max-w-screen-2xl flex-1 outline-hidden">
                @yield('content')
            </main>
        </div>
    </div>

    <x-ui.toaster />
    <x-ui.confirm-dialog />

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        window.showAppAlert = function(message, icon = 'warning', title = null) {
            const titles = {
                warning: 'Attention',
                error: 'Error',
                success: 'Success',
                info: 'Notice',
                question: 'Please Confirm'
            };
            return Swal.fire({
                icon: icon,
                title: title || titles[icon] || 'Attention',
                text: message
            });
        };

        window.showAppConfirm = function(message, options = {}) {
            return Swal.fire({
                title: options.title || 'Please Confirm',
                text: message,
                icon: options.icon || 'question',
                showCancelButton: true,
                confirmButtonText: options.confirmButtonText || 'Yes, continue',
                cancelButtonText: options.cancelButtonText || 'Cancel'
            });
        };
    </script>

    @stack('scripts')
</body>

</html>
