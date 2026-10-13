<?php

/**
 * Permission-scale spike: stock Spatie teams vs a database-first check on Spatie's tables.
 * Runs against a throwaway MySQL database (dfh_perm_spike); never touches the app database.
 * Results and conclusions: docs/architecture/06-roles-permissions.md §2.
 *
 * Run from any directory with APP_ROOT pointing at this repository (it reuses vendor/ and the
 * MySQL credentials in .env, but creates and later drops its own database):
 *   APP_ROOT=/path/to/repo php -d memory_limit=4G permission-scale-spike.php setup
 *   ... seed 2000, stock database 30, stock redis 30, dbfirst 5000, functional, isolation ...
 *   APP_ROOT=/path/to/repo php permission-scale-spike.php teardown
 * The registrar override is bound after BootProviders because Spatie binds its registrar in boot().
 *
 * php spike.php setup
 * php spike.php seed <companies>
 * php spike.php stock <database|redis> <samples>
 * php spike.php dbfirst <samples>
 * php spike.php worker <lookups>          (used by the concurrency run)
 * php spike.php functional
 * php spike.php isolation
 * php spike.php teardown
 */

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;

$root = getenv('APP_ROOT');
$mode = $argv[1] ?? 'help';
$arg = $argv[2] ?? null;
$arg2 = $argv[3] ?? null;
$spikeDb = 'dfh_perm_spike';

require $root.'/vendor/autoload.php';

/** Permissions are cached WITHOUT their roles: constant-size cache, no cross-tenant role data. */
class SlimRegistrar extends PermissionRegistrar
{
    protected function getPermissionsWithRoles(): EloquentCollection
    {
        return $this->permissionClass::select()->get()->each(fn ($p) => $p->setRelation('roles', new EloquentCollection()));
    }
}

class SpikeUser extends Model
{
    use HasRoles;

    protected $table = 'users';
    protected $guarded = [];
    protected string $guard_name = 'web';
    public $timestamps = false;
}

/** Database-first check: the user's effective permission names in one team, one indexed query. */
final class TenantPermissionResolver
{
    private static array $memo = [];

    public static function names(int $userId, int $teamId): array
    {
        $key = $teamId.':'.$userId;
        if (isset(self::$memo[$key])) {
            return self::$memo[$key];
        }
        $rows = DB::select(
            'SELECT p.name FROM model_has_roles mhr
               JOIN roles r ON r.id = mhr.role_id AND r.team_id = mhr.team_id
               JOIN role_has_permissions rhp ON rhp.role_id = r.id
               JOIN permissions p ON p.id = rhp.permission_id AND p.guard_name = ?
              WHERE mhr.model_type = ? AND mhr.model_id = ? AND mhr.team_id = ?
             UNION
             SELECT p.name FROM model_has_permissions mhp
               JOIN permissions p ON p.id = mhp.permission_id AND p.guard_name = ?
              WHERE mhp.model_type = ? AND mhp.model_id = ? AND mhp.team_id = ?',
            ['web', SpikeUser::class, $userId, $teamId, 'web', SpikeUser::class, $userId, $teamId]
        );

        return self::$memo[$key] = array_flip(array_column($rows, 'name'));
    }

    public static function reset(): void
    {
        self::$memo = [];
    }
}

$store = in_array($mode, ['stock'], true) ? ($arg ?: 'database') : 'array';
$useSlim = in_array($mode, ['samename', 'multirole', 'whichreg', 'dbfirst', 'worker', 'functional', 'isolation', 'slimsize'], true);

