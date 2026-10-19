<?php

namespace App\Support\Ui;

/**
 * The platform-wide look, from Platform → Settings → Site → Appearance.
 */
final class Theme
{
    public const DEFAULT_BRAND = '#465fff';

    public const DEFAULT_HIGHLIGHT = '#0ba5ec';

    public const MODES = ['light', 'dark', 'system'];

    /**
     * @return array{brand: string, brandForeground: string, highlight: string, mode: string, logo: ?string, logoDark: ?string}
     */
    public static function current(): array
    {
        $brand = settings('primary_color');
        $brand = Color::valid($brand) ? strtolower($brand) : self::DEFAULT_BRAND;
        $highlight = settings('accent_color');
        $mode = settings('default_color_mode');
        $logo = settings('logo');
        $logoDark = settings('logo_dark');

        return [
            'brand' => $brand,
            'brandForeground' => Color::foreground($brand),
            'highlight' => Color::valid($highlight) ? strtolower($highlight) : self::DEFAULT_HIGHLIGHT,
            'mode' => in_array($mode, self::MODES, true) ? $mode : 'system',
            'logo' => $logo ? asset('storage/'.$logo) : null,
            'logoDark' => $logoDark ? asset('storage/'.$logoDark) : ($logo ? asset('storage/'.$logo) : null),
        ];
    }

    /**
     * The logo URL for emails, or null when there is none or its format is one that many mail
     * clients do not show (webp, svg); emails then show the site name alone.
     */
    public static function emailLogo(): ?string
    {
        $logo = settings('logo');

        if (! is_string($logo) || ! in_array(strtolower(pathinfo($logo, PATHINFO_EXTENSION)), ['png', 'jpg', 'jpeg', 'gif'], true)) {
            return null;
        }

        return asset('storage/'.$logo);
    }
}
