<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
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

    public const COMPANY_MODULES = ['drivers', 'fleets', 'vehicles', 'trailers', 'asset-groups', 'maintenance', 'scheduled'];

    public const COMPANY_EXTRA_PERMISSIONS = ['companies.edit'];

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
     * Sync permissions and roles. Non-destructive and idempotent, so it is safe to run in
     * production to add new permissions: it never deletes users, companies, roles or permissions.
     * The demo super-admin login lives in DemoUserSeeder (local only).
     *
     * php artisan db:seed --class=PermissionSeeder
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::permissionNames() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // super-admin always holds every permission (RoleController enforces the same rule).
        Role::findOrCreate('super-admin', 'web')->syncPermissions(Permission::all());

        // Additive only: permissions an admin added to the company role in the Roles UI are kept.
        Role::findOrCreate('company', 'web')->givePermissionTo(
            Permission::where(function ($query) {
                foreach (self::COMPANY_MODULES as $module) {
                    $query->orWhere('name', 'like', $module . '.%');
                }
            })->get()
        );

        // Allow company users to edit their own company profile (My Account)
        Role::findByName('company', 'web')->givePermissionTo(self::COMPANY_EXTRA_PERMISSIONS);

        Role::findOrCreate('user', 'web');

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
