<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Files\StoredFileService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The integrity queries of docs/architecture/11-migration-strategy.md §6. Each returns the ids
 * that break a rule the database can't express on its own, so every query must return no rows.
 * A query whose tables don't exist yet (external_references arrive in Phase 4) is reported as
 * skipped.
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
            'rule' => 'A document filed in an entity folder is about that entity or one of its children (F18)',
            'tables' => ['documents', 'folders', 'service_logs', 'inspections'],
            'sql' => 'SELECT d.id FROM documents d JOIN folders f ON f.id = d.folder_id
                WHERE (f.driver_id IS NOT NULL AND (d.driver_id IS NULL OR d.driver_id <> f.driver_id))
                OR (f.trailer_id IS NOT NULL AND (d.trailer_id IS NULL OR d.trailer_id <> f.trailer_id))
                OR (f.vehicle_id IS NOT NULL AND (d.vehicle_id IS NULL OR d.vehicle_id <> f.vehicle_id)
                    AND NOT EXISTS (SELECT 1 FROM service_logs l WHERE l.id = d.service_log_id AND l.vehicle_id = f.vehicle_id))
                OR (f.trip_id IS NOT NULL AND (d.trip_id IS NULL OR d.trip_id <> f.trip_id)
                    AND NOT EXISTS (SELECT 1 FROM inspections i WHERE i.id = d.inspection_id AND i.trip_id = f.trip_id))',
        ],
        6 => [
            'rule' => 'Storage counter matches stored files (exports are not counted)',
            'tables' => ['companies', 'stored_files'],
            'sql' => 'SELECT c.id FROM companies c
                LEFT JOIN (SELECT company_id, SUM(size_bytes) s FROM stored_files WHERE '.StoredFileService::COUNTED_SQL.' GROUP BY company_id) f ON f.company_id = c.id
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
        9 => [
            'rule' => 'No email is both a tenant and a platform login (D3a)',
            'tables' => ['users', 'platform_users'],
            'sql' => 'SELECT u.id FROM users u JOIN platform_users p ON LOWER(p.email) = LOWER(u.email)',
        ],
        10 => [
            'rule' => 'A support user is active only during an active support session (04 §4.3)',
            'tables' => ['users', 'platform_support_sessions'],
            'sql' => "SELECT u.id FROM users u WHERE u.user_type = 'support' AND u.status = 'active'
                AND NOT EXISTS (SELECT 1 FROM platform_support_sessions s WHERE s.company_id = u.company_id AND s.status = 'active')",
        ],
        11 => [
            'rule' => 'No folder is nested deeper than 5 levels (08 §8)',
            'tables' => ['folders'],
            'sql' => 'SELECT f6.id FROM folders f6
                JOIN folders f5 ON f5.id = f6.parent_id
                JOIN folders f4 ON f4.id = f5.parent_id
                JOIN folders f3 ON f3.id = f4.parent_id
                JOIN folders f2 ON f2.id = f3.parent_id
                JOIN folders f1 ON f1.id = f2.parent_id',
        ],
        12 => [
            'rule' => "A child folder has its parent's binding (08 §8)",
            'tables' => ['folders'],
            'sql' => 'SELECT c.id FROM folders c JOIN folders p ON p.id = c.parent_id
                WHERE COALESCE(c.driver_id, 0) <> COALESCE(p.driver_id, 0)
                OR COALESCE(c.vehicle_id, 0) <> COALESCE(p.vehicle_id, 0)
                OR COALESCE(c.trailer_id, 0) <> COALESCE(p.trailer_id, 0)
                OR COALESCE(c.trip_id, 0) <> COALESCE(p.trip_id, 0)',
        ],
        13 => [
            'rule' => "A converted document's PDF was made from its original image (req 2.4)",
            'tables' => ['documents', 'stored_files'],
            'sql' => 'SELECT d.id FROM documents d JOIN stored_files f ON f.id = d.file_id
                WHERE COALESCE(f.converted_from_file_id, 0) <> COALESCE(d.original_file_id, 0)',
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
