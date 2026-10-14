<?php

namespace App\Support;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class Seo
{
    public static function indexable(?Request $request = null): bool
    {
        $request ??= request();

        return app()->environment(config('seo.index_environments'))
            && in_array($request->route()?->getName(), config('seo.indexable_routes'), true);
    }

    public static function robots(?Request $request = null): string
    {
        return self::indexable($request) ? 'index, follow, max-image-preview:large' : 'noindex, nofollow';
    }

    /** Stable absolute URL: configured host + path, never the query string. */
    public static function canonical(?Request $request = null): string
    {
        $request ??= request();

        return rtrim(config('app.url'), '/').'/'.ltrim($request->path() === '/' ? '' : $request->path(), '/');
    }

    public static function image(): ?string
    {
        if ($logo = settings('logo')) {
            return asset('storage/'.$logo);
        }

        $default = config('seo.default_image');

        return $default ? (str_starts_with($default, 'http') ? $default : asset($default)) : null;
    }

    /** @param Collection<int, Plan> $plans */
    public static function homeSchema(Collection $plans): array
    {
        $name = settings('site_name', config('app.name'));
        $url = rtrim(config('app.url'), '/').'/';
        $sameAs = array_values(array_filter([
            settings('facebook_url'), settings('twitter_url'), settings('linkedin_url'),
        ]));

        $organization = array_filter([
            '@type' => 'Organization',
            '@id' => $url.'#organization',
            'name' => $name,
            'url' => $url,
            'logo' => self::image(),
            'sameAs' => $sameAs ?: null,
            'contactPoint' => settings('email') || settings('phone') ? array_filter([
                '@type' => 'ContactPoint',
                'contactType' => 'customer support',
                'email' => settings('email'),
                'telephone' => settings('phone'),
            ]) : null,
        ]);

        $application = [
            '@type' => 'SoftwareApplication',
            'name' => $name,
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web',
            'url' => $url,
            'publisher' => ['@id' => $url.'#organization'],
            // Real prices only: plans without a current monthly price are left out.
            'offers' => $plans->map(fn (Plan $plan) => $plan->currentPrice() ? [
                '@type' => 'Offer',
                'name' => $plan->name,
                'price' => number_format((float) $plan->currentPrice()->amount, 2, '.', ''),
                'priceCurrency' => strtoupper($plan->currentPrice()->currency),
            ] : null)->filter()->values()->all(),
        ];

        if (! $application['offers']) {
            unset($application['offers']);
        }

        return ['@context' => 'https://schema.org', '@graph' => [$organization, $application]];
    }
}
