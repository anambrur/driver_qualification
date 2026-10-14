<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Adds CHECK constraints from a migration. Laravel's Blueprint has no check().
 *
 * SQLite can't add a constraint to an existing table, so the (still empty) table is recreated
 * from its own CREATE statement with the checks appended, and its indexes are replayed.
 * Call this as the last statement of the table's create migration: any later Schema::table()
 * alter on SQLite rebuilds the table from Laravel's state and drops these checks.
 */
final class CheckConstraints
{
    /**
     * @param  array<string, string>  $checks  constraint name => boolean SQL expression
     */
    public static function add(string $table, array $checks): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            foreach ($checks as $name => $expression) {
                DB::statement("alter table `{$table}` add constraint `{$name}` check ({$expression})");
            }

            return;
        }

        if (DB::table($table)->exists()) {
            throw new RuntimeException("CHECK constraints can only be added to the empty table [{$table}].");
        }

        $objects = collect(DB::select(
            'select type, sql from sqlite_master where tbl_name = ? and sql is not null',
            [$table]
        ));
        $createSql = rtrim($objects->firstWhere('type', 'table')->sql);
        $constraints = collect($checks)
            ->map(fn (string $expression, string $name) => "constraint \"{$name}\" check ({$expression})")
            ->implode(', ');

        DB::statement("drop table \"{$table}\"");
        DB::statement(substr($createSql, 0, -1).", {$constraints})");

        foreach ($objects->where('type', 'index') as $index) {
            DB::statement($index->sql);
        }
    }
}
