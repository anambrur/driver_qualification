<?php

namespace App\Services\Authorization;

use App\Authorization\RoleTemplates;
use App\Authorization\TeamScope;
use App\Authorization\TenantPermissionResolver;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Brings each company's built-in roles up to RoleTemplates after the catalogue changes (06 §6).
 * Owner, Driver and the Support roles always follow the template; Administrator, Dispatcher
 * and Office / Compliance follow it until the company edits them (customized_at). One company
 * at a time, inside its own team: a role is only ever looked up by that company's team_id.
 * A built-in role the company deleted is not recreated.
 */
class CompanyRoleSync
{
    public function __construct(private TenantPermissionResolver $resolver) {}

    /**
     * @return int roles changed in this company
     */
    public function sync(int $companyId): int
    {
        $permissionIds = Permission::query()->where('guard_name', 'web')->pluck('id', 'name');

        $changed = TeamScope::run($companyId, function () use ($companyId, $permissionIds) {
            $changed = 0;

            foreach (RoleTemplates::SYSTEM_ROLES as $name => $key) {
                $role = Role::query()
                    ->where('team_id', $companyId)
                    ->where('guard_name', 'web')
                    ->where('is_system', true)
                    ->when($key !== null, fn ($query) => $query->where('system_key', $key), fn ($query) => $query->where('name', $name))
                    ->first();

                if ($role === null || ($key === null && $role->customized_at !== null)) {
                    continue;
                }

                $expected = $permissionIds->only(RoleTemplates::permissionsFor($name))->map(fn ($id) => (int) $id)->sort()->values()->all();
                $current = DB::table('role_has_permissions')->where('role_id', $role->id)->orderBy('permission_id')->pluck('permission_id')->all();

                if ($expected !== array_map('intval', $current)) {
                    $role->permissions()->sync($expected);
                    $changed++;
                }
            }

            return $changed;
        });

        if ($changed > 0) {
            $this->resolver->flush();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        return $changed;
    }

    /**
     * @return int roles changed across all companies
     */
    public function syncAll(): int
    {
        $changed = 0;

        DB::table('companies')->select('id')->orderBy('id')->chunkById(200, function ($companies) use (&$changed) {
            foreach ($companies as $company) {
                $changed += $this->sync((int) $company->id);
            }
        });

        return $changed;
    }
}
