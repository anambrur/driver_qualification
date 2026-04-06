<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="ie=edge" />
    
    <!-- Dynamic Title -->
    <title>
        @hasSection('title')
            @yield('title') | {{ settings('site_name', 'Application') }}
        @else
            {{ settings('meta_title', settings('site_name', 'Laravel Application')) }}
        @endif
    </title>

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

    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.tailwindcss.min.css">
    <!-- Add these Vite directives -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://kit.fontawesome.com/14c3ef5dcc.js" crossorigin="anonymous"></script>

    <!-- Custom Page Styles -->
    @stack('styles')

    <!-- Google Analytics -->
    @php
        $gaId = settings('google_analytics_id', env('GA_MEASUREMENT_ID'));
    @endphp
    @if(!empty($gaId))
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '{{ $gaId }}');
        </script>
    @endif
</head>

<body x-data="{ page: 'ecommerce', 'loaded': true, 'darkMode': false, 'stickyMenu': false, 'sidebarToggle': false, 'scrollTop': false }" x-init="darkMode = JSON.parse(localStorage.getItem('darkMode'));
$watch('darkMode', value => localStorage.setItem('darkMode', JSON.stringify(value)))" :class="{ 'dark bg-gray-900': darkMode === true }">
    <!-- ===== Preloader Start ===== -->
    @include('partials.preloader')
    <!-- ===== Preloader End ===== -->

    <!-- ===== Page Wrapper Start ===== -->
    <div class="flex h-screen overflow-hidden">
        <!-- ===== Sidebar Start ===== -->
        @include('partials.sidebar')
        <!-- ===== Sidebar End ===== -->

        <!-- ===== Content Area Start ===== -->
        <div class="relative flex flex-col flex-1 overflow-x-hidden overflow-y-auto">
            <!-- Small Device Overlay Start -->
            @include('partials.overlay')
            <!-- Small Device Overlay End -->

            <!-- ===== Header Start ===== -->
            @include('partials.header')
            <!-- ===== Header End ===== -->

            <x-subscription-alert />

            <!-- ===== Main Content Start ===== -->
            <main>
                @yield('content')
            </main>
            <!-- ===== Main Content End ===== -->
        </div>
        <!-- ===== Content Area End ===== -->
    </div>
    <!-- ===== Page Wrapper End ===== -->

    <!-- jQuery (required for Toastr and DataTables) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.tailwindcss.min.js"></script>

    @stack('scripts')
</body>

</html>
