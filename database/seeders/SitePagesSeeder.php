<?php

namespace Database\Seeders;

use App\Models\FooterSetting;
use App\Models\Page;
use App\Models\Post;
use App\Support\Footer;
use App\Support\RichText;
use Illuminate\Database\Seeder;

/**
 * The public site pages, three starter blog posts and the footer. Never overwrites what platform
 * staff entered: pages are created only when missing, posts only into an empty table and the
 * footer only when none is saved. Page and post bodies live in database/seeders/content.
 */
class SitePagesSeeder extends Seeder
{
    private const SUMMARIES = [
        'about' => 'We help motor carriers keep every Driver Qualification File complete, current and ready for a DOT audit.',
        'blog' => 'Guides and news on driver qualification, FMCSA compliance and running a safer fleet.',
        'careers' => 'Help trucking companies stay safe and compliant. See how to join the team.',
        'contact' => 'Questions about the platform, pricing or a demo? Send us a message.',
        'security' => 'How we protect driver files, documents and account data.',
        'privacy-policy' => 'What personal information we collect, how we use it and the choices you have.',
        'terms-of-service' => 'The terms that apply when you use the platform.',
    ];

    private const POSTS = [
        'what-goes-in-a-driver-qualification-file' => ['What Goes in a Driver Qualification File?', 'A plain-English checklist of the documents 49 CFR Part 391 expects in every driver file.', 21],
        'preparing-for-a-dot-audit' => ['Five Steps to Prepare for a DOT Audit', 'How to make audit preparation a habit instead of a last-minute scramble.', 14],
        'digital-driver-applications' => ['Why Digital Driver Applications Speed Up Hiring', 'Phone-friendly applications with built-in consents get drivers on the road faster.', 7],
    ];

    public function run(): void
    {
        $siteName = (string) settings('site_name', config('app.name'));
        $email = settings('support_email') ?: settings('email');
        $replace = [
            '{site}' => e($siteName),
            '{contact_url}' => '/contact',
            '{security_url}' => '/security',
            '{privacy_url}' => '/privacy-policy',
            '{email_line}' => $email ? ' or email <a href="mailto:'.e($email).'">'.e($email).'</a>' : '',
        ];

        foreach (Page::SLUGS as $slug => $title) {
            Page::query()->firstOrCreate(['slug' => $slug], [
                'title' => $title,
                'summary' => self::SUMMARIES[$slug],
                'body' => RichText::clean(strtr((string) file_get_contents(__DIR__."/content/pages/{$slug}.html"), $replace)),
                'is_published' => true,
            ]);
        }

        if (! Post::query()->exists()) {
            foreach (self::POSTS as $slug => [$title, $excerpt, $daysAgo]) {
                Post::query()->create([
                    'title' => $title,
                    'slug' => $slug,
                    'excerpt' => $excerpt,
                    'body' => RichText::clean((string) file_get_contents(__DIR__."/content/posts/{$slug}.html")),
                    'published_at' => now()->subDays($daysAgo)->startOfHour(),
                ]);
            }
        }

        if (! FooterSetting::query()->exists()) {
            $footer = Footer::defaults();
            $footer['contact'] = [
                'email' => $email ?: null,
                'phone' => settings('support_phone') ?: settings('phone'),
                'address' => settings('address'),
            ];
            FooterSetting::query()->create(['content' => $footer]);
        }
    }
}
