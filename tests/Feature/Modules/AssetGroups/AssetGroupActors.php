<?php

namespace Tests\Feature\Modules\AssetGroups;

use App\Models\Company;
use App\Models\User;
use Tests\Support\Actors;

/**
 * A subscribed tenant. The `company` role gets asset-groups.* from PermissionSeeder::COMPANY_MODULES.
 */
final class AssetGroupActors
{
    public const PERMISSIONS = ['asset-groups.view', 'asset-groups.create', 'asset-groups.edit', 'asset-groups.delete'];

    public static function tenant(): User
    {
        return Actors::companyOwner();
    }

    public static function company(User $user): Company
    {
        return Actors::companyOf($user);
    }
}
