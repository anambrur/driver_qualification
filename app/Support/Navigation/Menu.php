<?php

namespace App\Support\Navigation;

use Illuminate\Http\Request;

/**
 * Turns a menu definition into what the sidebar renders: hidden items and empty
 * groups removed, hrefs built, active state resolved on the server.
 *
 * Item keys: label, icon, route, params?, active? (route patterns), visible?, badge?, children?
 */
final class Menu
{
    /**
     * @param  array<string, list<array<string, mixed>>>  $groups
     * @return list<array{label: string, items: list<array<string, mixed>>}>
     */
    public static function resolve(array $groups, Request $request): array
    {
        $resolved = [];

        foreach ($groups as $label => $items) {
            $items = self::items($items, $request);

            if ($items !== []) {
                $resolved[] = ['label' => $label, 'items' => $items];
            }
        }

        return $resolved;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private static function items(array $items, Request $request): array
    {
        $resolved = [];

        foreach ($items as $item) {
            if (($item['visible'] ?? true) !== true) {
                continue;
            }

            if (isset($item['children'])) {
                $item['children'] = self::items($item['children'], $request);

                if ($item['children'] === []) {
                    continue;
                }

                $item['active'] = in_array(true, array_column($item['children'], 'active'), true)
                    || (isset($item['active']) && $request->routeIs(...$item['active']));
                $item['href'] = null;
            } else {
                $item['href'] = route($item['route'], $item['params'] ?? []);
                $item['active'] = $request->routeIs(...($item['active'] ?? [$item['route']]));
            }

            unset($item['visible'], $item['route'], $item['params']);
            $resolved[] = $item;
        }

        return $resolved;
    }
}
