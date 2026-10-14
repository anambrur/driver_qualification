<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    // Shared with the test suite (tests/Support/Actors.php) so tests use the real permission map.
    public const MODULES = [
        'roles',
        'users',
        'permission',
        'settings',
        'companies',
        'drivers',
        'policy-pdf',
        'fleets',
        'vehicle-types',
        'vehicle-groups',
        'vehicles',
        'fuel-types',
        'equipment-types',
        'trailers',
        'asset-groups',
        'asset-histories',
        'document-types',
        'maintenance-categories',
        'maintenance',
        'scheduled',
        'subscription',
    ];

    public const BASIC_ACTIONS = ['create', 'view', 'edit', 'delete'];

    public const SPECIAL_ACTIONS = [
        'drivers' => ['hire', 'dashboard'],
        'fleets' => ['dashboard'],
    ];

    /**
     * All permission names, e.g. "drivers.create", "drivers.hire".
     *
     * @return list<string>
     */
    public static function permissionNames(): array
    {
        $names = [];

        foreach (self::MODULES as $module) {
            foreach (self::BASIC_ACTIONS as $action) {
                $names[] = "$module.$action";
            }

            foreach (self::SPECIAL_ACTIONS[$module] ?? [] as $action) {
                $names[] = "$module.$action";
            }
        }

        return $names;
    }

    /**
     * Sync tenant permissions (web guard). Non-destructive and idempotent, so it is safe to run
     * in production: it only adds permissions and never touches roles or their grants. Roles are
     * per company (Spatie teams) and come from the role templates (WP3); the legacy global
     * super-admin, company and user roles are not created (06 §10).
     *
     * php artisan db:seed --class=PermissionSeeder
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::permissionNames() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