$app = require $root.'/bootstrap/app.php';
$app->afterBootstrapping(Illuminate\Foundation\Bootstrap\LoadConfiguration::class, function ($app) use ($spikeDb, $store) {
    $c = $app['config'];
    $mysql = $c->get('database.connections.mysql');
    $c->set('database.connections.spike', array_merge($mysql, ['database' => $spikeDb]));
    $c->set('database.connections.spike_admin', array_merge($mysql, ['database' => null]));
    $c->set('database.default', 'spike');
    $c->set('cache.default', $store);
    $c->set('cache.prefix', 'dfh_perm_spike_');
    $c->set('cache.stores.database.connection', 'spike');
    $c->set('permission.teams', true);
    $c->set('permission.cache.store', $store);
    $c->set('permission.cache.key', 'dfh_perm_spike.permission.cache');
    $c->set('queue.default', 'sync');
});
if ($useSlim) {
    $app->afterBootstrapping(Illuminate\Foundation\Bootstrap\BootProviders::class, function ($app) {
        $app->singleton(PermissionRegistrar::class, SlimRegistrar::class);
    });
}
if ($mode !== 'setup') {
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
} else {
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
}

config(['logging.default' => 'stderr']);
set_exception_handler(function (\Throwable $e) {
    fwrite(STDERR, get_class($e).': '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine().PHP_EOL);
    exit(1);
});
set_error_handler(function ($no, $str, $file, $line) {
    fwrite(STDERR, "PHP error $no: $str @ $file:$line".PHP_EOL);
    return true;
});
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true)) {
        fwrite(STDERR, 'FATAL: '.$e['message'].' @ '.$e['file'].':'.$e['line'].PHP_EOL);
    }
});

function out(string $line): void { fwrite(STDOUT, $line.PHP_EOL); }
function pct(array $xs, float $p): float { sort($xs); return $xs[(int) floor(($p / 100) * (count($xs) - 1))]; }
function ms(float $s): string { return number_format($s * 1000, 3).' ms'; }

/** 110 tenant permissions shaped like the catalogue in docs/architecture/06 §4. */
function permissionNames(): array
{
    $modules = ['company','users','billing','roles','drivers','driver-documents','vehicles','trailers','asset-groups','fleets',
        'maintenance','scheduled','reminders','folders','documents','exports','trips','shippers','inspections','trip-events',
        'fuel','tracking','mobile','messages','time-off','pay','timesheets','reports'];
    $actions = ['view', 'create', 'edit', 'delete'];
    $names = [];
    foreach ($modules as $m) {
        foreach ($actions as $a) {
            $names[] = "$m.$a";
        }
    }
    return array_slice($names, 0, 110);
}

function roleTemplates(array $all): array
{
    $pick = fn (string $prefixes, int $n) => array_slice(array_values(array_filter($all, fn ($p) => preg_match('/^('.$prefixes.')\./', $p))), 0, $n);
    $views = array_values(array_filter($all, fn ($p) => str_ends_with($p, '.view')));

    return [
        'owner' => $all,
        'administrator' => array_values(array_diff($all, ['billing.edit', 'billing.delete'])),
        'dispatcher' => $pick('trips|shippers|drivers|vehicles|trailers|tracking|trip-events|messages|inspections', 28),
        'office' => $pick('drivers|driver-documents|vehicles|trailers|asset-groups|fleets|maintenance|scheduled|folders|documents|exports|reminders', 45),
        'driver' => $pick('trips|inspections|trip-events|fuel|tracking|messages|time-off', 12),
        'support_read' => $views,
        'support_read_write' => array_values(array_diff($all, ['billing.view', 'billing.create', 'billing.edit', 'billing.delete', 'users.create', 'users.edit', 'users.delete', 'roles.create', 'roles.edit', 'roles.delete', 'drivers.delete', 'documents.delete'])),
    ];
}

