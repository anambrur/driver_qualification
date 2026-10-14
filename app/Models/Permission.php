<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\PermissionRegistrar;

/**
 * A catalogue permission (06 §5). Global: no team column.
 *
 * @property string $name
 * @property string $guard_name
 * @property string|null $feature_key
 * @property bool $is_write
 * @property bool $self_scoped
 */
class Permission extends SpatiePermission
{
    protected function casts(): array
    {
        return [
            'is_write' => 'boolean',
            'self_scoped' => 'boolean',
        ];
    }

    /**
     * The catalogue entry from the registrar's cache (permissions only, no roles), or null
     * when the ability is not a permission.
     */
    public static function fromCatalogue(string $name, ?string $guardName = null): ?self
    {
        $params = ['name' => $name];

        if ($guardName !== null) {
            $params['guard_name'] = $guardName;
        }

        /** @var self|null */
        return app(PermissionRegistrar::class)->getPermissions($params, true)->first();
    }
}
