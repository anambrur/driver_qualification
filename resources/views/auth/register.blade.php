<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Create Account — {{ config('app.name', 'Laravel') }}</title>
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

        @keyframes check-pop {
            0% {
                transform: scale(0);
            }

            50% {
                transform: scale(1.2);
            }

            100% {
                transform: scale(1);
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

        .strength-bar {
            transition: all 0.4s ease;
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
            <div class="absolute inset-0 bg-gradient-to-br from-brand-700 via-brand-800 to-brand-950 animate-gradient">
            </div>

            {{-- Floating orbs --}}
            <div class="absolute inset-0 pointer-events-none">
                <div class="absolute top-16 right-20 w-72 h-72 bg-success-400/8 rounded-full blur-3xl animate-float">
                </div>
                <div
                    class="absolute bottom-24 left-16 w-64 h-64 bg-brand-400/10 rounded-full blur-3xl animate-float-delayed">
                </div>
                <div
                    class="absolute top-1/3 right-1/4 w-48 h-48 bg-blue-light-400/8 rounded-full blur-2xl animate-pulse-soft">
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
                        Start managing your fleet
                        <span class="text-success-300">in minutes.</span>
                    </h1>
                    <p class="text-brand-100/80 text-base leading-relaxed mb-10">
                        Join 5,200+ trucking companies that trust our platform to stay DOT compliant. Set up takes less
                        than 5 minutes.
                    </p>

                    {{-- What you get --}}
                    <div class="space-y-4">
                        @php
                            $benefits = [
                                ['text' => '14-day free trial, no credit card', 'color' => 'success'],
                                ['text' => 'Complete DQF management tools', 'color' => 'brand'],
                                ['text' => 'Automated document tracking', 'color' => 'blue-light'],
                                ['text' => 'Instant audit-ready reports', 'color' => 'warning'],
                            ];
                        @endphp

                        @foreach ($benefits as $b)
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-6 h-6 rounded-full bg-{{ $b['color'] }}-400/20 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-3.5 h-3.5 text-{{ $b['color'] }}-300" fill="currentColor"
                                        viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <span class="text-sm font-medium text-white/90">{{ $b['text'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Testimonial --}}
                <div class="glass-panel rounded-2xl p-5 max-w-md">
                    <div class="flex gap-1 mb-3">
                        @for ($i = 0; $i < 5; $i++)
                            <svg class="w-4 h-4 text-warning-400" fill="currentColor" viewBox="0 0 20 20">
                                <path
                                    d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                            </svg>
                        @endfor
                    </div>
                    <p class="text-sm text-white/80 italic leading-relaxed mb-3">"We went from paper files to being
                        fully audit-ready in a week. This platform saved us from a $16,000 fine."</p>
                    <div class="flex items-center gap-2">
                        <div
                            class="w-8 h-8 rounded-full bg-gradient-to-br from-brand-400 to-brand-600 flex items-center justify-center text-white text-xs font-bold">
                            M</div>
                        <div>
                            <p class="text-xs font-semibold text-white">Marcus Johnson</p>
                            <p class="text-[10px] text-brand-200/60">Fleet Manager, JM Logistics</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============================================================ --}}
        {{-- RIGHT: Register Form --}}
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
                    <h2 class="text-2xl sm:text-3xl font-bold tracking-tight mb-2">Create your account</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Already have an account?
                        <a href="{{ route('login') }}"
                            class="font-semibold text-brand-500 hover:text-brand-600 transition-colors">Sign in</a>
                    </p>
                </div>

                {{-- Register Form --}}
                <form method="POST" action="{{ route('register') }}" x-data="{
                    password: '',
                    get strength() {
                        let s = 0;
                        if (this.password.length >= 8) s++;
                        if (/[A-Z]/.test(this.password)) s++;
                        if (/[0-9]/.test(this.password)) s++;
                        if (/[^A-Za-z0-9]/.test(this.password)) s++;
                        return s;
                    },
                    get strengthLabel() {
                        const labels = ['', 'Weak', 'Fair', 'Good', 'Strong'];
                        return labels[this.strength];
                    },
                    get strengthColor() {
                        const colors = ['bg-gray-200', 'bg-error-500', 'bg-warning-500', 'bg-blue-light-500', 'bg-success-500'];
                        return colors[this.strength];
                    },
                    showPassword: false,
                    showConfirm: false
                }" class="space-y-5">
                    @csrf

                    {{-- Name --}}
                    <div>
                        <label for="name"
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Full name</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>
                            <input id="name" type="text" name="name" value="{{ old('name') }}" required
                                autofocus autocomplete="name"
                                class="auth-input w-full pl-11 pr-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-0"
                                placeholder="John Doe">
                        </div>
                        <x-input-error :messages="$errors->get('name')" class="mt-1.5" />
                    </div>

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
                            <input id="email" type="email" name="email" value="{{ old('email') }}"
                                required autocomplete="username"
                                class="auth-input w-full pl-11 pr-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-0"
                                placeholder="you@company.com">
                        </div>
                        <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
                    </div>

                    {{-- Password --}}
                    <div>
                        <label for="password"
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                            <input id="password" :type="showPassword ? 'text' : 'password'" name="password" required
                                autocomplete="new-password" x-model="password"
                                class="auth-input w-full pl-11 pr-11 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-0"
                                placeholder="Min 8 characters">
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

                        {{-- Password strength meter --}}
                        <div x-show="password.length > 0" x-transition class="mt-2">
                            <div class="flex gap-1 mb-1">
                                <template x-for="i in 4" :key="i">
                                    <div class="h-1 flex-1 rounded-full transition-all duration-300"
                                        :class="i <= strength ? strengthColor : 'bg-gray-200 dark:bg-gray-700'"></div>
                                </template>
                            </div>
                            <p class="text-xs"
                                :class="{
                                    'text-error-500': strength === 1,
                                    'text-warning-500': strength === 2,
                                    'text-blue-light-500': strength === 3,
                                    'text-success-500': strength === 4
                                }"
                                x-text="strengthLabel"></p>
                        </div>

                        <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
                    </div>

                    {{-- Confirm Password --}}
                    <div>
                        <label for="password_confirmation"
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Confirm
                            password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </div>
                            <input id="password_confirmation" :type="showConfirm ? 'text' : 'password'"
                                name="password_confirmation" required autocomplete="new-password"
                                class="auth-input w-full pl-11 pr-11 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-0"
                                placeholder="Repeat password">
                            <button type="button" @click="showConfirm = !showConfirm"
                                class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                                <svg x-show="!showConfirm" class="w-5 h-5" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <svg x-show="showConfirm" x-cloak class="w-5 h-5" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                </svg>
                            </button>
                        </div>
                        <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1.5" />
                    </div>

                    {{-- Submit --}}
                    <button type="submit"
                        class="w-full py-3 px-4 bg-gradient-to-r from-brand-500 to-brand-600 hover:from-brand-600 hover:to-brand-700 text-white text-sm font-semibold rounded-xl transition-all duration-300 shadow-lg shadow-brand-500/25 hover:shadow-xl hover:shadow-brand-500/40 hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                        Create Account
                    </button>
                </form>

                {{-- Divider --}}
                <div class="relative my-6">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-200 dark:border-gray-700"></div>
                    </div>
                    <div class="relative flex justify-center text-xs">
                        <span class="px-3 bg-gray-50 dark:bg-gray-900 text-gray-400">or sign up with</span>
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
                    By creating an account, you agree to our
                    <a href="#" class="text-brand-500 hover:underline">Terms</a> and
                    <a href="#" class="text-brand-500 hover:underline">Privacy Policy</a>
                </p>
            </div>
        </div>
    </div>
    @include('partials.tawk-widget')
</body>

</html>
