<?php

namespace App\Features;

/**
 * How a company may use one feature (05 §4, §7.2). ReadOnly keeps viewing and exporting.
 */
enum FeatureState: string
{
    case On = 'on';
    case ReadOnly = 'read_only';
    case Off = 'off';

    public function allowsRead(): bool
    {
        return $this !== self::Off;
    }

    public function allowsWrite(): bool
    {
        return $this === self::On;
    }
}
