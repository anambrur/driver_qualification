<?php

namespace App\Support\Navigation;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

/**
 * Platform-admin sidebar. Each link carries the permission its route requires.
 */
final class PlatformMenu
{
    private const LINKS = [
        'Overview' => [
            ['Dashboard', 'platform.dashboard', null, 'layout-dashboard'],
            ['Companies', 'platform.companies.index', 'platform.companies.view', 'building-2'],
            ['Support sessions', 'platform.support-sessions.index', 'platform.support.request', 'life-buoy'],
            ['Asset transfers', 'platform.asset-transfers.index', 'platform.assets.transfer', 'arrow-left-right'],
        ],
        'Billing' => [
            ['Subscriptions', 'platform.subscriptions.dashboard', 'platform.subscriptions.view', 'chart-line'],
            ['All subscriptions', 'platform.subscriptions.index', 'platform.subscriptions.view', 'list'],
            ['Payments', 'platform.subscriptions.payments', 'platform.payments.view', 'receipt'],
        ],
        'Catalogue' => [
            ['Plans', 'platform.plans.index', 'platform.plans.manage', 'layers'],
            ['Features', 'platform.features.index', 'platform.features.manage', 'toggle-right'],
        ],
        'Lookups' => [
            ['Document types', 'platform.lookups.document-types.index', 'platform.settings.manage', 'file-text'],
            ['Vehicle types', 'platform.lookups.vehicle-types.index', 'platform.settings.manage', 'truck'],
            ['Vehicle groups', 'platform.lookups.vehicle-groups.index', 'platform.settings.manage', 'boxes'],
            ['Fuel types', 'platform.lookups.fuel-types.index', 'platform.settings.manage', 'fuel'],
            ['Equipment types', 'platform.lookups.equipment-types.index', 'platform.settings.manage', 'caravan'],
            ['Maintenance categories', 'platform.lookups.maintenance-categories.index', 'platform.settings.manage', 'wrench'],
        ],
        'Administration' => [
            ['Site settings', 'platform.settings.site.index', 'platform.settings.manage', 'settings'],
            ['Live chat', 'platform.settings.tawk.index', 'platform.settings.manage', 'message-circle'],
            ['Staff', 'platform.staff.index', 'platform.staff.manage', 'user-cog'],
            ['Audit log', 'platform.audit.index', 'platform.audit.view', 'history'],
        ],
    ];

    /**
     * @return list<array{label: string, items: list<array<string, mixed>>}>
     */
    public static function for(?Authenticatable $user, Request $request): array
    {
        if (! $user instanceof Authorizable) {
            return [];
        }

        $sections = array_count_values(array_map(
            fn (array $link): string => self::section($link[1]),
            array_merge(...array_values(self::LINKS)),
        ));
        $groups = [];

        foreach (self::LINKS as $group => $links) {
            foreach ($links as [$label, $route, $permission, $icon]) {
                $section = self::section($route);

                $groups[$group][] = [
                    'label' => $label,
                    'icon' => $icon,
                    'route' => $route,
                    // An index link stays active on its show/edit pages, unless siblings share the prefix.
                    'active' => $sections[$section] === 1 ? [$route, $section.'.*'] : [$route],
                    'visible' => $permission === null || $user->can($permission),
                ];
            }
        }

        return Menu::resolve($groups, $request);
    }

    private static function section(string $route): string
    {
        return substr($route, 0, (int) strrpos($route, '.'));
    }
}
