<?php

namespace Database\Seeders;

use App\Authorization\RoleTemplates;
use App\Authorization\TeamScope;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PlatformRoleSeeder extends Seeder
{
    /**
     * The system-defined platform roles in team 0 (04 §3). Idempotent: roles are found by
     * system_key and their grants re-synced, except a role a Platform Owner has edited
     * (customized_at); Platform Owner always gets every permission. Company roles are never touched.
     */
    public function run(): void
    {
        TeamScope::run(0, function () {
            foreach (RoleTemplates::PLATFORM_ROLES as $key => [$name]) {
                $role = Role::query()->firstOrCreate(
                    ['team_id' => 0, 'system_key' => $key],
                    ['name' => $name, 'guard_name' => 'platform', 'is_system' => true],
                );

                if ($key === RoleTemplates::PLATFORM_OWNER || $role->customized_at === null) {
                    $role->syncPermissions(RoleTemplates::platformPermissionsFor($key));
                }
            }
        });
    }
}
