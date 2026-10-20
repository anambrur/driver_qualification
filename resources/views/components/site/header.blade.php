{{--
    The public site navigation. $links is href => label; by default the homepage sections that are
    switched on (Platform > Content > Landing page) plus the blog when it is published.
--}}
@props(['links' => null])
@php
    if ($links === null) {
        $landing = \App\Support\Landing::content();
        $on = [
            'features' => $landing['features']['enabled'], 'how-it-works' => $landing['steps']['enabled'],
            'pricing' => $landing['pricing']['enabled'], 'faq' => $landing['faq']['enabled'],
        ];
        $links = [];
        foreach (array_filter($on) as $anchor => $enabled) {
            $links[route('home').'#'.$anchor] = \App\Support\Footer::SECTIONS[$anchor];
        }
    }
    if ($blog = \App\Models\Page::publishedUrl('blog')) {
        $links[$blog] = \App\Models\Page::published()['blog'];
    }
@endphp
{{-- ============================================================ --}}
{{-- NAVIGATION --}}
{{-- ============================================================ --}}
<nav x-data="{ mobileOpen: false, scrolled: false }"
    x-init="window.addEventListener('scroll', () => { scrolled = window.scrollY > 20 })"
    :class="scrolled ? 'bg-background/80 backdrop-blur-xl shadow-lg border-b border-border/50' : 'bg-transparent'"
    class="fixed top-0 left-0 right-0 z-50 transition-all duration-300" id="main-nav">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16 lg:h-20">
            {{-- Logo --}}
            <a href="{{ route('home') }}" class="flex items-center gap-2 group shrink-0" aria-label="{{ settings('site_name', 'Home') }}">
                @if(settings('logo'))
                    <img src="{{ asset('storage/' . settings('logo')) }}" alt="{{ settings('site_name', 'Company') }} Logo" class="h-11 lg:h-14 w-auto max-w-[200px] lg:max-w-[240px] object-contain">
                @else
                    <img src="{{ asset('images/logo/logo-icon.svg') }}" alt="" width="40" height="40" class="size-9 lg:size-10 shrink-0">
                    <span class="text-lg lg:text-xl font-semibold text-foreground">{{ settings('site_name', config('app.name')) }}</span>
                @endif
            </a>

            {{-- Desktop Nav --}}
            <div class="hidden lg:flex items-center gap-8">
                @foreach ($links as $href => $label)
                <a href="{{ $href }}" class="text-sm font-medium text-muted-foreground hover:text-primary transition-colors">{{ $label }}</a>
                @endforeach
            </div>

            {{-- Auth Buttons --}}
            <div class="hidden lg:flex items-center gap-3">
                @if (Route::has('login'))
                @auth
                <a href="{{ url('/dashboard') }}" class="px-5 py-2.5 text-sm font-semibold text-white bg-primary hover:bg-primary/90 rounded-xl transition-all duration-200 shadow-md shadow-primary/20 hover:shadow-lg hover:shadow-primary/30 hover:-translate-y-0.5">Dashboard</a>
                @else
                <a href="{{ route('login') }}" class="px-5 py-2.5 text-sm font-medium text-foreground hover:text-primary transition-colors">Log In</a>
                @if (Route::has('register'))
                <a href="{{ route('register') }}" class="px-5 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-primary to-[color-mix(in_oklch,var(--primary)_88%,black)] hover:from-[color-mix(in_oklch,var(--primary)_88%,black)] hover:to-[color-mix(in_oklch,var(--primary)_74%,black)] rounded-xl transition-all duration-200 shadow-md shadow-primary/25 hover:shadow-lg hover:shadow-primary/40 hover:-translate-y-0.5">Start Free Trial</a>
                @endif
                @endauth
                @endif
            </div>

            {{-- Mobile Toggle --}}
            <button type="button" @click="mobileOpen = !mobileOpen" :aria-expanded="mobileOpen.toString()" aria-controls="site-mobile-menu" aria-label="Menu" class="lg:hidden p-2 rounded-lg hover:bg-muted transition-colors">
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
    <div id="site-mobile-menu" x-show="mobileOpen" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        class="lg:hidden bg-background/95 backdrop-blur-xl border-b border-border">
        <div class="px-4 py-4 space-y-2">
            @foreach ($links as $href => $label)
            <a @click="mobileOpen = false" href="{{ $href }}" class="block px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-muted transition-colors">{{ $label }}</a>
            @endforeach
            <hr class="border-border my-2">
            @if (Route::has('login'))
            @auth
            <a href="{{ url('/dashboard') }}" class="block px-4 py-2.5 text-center rounded-xl text-sm font-semibold text-white bg-primary">Dashboard</a>
            @else
            <a href="{{ route('login') }}" class="block px-4 py-2.5 rounded-lg text-sm font-medium text-center hover:bg-muted">Log In</a>
            @if (Route::has('register'))
            <a href="{{ route('register') }}" class="block px-4 py-2.5 text-center rounded-xl text-sm font-semibold text-white bg-gradient-to-r from-primary to-[color-mix(in_oklch,var(--primary)_88%,black)]">Start Free Trial</a>
            @endif
            @endauth
            @endif
        </div>
    </div>
</nav>
