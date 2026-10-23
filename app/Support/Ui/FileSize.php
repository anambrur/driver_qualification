<?php

namespace App\Support\Ui;

/**
 * A byte count for people: 1.2 MB. Number::fileSize needs intl, which production may not have
 * (PO-20).
 */
final class FileSize
{
    public static function format(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = $bytes > 0 ? min((int) floor(log($bytes, 1024)), count($units) - 1) : 0;

        return number_format($bytes / 1024 ** $power, $power === 0 ? 0 : 1).' '.$units[$power];
    }
}