switch ($mode) {
    case 'setup':
        DB::connection('spike_admin')->statement("CREATE DATABASE IF NOT EXISTS `$spikeDb`");
        DB::purge('spike');
        $s = DB::connection('spike');
        foreach (['model_has_permissions', 'model_has_roles', 'role_has_permissions', 'roles', 'permissions', 'users', 'companies', 'cache', 'cache_locks'] as $t) {
            $s->statement("DROP TABLE IF EXISTS `$t`");
        }
        $s->statement('CREATE TABLE companies (id BIGINT UNSIGNED PRIMARY KEY)');
        $s->statement('CREATE TABLE users (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, company_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL, UNIQUE KEY users_company_id_id_unique (company_id, id),
            CONSTRAINT users_company_fk FOREIGN KEY (company_id) REFERENCES companies(id))');
        $s->statement('CREATE TABLE permissions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL,
            guard_name VARCHAR(255) NOT NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
            UNIQUE KEY permissions_name_guard_name_unique (name, guard_name))');
        $s->statement('CREATE TABLE roles (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, team_id BIGINT UNSIGNED NULL,
            name VARCHAR(255) NOT NULL, guard_name VARCHAR(255) NOT NULL, system_key VARCHAR(64) NULL,
            created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
            UNIQUE KEY roles_team_id_name_guard_name_unique (team_id, name, guard_name),
            UNIQUE KEY roles_team_id_id_unique (team_id, id), KEY roles_team_foreign_key_index (team_id))');
        $s->statement('CREATE TABLE role_has_permissions (permission_id BIGINT UNSIGNED NOT NULL, role_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (permission_id, role_id), KEY rhp_role_id_index (role_id),
            CONSTRAINT rhp_permission_fk FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
            CONSTRAINT rhp_role_fk FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE)');
        $s->statement('CREATE TABLE model_has_roles (role_id BIGINT UNSIGNED NOT NULL, model_type VARCHAR(255) NOT NULL,
            model_id BIGINT UNSIGNED NOT NULL, team_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (team_id, role_id, model_id, model_type),
            KEY model_has_roles_model_id_model_type_index (model_id, model_type),
            KEY mhr_lookup_index (model_type, model_id, team_id),
            CONSTRAINT mhr_team_role_fk FOREIGN KEY (team_id, role_id) REFERENCES roles(team_id, id) ON DELETE CASCADE)');
        $s->statement('CREATE TABLE model_has_permissions (permission_id BIGINT UNSIGNED NOT NULL, model_type VARCHAR(255) NOT NULL,
            model_id BIGINT UNSIGNED NOT NULL, team_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (team_id, permission_id, model_id, model_type),
            KEY mhp_lookup_index (model_type, model_id, team_id),
            CONSTRAINT mhp_permission_fk FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE)');
        $s->statement('CREATE TABLE cache (`key` VARCHAR(255) PRIMARY KEY, value MEDIUMTEXT NOT NULL, expiration INT NOT NULL)');
        $s->statement('CREATE TABLE cache_locks (`key` VARCHAR(255) PRIMARY KEY, owner VARCHAR(255) NOT NULL, expiration INT NOT NULL)');
        out('schema ready ('.DB::selectOne('SELECT VERSION() v')->v.')');
        break;

    case 'seed':
        $n = (int) $arg;
        $names = permissionNames();
        $now = now();
        $have = (int) DB::table('companies')->max('id');
        if ($have === 0) {
            DB::table('permissions')->insert(array_map(fn ($p) => ['name' => $p, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now], $names));
        }
        $permIds = DB::table('permissions')->pluck('id', 'name')->all();
        $templates = roleTemplates($names);
        $t0 = microtime(true);
        DB::statement('SET foreign_key_checks = 1');
        for ($c = $have + 1; $c <= $n; $c++) {
            DB::table('companies')->insert(['id' => $c]);
            $rhp = [];
            $roleIds = [];
            foreach ($templates as $key => $perms) {
                $roleIds[$key] = DB::table('roles')->insertGetId(['team_id' => $c, 'name' => ucfirst($key), 'guard_name' => 'web', 'system_key' => $key, 'created_at' => $now, 'updated_at' => $now]);
                foreach ($perms as $p) {
                    $rhp[] = ['permission_id' => $permIds[$p], 'role_id' => $roleIds[$key]];
                }
            }
            DB::table('role_has_permissions')->insert($rhp);
            $mhr = [];
            foreach (['owner', 'administrator', 'dispatcher', 'office', 'driver', 'driver', 'support_read'] as $i => $role) {
                $uid = DB::table('users')->insertGetId(['company_id' => $c, 'name' => "c{$c}u{$i}"]);
                $mhr[] = ['role_id' => $roleIds[$role], 'model_type' => SpikeUser::class, 'model_id' => $uid, 'team_id' => $c];
            }
            DB::table('model_has_roles')->insert($mhr);
            if ($c % 500 === 0) {
                out("  seeded $c companies (".round(microtime(true) - $t0, 1).'s)');
            }
        }
        out(sprintf('companies=%d roles=%d role_has_permissions=%d users=%d model_has_roles=%d',
            DB::table('companies')->count(), DB::table('roles')->count(), DB::table('role_has_permissions')->count(),
            DB::table('users')->count(), DB::table('model_has_roles')->count()));
        break;

    case 'stock':
        $samples = (int) ($arg2 ?: 50);
        $reg = app(PermissionRegistrar::class);
        $reg->forgetCachedPermissions();
        $m0 = memory_get_usage(true);
        $t0 = microtime(true);
        $reg->getPermissions();
        $build = microtime(true) - $t0;
        $raw = $store === 'redis'
            ? app('redis')->connection('cache')->client()->get(config('cache.prefix').config('permission.cache.key'))
            : DB::table('cache')->where('key', config('cache.prefix').config('permission.cache.key'))->value('value');
        $size = $raw === null || $raw === false ? 0 : strlen($raw);
        out(sprintf('stock[%s]: cache build %s, stored payload %.2f MB, memory +%.1f MB', $store, ms($build), $size / 1048576, (memory_get_usage(true) - $m0) / 1048576));

        $maxUser = (int) DB::table('users')->max('id');
        $load = [];
        $check = [];
        $ok = 0;
        for ($i = 0; $i < $samples; $i++) {
            $reg->clearPermissionsCollection();                     // a new request: reload from the cache store
            $user = SpikeUser::find(random_int(1, $maxUser));
            setPermissionsTeamId($user->company_id);
            $t = microtime(true);
            $reg->getPermissions();
            $load[] = microtime(true) - $t;
            $t = microtime(true);
            $ok += $user->hasPermissionTo('drivers.view') ? 1 : 0;
            $user->hasPermissionTo('trips.edit');
            $user->hasPermissionTo('billing.edit');
            $check[] = (microtime(true) - $t) / 3;
        }
        out(sprintf('stock[%s]: per-request cache load p50 %s p95 %s | per check p50 %s p95 %s | peak memory %.1f MB | allowed %d/%d',
            $store, ms(pct($load, 50)), ms(pct($load, 95)), ms(pct($check, 50)), ms(pct($check, 95)), memory_get_peak_usage(true) / 1048576, $ok, $samples));
        break;

    case 'whichreg':
        out(get_class(app(PermissionRegistrar::class)));
        break;

    case 'slimsize':
        $reg = app(PermissionRegistrar::class);
        $reg->forgetCachedPermissions();
        $reg->getPermissions();
        out(sprintf('slim registrar: cached permissions=%d, roles in cache=%d', $reg->getPermissions()->count(), $reg->getPermissions()->sum(fn ($p) => $p->roles->count())));
        break;

    case 'dbfirst':
        $samples = (int) ($arg ?: 5000);
        $maxUser = (int) DB::table('users')->max('id');
        $lat = [];
        $first = DB::select('EXPLAIN SELECT p.name FROM model_has_roles mhr JOIN roles r ON r.id = mhr.role_id AND r.team_id = mhr.team_id
            JOIN role_has_permissions rhp ON rhp.role_id = r.id JOIN permissions p ON p.id = rhp.permission_id
            WHERE mhr.model_type = ? AND mhr.model_id = ? AND mhr.team_id = ?', [SpikeUser::class, 1, 1]);
        foreach ($first as $row) {
            out(sprintf('  explain: table=%s type=%s key=%s rows=%s', $row->table, $row->type, $row->key, $row->rows));
        }
        $users = DB::table('users')->select('id', 'company_id')->whereIn('id', array_map(fn () => random_int(1, $maxUser), range(1, $samples)))->get()->all();
        $m0 = memory_get_peak_usage(true);
        foreach ($users as $u) {
            TenantPermissionResolver::reset();                      // a new request
            $t = microtime(true);
            $set = TenantPermissionResolver::names($u->id, $u->company_id);
            isset($set['drivers.view']);
            isset($set['trips.edit']);
            isset($set['billing.edit']);
            $lat[] = microtime(true) - $t;
        }
        out(sprintf('dbfirst: %d requests, resolve+3 checks p50 %s p95 %s p99 %s max %s | peak memory %.1f MB',
            count($lat), ms(pct($lat, 50)), ms(pct($lat, 95)), ms(pct($lat, 99)), ms(max($lat)), memory_get_peak_usage(true) / 1048576));
        break;

    case 'worker':
        $n = (int) $arg;
        $maxUser = (int) DB::table('users')->max('id');
        $map = DB::table('users')->pluck('company_id', 'id')->all();
        $lat = [];
        $t0 = microtime(true);
        for ($i = 0; $i < $n; $i++) {
            $uid = random_int(1, $maxUser);
            TenantPermissionResolver::reset();
            $t = microtime(true);
            TenantPermissionResolver::names($uid, $map[$uid]);
            $lat[] = microtime(true) - $t;
        }
        $wall = microtime(true) - $t0;
        echo json_encode(['n' => $n, 'wall' => $wall, 'p50' => pct($lat, 50), 'p95' => pct($lat, 95), 'p99' => pct($lat, 99)]).PHP_EOL;
        break;

    case 'functional':
        // Spatie management APIs keep working with the slim registrar; the stock check fails closed.
        $c = (int) DB::table('companies')->max('id') + 1;
        DB::table('companies')->insert(['id' => $c]);
        setPermissionsTeamId($c);
        $role = Role::create(['name' => 'Custom', 'guard_name' => 'web']);
        $role->givePermissionTo('drivers.view', 'trips.view');
        $role->syncPermissions(['drivers.view', 'trips.edit']);
        $user = SpikeUser::create(['company_id' => $c, 'name' => 'functional']);
        $user->assignRole($role);
        $user->givePermissionTo('reports.view');
        $set = TenantPermissionResolver::names($user->id, $c);
        out('functional: role team_id='.$role->team_id.' (expected '.$c.')');
        out('functional: hasRole(Custom)='.var_export($user->fresh()->hasRole('Custom'), true));
        out('functional: resolver allows drivers.view='.var_export(isset($set['drivers.view']), true).', trips.edit='.var_export(isset($set['trips.edit']), true).', reports.view (direct)='.var_export(isset($set['reports.view']), true).', trips.view (removed by sync)='.var_export(isset($set['trips.view']), true));
        out('functional: stock Spatie hasPermissionTo(trips.edit) with slim cache='.var_export($user->fresh()->hasPermissionTo('trips.edit'), true).' (expected false: role link not in cache, so stock checks must be routed to the resolver)');
        $user->removeRole($role);
        TenantPermissionResolver::reset();
        out('functional: after removeRole, resolver allows trips.edit='.var_export(isset(TenantPermissionResolver::names($user->id, $c)['trips.edit']), true));
        break;

    case 'isolation':
        $a = 1;
        $b = 2;
        $userA = SpikeUser::where('company_id', $a)->orderBy('id')->first();  // owner of company 1
        $roleB = DB::table('roles')->where('team_id', $b)->where('system_key', 'owner')->value('id');
        $roleA = DB::table('roles')->where('team_id', $a)->where('system_key', 'owner')->value('id');
        TenantPermissionResolver::reset();
        out('isolation: user A in team A has drivers.view='.var_export(isset(TenantPermissionResolver::names($userA->id, $a)['drivers.view']), true));
        out('isolation: user A evaluated in team B has '.count(TenantPermissionResolver::names($userA->id, $b)).' permissions (expected 0)');
        try {
            DB::table('model_has_roles')->insert(['role_id' => $roleB, 'model_type' => SpikeUser::class, 'model_id' => $userA->id, 'team_id' => $a]);
            out('isolation: FAIL - assigning team B role with pivot team A was accepted');
        } catch (\Throwable $e) {
            out('isolation: assigning team B role with pivot team A rejected by composite FK (OK)');
        }
        $nullRole = DB::table('roles')->insertGetId(['team_id' => null, 'name' => 'Global', 'guard_name' => 'web']);
        DB::table('role_has_permissions')->insert(['permission_id' => DB::table('permissions')->where('name', 'billing.edit')->value('id'), 'role_id' => $nullRole]);
        try {
            DB::table('model_has_roles')->insert(['role_id' => $nullRole, 'model_type' => SpikeUser::class, 'model_id' => $userA->id, 'team_id' => $a]);
            out('isolation: FAIL - null-team role assignable');
        } catch (\Throwable $e) {
            out('isolation: null-team role cannot be assigned in a company team (composite FK) (OK)');
        }
        // Stock Spatie would treat a null-team role as visible in every team:
        $visible = Role::query()->where(fn ($q) => $q->whereNull('team_id')->orWhere('team_id', $a))->where('name', 'Global')->exists();
        out('isolation: Spatie team scope (team_id IS NULL OR team_id = A) sees the null-team role: '.var_export($visible, true).' -> rule "no null-team roles" plus integrity query required');
        // A pivot recorded under team B for a company A user (application bug) never grants anything in team A:
        DB::table('model_has_roles')->insert(['role_id' => $roleB, 'model_type' => SpikeUser::class, 'model_id' => $userA->id, 'team_id' => $b]);
        TenantPermissionResolver::reset();
        $inA = TenantPermissionResolver::names($userA->id, $a);
        out('isolation: stray team-B pivot for user A; resolver in team A still uses only team A roles: '.count($inA).' permissions (owner role = 110)');
        $stray = DB::selectOne('SELECT COUNT(*) n FROM model_has_roles m JOIN users u ON u.id = m.model_id AND m.model_type = ? WHERE m.team_id <> u.company_id', [SpikeUser::class])->n;
        out("isolation: integrity query finds $stray stray pivot row(s) (expected 1)");
        DB::table('model_has_roles')->where('team_id', $b)->where('model_id', $userA->id)->delete();
        DB::table('role_has_permissions')->where('role_id', $nullRole)->delete();
        DB::table('roles')->where('id', $nullRole)->delete();
        break;

    case 'samename':
        // Two companies each create their own role called "Admin". Editing one must never touch the other.
        $ca = (int) DB::table('companies')->max('id') + 1;
        $cb = $ca + 1;
        DB::table('companies')->insert([['id' => $ca], ['id' => $cb]]);
        setPermissionsTeamId($ca);
        $adminA = Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        $adminA->syncPermissions(['drivers.view', 'drivers.edit', 'trips.view']);
        $userA = SpikeUser::create(['company_id' => $ca, 'name' => 'admin-a']);
        $userA->assignRole('Admin');                                   // resolved by name inside team A
        setPermissionsTeamId($cb);
        $adminB = Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        $adminB->syncPermissions(['billing.view']);
        $userB = SpikeUser::create(['company_id' => $cb, 'name' => 'admin-b']);
        $userB->assignRole('Admin');                                   // resolved by name inside team B
        out(sprintf('samename: two separate roles named Admin: A id=%d team=%d, B id=%d team=%d', $adminA->id, $adminA->team_id, $adminB->id, $adminB->team_id));

        // Company A edits its Admin role.
        setPermissionsTeamId($ca);
        Role::findByName('Admin', 'web')->syncPermissions(['drivers.view', 'reports.view']);
        $permsA = Role::findById($adminA->id)->permissions->pluck('name')->sort()->values()->all();
        try {
            Role::findById($adminB->id);                               // still acting as company A
            out('samename: FAIL - company A could load company B\'s Admin by id');
        } catch (\Spatie\Permission\Exceptions\RoleDoesNotExist) {
            out('samename: acting as company A, loading company B\'s Admin by id is refused (OK)');
        }
        setPermissionsTeamId($cb);
        $permsB = Role::findById($adminB->id)->permissions->pluck('name')->sort()->values()->all();
        out('samename: after A edits its Admin -> A Admin = ['.implode(', ', $permsA).'], B Admin = ['.implode(', ', $permsB).'] (B must still be [billing.view])');

        TenantPermissionResolver::reset();
        $a = TenantPermissionResolver::names($userA->id, $ca);
        $b = TenantPermissionResolver::names($userB->id, $cb);
        out('samename: user A can reports.view='.var_export(isset($a['reports.view']), true).', billing.view='.var_export(isset($a['billing.view']), true));
        out('samename: user B can billing.view='.var_export(isset($b['billing.view']), true).', reports.view='.var_export(isset($b['reports.view']), true).', drivers.view='.var_export(isset($b['drivers.view']), true));

        // Company B deletes its Admin role; company A's Admin and its user are untouched.
        setPermissionsTeamId($cb);
        Role::findById($adminB->id)->delete();
        TenantPermissionResolver::reset();
        out('samename: after B deletes its Admin -> A Admin still exists='.var_export(Role::query()->whereKey($adminA->id)->exists(), true).', user A permissions='.count(TenantPermissionResolver::names($userA->id, $ca)).', user B permissions='.count(TenantPermissionResolver::names($userB->id, $cb)));

        // A company cannot see or load another company's role by name.
        setPermissionsTeamId($cb);
        try {
            Role::findByName('Admin', 'web');
            out('samename: FAIL - team B found a role named Admin after deleting its own');
        } catch (\Spatie\Permission\Exceptions\RoleDoesNotExist) {
            out('samename: team B looking up "Admin" does not find company A\'s Admin (OK)');
        }
        break;

    case 'multirole':
        // A user holding several roles: resolution cost with 1, 3 and 6 roles.
        $c = (int) DB::table('companies')->max('id') + 1;
        DB::table('companies')->insert(['id' => $c]);
        setPermissionsTeamId($c);
        $names = permissionNames();
        $user = SpikeUser::create(['company_id' => $c, 'name' => 'multi']);
        foreach ([1, 3, 6] as $n) {
            while ($user->roles()->count() < $n) {
                $r = Role::create(['name' => 'R'.$user->roles()->count(), 'guard_name' => 'web']);
                $r->syncPermissions(array_slice($names, 0, 60));
                $user->assignRole($r);
            }
            $lat = [];
            for ($i = 0; $i < 2000; $i++) {
                TenantPermissionResolver::reset();
                $t = microtime(true);
                TenantPermissionResolver::names($user->id, $c);
                $lat[] = microtime(true) - $t;
            }
            out(sprintf('multirole: user with %d roles x 60 permissions -> p50 %s p95 %s', $n, ms(pct($lat, 50)), ms(pct($lat, 95))));
        }
        break;

    case 'teardown':
        DB::connection('spike_admin')->statement("DROP DATABASE IF EXISTS `$spikeDb`");
        try { app('redis')->connection('cache')->client()->del(config('cache.prefix').config('permission.cache.key')); } catch (\Throwable) {}
        out('dropped '.$spikeDb);
        break;

    default:
        out('usage: setup | seed N | stock <database|redis> N | slimsize | dbfirst N | worker N | functional | isolation | teardown');
}
