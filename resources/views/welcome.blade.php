<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- SEO: title, description, canonical, Open Graph, Twitter, JSON-LD (see app/Support/Seo.php) -->
    @php
        // Sections shown on the page (Platform > Content); nav and footer links follow them
        $show = [
            'features' => $landing['features']['enabled'] && ! empty($landing['features']['items']),
            'how-it-works' => $landing['steps']['enabled'] && ! empty($landing['steps']['items']),
            'pricing' => (bool) $landing['pricing']['enabled'],
            'testimonials' => $landing['testimonials']['enabled'] && $testimonials->isNotEmpty(),
            'faq' => $landing['faq']['enabled'] && $faqs->isNotEmpty(),
        ];
        $navLinks = array_filter(
            ['features' => 'Features', 'how-it-works' => 'How It Works', 'pricing' => 'Pricing', 'testimonials' => 'Testimonials', 'faq' => 'FAQ'],
            fn (string $anchor) => $show[$anchor],
            ARRAY_FILTER_USE_KEY,
        );
        $hero = $landing['hero'];
        $seoTitle = settings('meta_title', settings('site_name', 'Driver Qualification Platform')).' — Always Audit-Ready';
        $seoDescription = settings('meta_description', 'Complete Driver Qualification File management platform. Stay DOT audit-ready with automated tracking, expiration alerts, and digital document management.');
    @endphp
    <title>{{ $seoTitle }}</title>
    <x-seo :title="$seoTitle" :description="$seoDescription" :schema="\App\Support\Seo::homeSchema($plans, $show['faq'] ? $faqs : null)" />

    @include('partials.favicon')

    <!-- Google Analytics Integration -->
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
            background: color-mix(in oklch, var(--card) 60%, transparent);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .glass-card:hover {
            background: rgba(255, 255, 255, 0.85);
            border-color: color-mix(in oklch, var(--brand) 30%, transparent);
            transform: translateY(-4px);
            box-shadow: 0 20px 40px -12px color-mix(in oklch, var(--brand) 15%, transparent);
        }

        .dark .glass-card:hover {
            background: color-mix(in oklch, var(--card) 80%, transparent);
            border-color: color-mix(in oklch, var(--primary) 40%, transparent);
        }

        .hero-gradient {
            background: linear-gradient(135deg, var(--background) 0%, color-mix(in oklch, var(--brand) 6%, var(--background)) 35%, var(--background) 60%, color-mix(in oklch, var(--highlight) 10%, var(--background)) 100%);
        }

        .dark .hero-gradient {
            background: linear-gradient(135deg, var(--background) 0%, color-mix(in oklch, var(--brand) 35%, var(--background)) 30%, var(--background) 60%, color-mix(in oklch, var(--highlight) 12%, var(--background)) 100%);
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
            background: linear-gradient(90deg, var(--brand), transparent);
        }
    </style>
</head>

