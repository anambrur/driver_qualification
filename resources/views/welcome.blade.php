<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- Dynamic Primary SEO Elements -->
    <title>{{ settings('meta_title', settings('site_name', 'Driver Qualification Platform')) }} — Always Audit-Ready</title>
    <meta name="description" content="{{ settings('meta_description', 'Complete Driver Qualification File management platform. Stay DOT audit-ready with automated tracking, expiration alerts, and digital document management.') }}">
    <meta name="keywords" content="{{ settings('meta_keywords', 'driver qualification, DOT compliance, fleet management, trucking software') }}">
    
    <!-- Open Graph (Facebook/LinkedIn) -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="{{ settings('meta_title', settings('site_name', 'Driver Qualification Platform')) }}">
    <meta property="og:description" content="{{ settings('meta_description', 'Complete Driver Qualification File management platform. Stay DOT audit-ready with automated tracking.') }}">
    @if(settings('logo'))
        <meta property="og:image" content="{{ asset('storage/' . settings('logo')) }}">
    @endif
    
    <!-- Favicon Integration -->
    @if(settings('favicon'))
        <link rel="icon" type="image/png" href="{{ asset('storage/' . settings('favicon')) }}">
        <link rel="apple-touch-icon" href="{{ asset('storage/' . settings('favicon')) }}">
    @endif

    <!-- Google Analytics Integration -->
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <style>
        [x-cloak] {
            display: none !important;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Outfit', sans-serif;
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

        @keyframes pulse-ring {
            0% {
                transform: scale(0.8);
                opacity: 0.5;
            }

            50% {
                transform: scale(1.1);
                opacity: 0.2;
            }

            100% {
                transform: scale(0.8);
                opacity: 0.5;
            }
        }

        @keyframes truck-drive {
            0% {
                transform: translateX(-20px);
            }

            50% {
                transform: translateX(20px);
            }

            100% {
                transform: translateX(-20px);
            }
        }

        @keyframes dash-move {
            to {
                stroke-dashoffset: -20;
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-40px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes shimmer {
            0% {
                background-position: -200% 0;
            }

            100% {
                background-position: 200% 0;
            }
        }

        .animate-gradient {
            background-size: 200% 200%;
            animation: gradientMove 6s ease infinite;
        }

        .animate-float {
            animation: float 6s ease-in-out infinite;
        }

        .animate-float-delayed {
            animation: float-delayed 5s ease-in-out 1s infinite;
        }

        .animate-pulse-ring {
            animation: pulse-ring 3s ease-in-out infinite;
        }

        .animate-truck {
            animation: truck-drive 4s ease-in-out infinite;
        }

        .animate-dash {
            animation: dash-move 1s linear infinite;
        }

        .animate-fadeInUp {
            animation: fadeInUp 0.7s ease-out forwards;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .dark .glass-card {
            background: rgba(29, 41, 57, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .glass-card:hover {
            background: rgba(255, 255, 255, 0.85);
            border-color: rgba(70, 95, 255, 0.3);
            transform: translateY(-4px);
            box-shadow: 0 20px 40px -12px rgba(70, 95, 255, 0.15);
        }

        .dark .glass-card:hover {
            background: rgba(29, 41, 57, 0.8);
            border-color: rgba(70, 95, 255, 0.4);
        }

        .hero-gradient {
            background: linear-gradient(135deg, #eff6ff 0%, #ecf3ff 25%, #f0f9ff 50%, #ecfdf3 75%, #eff6ff 100%);
        }

        .dark .hero-gradient {
            background: linear-gradient(135deg, #0c111d 0%, #161950 30%, #0c111d 60%, #053321 100%);
        }

        .shimmer-btn {
            background-size: 200% 100%;
            animation: shimmer 3s linear infinite;
        }

        .feature-icon-wrap {
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .glass-card:hover .feature-icon-wrap {
            transform: scale(1.1) rotate(-5deg);
        }

        .step-connector {
            position: relative;
        }

        .step-connector::after {
            content: '';
            position: absolute;
            top: 50%;
            right: -2rem;
            width: 4rem;
            height: 2px;
            background: linear-gradient(90deg, #465fff, transparent);
        }
    </style>
</head>

<body class="bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 antialiased overflow-x-hidden">

    {{-- ============================================================ --}}
    {{-- NAVIGATION --}}
    {{-- ============================================================ --}}
    <nav x-data="{ mobileOpen: false, scrolled: false }"
        x-init="window.addEventListener('scroll', () => { scrolled = window.scrollY > 20 })"
        :class="scrolled ? 'bg-white/80 dark:bg-gray-900/80 backdrop-blur-xl shadow-lg border-b border-gray-200/50 dark:border-gray-800/50' : 'bg-transparent'"
        class="fixed top-0 left-0 right-0 z-50 transition-all duration-300" id="main-nav">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 lg:h-20">
                {{-- Logo --}}
                <a href="/" class="flex items-center gap-2 group shrink-0" aria-label="{{ settings('site_name', 'Home') }}">
                    @if(settings('logo'))
                        <img src="{{ asset('storage/' . settings('logo')) }}" alt="{{ settings('site_name', 'Company') }} Logo" class="h-11 lg:h-14 w-auto max-w-[200px] lg:max-w-[240px] object-contain">
                    @else
                        <img src="{{ asset('images/logo/logo.svg') }}" alt="Driver Qualification File Management Logo" class="h-11 lg:h-14 w-auto object-contain dark:hidden">
                        <img src="{{ asset('images/logo/logo-dark.svg') }}" alt="Driver Qualification File Management Logo" class="h-11 lg:h-14 w-auto object-contain hidden dark:block">
                    @endif
                </a>

                {{-- Desktop Nav --}}
                <div class="hidden lg:flex items-center gap-8">
                    <a href="#features" class="text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-brand-500 dark:hover:text-brand-400 transition-colors">Features</a>
                    <a href="#how-it-works" class="text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-brand-500 dark:hover:text-brand-400 transition-colors">How It Works</a>
                    <a href="#pricing" class="text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-brand-500 dark:hover:text-brand-400 transition-colors">Pricing</a>
                    <a href="#testimonials" class="text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-brand-500 dark:hover:text-brand-400 transition-colors">Testimonials</a>
                    <a href="#faq" class="text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-brand-500 dark:hover:text-brand-400 transition-colors">FAQ</a>
                </div>

                {{-- Auth Buttons --}}
                <div class="hidden lg:flex items-center gap-3">
                    @if (Route::has('login'))
                    @auth
                    <a href="{{ url('/dashboard') }}" class="px-5 py-2.5 text-sm font-semibold text-white bg-brand-500 hover:bg-brand-600 rounded-xl transition-all duration-200 shadow-md shadow-brand-500/20 hover:shadow-lg hover:shadow-brand-500/30 hover:-translate-y-0.5">Dashboard</a>
                    @else
                    <a href="{{ route('login') }}" class="px-5 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-200 hover:text-brand-500 dark:hover:text-brand-400 transition-colors">Log In</a>
                    @if (Route::has('register'))
                    <a href="{{ route('register') }}" class="px-5 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-brand-500 to-brand-600 hover:from-brand-600 hover:to-brand-700 rounded-xl transition-all duration-200 shadow-md shadow-brand-500/25 hover:shadow-lg hover:shadow-brand-500/40 hover:-translate-y-0.5">Start Free Trial</a>
                    @endif
                    @endauth
                    @endif
                </div>

                {{-- Mobile Toggle --}}
                <button @click="mobileOpen = !mobileOpen" class="lg:hidden p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                    <svg x-show="!mobileOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <svg x-show="mobileOpen" x-cloak class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        {{-- Mobile Menu --}}
        <div x-show="mobileOpen" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            class="lg:hidden bg-white/95 dark:bg-gray-900/95 backdrop-blur-xl border-b border-gray-200 dark:border-gray-800">
            <div class="px-4 py-4 space-y-2">
                <a @click="mobileOpen = false" href="#features" class="block px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">Features</a>
                <a @click="mobileOpen = false" href="#how-it-works" class="block px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">How It Works</a>
                <a @click="mobileOpen = false" href="#pricing" class="block px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">Pricing</a>
                <a @click="mobileOpen = false" href="#testimonials" class="block px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">Testimonials</a>
                <a @click="mobileOpen = false" href="#faq" class="block px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">FAQ</a>
                <hr class="border-gray-200 dark:border-gray-700 my-2">
                @if (Route::has('login'))
                @auth
                <a href="{{ url('/dashboard') }}" class="block px-4 py-2.5 text-center rounded-xl text-sm font-semibold text-white bg-brand-500">Dashboard</a>
                @else
                <a href="{{ route('login') }}" class="block px-4 py-2.5 rounded-lg text-sm font-medium text-center hover:bg-gray-100 dark:hover:bg-gray-800">Log In</a>
                @if (Route::has('register'))
                <a href="{{ route('register') }}" class="block px-4 py-2.5 text-center rounded-xl text-sm font-semibold text-white bg-gradient-to-r from-brand-500 to-brand-600">Start Free Trial</a>
                @endif
                @endauth
                @endif
            </div>
        </div>
    </nav>

    {{-- ============================================================ --}}
    {{-- HERO SECTION --}}
    {{-- ============================================================ --}}
    <section class="hero-gradient relative min-h-screen flex items-center pt-20 overflow-hidden">
        {{-- Animated background orbs --}}
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="absolute top-20 left-10 w-72 h-72 bg-brand-400/10 dark:bg-brand-500/10 rounded-full blur-3xl animate-float"></div>
            <div class="absolute bottom-20 right-10 w-96 h-96 bg-success-400/10 dark:bg-success-500/10 rounded-full blur-3xl animate-float-delayed"></div>
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-blue-light-400/5 rounded-full blur-3xl animate-pulse-ring"></div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 py-12 lg:py-0">
            <div class="grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">
                {{-- Left: Content --}}
                <div class="text-center lg:text-left">
                    <!-- Added header element wrapper for SEO semantic flow -->
                    <header>
                        <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-brand-50 dark:bg-brand-500/10 border border-brand-200 dark:border-brand-500/20 mb-6">
                            <span class="w-2 h-2 rounded-full bg-success-500 animate-pulse"></span>
                            <span class="text-xs font-semibold text-brand-700 dark:text-brand-300 uppercase tracking-wider">DOT Compliance Platform</span>
                        </div>

                        <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold leading-tight tracking-tight mb-6">
                            Every Driver File.
                            <span class="bg-gradient-to-r from-brand-500 via-brand-600 to-blue-light-500 bg-clip-text text-transparent animate-gradient">Always Audit-Ready.</span>
                        </h1>
                    </header>

                    <p class="text-lg sm:text-xl text-gray-600 dark:text-gray-300 leading-relaxed mb-8 max-w-xl mx-auto lg:mx-0">
                        Complete Driver Qualification Files — from hire to compliance. Automated tracking, expiration alerts, and digital document management.
                    </p>

                    <div class="flex flex-col sm:flex-row gap-4 justify-center lg:justify-start mb-10">
                        @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="group px-8 py-4 text-base font-semibold text-white bg-gradient-to-r from-brand-500 to-brand-600 hover:from-brand-600 hover:to-brand-700 rounded-2xl transition-all duration-300 shadow-xl shadow-brand-500/25 hover:shadow-2xl hover:shadow-brand-500/40 hover:-translate-y-1 inline-flex items-center justify-center gap-2">
                            Start Free Trial
                            <svg class="w-5 h-5 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                        </a>
                        @endif
                        <a href="#how-it-works" class="px-8 py-4 text-base font-semibold text-gray-700 dark:text-gray-200 bg-white/60 dark:bg-gray-800/60 backdrop-blur-sm border border-gray-200 dark:border-gray-700 hover:border-brand-300 dark:hover:border-brand-500/40 rounded-2xl transition-all duration-300 hover:-translate-y-1 hover:shadow-lg inline-flex items-center justify-center gap-2">
                            <svg class="w-5 h-5 text-brand-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            See How It Works
                        </a>
                    </div>

                    <div class="flex items-center gap-6 justify-center lg:justify-start text-sm text-gray-500 dark:text-gray-400">
                        <span class="flex items-center gap-1.5"><svg class="w-4 h-4 text-success-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg> No credit card</span>
                        <span class="flex items-center gap-1.5"><svg class="w-4 h-4 text-success-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg> Free 14-day trial</span>
                        <span class="hidden sm:flex items-center gap-1.5"><svg class="w-4 h-4 text-success-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg> Cancel anytime</span>
                    </div>
                </div>

                {{-- Right: CSS-only animated illustration --}}
                <div class="relative flex items-center justify-center">
                    <div class="relative w-full max-w-md mx-auto">
                        {{-- Main dashboard card --}}
                        <div class="glass-card rounded-3xl p-6 shadow-2xl animate-float" style="animation-duration: 7s;">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="w-3 h-3 rounded-full bg-error-400"></div>
                                <div class="w-3 h-3 rounded-full bg-warning-400"></div>
                                <div class="w-3 h-3 rounded-full bg-success-400"></div>
                                <span class="text-xs font-medium text-gray-400 ml-2">Compliance Dashboard</span>
                            </div>
                            <div class="space-y-3">
                                <div class="flex items-center justify-between p-3 rounded-xl bg-success-50 dark:bg-success-500/10">
                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 rounded-lg bg-success-500 flex items-center justify-center"><svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg></div><span class="text-sm font-medium">CDL Valid</span>
                                    </div>
                                    <span class="text-xs font-semibold text-success-600 bg-success-100 dark:bg-success-500/20 px-2 py-1 rounded-full">Active</span>
                                </div>
                                <div class="flex items-center justify-between p-3 rounded-xl bg-warning-50 dark:bg-warning-500/10">
                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 rounded-lg bg-warning-500 flex items-center justify-center"><svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg></div><span class="text-sm font-medium">Medical Card</span>
                                    </div>
                                    <span class="text-xs font-semibold text-warning-600 bg-warning-100 dark:bg-warning-500/20 px-2 py-1 rounded-full">Expiring</span>
                                </div>
                                <div class="flex items-center justify-between p-3 rounded-xl bg-brand-50 dark:bg-brand-500/10">
                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 rounded-lg bg-brand-500 flex items-center justify-center"><svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg></div><span class="text-sm font-medium">MVR Report</span>
                                    </div>
                                    <span class="text-xs font-semibold text-brand-600 bg-brand-100 dark:bg-brand-500/20 px-2 py-1 rounded-full">Current</span>
                                </div>
                            </div>
                            <div class="mt-4 p-3 rounded-xl bg-gray-50 dark:bg-gray-800/50">
                                <div class="flex justify-between items-center mb-2"><span class="text-xs font-medium text-gray-500">Compliance Score</span><span class="text-sm font-bold text-success-600">94%</span></div>
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                    <div class="bg-gradient-to-r from-success-400 to-success-500 h-2 rounded-full" style="width: 94%"></div>
                                </div>
                            </div>
                        </div>

                        {{-- Floating notification --}}
                        <div class="absolute -top-4 -right-4 glass-card rounded-2xl p-3 shadow-xl animate-float-delayed z-10" style="animation-duration: 5s;">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-success-500 flex items-center justify-center"><svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg></div>
                                <div>
                                    <p class="text-xs font-semibold">Audit Ready</p>
                                    <p class="text-[10px] text-gray-400">All files complete</p>
                                </div>
                            </div>
                        </div>

                        {{-- Floating alert --}}
                        <div class="absolute -bottom-2 -left-4 glass-card rounded-2xl p-3 shadow-xl animate-float z-10" style="animation-duration: 6s; animation-delay: 0.5s;">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-brand-500 flex items-center justify-center"><svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                    </svg></div>
                                <div>
                                    <p class="text-xs font-semibold">3 Alerts</p>
                                    <p class="text-[10px] text-gray-400">Documents expiring</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Wave divider --}}
        <div class="absolute bottom-0 left-0 right-0">
            <svg viewBox="0 0 1440 100" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-full">
                <path d="M0 50L48 45.7C96 41.3 192 32.7 288 30.2C384 27.7 480 31.3 576 38.5C672 45.7 768 56.3 864 58.8C960 61.3 1056 55.7 1152 48.5C1248 41.3 1344 32.7 1392 28.3L1440 24V100H1392C1344 100 1248 100 1152 100C1056 100 960 100 864 100C768 100 672 100 576 100C480 100 384 100 288 100C192 100 96 100 48 100H0V50Z" class="fill-white dark:fill-gray-900" />
            </svg>
        </div>
    </section>

    {{-- ============================================================ --}}
    {{-- TRUST / STATS BAR --}}
    {{-- ============================================================ --}}
    <section class="py-12 bg-white dark:bg-gray-900 border-b border-gray-100 dark:border-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 md:gap-8">
                <div class="text-center">
                    <p class="text-3xl sm:text-4xl font-extrabold bg-gradient-to-r from-brand-500 to-brand-600 bg-clip-text text-transparent">5,200+</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Trucking Companies</p>
                </div>
                <div class="text-center">
                    <p class="text-3xl sm:text-4xl font-extrabold bg-gradient-to-r from-success-500 to-success-600 bg-clip-text text-transparent">50K+</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Driver Files Managed</p>
                </div>
                <div class="text-center">
                    <p class="text-3xl sm:text-4xl font-extrabold bg-gradient-to-r from-blue-light-500 to-blue-light-600 bg-clip-text text-transparent">99.9%</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Audit Pass Rate</p>
                </div>
                <div class="text-center">
                    <p class="text-3xl sm:text-4xl font-extrabold bg-gradient-to-r from-warning-500 to-orange-500 bg-clip-text text-transparent">24/7</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Compliance Monitoring</p>
                </div>
            </div>
        </div>
    </section>

    {{-- ============================================================ --}}
    {{-- FEATURES GRID --}}
    {{-- ============================================================ --}}
    <section id="features" class="py-20 lg:py-28 bg-gray-50 dark:bg-gray-900/50 relative overflow-hidden">
        <div class="absolute inset-0 pointer-events-none">
            <div class="absolute top-0 right-0 w-96 h-96 bg-brand-400/5 rounded-full blur-3xl"></div>
            <div class="absolute bottom-0 left-0 w-96 h-96 bg-success-400/5 rounded-full blur-3xl"></div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <span class="inline-flex items-center px-3 py-1 rounded-full bg-brand-50 dark:bg-brand-500/10 text-xs font-semibold text-brand-600 dark:text-brand-400 uppercase tracking-wider mb-4">Features</span>
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight mb-4">Everything You Need for <span class="bg-gradient-to-r from-brand-500 to-brand-600 bg-clip-text text-transparent">DOT Compliance</span></h2>
                <p class="text-lg text-gray-500 dark:text-gray-400">Manage your entire driver qualification process from a single platform — built for 49 CFR Part 391 compliance.</p>
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
                @php
                $features = [
                ['icon' => '
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />', 'color' => 'brand', 'title' => 'DQF Checklists', 'desc' => '49 CFR Part 391 requirements tracked per driver, automatically. Never miss a required document again.'],
                ['icon' => '
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />', 'color' => 'success', 'title' => 'Digital Applications', 'desc' => '10-step digital driver application workflow with OTP verification and real-time progress tracking.'],
                ['icon' => '
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />', 'color' => 'warning', 'title' => 'Expiration Alerts', 'desc' => 'Licenses, medicals, MVRs — get notified before they expire. Automated email reminders keep you compliant.'],
                ['icon' => '
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />', 'color' => 'blue-light', 'title' => 'Fleet Compliance', 'desc' => 'Vehicle & trailer document tracking with real-time compliance scores and maintenance scheduling.'],
                ['icon' => '
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />', 'color' => 'purple', 'title' => 'Maintenance Tracking', 'desc' => 'Service logs, scheduled maintenance, and preventive care management for your entire fleet.'],
                ['icon' => '
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />', 'color' => 'error', 'title' => 'Audit-Ready Reports', 'desc' => 'Generate complete, compliant driver files for DOT inspections instantly. One-click export to PDF.'],
                ];
                $colorMap = ['brand' => 'brand-500', 'success' => 'success-500', 'warning' => 'warning-500', 'blue-light' => 'blue-light-500', 'purple' => 'purple-500', 'error' => 'error-500'];
                $bgMap = ['brand' => 'brand-50 dark:bg-brand-500/10', 'success' => 'success-50 dark:bg-success-500/10', 'warning' => 'warning-50 dark:bg-warning-500/10', 'blue-light' => 'blue-light-50 dark:bg-blue-light-500/10', 'purple' => 'purple-500/10', 'error' => 'error-50 dark:bg-error-500/10'];
                @endphp

                @foreach ($features as $i => $f)
                <div class="glass-card rounded-2xl p-6 transition-all duration-500 cursor-default group" style="animation: fadeInUp 0.6s ease-out {{ $i * 0.1 }}s both;">
                    <div class="feature-icon-wrap w-12 h-12 rounded-xl bg-{{ $bgMap[$f['color']] }} flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-{{ $colorMap[$f['color']] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $f['icon'] !!}</svg>
                    </div>
                    <h3 class="text-lg font-bold mb-2">{{ $f['title'] }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 leading-relaxed">{{ $f['desc'] }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============================================================ --}}
    {{-- HOW IT WORKS --}}
    {{-- ============================================================ --}}
    <section id="how-it-works" class="py-20 lg:py-28 bg-white dark:bg-gray-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <span class="inline-flex items-center px-3 py-1 rounded-full bg-success-50 dark:bg-success-500/10 text-xs font-semibold text-success-600 dark:text-success-400 uppercase tracking-wider mb-4">How It Works</span>
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight mb-4">Get Audit-Ready in <span class="bg-gradient-to-r from-success-500 to-success-600 bg-clip-text text-transparent">3 Simple Steps</span></h2>
                <p class="text-lg text-gray-500 dark:text-gray-400">No complex setup. No steep learning curve. Start managing your driver files in minutes.</p>
            </div>

            <div class="grid md:grid-cols-3 gap-8 lg:gap-12">
                @php
                $steps = [
                ['num' => '01', 'title' => 'Sign Up & Setup', 'desc' => 'Create your company profile, configure compliance requirements, and invite your team. Takes less than 5 minutes.', 'icon' => '
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />'],
                ['num' => '02', 'title' => 'Add Drivers & Fleet', 'desc' => 'Onboard drivers digitally with our 10-step application. Register vehicles and trailers with document tracking.', 'icon' => '
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />'],
                ['num' => '03', 'title' => 'Stay Compliant', 'desc' => 'Automated tracking, expiration alerts, and audit-ready reporting keep you compliant 24/7. Zero effort.', 'icon' => '
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />'],
                ];
                @endphp

                @foreach ($steps as $i => $step)
                <div class="relative text-center group">
                    <div class="w-20 h-20 mx-auto mb-6 rounded-2xl bg-gradient-to-br from-brand-500 to-brand-600 flex items-center justify-center shadow-xl shadow-brand-500/20 group-hover:shadow-2xl group-hover:shadow-brand-500/30 transition-all duration-300 group-hover:-translate-y-2">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $step['icon'] !!}</svg>
                    </div>
                    <span class="inline-block text-xs font-bold text-brand-500 bg-brand-50 dark:bg-brand-500/10 px-3 py-1 rounded-full mb-3">Step {{ $step['num'] }}</span>
                    <h3 class="text-xl font-bold mb-2">{{ $step['title'] }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 leading-relaxed max-w-xs mx-auto">{{ $step['desc'] }}</p>
                    @if ($i < 2)
                        <div class="hidden md:block absolute top-10 -right-6 lg:-right-6">
                        <svg class="w-12 h-12 text-gray-200 dark:text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                </div>
                @endif
            </div>
            @endforeach
        </div>
        </div>
    </section>

    {{-- ============================================================ --}}
    {{-- PRICING --}}
    {{-- ============================================================ --}}
    <section id="pricing" class="py-20 lg:py-28 bg-gray-50 dark:bg-gray-900/50 relative overflow-hidden">
        <div class="absolute inset-0 pointer-events-none">
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[800px] h-[800px] bg-brand-400/5 rounded-full blur-3xl"></div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <span class="inline-flex items-center px-3 py-1 rounded-full bg-brand-50 dark:bg-brand-500/10 text-xs font-semibold text-brand-600 dark:text-brand-400 uppercase tracking-wider mb-4">Pricing</span>
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight mb-4">Simple, Transparent <span class="bg-gradient-to-r from-brand-500 to-brand-600 bg-clip-text text-transparent">Pricing</span></h2>
                <p class="text-lg text-gray-500 dark:text-gray-400">Choose the plan that fits your fleet. All plans include a free trial.</p>
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-{{ min(count($plans ?? []), 3) ?: 3 }} gap-8 max-w-5xl mx-auto">
                @forelse ($plans ?? [] as $plan)
                <div class="relative glass-card rounded-3xl p-8 transition-all duration-500 flex flex-col {{ $plan->is_featured ? 'ring-2 ring-brand-500 shadow-xl shadow-brand-500/10' : '' }}">
                    @if ($plan->is_featured)
                    <div class="absolute -top-4 left-1/2 -translate-x-1/2">
                        <span class="px-4 py-1.5 rounded-full bg-gradient-to-r from-brand-500 to-brand-600 text-white text-xs font-bold uppercase tracking-wider shadow-lg">Most Popular</span>
                    </div>
                    @endif

                    <h3 class="text-xl font-bold mb-2">{{ $plan->name }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">{{ $plan->description ?? 'Perfect for growing fleets' }}</p>

                    <div class="mb-6">
                        <span class="text-4xl font-extrabold">${{ number_format($plan->price, 0) }}</span>
                        <span class="text-sm text-gray-500 dark:text-gray-400">/{{ $plan->billing_cycle ?? 'month' }}</span>
                    </div>

                    @if ($plan->features)
                    <ul class="space-y-3 mb-8 flex-grow">
                        @foreach ($plan->features as $feature)
                        <li class="flex items-start gap-2 text-sm">
                            <svg class="w-5 h-5 text-success-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                            <span class="text-gray-600 dark:text-gray-300">{{ $feature }}</span>
                        </li>
                        @endforeach
                    </ul>
                    @endif

                    @if (Route::has('register'))
                    <a href="{{ route('checkout', $plan->slug ?? $plan->name) }}" class="block w-full py-3 text-center rounded-xl font-semibold transition-all duration-300 {{ $plan->is_featured ? 'bg-gradient-to-r from-brand-500 to-brand-600 text-white shadow-lg shadow-brand-500/25 hover:shadow-xl hover:shadow-brand-500/40 hover:-translate-y-0.5' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-brand-50 dark:hover:bg-brand-500/10 hover:text-brand-600 dark:hover:text-brand-400' }}">
                        Get Started
                    </a>
                    @endif
                </div>
                @empty
                {{-- Fallback static pricing --}}
                @php
                $staticPlans = [
                ['name' => 'Starter', 'price' => 49, 'desc' => 'For small fleets', 'featured' => false, 'features' => ['Up to 10 drivers', 'Basic compliance tracking', 'Email alerts', 'Document storage', 'Standard support']],
                ['name' => 'Professional', 'price' => 99, 'desc' => 'For growing carriers', 'featured' => true, 'features' => ['Up to 50 drivers', 'Full DQF management', 'Automated alerts & reminders', 'Fleet compliance dashboard', 'Priority support', 'Custom application forms']],
                ['name' => 'Enterprise', 'price' => 199, 'desc' => 'For large operations', 'featured' => false, 'features' => ['Unlimited drivers', 'Multi-company support', 'Advanced analytics', 'API access', 'Dedicated account manager', 'Custom integrations']],
                ];
                @endphp
                @foreach ($staticPlans as $sp)
                <div class="relative glass-card rounded-3xl p-8 transition-all duration-500 flex flex-col {{ $sp['featured'] ? 'ring-2 ring-brand-500 shadow-xl shadow-brand-500/10' : '' }}">
                    @if ($sp['featured'])
                    <div class="absolute -top-4 left-1/2 -translate-x-1/2"><span class="px-4 py-1.5 rounded-full bg-gradient-to-r from-brand-500 to-brand-600 text-white text-xs font-bold uppercase tracking-wider shadow-lg">Most Popular</span></div>
                    @endif
                    <h3 class="text-xl font-bold mb-2">{{ $sp['name'] }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">{{ $sp['desc'] }}</p>
                    <div class="mb-6"><span class="text-4xl font-extrabold">${{ $sp['price'] }}</span><span class="text-sm text-gray-500">/month</span></div>
                    <ul class="space-y-3 mb-8 flex-grow">
                        @foreach ($sp['features'] as $feat)
                        <li class="flex items-start gap-2 text-sm"><svg class="w-5 h-5 text-success-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg><span class="text-gray-600 dark:text-gray-300">{{ $feat }}</span></li>
                        @endforeach
                    </ul>
                    @if (Route::has('register'))
                    <a href="{{ route('register') }}" class="block w-full py-3 text-center rounded-xl font-semibold transition-all duration-300 {{ $sp['featured'] ? 'bg-gradient-to-r from-brand-500 to-brand-600 text-white shadow-lg shadow-brand-500/25 hover:shadow-xl hover:-translate-y-0.5' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-brand-50 dark:hover:bg-brand-500/10 hover:text-brand-600' }}">Get Started</a>
                    @endif
                </div>
                @endforeach
                @endforelse
            </div>
        </div>
    </section>

    {{-- ============================================================ --}}
    {{-- TESTIMONIALS --}}
    {{-- ============================================================ --}}
    <section id="testimonials" class="py-20 lg:py-28 bg-white dark:bg-gray-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <span class="inline-flex items-center px-3 py-1 rounded-full bg-warning-50 dark:bg-warning-500/10 text-xs font-semibold text-warning-600 dark:text-warning-400 uppercase tracking-wider mb-4">Testimonials</span>
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight mb-4">Trusted by Carriers <span class="bg-gradient-to-r from-warning-500 to-orange-500 bg-clip-text text-transparent">Nationwide</span></h2>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                @php
                $testimonials = [
                ['quote' => 'We went from paper files and spreadsheets to being fully DOT audit-ready in a week. This platform saved us from a potential $16,000 fine.', 'name' => 'Marcus Johnson', 'role' => 'Fleet Manager', 'company' => 'JM Logistics, Texas', 'rating' => 5],
                ['quote' => 'The expiration alerts alone are worth the price. We used to miss renewals constantly. Now everything is automated and we sleep better at night.', 'name' => 'Sarah Chen', 'role' => 'Compliance Officer', 'company' => 'Pacific Freight, California', 'rating' => 5],
                ['quote' => 'Onboarding new drivers used to take 3 days of paperwork. Now it takes 30 minutes with the digital application. Our drivers love how easy it is.', 'name' => 'Robert Williams', 'role' => 'Owner-Operator', 'company' => 'Williams Trucking, Ohio', 'rating' => 5],
                ];
                @endphp

                @foreach ($testimonials as $t)
                <div class="glass-card rounded-2xl p-8 transition-all duration-500 flex flex-col">
                    <div class="flex gap-1 mb-4">
                        @for ($s = 0; $s < $t['rating']; $s++)
                            <svg class="w-5 h-5 text-warning-400" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" /></svg>
                            @endfor
                    </div>
                    <blockquote class="text-gray-600 dark:text-gray-300 leading-relaxed mb-6 flex-grow italic">"{{ $t['quote'] }}"</blockquote>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-brand-400 to-brand-600 flex items-center justify-center text-white font-bold text-sm">{{ substr($t['name'], 0, 1) }}</div>
                        <div>
                            <p class="text-sm font-semibold">{{ $t['name'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $t['role'] }}, {{ $t['company'] }}</p>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============================================================ --}}
    {{-- CTA BANNER --}}
    {{-- ============================================================ --}}
    <section class="py-20 lg:py-28 relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-brand-600 via-brand-700 to-brand-900 animate-gradient"></div>
        <div class="absolute inset-0 pointer-events-none">
            <div class="absolute top-0 right-0 w-96 h-96 bg-white/5 rounded-full blur-3xl"></div>
            <div class="absolute bottom-0 left-0 w-96 h-96 bg-brand-400/10 rounded-full blur-3xl"></div>
        </div>

        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center">
            <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-white mb-6 tracking-tight">Ready to Go Audit-Ready?</h2>
            <p class="text-lg sm:text-xl text-brand-100 mb-10 max-w-2xl mx-auto">Start your free trial today — no credit card required. Join 5,200+ trucking companies already using our platform.</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                @if (Route::has('register'))
                <a href="{{ route('register') }}" class="px-8 py-4 text-base font-semibold text-brand-600 bg-white hover:bg-gray-50 rounded-2xl transition-all duration-300 shadow-xl hover:shadow-2xl hover:-translate-y-1 inline-flex items-center justify-center gap-2">Start Free Trial <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                    </svg></a>
                @endif
                <button @click="$dispatch('open-demo-modal')" class="px-8 py-4 text-base font-semibold text-white border-2 border-white/30 hover:border-white/60 hover:bg-white/10 rounded-2xl transition-all duration-300 hover:-translate-y-1 inline-flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    Schedule a Demo
                </button>
            </div>
        </div>
    </section>

    {{-- ============================================================ --}}
    {{-- FAQ --}}
    {{-- ============================================================ --}}
    <section id="faq" class="py-20 lg:py-28 bg-white dark:bg-gray-900">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <span class="inline-flex items-center px-3 py-1 rounded-full bg-blue-light-50 dark:bg-blue-light-500/10 text-xs font-semibold text-blue-light-600 dark:text-blue-light-400 uppercase tracking-wider mb-4">FAQ</span>
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight">Frequently Asked <span class="bg-gradient-to-r from-blue-light-500 to-blue-light-600 bg-clip-text text-transparent">Questions</span></h2>
            </div>

            <div x-data="{ openFaq: null }" class="space-y-4">
                @php
                $faqs = [
                ['q' => 'What is a Driver Qualification File (DQF)?', 'a' => 'A Driver Qualification File is a federally-mandated set of documents that motor carriers must maintain for every driver they employ. Required under 49 CFR Part 391, it includes the driver\'s application, MVR, road test certificate, medical examiner\'s certificate, and more. Our platform digitizes and automates the entire process.'],
                ['q' => 'What documents are required under 49 CFR Part 391?', 'a' => 'Key required documents include: Driver application for employment, MVR (Motor Vehicle Record) pulled annually, Medical Examiner\'s Certificate, Road test certificate or equivalent, Driver\'s license copy, Previous employer Safety Performance History, FMCSA Clearinghouse query, and Alcohol & drug testing records.'],
                ['q' => 'How do automated expiration alerts work?', 'a' => 'Our system continuously monitors expiration dates for all driver documents — CDLs, medical cards, MVRs, and more. You\'ll receive automated email notifications at 90, 60, 30, and 7 days before expiration, ensuring you never miss a renewal deadline.'],
                ['q' => 'Can drivers submit documents from their phones?', 'a' => 'Yes! Our digital application process is fully mobile-responsive. Drivers can complete the 10-step onboarding workflow, upload photos of documents, and sign consent forms directly from their smartphones — no app download required.'],
                ['q' => 'Is my data secure?', 'a' => 'Absolutely. We use enterprise-grade encryption for all data at rest and in transit. Our platform features role-based access controls, audit trails, and secure document storage. Your sensitive driver information is protected with industry-leading security standards.'],
                ['q' => 'Can I try the platform before committing?', 'a' => 'Yes! We offer a free 14-day trial with full access to all features. No credit card required to start. You can also schedule a personalized demo with our team to see the platform in action.'],
                ];
                @endphp

                @foreach ($faqs as $i => $faq)
                <div class="glass-card rounded-2xl overflow-hidden transition-all duration-300" :class="openFaq === {{ $i }} ? 'ring-1 ring-brand-500/30' : ''">
                    <button @click="openFaq = openFaq === {{ $i }} ? null : {{ $i }}" class="w-full flex items-center justify-between px-6 py-5 text-left">
                        <span class="font-semibold text-gray-900 dark:text-white pr-4">{{ $faq['q'] }}</span>
                        <svg class="w-5 h-5 text-gray-400 flex-shrink-0 transition-transform duration-300" :class="openFaq === {{ $i }} ? 'rotate-180 text-brand-500' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div x-show="openFaq === {{ $i }}" x-collapse x-cloak>
                        <div class="px-6 pb-5 text-sm text-gray-500 dark:text-gray-400 leading-relaxed border-t border-gray-100 dark:border-gray-800 pt-4">{{ $faq['a'] }}</div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============================================================ --}}
    {{-- FOOTER --}}
    {{-- ============================================================ --}}
    <footer class="bg-gray-900 dark:bg-gray-950 text-gray-400 pt-16 pb-8 border-t border-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-8 mb-12">
                <div>
                    <img src="{{ asset('images/logo/logo-dark.svg') }}" alt="Logo" class="h-8 mb-4">
                    <p class="text-sm leading-relaxed">Complete Driver Qualification File management. Stay DOT audit-ready with automated compliance tracking.</p>
                </div>
                <div>
                    <h4 class="text-white font-semibold mb-4">Product</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="#features" class="hover:text-white transition-colors">Features</a></li>
                        <li><a href="#pricing" class="hover:text-white transition-colors">Pricing</a></li>
                        <li><a href="#how-it-works" class="hover:text-white transition-colors">How It Works</a></li>
                        <li><a href="#faq" class="hover:text-white transition-colors">FAQ</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-white font-semibold mb-4">Company</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="#" class="hover:text-white transition-colors">About Us</a></li>
                        <li><a href="#" class="hover:text-white transition-colors">Blog</a></li>
                        <li><a href="#" class="hover:text-white transition-colors">Careers</a></li>
                        <li><a href="#" class="hover:text-white transition-colors">Contact</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-white font-semibold mb-4">Legal</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="#" class="hover:text-white transition-colors">Privacy Policy</a></li>
                        <li><a href="#" class="hover:text-white transition-colors">Terms of Service</a></li>
                        <li><a href="#" class="hover:text-white transition-colors">Security</a></li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-gray-800 pt-8 flex flex-col sm:flex-row justify-between items-center gap-4">
                <p class="text-sm">&copy; {{ date('Y') }} Driver Qualification Platform. All rights reserved.</p>
                <div class="flex gap-4">
                    <a href="#" class="w-9 h-9 rounded-lg bg-gray-800 hover:bg-brand-500 flex items-center justify-center transition-all duration-300 hover:-translate-y-0.5"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z" />
                        </svg></a>
                    <a href="#" class="w-9 h-9 rounded-lg bg-gray-800 hover:bg-brand-500 flex items-center justify-center transition-all duration-300 hover:-translate-y-0.5"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z" />
                        </svg></a>
                </div>
            </div>
        </div>
    </footer>

    {{-- ============================================================ --}}
    {{-- DEMO MODAL --}}
    {{-- ============================================================ --}}
    <div x-data="{ open: false, submitted: false, name: '', email: '', company: '', message: '' }"
        @open-demo-modal.window="open = true"
        x-show="open" x-cloak
        class="fixed inset-0 z-[9999] flex items-center justify-center p-4">

        {{-- Backdrop --}}
        <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            @click="open = false" class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm"></div>

        {{-- Modal content --}}
        <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95 translate-y-4" x-transition:enter-end="opacity-100 scale-100 translate-y-0" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95 translate-y-4"
            class="relative w-full max-w-lg bg-white dark:bg-gray-800 rounded-3xl shadow-2xl p-8 overflow-y-auto max-h-[90vh]">

            <button @click="open = false" class="absolute top-4 right-4 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <template x-if="!submitted">
                <div>
                    <h3 class="text-2xl font-bold mb-2">Schedule a Demo</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Fill in your details and our team will reach out within 24 hours.</p>

                    <form @submit.prevent="submitted = true" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Full Name</label>
                            <input x-model="name" type="text" required placeholder="John Doe" class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-sm focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Email Address</label>
                            <input x-model="email" type="email" required placeholder="john@company.com" class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-sm focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Company Name</label>
                            <input x-model="company" type="text" placeholder="Your Trucking Company" class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-sm focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Message (optional)</label>
                            <textarea x-model="message" rows="3" placeholder="Tell us about your fleet size and needs..." class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-sm focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all resize-none"></textarea>
                        </div>
                        <button type="submit" class="w-full py-3 bg-gradient-to-r from-brand-500 to-brand-600 hover:from-brand-600 hover:to-brand-700 text-white font-semibold rounded-xl transition-all duration-300 shadow-lg shadow-brand-500/25 hover:shadow-xl hover:shadow-brand-500/40">
                            Request Demo
                        </button>
                    </form>
                </div>
            </template>

            <template x-if="submitted">
                <div class="text-center py-8">
                    <div class="w-16 h-16 mx-auto rounded-full bg-success-50 dark:bg-success-500/10 flex items-center justify-center mb-4">
                        <svg class="w-8 h-8 text-success-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold mb-2">Thank You!</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">We've received your request. Our team will contact you within 24 hours to schedule your personalized demo.</p>
                    <button @click="open = false; submitted = false" class="px-6 py-3 bg-brand-500 hover:bg-brand-600 text-white font-semibold rounded-xl transition-all duration-300">Close</button>
                </div>
            </template>
        </div>
    </div>

    @include('partials.tawk-widget')
</body>

</html>