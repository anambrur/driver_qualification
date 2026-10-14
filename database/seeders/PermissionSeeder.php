<?php

namespace Database\Seeders;

use App\Authorization\PermissionCatalogue;
use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Sync the permission catalogue (06 §5, 04 §3). Idempotent and safe in production: it only
     * creates or updates permission rows and never deletes one, so it never touches team roles
     * or their grants. A feature_key is set once its features row exists (WP7 registry sync).
     *
     * php artisan db:seed --class=PermissionSeeder
     */
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $features = DB::table('features')->pluck('key')->flip();

        $now = now();
        $rows = [];

        foreach (PermissionCatalogue::TENANT as $name => [$feature, $write, $own]) {
            $rows[] = [
                'name' => $name, 'guard_name' => 'web', 'is_write' => $write, 'self_scoped' => $own,
                'feature_key' => $feature !== null && $features->has($feature) ? $feature : null,
                'created_at' => $now, 'updated_at' => $now,
            ];
        }

        foreach (PermissionCatalogue::PLATFORM as $name => $write) {
            $rows[] = [
                'name' => $name, 'guard_name' => 'platform', 'is_write' => $write, 'self_scoped' => false,
                'feature_key' => null, 'created_at' => $now, 'updated_at' => $now,
            ];
        }

        Permission::query()->upsert($rows, ['name', 'guard_name'], ['is_write', 'self_scoped', 'feature_key', 'updated_at']);

        $known = array_merge(
            array_map(fn (string $name) => "web:{$name}", PermissionCatalogue::tenantNames()),
            array_map(fn (string $name) => "platform:{$name}", PermissionCatalogue::platformNames()),
        );
        $stale = Permission::query()->get(['name', 'guard_name'])
            ->map(fn (Permission $permission) => "{$permission->guard_name}:{$permission->name}")
            ->diff($known);

        if ($stale->isNotEmpty()) {
            $this->command?->warn('Permissions not in the catalogue (left in place): '.$stale->implode(', '));
        }

        $registrar->forgetCachedPermissions();
    }
}
