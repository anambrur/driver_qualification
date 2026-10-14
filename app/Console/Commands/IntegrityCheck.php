<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The integrity queries of docs/architecture/11-migration-strategy.md §6. Each returns the ids
 * that break a rule the database can't express on its own, so every query must return no rows.
 * A query whose tables don't exist yet (documents arrive in Phase 1, external_references in
 * Phase 4) is reported as skipped.
 */
class IntegrityCheck extends Command
{
    protected $signature = 'integrity:check';

    protected $description = 'Run the nightly data integrity queries; fails if any returns rows';

    /**
     * @var array<int, array{rule: string, tables: list<string>, sql: string}>
     */
    public const QUERIES = [
        1 => [
            'rule' => 'Roles never have a null team; guard matches team kind',
            'tables' => ['roles'],
            'sql' => "SELECT id FROM roles WHERE team_id IS NULL OR ((guard_name = 'platform') <> (team_id = 0))",
        ],
        2 => [
            'rule' => "Role pivots belong to the user's own company",
            'tables' => ['model_has_roles', 'users'],
            // model_type is a binding: MySQL and SQLite read backslashes in literals differently.
            'sql' => 'SELECT m.model_id FROM model_has_roles m
                JOIN users u ON m.model_type = :user_type AND u.id = m.model_id
                WHERE m.team_id <> u.company_id',
        ],
        3 => [
            'rule' => 'Exactly the owner holds the Owner role',
            'tables' => ['companies', 'roles', 'model_has_roles'],
            'sql' => "SELECT c.id FROM companies c
                JOIN roles r ON r.team_id = c.id AND r.system_key = 'owner'
                LEFT JOIN model_has_roles m ON m.role_id = r.id AND m.team_id = c.id
                GROUP BY c.id, c.owner_user_id
                HAVING COUNT(m.model_id) <> 1 OR MAX(m.model_id) <> c.owner_user_id",
        ],
        4 => [
            'rule' => 'Every company has its settings',
            'tables' => ['companies', 'company_settings'],
            'sql' => 'SELECT c.id FROM companies c
                LEFT JOIN company_settings s ON s.company_id = c.id
                WHERE s.company_id IS NULL',
        ],
        5 => [
            'rule' => 'Documents filed in a driver folder have that driver as subject',
            'tables' => ['documents', 'folders'],
            'sql' => 'SELECT d.id FROM documents d JOIN folders f ON f.id = d.folder_id
                WHERE f.driver_id IS NOT NULL AND (d.driver_id IS NULL OR d.driver_id <> f.driver_id)',
        ],
        6 => [
            'rule' => 'Storage counter matches stored files',
            'tables' => ['companies', 'stored_files'],
            'sql' => 'SELECT c.id FROM companies c
                LEFT JOIN (SELECT company_id, SUM(size_bytes) s FROM stored_files GROUP BY company_id) f ON f.company_id = c.id
                WHERE c.storage_bytes <> COALESCE(f.s, 0)',
        ],
        7 => [
            'rule' => 'One open subscription per company',
            'tables' => ['subscriptions'],
            'sql' => 'SELECT company_id FROM subscriptions WHERE ended_at IS NULL GROUP BY company_id HAVING COUNT(*) > 1',
        ],
        8 => [
            'rule' => 'External driver references point at a driver of the same company',
            'tables' => ['external_references', 'drivers'],
            'sql' => "SELECT e.id FROM external_references e
                LEFT JOIN drivers d ON e.local_type = 'driver' AND d.id = e.local_id AND d.company_id = e.company_id
                WHERE e.local_type = 'driver' AND d.id IS NULL",
        ],
    ];

    public function handle(): int
    {
        $failed = 0;
        $rows = [];

        foreach (self::QUERIES as $number => $query) {
            $missing = array_values(array_filter($query['tables'], fn (string $table) => ! Schema::hasTable($table)));

            if ($missing !== []) {
                $rows[] = [$number, $query['rule'], 'skipped (table absent: '.implode(', ', $missing).')'];

                continue;
            }

            $bindings = str_contains($query['sql'], ':user_type') ? ['user_type' => User::class] : [];
            $ids = array_map(fn ($row) => array_values((array) $row)[0], DB::select($query['sql'], $bindings));

            if ($ids === []) {
                $rows[] = [$number, $query['rule'], 'ok'];

                continue;
            }

            $failed++;
            $rows[] = [$number, $query['rule'], count($ids).' row(s): '.implode(', ', array_slice($ids, 0, 20))];
        }

        $this->table(['#', 'Rule', 'Result'], $rows);

        if ($failed > 0) {
            $this->error("{$failed} integrity check(s) failed.");

            return self::FAILURE;
        }

        $this->info('All integrity checks passed.');

        return self::SUCCESS;
    }
}
