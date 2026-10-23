<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Plan;
use App\Models\Post;
use App\Support\Seo;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class SeoController extends Controller
{
    public function robots(): Response
    {
        $lines = ['User-agent: *'];

        if (Seo::siteIndexable()) {
            foreach (config('seo.disallow') as $path) {
                $lines[] = 'Disallow: '.$path;
            }
            $lines[] = '';
            $lines[] = 'Sitemap: '.Seo::baseUrl().'/sitemap.xml';
        } else {
            $lines[] = 'Disallow: /';
        }

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function sitemap(): Response
    {
        $base = Seo::baseUrl();
        $lastmod = Plan::active()->max('updated_at');
        $urls = collect(config('seo.sitemap_routes'))->map(fn (string $name) => [
            'loc' => $base.route($name, [], false),
            'lastmod' => $name === 'home' && $lastmod ? Carbon::parse($lastmod)->toAtomString() : null,
        ]);

        $pages = Page::query()->where('is_published', true)->orderBy('id')->get(['slug', 'updated_at']);
        foreach ($pages as $page) {
            $urls->push(['loc' => $base.parse_url($page->url(), PHP_URL_PATH), 'lastmod' => $page->updated_at?->toAtomString()]);
        }

        if ($pages->contains('slug', 'blog')) {
            Post::query()->published()->latestFirst()->get(['slug', 'updated_at'])->each(fn (Post $post) => $urls->push([
                'loc' => $base.route('blog.show', $post, false),
                'lastmod' => $post->updated_at?->toAtomString(),
            ]));
        }

        return response()
            ->view('seo.sitemap', ['urls' => $urls], 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
