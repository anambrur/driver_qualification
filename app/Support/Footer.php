<?php

namespace App\Support;

use App\Models\FooterSetting;
use App\Models\Page;

/**
 * The public site footer, edited on Platform > Content > Footer and stored in footer_settings.
 * A section never saved falls back to the defaults below. Links point at site pages or homepage
 * sections only; a link to an unpublished page is hidden.
 */
final class Footer
{
    /** Homepage section anchors a link can target: anchor => label. */
    public const SECTIONS = [
        'features' => 'Features',
        'how-it-works' => 'How It Works',
        'pricing' => 'Pricing',
        'testimonials' => 'Testimonials',
        'faq' => 'FAQ',
    ];

    /** Social networks: key => [label, lucide icon]. */
    public const NETWORKS = [
        'facebook' => ['Facebook', 'facebook'],
        'x' => ['X', 'twitter'],
        'linkedin' => ['LinkedIn', 'linkedin'],
        'youtube' => ['YouTube', 'youtube'],
        'instagram' => ['Instagram', 'instagram'],
    ];

    /** Lucide icons offered for the trust badges. */
    public const BADGE_ICONS = [
        'shield-check', 'lock', 'credit-card', 'badge-check', 'file-check', 'server', 'cloud', 'headset', 'clock', 'truck',
    ];

    /** Most rows each list takes. */
    public const LIMITS = ['columns' => 4, 'links' => 8, 'social' => 6, 'badges' => 4];

    /**
     * Link targets: "page:<slug>" or "section:<anchor>" => label.
     *
     * @return array<string, string>
     */
    public static function targets(): array
    {
        $targets = [];
        foreach (Page::SLUGS as $slug => $title) {
            $targets["page:{$slug}"] = "Page: {$title}";
        }
        foreach (self::SECTIONS as $anchor => $label) {
            $targets["section:{$anchor}"] = "Homepage: {$label}";
        }

        return $targets;
    }

    /**
     * Where a target points, or null when it is an unpublished page or unknown.
     */
    public static function href(string $target): ?string
    {
        [$type, $key] = array_pad(explode(':', $target, 2), 2, '');

        return match ($type) {
            'page' => array_key_exists($key, Page::SLUGS) ? Page::publishedUrl($key) : null,
            'section' => array_key_exists($key, self::SECTIONS) ? route('home').'#'.$key : null,
            default => null,
        };
    }

    /**
     * Saved sections over the defaults. A saved list replaces the default list.
     *
     * @return array<string, mixed>
     */
    public static function content(): array
    {
        $saved = FooterSetting::stored();
        $content = self::defaults();

        foreach ($content as $key => $section) {
            if (is_array($saved[$key] ?? null)) {
                $content[$key] = array_is_list($section) ? $saved[$key] : $saved[$key] + $section;
            }
        }

        return $content;
    }

    /**
     * Columns with resolved links; links to unpublished pages and empty columns are left out.
     *
     * @param  array<string, mixed>  $content
     * @return list<array{heading: string, links: list<array{label: string, href: string}>}>
     */
    public static function columns(array $content): array
    {
        $columns = [];
        foreach ($content['columns'] as $column) {
            $links = [];
            foreach ($column['links'] ?? [] as $link) {
                if ($href = self::href((string) ($link['target'] ?? ''))) {
                    $links[] = ['label' => (string) $link['label'], 'href' => $href];
                }
            }
            if ($links) {
                $columns[] = ['heading' => (string) $column['heading'], 'links' => $links];
            }
        }

        return $columns;
    }

    /**
     * The copyright line with {year} and {company} filled in.
     */
    public static function copyright(string $line): string
    {
        $company = settings('legal_name') ?: settings('site_name', config('app.name'));

        return strtr($line, ['{year}' => date('Y'), '{company}' => (string) $company]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'brand' => [
                'tagline' => 'Complete Driver Qualification File management. Stay DOT audit-ready with automated compliance tracking.',
            ],
            'contact' => [
                'email' => null,
                'phone' => null,
                'address' => null,
            ],
            'social' => [],
            'columns' => [
                ['heading' => 'Product', 'links' => [
                    ['label' => 'Features', 'target' => 'section:features'],
                    ['label' => 'How It Works', 'target' => 'section:how-it-works'],
                    ['label' => 'Pricing', 'target' => 'section:pricing'],
                    ['label' => 'FAQ', 'target' => 'section:faq'],
                ]],
                ['heading' => 'Company', 'links' => [
                    ['label' => 'About Us', 'target' => 'page:about'],
                    ['label' => 'Blog', 'target' => 'page:blog'],
                    ['label' => 'Careers', 'target' => 'page:careers'],
                    ['label' => 'Contact', 'target' => 'page:contact'],
                ]],
                ['heading' => 'Legal', 'links' => [
                    ['label' => 'Privacy Policy', 'target' => 'page:privacy-policy'],
                    ['label' => 'Terms of Service', 'target' => 'page:terms-of-service'],
                    ['label' => 'Security', 'target' => 'page:security'],
                ]],
            ],
            'badges' => [
                ['icon' => 'shield-check', 'label' => 'Built for FMCSA 49 CFR Part 391'],
                ['icon' => 'lock', 'label' => 'HTTPS encrypted'],
                ['icon' => 'credit-card', 'label' => 'Payments secured by Stripe'],
            ],
            'cta' => [
                'enabled' => true,
                'title' => 'Ready to go audit-ready?',
                'text' => 'Start your free trial today. No credit card required.',
                'button_label' => 'Start Free Trial',
            ],
            'bottom' => [
                'copyright' => '© {year} {company}. All rights reserved.',
                'back_to_top' => true,
            ],
        ];
    }
}
