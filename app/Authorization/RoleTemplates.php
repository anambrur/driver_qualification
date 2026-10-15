<?php

namespace App\Authorization;

/**
 * System roles every company gets at provisioning (06 §6) and the seeded platform roles (04 §3).
 */
final class RoleTemplates
{
    /** Role name => system_key (null = system role without a key). */
    public const SYSTEM_ROLES = [
        'Owner' => 'owner',
        'Administrator' => null,
        'Dispatcher' => null,
        'Office / Compliance' => null,
        'Driver' => 'driver',
        'Support (read)' => 'support_read',
        'Support (read/write)' => 'support_read_write',
    ];

    public const OWNER = 'Owner';

    public const ADMINISTRATOR = 'Administrator';

    /**
     * Tenant grants. `x.*` means every `x.` permission except the self-scoped `.own` ones.
     *
     * @var array<string, list<string>>
     */
    private const GRANTS = [
        'Dispatcher' => [
            'trips.*', 'shippers.*', 'drivers.view', 'vehicles.view', 'trailers.view', 'asset-groups.view',
            'tracking.view', 'trip-events.*', 'inspections.view', 'messages.*', 'time-off.view',
        ],
        'Office / Compliance' => [
            'drivers.*', 'driver-documents.*', 'vehicles.*', 'trailers.*', 'asset-groups.*', 'fleets.*',
            'maintenance.*', 'scheduled.*', 'folders.*', 'documents.*', 'exports.*', 'reminders.send',
        ],
        'Driver' => [
            'trips.view.own', 'trips.drive.own', 'inspections.perform.own', 'trip-events.create.own',
            'fuel.create.own', 'tracking.report.own', 'timesheets.view.own', 'time-entries.create.own',
            'time-off.request.own', 'messages.view', 'messages.send', 'mobile.access',
        ],
    ];

    /** Withheld from Support (read/write). */
    private const SUPPORT_WRITE_EXCLUDED = [
        'billing.*', 'users.*', 'roles.*', 'permissions.grant', 'drivers.delete', 'documents.delete',
        'driver-documents.delete', 'fleets.delete',
    ];

    /**
     * Platform system roles: system_key => [name, permissions]. Null = every platform permission.
     *
     * @var array<string, array{0: string, 1: list<string>|null}>
     */
    public const PLATFORM_ROLES = [
        'platform_owner' => ['Platform Owner', null],
        'platform_admin' => ['Platform Administrator', [
            'platform.companies.view', 'platform.companies.manage', 'platform.companies.lifecycle',
            'platform.assets.transfer', 'platform.plans.manage', 'platform.features.manage',
            'platform.overrides.manage', 'platform.subscriptions.view', 'platform.settings.manage',
            'platform.audit.view', 'platform.support.request',
        ]],
        'billing_manager' => ['Billing Manager', [
            'platform.companies.view', 'platform.overrides.manage', 'platform.subscriptions.view',
            'platform.subscriptions.manage', 'platform.payments.view',
        ]],
        'platform_support' => ['Support Agent', [
            'platform.companies.view', 'platform.subscriptions.view', 'platform.support.request',
        ]],
        'auditor' => ['Auditor', [
            'platform.companies.view', 'platform.subscriptions.view', 'platform.payments.view',
            'platform.audit.view',
        ]],
    ];

    public const PLATFORM_OWNER = 'platform_owner';

    /**
     * @return list<string>
     */
    public static function platformPermissionsFor(string $systemKey): array
    {
        $role = self::PLATFORM_ROLES[$systemKey] ?? throw new \InvalidArgumentException("Unknown platform role [{$systemKey}].");

        return $role[1] ?? PermissionCatalogue::platformNames();
    }

    /**
     * Permission names for a tenant system role.
     *
     * @return list<string>
     */
    public static function permissionsFor(string $role): array
    {
        $all = PermissionCatalogue::tenantNames();

        return match ($role) {
            'Owner' => $all,
            'Administrator' => array_values(array_diff($all, ['billing.manage'])),
            'Support (read)' => array_keys(array_filter(PermissionCatalogue::TENANT, fn (array $p) => ! $p[1])),
            'Support (read/write)' => array_values(array_diff($all, self::expand(self::SUPPORT_WRITE_EXCLUDED))),
            default => self::expand(self::GRANTS[$role] ?? throw new \InvalidArgumentException("Unknown system role [{$role}].")),
        };
    }

    /**
     * @param  list<string>  $patterns
     * @return list<string>
     */
    private static function expand(array $patterns): array
    {
        $names = [];

        foreach ($patterns as $pattern) {
            if (str_ends_with($pattern, '.*')) {
                $prefix = substr($pattern, 0, -1);
                $matches = array_filter(
                    PermissionCatalogue::tenantNames(),
                    fn (string $name) => str_starts_with($name, $prefix) && ! str_ends_with($name, '.own'),
                );
            } else {
                $matches = isset(PermissionCatalogue::TENANT[$pattern]) ? [$pattern] : [];
            }

            if ($matches === []) {
                throw new \LogicException("Role template entry [{$pattern}] matches no permission.");
            }

            array_push($names, ...$matches);
        }

        return array_values(array_unique($names));
    }
}
