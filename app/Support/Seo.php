<?php

namespace App\Support;

use App\Models\Faq;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class Seo
{
    /** An indexing environment, and the platform has not hidden the whole site (robots_noindex_default). */
    public static function siteIndexable(): bool
    {
        return app()->environment(config('seo.index_environments')) && ! settings('robots_noindex_default');
    }

    public static function indexable(?Request $request = null): bool
    {
        $request ??= request();

        return self::siteIndexable()
            && in_array($request->route()?->getName(), config('seo.indexable_routes'), true);
    }

    /** The public host: the platform's canonical base URL, else APP_URL. No trailing slash. */
    public static function baseUrl(): string
    {
        return rtrim((string) (settings('canonical_base_url') ?: config('app.url')), '/');
    }

    public static function robots(?Request $request = null): string
    {
        return self::indexable($request) ? 'index, follow, max-image-preview:large' : 'noindex, nofollow';
    }

    /** Stable absolute URL: configured host + path, never the query string. */
    public static function canonical(?Request $request = null): string
    {
        $request ??= request();

        return self::baseUrl().'/'.ltrim($request->path() === '/' ? '' : $request->path(), '/');
    }

    public static function image(): ?string
    {
        if ($ogImage = settings('og_image')) {
            return asset('storage/'.$ogImage);
        }

        if ($logo = settings('logo')) {
            return asset('storage/'.$logo);
        }

        $default = config('seo.default_image');

        return $default ? (str_starts_with($default, 'http') ? $default : asset($default)) : null;
    }

    /**
     * @param  Collection<int, Plan>  $plans
     * @param  Collection<int, Faq>|null  $faqs  the FAQ shown on the page, if any
     */
    public static function homeSchema(Collection $plans, ?Collection $faqs = null): array
    {
        $name = settings('site_name', config('app.name'));
        $url = self::baseUrl().'/';
        $sameAs = array_values(array_filter(array_column(Footer::content()['social'], 'url')));

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

        $graph = [$organization, $application];

        if ($faqs?->isNotEmpty()) {
            $graph[] = [
                '@type' => 'FAQPage',
                'mainEntity' => $faqs->map(fn (Faq $faq) => [
                    '@type' => 'Question',
                    'name' => $faq->question,
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq->answer],
                ])->values()->all(),
            ];
        }

        return ['@context' => 'https://schema.org', '@graph' => $graph];
    }
}
