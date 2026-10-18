{{-- Theme tokens + pre-paint colour mode. Include at the top of every <head>, before @vite.
     Pass ['colorMode' => 'light'] to pin a page that has no dark styles yet. --}}
@php($theme = \App\Support\Ui\Theme::current())
<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0c111d" media="(prefers-color-scheme: dark)">
<style>:root{--brand:{{ $theme['brand'] }};--brand-foreground:{{ $theme['brandForeground'] }};--highlight:{{ $theme['highlight'] }}}</style>
<script>
    (function (d, fallback, lock) {
        var mode;
        d.dataset.themeDefault = fallback;
        if (lock) {
            d.dataset.themeLock = lock;
            return;
        }
        try {
            mode = localStorage.getItem('theme');
            var legacy = localStorage.getItem('darkMode');
            if (!mode && legacy !== null) {
                mode = JSON.parse(legacy) ? 'dark' : 'light';
                localStorage.setItem('theme', mode);
            }
            if (localStorage.getItem('_x_sidebarCollapsed') === 'true') d.dataset.sidebar = 'collapsed';
        } catch (e) {}
        mode = mode || fallback;
        if (mode === 'dark' || (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            d.classList.add('dark');
        }
    })(document.documentElement, @json($theme['mode']), @json($colorMode ?? null));
</script>
