<?php

namespace Tests\Support;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PDOException;

class QueryFailure
{
    /**
     * Makes every query on the table fail with a QueryException. Dropping the table instead
     * would be DDL, which commits the test's transaction on MySQL. The listener runs after the
     * query, so a write fails after reaching the table and the caller's rollback must undo it.
     */
    public static function on(string $table): void
    {
        $wrapped = DB::getQueryGrammar()->wrapTable($table);

        DB::listen(function (QueryExecuted $query) use ($table, $wrapped) {
            if (str_contains($query->sql, $wrapped)) {
                throw new QueryException($query->connectionName, $query->sql, $query->bindings, new PDOException("SQLSTATE[42S02]: Base table or view not found: {$table}"));
            }
        });
    }
}
