{{-- Theme tokens + pre-paint colour mode. Include at the top of every <head>, before @vite. --}}
@php($theme = \App\Support\Ui\Theme::current())
<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="#f4f7f5" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0b1714" media="(prefers-color-scheme: dark)">
<style>:root{--brand:{{ $theme['brand'] }};--brand-foreground:{{ $theme['brandForeground'] }};--highlight:{{ $theme['highlight'] }};--sidebar-base:{{ $theme['sidebar'] }}}</style>
<script>
    (function (d, fallback) {
        var mode;
        d.dataset.themeDefault = fallback;
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
    })(document.documentElement, @json($theme['mode']));
</script>
