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

    /**
     * A deep, muted shade of $hex (same hue) that white text reads on: the dark sidebar.
     */
    public static function shade(string $hex): string
    {
        [$r, $g, $b] = array_map(fn (string $pair): float => hexdec($pair) / 255, str_split(substr($hex, 1), 2));
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $delta = $max - $min;
        $l = ($max + $min) / 2;
        $s = $delta == 0.0 ? 0.0 : $delta / (1 - abs(2 * $l - 1));
        $h = match (true) {
            $delta == 0.0 => 0.0,
            $max === $r => 60 * fmod(($g - $b) / $delta + 6, 6),
            $max === $g => 60 * (($b - $r) / $delta + 2),
            default => 60 * (($r - $g) / $delta + 4),
        };

        $l = min($l, 0.135);
        $s = min($s, 0.68);
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;
        [$r, $g, $b] = match (intdiv((int) $h, 60) % 6) {
            0 => [$c, $x, 0],
            1 => [$x, $c, 0],
            2 => [0, $c, $x],
            3 => [0, $x, $c],
            4 => [$x, 0, $c],
            default => [$c, 0, $x],
        };

        return sprintf('#%02x%02x%02x', (int) round(($r + $m) * 255), (int) round(($g + $m) * 255), (int) round(($b + $m) * 255));
    }
}
