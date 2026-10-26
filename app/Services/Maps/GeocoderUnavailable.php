<?php

namespace App\Services\Maps;

use RuntimeException;

/**
 * The service failed, timed out or the app-wide rate limit is reached. The message is shown to
 * the user, who can still type the address.
 */
class GeocoderUnavailable extends RuntimeException
{
    public static function failed(): self
    {
        return new self('Address search is unavailable right now. Enter the address by hand.');
    }

    public static function busy(): self
    {
        return new self('Address search is busy. Try again in a moment.');
    }
}