<body class="bg-card text-foreground antialiased overflow-x-hidden">

    @php
        $headerLinks = [];
        foreach ($navLinks as $anchor => $label) {
            $headerLinks['#'.$anchor] = $label;
        }
    @endphp
    <x-site.header :links="$headerLinks" />

    {{-- ============================================================ --}}
    {{-- HERO SECTION --}}
    {{-- ============================================================ --}}
    <section class="hero-gradient relative min-h-screen flex items-center pt-20 overflow-hidden">
        {{-- Animated background orbs --}}
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="absolute top-20 left-10 w-72 h-72 bg-primary/75 rounded-full blur-3xl animate-float"></div>
            <div class="absolute bottom-20 right-10 w-96 h-96 bg-success/10 rounded-full blur-3xl animate-float-delayed"></div>
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-info/5 rounded-full blur-3xl animate-pulse-ring"></div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 py-12 lg:py-0">
            <div class="grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">
                {{-- Left: Content --}}
                <div class="text-center lg:text-left">
                    <!-- Added header element wrapper for SEO semantic flow -->
                    <header>
                        @if (filled($hero['badge']))
                        <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-primary/10 border border-primary/30 mb-6">
                            <span class="w-2 h-2 rounded-full bg-success animate-pulse"></span>
                            <span class="text-xs font-semibold text-primary uppercase tracking-wider">{{ $hero['badge'] }}</span>
                        </div>
                        @endif

                        <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold leading-tight tracking-tight mb-6">
                            {{ $hero['title'] }}
                            @if (filled($hero['highlight']))
                            <span class="bg-gradient-to-r from-primary via-[color-mix(in_oklch,var(--primary)_88%,black)] to-highlight bg-clip-text text-transparent animate-gradient">{{ $hero['highlight'] }}</span>
                            @endif
                        </h1>
                    </header>

                    @if (filled($hero['subtitle']))
                    <p class="text-lg sm:text-xl text-muted-foreground leading-relaxed mb-8 max-w-xl mx-auto lg:mx-0">
                        {{ $hero['subtitle'] }}
                    </p>
                    @endif

                    <div class="flex flex-col sm:flex-row gap-4 justify-center lg:justify-start mb-10">
                        @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="group px-8 py-4 text-base font-semibold text-white bg-gradient-to-r from-primary to-[color-mix(in_oklch,var(--primary)_88%,black)] hover:from-[color-mix(in_oklch,var(--primary)_88%,black)] hover:to-[color-mix(in_oklch,var(--primary)_74%,black)] rounded-2xl transition-all duration-300 shadow-xl shadow-primary/25 hover:shadow-2xl hover:shadow-primary/40 hover:-translate-y-1 inline-flex items-center justify-center gap-2">
                            Start Free Trial
                            <svg class="w-5 h-5 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                        </a>
                        @endif
                        @if ($show['how-it-works'])
                        <a href="#how-it-works" class="px-8 py-4 text-base font-semibold text-foreground bg-background/60 backdrop-blur-sm border border-border hover:border-primary/50 rounded-2xl transition-all duration-300 hover:-translate-y-1 hover:shadow-lg inline-flex items-center justify-center gap-2">
                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            See How It Works
                        </a>
                        @endif
                    </div>

                    @if (! empty($hero['bullets']))
                    <div class="flex items-center gap-6 justify-center lg:justify-start text-sm text-muted-foreground">
                        @foreach ($hero['bullets'] as $bullet)
                        <span @class(['items-center gap-1.5', 'flex' => ! $loop->last || $loop->count < 3, 'hidden sm:flex' => $loop->last && $loop->count === 3])><svg class="w-4 h-4 text-success" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg> {{ $bullet }}</span>
                        @endforeach
                    </div>
                    @endif
                </div>

                {{-- Right: CSS-only animated illustration --}}
                <div class="relative flex items-center justify-center">
                    <div class="relative w-full max-w-md mx-auto">
                        {{-- Main dashboard card --}}
                        <div class="glass-card rounded-3xl p-6 shadow-2xl animate-float" style="animation-duration: 7s;">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="w-3 h-3 rounded-full bg-destructive"></div>
                                <div class="w-3 h-3 rounded-full bg-warning"></div>
                                <div class="w-3 h-3 rounded-full bg-success"></div>
                                <span class="text-xs font-medium text-muted-foreground ml-2">Compliance Dashboard</span>
                            </div>
                            <div class="space-y-3">
                                <div class="flex items-center justify-between p-3 rounded-xl bg-success/10">
                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 rounded-lg bg-success flex items-center justify-center"><svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg></div><span class="text-sm font-medium">CDL Valid</span>
                                    </div>
                                    <span class="text-xs font-semibold text-success bg-success/15 px-2 py-1 rounded-full">Active</span>
                                </div>
                                <div class="flex items-center justify-between p-3 rounded-xl bg-warning/10">
                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 rounded-lg bg-warning flex items-center justify-center"><svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg></div><span class="text-sm font-medium">Medical Card</span>
                                    </div>
                                    <span class="text-xs font-semibold text-warning bg-warning/15 px-2 py-1 rounded-full">Expiring</span>
                                </div>
                                <div class="flex items-center justify-between p-3 rounded-xl bg-primary/10">
                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 rounded-lg bg-primary flex items-center justify-center"><svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg></div><span class="text-sm font-medium">MVR Report</span>
                                    </div>
                                    <span class="text-xs font-semibold text-primary bg-primary/15 px-2 py-1 rounded-full">Current</span>
                                </div>
                            </div>
                            <div class="mt-4 p-3 rounded-xl bg-muted/50">
                                <div class="flex justify-between items-center mb-2"><span class="text-xs font-medium text-muted-foreground">Compliance Score</span><span class="text-sm font-bold text-success">94%</span></div>
                                <div class="w-full bg-muted rounded-full h-2">
                                    <div class="bg-gradient-to-r from-success/75 to-success h-2 rounded-full" style="width: 94%"></div>
                                </div>
                            </div>
                        </div>

                        {{-- Floating notification --}}
                        <div class="absolute -top-4 -right-4 glass-card rounded-2xl p-3 shadow-xl animate-float-delayed z-10" style="animation-duration: 5s;">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-success flex items-center justify-center"><svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg></div>
                                <div>
                                    <p class="text-xs font-semibold">Audit Ready</p>
                                    <p class="text-[10px] text-muted-foreground">All files complete</p>
                                </div>
                            </div>
                        </div>

                        {{-- Floating alert --}}
                        <div class="absolute -bottom-2 -left-4 glass-card rounded-2xl p-3 shadow-xl animate-float z-10" style="animation-duration: 6s; animation-delay: 0.5s;">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center"><svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                    </svg></div>
                                <div>
                                    <p class="text-xs font-semibold">3 Alerts</p>
                                    <p class="text-[10px] text-muted-foreground">Documents expiring</p>
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
                <path d="M0 50L48 45.7C96 41.3 192 32.7 288 30.2C384 27.7 480 31.3 576 38.5C672 45.7 768 56.3 864 58.8C960 61.3 1056 55.7 1152 48.5C1248 41.3 1344 32.7 1392 28.3L1440 24V100H1392C1344 100 1248 100 1152 100C1056 100 960 100 864 100C768 100 672 100 576 100C480 100 384 100 288 100C192 100 96 100 48 100H0V50Z" class="fill-white" />
            </svg>
        </div>
    </section>

    {{-- ============================================================ --}}
    {{-- TRUST / STATS BAR --}}
    {{-- ============================================================ --}}
    @if ($landing['stats']['enabled'] && ! empty($landing['stats']['items']))
    @php
        $statStyles = [
            'from-primary to-[color-mix(in_oklch,var(--primary)_88%,black)]',
            'from-success to-[color-mix(in_oklch,var(--success)_80%,black)]',
            'from-info to-[color-mix(in_oklch,var(--info)_80%,black)]',
            'from-warning to-[color-mix(in_oklch,var(--warning)_70%,var(--destructive))]',
        ];
        $statCols = [1 => 'md:grid-cols-1', 2 => 'md:grid-cols-2', 3 => 'md:grid-cols-3', 4 => 'md:grid-cols-4'];
    @endphp
    <section class="py-12 bg-card border-b border-border">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 {{ $statCols[count($landing['stats']['items'])] ?? 'md:grid-cols-4' }} gap-6 md:gap-8">
                @foreach ($landing['stats']['items'] as $stat)
                <div class="text-center">
                    <p class="text-3xl sm:text-4xl font-extrabold bg-gradient-to-r {{ $statStyles[$loop->index % 4] }} bg-clip-text text-transparent">{{ $stat['value'] }}</p>
                    <p class="text-sm text-muted-foreground mt-1">{{ $stat['label'] }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- ============================================================ --}}
    {{-- FEATURES GRID --}}
    {{-- ============================================================ --}}
    @if ($show['features'])
    @php $section = $landing['features']; @endphp
    <section id="features" class="py-20 lg:py-28 bg-muted/50 relative overflow-hidden">
        <div class="absolute inset-0 pointer-events-none">
            <div class="absolute top-0 right-0 w-96 h-96 bg-primary/75 rounded-full blur-3xl"></div>
            <div class="absolute bottom-0 left-0 w-96 h-96 bg-success/5 rounded-full blur-3xl"></div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="text-center max-w-3xl mx-auto mb-16">
                @if (filled($section['eyebrow']))
                <span class="inline-flex items-center px-3 py-1 rounded-full bg-primary/10 text-xs font-semibold text-primary uppercase tracking-wider mb-4">{{ $section['eyebrow'] }}</span>
                @endif
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight mb-4">{{ $section['heading'] }} <span class="bg-gradient-to-r from-primary to-[color-mix(in_oklch,var(--primary)_88%,black)] bg-clip-text text-transparent">{{ $section['highlight'] }}</span></h2>
                @if (filled($section['subtitle']))
                <p class="text-lg text-muted-foreground">{{ $section['subtitle'] }}</p>
                @endif
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
                @php
                $colorMap = ['brand' => 'text-primary', 'success' => 'text-success', 'warning' => 'text-warning', 'info' => 'text-info', 'purple' => 'text-[var(--chart-5)]', 'destructive' => 'text-destructive'];
                $bgMap = ['brand' => 'bg-primary/10', 'success' => 'bg-success/10', 'warning' => 'bg-warning/10', 'info' => 'bg-info/10', 'purple' => 'bg-[color-mix(in_oklch,var(--chart-5)_10%,transparent)]', 'destructive' => 'bg-destructive/10'];
                @endphp

                @foreach ($section['items'] as $i => $f)
                <div class="glass-card rounded-2xl p-6 transition-all duration-500 cursor-default group" style="animation: fadeInUp 0.6s ease-out {{ $i * 0.1 }}s both;">
                    <div class="feature-icon-wrap w-12 h-12 rounded-xl {{ $bgMap[$f['color']] ?? $bgMap['brand'] }} flex items-center justify-center mb-4">
                        <x-ui.icon :name="$f['icon']" class="w-6 h-6 {{ $colorMap[$f['color']] ?? $colorMap['brand'] }}" />
                    </div>
                    <h3 class="text-lg font-bold mb-2">{{ $f['title'] }}</h3>
                    <p class="text-sm text-muted-foreground leading-relaxed">{{ $f['text'] }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- ============================================================ --}}
    {{-- HOW IT WORKS --}}
    {{-- ============================================================ --}}
    @if ($show['how-it-works'])
    @php
        $section = $landing['steps'];
        $stepCols = [1 => 'md:grid-cols-1', 2 => 'md:grid-cols-2', 3 => 'md:grid-cols-3', 4 => 'md:grid-cols-4'];
    @endphp
    <section id="how-it-works" class="py-20 lg:py-28 bg-card">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16">
                @if (filled($section['eyebrow']))
                <span class="inline-flex items-center px-3 py-1 rounded-full bg-success/10 text-xs font-semibold text-success uppercase tracking-wider mb-4">{{ $section['eyebrow'] }}</span>
                @endif
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight mb-4">{{ $section['heading'] }} <span class="bg-gradient-to-r from-success to-[color-mix(in_oklch,var(--success)_80%,black)] bg-clip-text text-transparent">{{ $section['highlight'] }}</span></h2>
                @if (filled($section['subtitle']))
                <p class="text-lg text-muted-foreground">{{ $section['subtitle'] }}</p>
                @endif
            </div>

            <div class="grid {{ $stepCols[count($section['items'])] ?? 'md:grid-cols-4' }} gap-8 lg:gap-12">
                @foreach ($section['items'] as $step)
                <div class="relative text-center group">
                    <div class="w-20 h-20 mx-auto mb-6 rounded-2xl bg-gradient-to-br from-primary to-[color-mix(in_oklch,var(--primary)_88%,black)] flex items-center justify-center shadow-xl shadow-primary/20 group-hover:shadow-2xl group-hover:shadow-primary/30 transition-all duration-300 group-hover:-translate-y-2">
                        <x-ui.icon :name="$step['icon']" class="w-8 h-8 text-white" />
                    </div>
                    <span class="inline-block text-xs font-bold text-primary bg-primary/10 px-3 py-1 rounded-full mb-3">Step {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                    <h3 class="text-xl font-bold mb-2">{{ $step['title'] }}</h3>
                    <p class="text-sm text-muted-foreground leading-relaxed max-w-xs mx-auto">{{ $step['text'] }}</p>
                    @if (! $loop->last)
                        <div class="hidden md:block absolute top-10 -right-6 lg:-right-6">
                        <svg class="w-12 h-12 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                </div>
                @endif
            </div>
            @endforeach
        </div>
        </div>
    </section>
    @endif

    {{-- ============================================================ --}}
    {{-- PRICING --}}
    {{-- ============================================================ --}}
    @if ($show['pricing'])
    @php $section = $landing['pricing']; @endphp
    <section id="pricing" class="py-20 lg:py-28 bg-muted/50 relative overflow-hidden">
        <div class="absolute inset-0 pointer-events-none">
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[800px] h-[800px] bg-primary/75 rounded-full blur-3xl"></div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="text-center max-w-3xl mx-auto mb-16">
                @if (filled($section['eyebrow']))
                <span class="inline-flex items-center px-3 py-1 rounded-full bg-primary/10 text-xs font-semibold text-primary uppercase tracking-wider mb-4">{{ $section['eyebrow'] }}</span>
                @endif
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight mb-4">{{ $section['heading'] }} <span class="bg-gradient-to-r from-primary to-[color-mix(in_oklch,var(--primary)_88%,black)] bg-clip-text text-transparent">{{ $section['highlight'] }}</span></h2>
                @if (filled($section['subtitle']))
                <p class="text-lg text-muted-foreground">{{ $section['subtitle'] }}</p>
                @endif
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-{{ min(count($plans ?? []), 3) ?: 3 }} gap-8 max-w-5xl mx-auto">
                @forelse ($plans ?? [] as $plan)
                <div class="relative glass-card rounded-3xl p-8 transition-all duration-500 flex flex-col {{ $plan->is_featured ? 'ring-2 ring-primary shadow-xl shadow-primary/10' : '' }}">
                    @if ($plan->is_featured)
                    <div class="absolute -top-4 left-1/2 -translate-x-1/2">
                        <span class="px-4 py-1.5 rounded-full bg-gradient-to-r from-primary to-[color-mix(in_oklch,var(--primary)_88%,black)] text-white text-xs font-bold uppercase tracking-wider shadow-lg">Most Popular</span>
                    </div>
                    @endif

                    <h3 class="text-xl font-bold mb-2">{{ $plan->name }}</h3>
                    <p class="text-sm text-muted-foreground mb-6">{{ $plan->description ?? 'Perfect for growing fleets' }}</p>

                    @if ($monthly = $plan->currentPrice('month'))
                    <div class="mb-6">
                        <span class="text-4xl font-extrabold">${{ number_format((float) $monthly->amount, 0) }}</span>
                        <span class="text-sm text-muted-foreground">/month</span>
                    </div>
                    @endif

                    @if ($plan->marketing_bullets)
                    <ul class="space-y-3 mb-8 grow">
                        @foreach ($plan->marketing_bullets as $feature)
                        <li class="flex items-start gap-2 text-sm">
                            <svg class="w-5 h-5 text-success shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                            <span class="text-muted-foreground">{{ $feature }}</span>
                        </li>
                        @endforeach
                    </ul>
                    @endif

                    @if (Route::has('register'))
                    <a href="{{ auth()->check() ? route('pricing.plans') : route('register') }}" class="block w-full py-3 text-center rounded-xl font-semibold transition-all duration-300 {{ $plan->is_featured ? 'bg-gradient-to-r from-primary to-[color-mix(in_oklch,var(--primary)_88%,black)] text-white shadow-lg shadow-primary/25 hover:shadow-xl hover:shadow-primary/40 hover:-translate-y-0.5' : 'bg-muted text-foreground hover:bg-primary/10 hover:text-primary' }}">
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
                <div class="relative glass-card rounded-3xl p-8 transition-all duration-500 flex flex-col {{ $sp['featured'] ? 'ring-2 ring-primary shadow-xl shadow-primary/10' : '' }}">
                    @if ($sp['featured'])
                    <div class="absolute -top-4 left-1/2 -translate-x-1/2"><span class="px-4 py-1.5 rounded-full bg-gradient-to-r from-primary to-[color-mix(in_oklch,var(--primary)_88%,black)] text-white text-xs font-bold uppercase tracking-wider shadow-lg">Most Popular</span></div>
                    @endif
                    <h3 class="text-xl font-bold mb-2">{{ $sp['name'] }}</h3>
                    <p class="text-sm text-muted-foreground mb-6">{{ $sp['desc'] }}</p>
                    <div class="mb-6"><span class="text-4xl font-extrabold">${{ $sp['price'] }}</span><span class="text-sm text-muted-foreground">/month</span></div>
                    <ul class="space-y-3 mb-8 grow">
                        @foreach ($sp['features'] as $feat)
                        <li class="flex items-start gap-2 text-sm"><svg class="w-5 h-5 text-success shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg><span class="text-muted-foreground">{{ $feat }}</span></li>
                        @endforeach
                    </ul>
                    @if (Route::has('register'))
                    <a href="{{ route('register') }}" class="block w-full py-3 text-center rounded-xl font-semibold transition-all duration-300 {{ $sp['featured'] ? 'bg-gradient-to-r from-primary to-[color-mix(in_oklch,var(--primary)_88%,black)] text-white shadow-lg shadow-primary/25 hover:shadow-xl hover:-translate-y-0.5' : 'bg-muted text-foreground hover:bg-primary/10 hover:text-primary' }}">Get Started</a>
                    @endif
                </div>
                @endforeach
                @endforelse
            </div>
        </div>
    </section>
    @endif

    {{-- ============================================================ --}}
    {{-- TESTIMONIALS --}}
    {{-- ============================================================ --}}
    @if ($show['testimonials'])
    @php $section = $landing['testimonials']; @endphp
    <section id="testimonials" class="py-20 lg:py-28 bg-card">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16">
                @if (filled($section['eyebrow']))
                <span class="inline-flex items-center px-3 py-1 rounded-full bg-warning/10 text-xs font-semibold text-warning uppercase tracking-wider mb-4">{{ $section['eyebrow'] }}</span>
                @endif
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight mb-4">{{ $section['heading'] }} <span class="bg-gradient-to-r from-warning to-[color-mix(in_oklch,var(--warning)_70%,var(--destructive))] bg-clip-text text-transparent">{{ $section['highlight'] }}</span></h2>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                @foreach ($testimonials as $t)
                <div class="glass-card rounded-2xl p-8 transition-all duration-500 flex flex-col">
                    <div class="flex gap-1 mb-4">
                        @for ($s = 0; $s < $t->rating; $s++)
                            <svg class="w-5 h-5 text-warning" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" /></svg>
                            @endfor
                    </div>
                    <blockquote class="text-muted-foreground leading-relaxed mb-6 grow italic">"{{ $t->quote }}"</blockquote>
                    <div class="flex items-center gap-3">
                        @if ($t->avatar)
                        <img src="{{ asset('storage/'.$t->avatar) }}" alt="" width="40" height="40" loading="lazy" class="w-10 h-10 rounded-full object-cover">
                        @else
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary/75 to-[color-mix(in_oklch,var(--primary)_88%,black)] flex items-center justify-center text-white font-bold text-sm">{{ mb_substr($t->name, 0, 1) }}</div>
                        @endif
                        <div>
                            <p class="text-sm font-semibold">{{ $t->name }}</p>
                            @if ($t->role || $t->company)
                            <p class="text-xs text-muted-foreground">{{ collect([$t->role, $t->company])->filter()->join(', ') }}</p>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- ============================================================ --}}
    {{-- CTA BANNER --}}
    {{-- ============================================================ --}}
    @if ($landing['cta']['enabled'])
    <section class="py-20 lg:py-28 relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-[color-mix(in_oklch,var(--primary)_88%,black)] via-[color-mix(in_oklch,var(--primary)_74%,black)] to-[color-mix(in_oklch,var(--primary)_46%,black)] animate-gradient"></div>
        <div class="absolute inset-0 pointer-events-none">
            <div class="absolute top-0 right-0 w-96 h-96 bg-background/5 rounded-full blur-3xl"></div>
            <div class="absolute bottom-0 left-0 w-96 h-96 bg-primary/75 rounded-full blur-3xl"></div>
        </div>

        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center">
            <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-white mb-6 tracking-tight">{{ $landing['cta']['title'] }}</h2>
            @if (filled($landing['cta']['text']))
            <p class="text-lg sm:text-xl text-[color-mix(in_oklch,var(--primary)_16%,white)] mb-10 max-w-2xl mx-auto">{{ $landing['cta']['text'] }}</p>
            @endif
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                @if (Route::has('register'))
                <a href="{{ route('register') }}" class="px-8 py-4 text-base font-semibold text-primary bg-card hover:bg-muted/50 rounded-2xl transition-all duration-300 shadow-xl hover:shadow-2xl hover:-translate-y-1 inline-flex items-center justify-center gap-2">Start Free Trial <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                    </svg></a>
                @endif
                @if ($landing['cta']['demo'])
                <button @click="$dispatch('open-demo-modal')" class="px-8 py-4 text-base font-semibold text-white border-2 border-white/30 hover:border-white/60 hover:bg-white/10 rounded-2xl transition-all duration-300 hover:-translate-y-1 inline-flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    Schedule a Demo
                </button>
                @endif
            </div>
        </div>
    </section>
    @endif

    {{-- ============================================================ --}}
    {{-- FAQ --}}
    {{-- ============================================================ --}}
    @if ($show['faq'])
    @php $section = $landing['faq']; @endphp
    <section id="faq" class="py-20 lg:py-28 bg-card">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                @if (filled($section['eyebrow']))
                <span class="inline-flex items-center px-3 py-1 rounded-full bg-info/10 text-xs font-semibold text-info uppercase tracking-wider mb-4">{{ $section['eyebrow'] }}</span>
                @endif
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight">{{ $section['heading'] }} <span class="bg-gradient-to-r from-info to-[color-mix(in_oklch,var(--info)_80%,black)] bg-clip-text text-transparent">{{ $section['highlight'] }}</span></h2>
            </div>

            <div x-data="{ openFaq: null }" class="space-y-4">
                @foreach ($faqs as $i => $faq)
                <div class="glass-card rounded-2xl overflow-hidden transition-all duration-300" :class="openFaq === {{ $i }} ? 'ring-1 ring-primary/30' : ''">
                    <button @click="openFaq = openFaq === {{ $i }} ? null : {{ $i }}" class="w-full flex items-center justify-between px-6 py-5 text-left">
                        <span class="font-semibold text-foreground pr-4">{{ $faq->question }}</span>
                        <svg class="w-5 h-5 text-muted-foreground shrink-0 transition-transform duration-300" :class="openFaq === {{ $i }} ? 'rotate-180 text-primary' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div x-show="openFaq === {{ $i }}" x-collapse x-cloak>
                        <div class="px-6 pb-5 text-sm text-muted-foreground leading-relaxed border-t border-border pt-4 whitespace-pre-line">{{ $faq->answer }}</div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    <x-site.footer :cta="false" />

    {{-- ============================================================ --}}
    {{-- DEMO MODAL --}}
    {{-- ============================================================ --}}
    @if ($landing['cta']['enabled'] && $landing['cta']['demo'])
    <div x-data="{
            open: false, submitted: false, sending: false, errors: {}, error: '',
            form: { name: '', email: '', company: '', phone: '', message: '', website: '' },
            async submit() {
                this.sending = true; this.errors = {}; this.error = '';
                try {
                    const response = await fetch(@js(route('demo-requests.store')), {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) },
                        body: JSON.stringify(this.form),
                    });
                    if (response.ok) {
                        this.submitted = true;
                        this.form = { name: '', email: '', company: '', phone: '', message: '', website: '' };
                    } else if (response.status === 422) {
                        this.errors = (await response.json()).errors ?? {};
                    } else {
                        this.error = response.status === 429 ? 'Too many requests. Please try again in a few minutes.' : 'Something went wrong. Please try again.';
                    }
                } catch (e) {
                    this.error = 'Something went wrong. Please try again.';
                } finally {
                    this.sending = false;
                }
            },
        }"
        @open-demo-modal.window="open = true"
        x-show="open" x-cloak
        class="fixed inset-0 z-[9999] flex items-center justify-center p-4">

        {{-- Backdrop --}}
        <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            @click="open = false" class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>

        {{-- Modal content --}}
        <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95 translate-y-4" x-transition:enter-end="opacity-100 scale-100 translate-y-0" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95 translate-y-4"
            class="relative w-full max-w-lg bg-card rounded-3xl shadow-2xl p-8 overflow-y-auto max-h-[90vh]">

            <button @click="open = false" class="absolute top-4 right-4 p-2 rounded-lg hover:bg-muted transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <template x-if="!submitted">
                <div>
                    <h3 class="text-2xl font-bold mb-2">Schedule a Demo</h3>
                    <p class="text-sm text-muted-foreground mb-6">Fill in your details and our team will reach out within 24 hours.</p>

                    <form @submit.prevent="submit()" class="space-y-4">
                        {{-- Honeypot: people never see or fill it --}}
                        <div class="hidden" aria-hidden="true">
                            <label>Website <input x-model="form.website" type="text" name="website" tabindex="-1" autocomplete="off"></label>
                        </div>
                        <div>
                            <label for="demo-name" class="block text-sm font-medium mb-1">Full Name</label>
                            <input id="demo-name" x-model="form.name" type="text" required maxlength="100" autocomplete="name" placeholder="John Doe" class="w-full px-4 py-3 rounded-xl border border-border bg-muted/50 text-sm focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                        </div>
                        <div>
                            <label for="demo-email" class="block text-sm font-medium mb-1">Email Address</label>
                            <input id="demo-email" x-model="form.email" type="email" required maxlength="255" autocomplete="email" placeholder="john@company.com" class="w-full px-4 py-3 rounded-xl border border-border bg-muted/50 text-sm focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                        </div>
                        <div>
                            <label for="demo-company" class="block text-sm font-medium mb-1">Company Name</label>
                            <input id="demo-company" x-model="form.company" type="text" maxlength="150" autocomplete="organization" placeholder="Your Trucking Company" class="w-full px-4 py-3 rounded-xl border border-border bg-muted/50 text-sm focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                        </div>
                        <div>
                            <label for="demo-phone" class="block text-sm font-medium mb-1">Phone (optional)</label>
                            <input id="demo-phone" x-model="form.phone" type="tel" maxlength="50" autocomplete="tel" placeholder="(555) 123-4567" class="w-full px-4 py-3 rounded-xl border border-border bg-muted/50 text-sm focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                        </div>
                        <div>
                            <label for="demo-message" class="block text-sm font-medium mb-1">Message (optional)</label>
                            <textarea id="demo-message" maxlength="2000" x-model="form.message" rows="3" placeholder="Tell us about your fleet size and needs..." class="w-full px-4 py-3 rounded-xl border border-border bg-muted/50 text-sm focus:ring-2 focus:ring-primary focus:border-transparent transition-all resize-none"></textarea>
                        </div>
                        <template x-for="message in [...Object.values(errors).flat(), error].filter(Boolean)">
                            <p role="alert" class="text-sm text-destructive" x-text="message"></p>
                        </template>
                        <button type="submit" :disabled="sending" class="w-full py-3 disabled:opacity-60 bg-gradient-to-r from-primary to-[color-mix(in_oklch,var(--primary)_88%,black)] hover:from-[color-mix(in_oklch,var(--primary)_88%,black)] hover:to-[color-mix(in_oklch,var(--primary)_74%,black)] text-white font-semibold rounded-xl transition-all duration-300 shadow-lg shadow-primary/25 hover:shadow-xl hover:shadow-primary/40">
                            <span x-text="sending ? 'Sending…' : 'Request Demo'">Request Demo</span>
                        </button>
                    </form>
                </div>
            </template>

            <template x-if="submitted">
                <div class="text-center py-8">
                    <div class="w-16 h-16 mx-auto rounded-full bg-success/10 flex items-center justify-center mb-4">
                        <svg class="w-8 h-8 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold mb-2">Thank You!</h3>
                    <p class="text-sm text-muted-foreground mb-6">We've received your request. Our team will contact you within 24 hours to schedule your personalized demo.</p>
                    <button @click="open = false; submitted = false" class="px-6 py-3 bg-primary hover:bg-primary/90 text-white font-semibold rounded-xl transition-all duration-300">Close</button>
                </div>
            </template>
        </div>
    </div>
    @endif

    @include('partials.tawk-widget')
</body>

</html>