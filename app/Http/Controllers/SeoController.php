<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class SeoController extends Controller
{
    public function robots(): Response
    {
        $lines = ['User-agent: *'];

        if (app()->environment(config('seo.index_environments'))) {
            foreach (config('seo.disallow') as $path) {
                $lines[] = 'Disallow: '.$path;
            }
            $lines[] = '';
            $lines[] = 'Sitemap: '.url('/sitemap.xml');
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
        $lastmod = Plan::active()->max('updated_at');
        $urls = collect(config('seo.sitemap_routes'))->map(fn (string $name) => [
            'loc' => rtrim(config('app.url'), '/').route($name, [], false),
            'lastmod' => $name === 'home' && $lastmod ? Carbon::parse($lastmod)->toAtomString() : null,
        ]);

        return response()
            ->view('seo.sitemap', ['urls' => $urls], 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
