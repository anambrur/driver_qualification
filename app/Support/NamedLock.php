<?php

namespace App\Support;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * A short application lock on a name, not a row. MySQL uses GET_LOCK, which every app server
 * shares through the database; other drivers (the SQLite test suite) fall back to the cache lock.
 */
class NamedLock
{
    /**
     * Throws LockTimeoutException when the lock is not free within $seconds; anything the
     * callback throws passes through, after the lock is released.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function run(string $name, int $seconds, Closure $callback): mixed
    {
        // MySQL caps lock names at 64 characters.
        $name = 'lock:'.sha1($name);

        if (DB::getDriverName() !== 'mysql') {
            return Cache::lock($name, $seconds + 5)->block($seconds, $callback);
        }

        if ((int) DB::scalar('SELECT GET_LOCK(?, ?)', [$name, $seconds]) !== 1) {
            throw new LockTimeoutException;
        }

        try {
            return $callback();
        } finally {
            DB::scalar('SELECT RELEASE_LOCK(?)', [$name]);
        }
    }
}
