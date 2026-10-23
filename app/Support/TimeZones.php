<?php

namespace App\Support;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/**
 * Company time zones offered at registration (14 §2, Z1): North American zones only, the
 * same reach as plate states (PO-11). DEFAULT matches the companies.timezone column default.
 */
final class TimeZones
{
    public const DEFAULT = 'America/Chicago';

    public const OPTIONS = [
        'America/New_York' => 'Eastern Time',
        'America/Chicago' => 'Central Time',
        'America/Denver' => 'Mountain Time',
        'America/Phoenix' => 'Mountain Time, Arizona (no DST)',
        'America/Los_Angeles' => 'Pacific Time',
        'America/Anchorage' => 'Alaska Time',
        'Pacific/Honolulu' => 'Hawaii Time',
        'America/Puerto_Rico' => 'Atlantic Time, Puerto Rico (no DST)',
        'America/Halifax' => 'Atlantic Time, Canada',
        'America/St_Johns' => 'Newfoundland Time',
        'America/Regina' => 'Central Time, Saskatchewan (no DST)',
    ];

    public static function rule(): In
    {
        return Rule::in(array_keys(self::OPTIONS));
    }
}
