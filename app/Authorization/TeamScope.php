<?php

namespace App\Authorization;

use Closure;
use Spatie\Permission\PermissionRegistrar;

/**
 * Runs Spatie role and permission writes in one team (a company id, or 0 for platform), then
 * restores the previous team even when the callback throws.
 */
final class TeamScope
{
    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function run(int $teamId, Closure $callback): mixed
    {
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($teamId);

        try {
            return $callback();
        } finally {
            $registrar->setPermissionsTeamId($previous);
        }
    }
}
