<?php

namespace App\Support\Ui;

final class Color
{
    public const HEX = '/^#[0-9a-fA-F]{6}$/';

    public static function valid(?string $hex): bool
    {
        return $hex !== null && preg_match(self::HEX, $hex) === 1;
    }

    /**
     * Readable text colour on top of $hex (WCAG relative luminance).
     */
    public static function foreground(string $hex): string
    {
        $channels = array_map(function (string $pair): float {
            $c = hexdec($pair) / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, str_split(substr($hex, 1), 2));

        $luminance = 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];

        // Contrast against white vs. near-black (#101828, luminance ≈ 0.0103).
        return (1.05 / ($luminance + 0.05)) >= (($luminance + 0.05) / 0.0603) ? '#ffffff' : '#101828';
    }
}
