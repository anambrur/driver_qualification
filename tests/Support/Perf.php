<?php

namespace Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Assert;

/**
 * Query-budget helpers for performance tests (N+1 detection, unbounded queries).
 */
final class Perf
{
    /**
     * Run $callback and return the number of SQL queries it executed.
     */
    public static function countQueries(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $callback();

            return count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }

    /**
     * Assert $callback runs at most $max queries. On failure, prints the queries.
     */
    public static function assertMaxQueries(int $max, callable $callback): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $callback();
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
        }

        Assert::assertLessThanOrEqual(
            $max,
            count($queries),
            "Expected at most {$max} queries, got ".count($queries).":\n".
                implode("\n", array_map(fn ($q) => '  - '.$q['query'], $queries))
        );
    }

    /**
     * Assert the query count does not grow with the number of rows: run $callback
     * against $small then $large data sets (built by $seed) and compare.
     *
     * @param  callable(int): void  $seed  creates N rows
     */
    public static function assertConstantQueries(callable $seed, callable $callback, int $small = 2, int $large = 12, int $tolerance = 0): void
    {
        $seed($small);
        $before = self::countQueries($callback);

        $seed($large - $small);
        $after = self::countQueries($callback);

        Assert::assertLessThanOrEqual(
            $before + $tolerance,
            $after,
            "N+1 suspected: {$before} queries with {$small} rows, {$after} with {$large} rows."
        );
    }

    /**
     * Make lazy loading throw for the rest of the test (catches N+1 in views/DataTables).
     */
    public static function forbidLazyLoading(): void
    {
        Model::preventLazyLoading();
    }
}
