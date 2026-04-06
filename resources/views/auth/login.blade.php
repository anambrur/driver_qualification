<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign In — {{ config('app.name', 'Laravel') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak] {
            display: none !important;
        }

        body {
            font-family: 'Outfit', sans-serif;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-12px);
            }
        }

        @keyframes float-delayed {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-8px);
            }
        }

        @keyframes gradientMove {

            0%,
            100% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes pulse-soft {

            0%,
            100% {
                opacity: 0.4;
            }

            50% {
                opacity: 0.8;
            }
        }

        .animate-float {
            animation: float 6s ease-in-out infinite;
        }

        .animate-float-delayed {
            animation: float-delayed 5s ease-in-out 1s infinite;
        }

        .animate-gradient {
            background-size: 200% 200%;
            animation: gradientMove 8s ease infinite;
        }

        .animate-fadeInUp {
            animation: fadeInUp 0.6s ease-out both;
        }

        .animate-slideInRight {
            animation: slideInRight 0.6s ease-out both;
        }

        .animate-pulse-soft {
            animation: pulse-soft 4s ease-in-out infinite;
        }

        .auth-input {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .auth-input:focus {
            box-shadow: 0 0 0 4px rgba(70, 95, 255, 0.12);
            border-color: #465fff;
        }

        .glass-panel {
            background: rgba(255, 255, 255, 0.06);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
    </style>
</head>

<body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 antialiased">
    <div class="min-h-screen flex">

        {{-- ============================================================ --}}
        {{-- LEFT: Branding Panel --}}
        {{-- ============================================================ --}}
        <div class="hidden lg:flex lg:w-1/2 relative overflow-hidden">
            {{-- Gradient background --}}
            <div class="absolute inset-0 bg-gradient-to-br from-brand-600 via-brand-700 to-brand-900 animate-gradient">
            </div>

            {{-- Floating orbs --}}
            <div class="absolute inset-0 pointer-events-none">
                <div class="absolute top-20 left-16 w-64 h-64 bg-white/5 rounded-full blur-3xl animate-float"></div>
                <div
                    class="absolute bottom-32 right-12 w-80 h-80 bg-brand-400/10 rounded-full blur-3xl animate-float-delayed">
                </div>
                <div
                    class="absolute top-1/2 left-1/3 w-40 h-40 bg-success-400/10 rounded-full blur-2xl animate-pulse-soft">
                </div>
            </div>

            {{-- Grid pattern overlay --}}
            <div class="absolute inset-0 opacity-[0.03]"
                style="background-image: radial-gradient(circle, white 1px, transparent 1px); background-size: 30px 30px;">
            </div>

            {{-- Content --}}
            <div class="relative z-10 flex flex-col justify-between p-12 xl:p-16 w-full">
                {{-- Logo --}}
                <div>
                    <a href="/" class="inline-block">
                        @if (settings('logo'))
                            <img src="{{ asset('storage/' . settings('logo')) }}"
                                alt="{{ settings('site_name', 'Company') }} Logo" class="h-8 max-w-xs object-contain">
                        @else
                            <img src="{{ asset('images/logo/logo.svg') }}"
                                alt="Driver Qualification File Management Logo" class="h-8 dark:hidden">
                            <img src="{{ asset('images/logo/logo-dark.svg') }}"
                                alt="Driver Qualification File Management Logo" class="h-8 hidden dark:block">
                        @endif
                    </a>
                </div>

                {{-- Main messaging --}}
                <div class="max-w-md">
                    <h1 class="text-3xl xl:text-4xl font-extrabold text-white leading-tight mb-6">
                        Welcome back to your
                        <span class="text-brand-200">compliance dashboard.</span>
                    </h1>
                    <p class="text-brand-100/80 text-base leading-relaxed mb-10">
                        Access your driver qualification files, track expirations, and stay audit-ready — all from one
                        secure platform.
                    </p>

                    {{-- Feature highlights --}}
                    <div class="space-y-4">
                        @php
                            $highlights = [
                                [
                                    'icon' =>
                                        'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
                                    'text' => 'Always audit-ready compliance',
                                ],
                                [
                                    'icon' =>
                                        'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
                                    'text' => 'Automated expiration alerts',
                                ],
                                [
                                    'icon' =>
                                        'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                                    'text' => 'Instant audit-ready reports',
                                ],
                            ];
                        @endphp

                        @foreach ($highlights as $h)
                            <div class="flex items-center gap-3 glass-panel rounded-xl px-4 py-3">
                                <div
                                    class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4 h-4 text-brand-200" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="{{ $h['icon'] }}" />
                                    </svg>
                                </div>
                                <span class="text-sm font-medium text-white/90">{{ $h['text'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Bottom stats --}}
                <div class="flex items-center gap-8">
                    <div>
                        <p class="text-2xl font-bold text-white">5,200+</p>
                        <p class="text-xs text-brand-200/60">Companies</p>
                    </div>
                    <div class="w-px h-8 bg-white/10"></div>
                    <div>
                        <p class="text-2xl font-bold text-white">50K+</p>
                        <p class="text-xs text-brand-200/60">Driver Files</p>
                    </div>
                    <div class="w-px h-8 bg-white/10"></div>
                    <div>
                        <p class="text-2xl font-bold text-white">99.9%</p>
                        <p class="text-xs text-brand-200/60">Audit Pass</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============================================================ --}}
        {{-- RIGHT: Login Form --}}
        {{-- ============================================================ --}}
        <div class="w-full lg:w-1/2 flex items-center justify-center p-6 sm:p-8 lg:p-12">
            <div class="w-full max-w-md animate-slideInRight">

                {{-- Mobile logo --}}
                <div class="lg:hidden text-center mb-8">
                    <a href="/" class="inline-block">
                        <img src="{{ asset('images/logo/logo.svg') }}" alt="Logo" class="h-8 mx-auto dark:hidden">
                        <img src="{{ asset('images/logo/logo-dark.svg') }}" alt="Logo"
                            class="h-8 mx-auto hidden dark:block">
                    </a>
                </div>

                {{-- Header --}}
                <div class="mb-8">
                    <h2 class="text-2xl sm:text-3xl font-bold tracking-tight mb-2">Sign in to your account</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Don't have an account?
                        <a href="{{ route('register') }}"
                            class="font-semibold text-brand-500 hover:text-brand-600 transition-colors">Create one
                            free</a>
                    </p>
                </div>

                {{-- Session Status --}}
                <x-auth-session-status class="mb-4" :status="session('status')" />

                {{-- Login Form --}}
                <form method="POST" action="{{ route('login') }}" class="space-y-5">
                    @csrf

                    {{-- Email --}}
                    <div>
                        <label for="email"
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Email
                            address</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" required
                                autofocus autocomplete="username"
                                class="auth-input w-full pl-11 pr-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-0"
                                placeholder="you@company.com">
                        </div>
                        <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
                    </div>

                    {{-- Password --}}
                    <div x-data="{ showPassword: false }">
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="password"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">Password</label>
                            @if (Route::has('password.request'))
                                <a href="{{ route('password.request') }}"
                                    class="text-xs font-medium text-brand-500 hover:text-brand-600 transition-colors">Forgot
                                    password?</a>
                            @endif
                        </div>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                            <input id="password" :type="showPassword ? 'text' : 'password'" name="password" required
                                autocomplete="current-password"
                                class="auth-input w-full pl-11 pr-11 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-0"
                                placeholder="••••••••">
                            <button type="button" @click="showPassword = !showPassword"
                                class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                                <svg x-show="!showPassword" class="w-5 h-5" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <svg x-show="showPassword" x-cloak class="w-5 h-5" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                </svg>
                            </button>
                        </div>
                        <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
                    </div>

                    {{-- Remember Me --}}
                    <div class="flex items-center">
                        <input id="remember_me" type="checkbox" name="remember"
                            class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-brand-500 focus:ring-brand-500 dark:focus:ring-brand-400 dark:bg-gray-800 transition-colors cursor-pointer">
                        <label for="remember_me"
                            class="ml-2 text-sm text-gray-600 dark:text-gray-400 cursor-pointer select-none">Remember
                            me</label>
                    </div>

                    {{-- Submit --}}
                    <button type="submit"
                        class="w-full py-3 px-4 bg-gradient-to-r from-brand-500 to-brand-600 hover:from-brand-600 hover:to-brand-700 text-white text-sm font-semibold rounded-xl transition-all duration-300 shadow-lg shadow-brand-500/25 hover:shadow-xl hover:shadow-brand-500/40 hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                        Sign In
                    </button>
                </form>

                {{-- Divider --}}
                <div class="relative my-6">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-200 dark:border-gray-700"></div>
                    </div>
                    <div class="relative flex justify-center text-xs">
                        <span class="px-3 bg-gray-50 dark:bg-gray-900 text-gray-400">or continue with</span>
                    </div>
                </div>

                {{-- Social login placeholders --}}
                <!-- <div class="grid grid-cols-2 gap-3">
                    <button type="button" class="flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-750 text-sm font-medium transition-all duration-200 hover:border-gray-300 dark:hover:border-gray-600 hover:-translate-y-0.5 hover:shadow-md">
                        <svg class="w-5 h-5" viewBox="0 0 24 24">
                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 01-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4" />
                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853" />
                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05" />
                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335" />
                        </svg>
                        Google
                    </button>
                    <button type="button" class="flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-750 text-sm font-medium transition-all duration-200 hover:border-gray-300 dark:hover:border-gray-600 hover:-translate-y-0.5 hover:shadow-md">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12.152 6.896c-.948 0-2.415-1.078-3.96-1.04-2.04.027-3.91 1.183-4.961 3.014-2.117 3.675-.546 9.103 1.519 12.09 1.013 1.454 2.208 3.09 3.792 3.039 1.52-.065 2.09-.987 3.935-.987 1.831 0 2.35.987 3.96.948 1.637-.026 2.676-1.48 3.676-2.948 1.156-1.688 1.636-3.325 1.662-3.415-.039-.013-3.182-1.221-3.22-4.857-.026-3.04 2.48-4.494 2.597-4.559-1.429-2.09-3.623-2.324-4.39-2.376-2-.156-3.675 1.09-4.61 1.09zM15.53 3.83c.843-1.012 1.4-2.427 1.245-3.83-1.207.052-2.662.805-3.532 1.818-.78.896-1.454 2.338-1.273 3.714 1.338.104 2.715-.688 3.559-1.701" />
                        </svg>
                        Apple
                    </button>
                </div> -->

                {{-- Footer --}}
                <p class="text-center text-xs text-gray-400 dark:text-gray-500 mt-8">
                    By signing in, you agree to our
                    <a href="#" class="text-brand-500 hover:underline">Terms</a> and
                    <a href="#" class="text-brand-500 hover:underline">Privacy Policy</a>
                </p>
            </div>
        </div>
    </div>
</body>

</html>
