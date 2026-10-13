---
name: seo-checklist
description: SEO rules for Blade views, layouts, routes, and public pages. Use when adding or editing any page, layout, route, redirect, sitemap, robots, or page metadata.
---
**Already built (do not rebuild):** see `docs/SEO.md`. Default is noindex for every response (`SeoRobotsHeader`); a page is indexable only if its route name is in `config/seo.php` `indexable_routes` and the env is production. `<x-seo>` renders robots, canonical, OG, Twitter, JSON-LD. `/robots.txt` and `/sitemap.xml` come from `SeoController`.

**First decide: public or private.** Private (auth, admin, drivers, billing, documents, application steps, status pages): do nothing, it is noindex by default; never put PII in titles/URLs. Public marketing page: name the route, add it to `indexable_routes` (+ `sitemap_routes`), then:
- Unique `<title>` (~50-60 chars) and description (~150 chars) passed to `<x-seo>`; one `<h1>`; semantic landmarks; `<html lang>`.
- Images: descriptive `alt`, `width`/`height`, `loading="lazy"` below the fold. Descriptive link text, kebab-case URLs.
- JSON-LD from real data only (`Seo::homeSchema` pattern); never invented ratings/reviews.
- Changed public URL: 301 redirect. Real 404/410 codes. Never block a public page in robots.
- Performance (Core Web Vitals): defer third-party scripts (chat widget, analytics), preload key font/hero, no render-blocking CSS/JS.
- Add or update a test in `tests/Feature/SeoTest.php`.

End the task with one line: public or private, and what was added.
