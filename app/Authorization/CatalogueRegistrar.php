<?php

namespace App\Authorization;

use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\PermissionRegistrar;

/**
 * Caches the permission catalogue without roles, so the cache stays about 110 rows at any
 * number of companies (06 §2, §3). Permission checks never read roles from this cache; they go
 * through TenantPermissionResolver.
 */
class CatalogueRegistrar extends PermissionRegistrar
{
    protected function getPermissionsWithRoles(): Collection
    {
        return $this->permissionClass::select()->get()
            ->each(fn ($permission) => $permission->setRelation('roles', new Collection));
    }
}
