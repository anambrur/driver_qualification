<?php

return [
    // Only these environments may be indexed. Staging/local always get noindex + `Disallow: /`.
    'index_environments' => ['production'],

    // Route names that are public marketing pages. EVERYTHING else is noindex (safe default).
    // Add a route name here only after deciding the page is meant to appear in Google.
    'indexable_routes' => ['home', 'login', 'register', 'pages.show', 'blog.index', 'blog.show'],

    // Subset of the above that goes into /sitemap.xml (login/register are indexable but low value).
    // Published site pages and blog posts are added by SeoController.
    'sitemap_routes' => ['home'],

    // Paths blocked in robots.txt (crawl-budget + defence in depth; noindex is still sent).
    'disallow' => [
        '/admin', '/platform', '/billing', '/checkout', '/checkout-success', '/dashboard', '/pricing',
        '/subscription', '/users', '/forgot-password', '/reset-password', '/confirm-password',
        '/verify-email', '/*/application/', '/auth/',
    ],

    // Absolute URL or asset path (1200x630 JPG/PNG) used when no logo is set. PENDING: supply one.
    'default_image' => null,
];
