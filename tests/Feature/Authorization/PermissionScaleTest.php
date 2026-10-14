<?php

use App\Authorization\RoleTemplates;
use App\Authorization\TenantPermissionResolver;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/*
| 06 §2.2 on the real app code: resolve a user's permissions database-first at 2,000 companies
| (7 system roles and 7 users each) on MySQL, p95 under 5 ms. Runs in a throwaway database
| that is dropped afterwards. Excluded from the default run: php artisan test --group=scale
*/

const SCALE_DB = 'dq_perm_scale';
const SCALE_COMPANIES = 2000;
const SCALE_SAMPLES = 2000;

function scalePercentile(array $values, float $p): float
{
    sort($values);

    return $values[(int) floor(($p / 100) * (count($values) - 1))];
}

function seedScaleCompanies(int $count): void
{
    $now = now();
    $permissionIds = DB::table('permissions')->where('guard_name', 'web')->pluck('id', 'name');
    $grants = [];

    foreach (array_keys(RoleTemplates::SYSTEM_ROLES) as $role) {
        $grants[$role] = $permissionIds->only(RoleTemplates::permissionsFor($role))->values()->all();
    }

    $holders = ['Owner', 'Administrator', 'Dispatcher', 'Office / Compliance', 'Driver', 'Driver', 'Support (read)'];
    $userType = (new User)->getMorphClass();

    foreach (array_chunk(range(1, $count), 100) as $chunk) {
        DB::table('companies')->insert(array_map(fn (int $n) => [
            'company_name' => "Scale {$n}", 'slug' => "scale-{$n}", 'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
        ], $chunk));
        $companyIds = DB::table('companies')->whereIn('slug', array_map(fn (int $n) => "scale-{$n}", $chunk))->pluck('id')->all();

        $roles = [];
        $users = [];
        foreach ($companyIds as $companyId) {
            foreach (RoleTemplates::SYSTEM_ROLES as $name => $key) {
                $roles[] = ['team_id' => $companyId, 'name' => $name, 'guard_name' => 'web', 'system_key' => $key, 'is_system' => true, 'created_at' => $now, 'updated_at' => $now];
            }
            foreach ($holders as $i => $role) {
                $users[] = ['company_id' => $companyId, 'name' => "u{$i}", 'email' => "c{$companyId}u{$i}@scale.test", 'password' => 'x', 'created_at' => $now, 'updated_at' => $now];
            }
        }
        DB::table('roles')->insert($roles);
        DB::table('users')->insert($users);

        $roleIds = DB::table('roles')->whereIn('team_id', $companyIds)->get(['id', 'team_id', 'name'])
            ->groupBy('team_id')->map(fn ($rows) => $rows->pluck('id', 'name'));
        $userIds = DB::table('users')->whereIn('company_id', $companyIds)->orderBy('id')->get(['id', 'company_id'])->groupBy('company_id');

        $rolePermissions = [];
        $modelRoles = [];
        foreach ($companyIds as $companyId) {
            foreach ($grants as $role => $ids) {
                foreach ($ids as $permissionId) {
                    $rolePermissions[] = ['permission_id' => $permissionId, 'role_id' => $roleIds[$companyId][$role]];
                }
            }
            foreach ($userIds[$companyId]->values() as $i => $user) {
                $modelRoles[] = ['team_id' => $companyId, 'role_id' => $roleIds[$companyId][$holders[$i]], 'model_type' => $userType, 'model_id' => $user->id];
            }
        }
        foreach (array_chunk($rolePermissions, 5000) as $rows) {
            DB::table('role_has_permissions')->insert($rows);
        }
        DB::table('model_has_roles')->insert($modelRoles);
    }
}

it('resolves permissions database-first at 2,000 companies with p95 under 5 ms', function () {
    $mysql = config('database.connections.mysql');
    config([
        'database.connections.scale_admin' => array_merge($mysql, ['database' => null]),
        'database.connections.scale' => array_merge($mysql, ['database' => SCALE_DB]),
    ]);
    $previous = DB::getDefaultConnection();
    DB::connection('scale_admin')->statement('DROP DATABASE IF EXISTS `'.SCALE_DB.'`');
    DB::connection('scale_admin')->statement('CREATE DATABASE `'.SCALE_DB.'`');

    try {
        DB::setDefaultConnection('scale');
        Artisan::call('migrate', ['--database' => 'scale', '--force' => true]);
        (new PermissionSeeder)->run();
        seedScaleCompanies(SCALE_COMPANIES);

        expect(DB::table('companies')->count())->toBe(SCALE_COMPANIES)
            ->and(DB::table('roles')->count())->toBe(SCALE_COMPANIES * 7);

        $maxUser = (int) DB::table('users')->max('id');
        $users = User::query()->whereIn('id', array_map(fn () => random_int(1, $maxUser), range(1, SCALE_SAMPLES)))->get();
        $resolver = app(TenantPermissionResolver::class);
        $latencies = [];
        $allowed = 0;

        foreach ($users as $user) {
            $resolver->flush();                                   // a new request
            $start = hrtime(true);
            $allowed += (int) $resolver->has($user, 'drivers.view');
            $resolver->has($user, 'trips.edit');
            $resolver->has($user, 'billing.manage');
            $latencies[] = (hrtime(true) - $start) / 1e6;
        }

        $p50 = scalePercentile($latencies, 50);
        $p95 = scalePercentile($latencies, 95);
        $p99 = scalePercentile($latencies, 99);
        fwrite(STDERR, sprintf("\nPermissionScale: %d companies, %d role_has_permissions, %d requests: p50 %.3f ms, p95 %.3f ms, p99 %.3f ms\n",
            SCALE_COMPANIES, DB::table('role_has_permissions')->count(), count($latencies), $p50, $p95, $p99));

        expect($p95)->toBeLessThan(5.0)
            ->and($allowed)->toBeGreaterThan(0);
    } finally {
        DB::setDefaultConnection($previous);
        DB::purge('scale');
        DB::connection('scale_admin')->statement('DROP DATABASE IF EXISTS `'.SCALE_DB.'`');
        DB::purge('scale_admin');
    }
})->group('scale');
