<?php

namespace App\Support;

/**
 * Homepage copy and lists, edited on Platform > Content > Landing page and stored in
 * site_settings.landing. A section never saved falls back to the defaults below.
 */
final class Landing
{
    /** Lucide icons offered for features and steps. */
    public const ICONS = [
        'clipboard-check', 'smartphone', 'bell', 'arrow-left-right', 'settings', 'file-text',
        'user-plus', 'users', 'shield-check', 'shield', 'truck', 'gauge', 'clock', 'calendar-clock',
        'alarm-clock', 'chart-column', 'file-check', 'folder-check', 'lock', 'cloud-upload', 'search',
        'wrench', 'zap', 'star', 'badge-check', 'id-card', 'mail', 'circle-check-big', 'signature', 'headset',
    ];

    /** Feature card colours: key => label. */
    public const COLORS = [
        'brand' => 'Brand', 'success' => 'Green', 'warning' => 'Amber',
        'info' => 'Blue', 'purple' => 'Purple', 'destructive' => 'Red',
    ];

    /** Most rows each list takes. */
    public const LIMITS = ['stats' => 4, 'features' => 9, 'steps' => 4, 'links' => 6];

    /**
     * Saved sections over the defaults. A saved list replaces the default list.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function content(): array
    {
        $saved = settings('landing');
        $content = self::defaults();

        foreach ($content as $key => $section) {
            if (is_array($saved) && is_array($saved[$key] ?? null)) {
                $content[$key] = $saved[$key] + $section;
            }
        }

        return $content;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function defaults(): array
    {
        return [
            'hero' => [
                'badge' => 'DOT Compliance Platform',
                'title' => 'Every Driver File.',
                'highlight' => 'Always Audit-Ready.',
                'subtitle' => 'Complete Driver Qualification Files — from hire to compliance. Automated tracking, expiration alerts, and digital document management.',
                'bullets' => ['No credit card', 'Free 14-day trial', 'Cancel anytime'],
            ],
            'stats' => [
                'enabled' => true,
                'items' => [
                    ['value' => '5,200+', 'label' => 'Trucking Companies'],
                    ['value' => '50K+', 'label' => 'Driver Files Managed'],
                    ['value' => '99.9%', 'label' => 'Audit Pass Rate'],
                    ['value' => '24/7', 'label' => 'Compliance Monitoring'],
                ],
            ],
            'features' => [
                'enabled' => true,
                'eyebrow' => 'Features',
                'heading' => 'Everything You Need for',
                'highlight' => 'DOT Compliance',
                'subtitle' => 'Manage your entire driver qualification process from a single platform — built for 49 CFR Part 391 compliance.',
                'items' => [
                    ['icon' => 'clipboard-check', 'color' => 'brand', 'title' => 'DQF Checklists', 'text' => '49 CFR Part 391 requirements tracked per driver, automatically. Never miss a required document again.'],
                    ['icon' => 'smartphone', 'color' => 'success', 'title' => 'Digital Applications', 'text' => '10-step digital driver application workflow with OTP verification and real-time progress tracking.'],
                    ['icon' => 'bell', 'color' => 'warning', 'title' => 'Expiration Alerts', 'text' => 'Licenses, medicals, MVRs — get notified before they expire. Automated email reminders keep you compliant.'],
                    ['icon' => 'arrow-left-right', 'color' => 'info', 'title' => 'Fleet Compliance', 'text' => 'Vehicle & trailer document tracking with real-time compliance scores and maintenance scheduling.'],
                    ['icon' => 'settings', 'color' => 'purple', 'title' => 'Maintenance Tracking', 'text' => 'Service logs, scheduled maintenance, and preventive care management for your entire fleet.'],
                    ['icon' => 'file-text', 'color' => 'destructive', 'title' => 'Audit-Ready Reports', 'text' => 'Generate complete, compliant driver files for DOT inspections instantly. One-click export to PDF.'],
                ],
            ],
            'steps' => [
                'enabled' => true,
                'eyebrow' => 'How It Works',
                'heading' => 'Get Audit-Ready in',
                'highlight' => '3 Simple Steps',
                'subtitle' => 'No complex setup. No steep learning curve. Start managing your driver files in minutes.',
                'items' => [
                    ['icon' => 'user-plus', 'title' => 'Sign Up & Setup', 'text' => 'Create your company profile, configure compliance requirements, and invite your team. Takes less than 5 minutes.'],
                    ['icon' => 'users', 'title' => 'Add Drivers & Fleet', 'text' => 'Onboard drivers digitally with our 10-step application. Register vehicles and trailers with document tracking.'],
                    ['icon' => 'shield-check', 'title' => 'Stay Compliant', 'text' => 'Automated tracking, expiration alerts, and audit-ready reporting keep you compliant 24/7. Zero effort.'],
                ],
            ],
            'pricing' => [
                'enabled' => true,
                'eyebrow' => 'Pricing',
                'heading' => 'Simple, Transparent',
                'highlight' => 'Pricing',
                'subtitle' => 'Choose the plan that fits your fleet. All plans include a free trial.',
            ],
            'testimonials' => [
                'enabled' => true,
                'eyebrow' => 'Testimonials',
                'heading' => 'Trusted by Carriers',
                'highlight' => 'Nationwide',
            ],
            'faq' => [
                'enabled' => true,
                'eyebrow' => 'FAQ',
                'heading' => 'Frequently Asked',
                'highlight' => 'Questions',
            ],
            'cta' => [
                'enabled' => true,
                'title' => 'Ready to Go Audit-Ready?',
                'text' => 'Start your free trial today — no credit card required. Join 5,200+ trucking companies already using our platform.',
                'demo' => true,
            ],
            'footer' => [
                'tagline' => 'Complete Driver Qualification File management. Stay DOT audit-ready with automated compliance tracking.',
                'links' => [],
            ],
        ];
    }
}
