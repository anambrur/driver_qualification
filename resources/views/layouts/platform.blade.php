<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>@hasSection('title')@yield('title') | @endif Platform | {{ settings('site_name', config('app.name')) }}</title>

    {{-- Private area: noindex (seo-checklist) --}}
    <x-seo />
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @if (settings('favicon'))
        <link rel="icon" type="image/png" href="{{ asset('storage/' . settings('favicon')) }}">
    @else
        <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    @endif

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.tailwindcss.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://kit.fontawesome.com/14c3ef5dcc.js" crossorigin="anonymous"></script>
    @stack('styles')
</head>

<body x-data="{ 'darkMode': false, 'sidebarToggle': false }" x-init="darkMode = JSON.parse(localStorage.getItem('darkMode'));
$watch('darkMode', value => localStorage.setItem('darkMode', JSON.stringify(value)))" :class="{ 'dark bg-gray-900': darkMode === true }">
    <div class="flex h-screen overflow-hidden">
        @include('partials.platform-sidebar')

        <div class="relative flex flex-col flex-1 overflow-x-hidden overflow-y-auto">
            <header class="sticky top-0 z-99 flex w-full items-center justify-between border-b border-gray-200 bg-white px-6 py-4 dark:border-gray-800 dark:bg-gray-900">
                <span class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Platform administration</span>
                @auth('platform')
                    <div class="flex items-center gap-4">
                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ auth('platform')->user()->name }}</span>
                        <form method="POST" action="{{ route('platform.logout') }}">
                            @csrf
                            <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-700">Log out</button>
                        </form>
                    </div>
                @endauth
            </header>

            <main class="p-4 mx-auto max-w-(--breakpoint-2xl) md:p-6 w-full">
                @if (session('success'))
                    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/30 dark:text-green-200">{{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/30 dark:text-red-200">{{ session('error') }}</div>
                @endif
                @if ($errors->any())
                    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/30 dark:text-red-200">
                        <ul class="list-disc pl-5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.tailwindcss.min.js"></script>
    <script>
        window.showAppAlert = function(message, icon = 'warning', title = null) {
            return Swal.fire({ icon: icon, title: title || 'Attention', text: message, confirmButtonColor: '#2563eb' });
        };
        window.showAppConfirm = function(message, options = {}) {
            return Swal.fire({
                title: options.title || 'Please Confirm', text: message, icon: options.icon || 'question',
                showCancelButton: true, confirmButtonColor: options.confirmButtonColor || '#2563eb',
                cancelButtonColor: '#6b7280', confirmButtonText: options.confirmButtonText || 'Yes, continue',
            });
        };
    </script>
    @stack('scripts')
</body>

</html>
