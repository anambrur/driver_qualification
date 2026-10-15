@php
    $links = [
        'Overview' => [
            ['Dashboard', 'platform.dashboard', null, 'fa-th-large'],
            ['Companies', 'platform.companies.index', 'platform.companies.view', 'fa-building'],
            ['Asset transfers', 'platform.asset-transfers.index', 'platform.assets.transfer', 'fa-exchange-alt'],
        ],
        'Billing' => [
            ['Subscriptions', 'platform.subscriptions.dashboard', 'platform.subscriptions.view', 'fa-chart-line'],
            ['All subscriptions', 'platform.subscriptions.index', 'platform.subscriptions.view', 'fa-list'],
            ['Payments', 'platform.subscriptions.payments', 'platform.payments.view', 'fa-receipt'],
        ],
        'Catalogue' => [
            ['Plans', 'platform.plans.index', 'platform.plans.manage', 'fa-layer-group'],
            ['Features', 'platform.features.index', 'platform.features.manage', 'fa-toggle-on'],
        ],
        'Lookups' => [
            ['Document types', 'platform.lookups.document-types.index', 'platform.settings.manage', 'fa-file-alt'],
            ['Vehicle types', 'platform.lookups.vehicle-types.index', 'platform.settings.manage', 'fa-truck'],
            ['Vehicle groups', 'platform.lookups.vehicle-groups.index', 'platform.settings.manage', 'fa-object-group'],
            ['Fuel types', 'platform.lookups.fuel-types.index', 'platform.settings.manage', 'fa-gas-pump'],
            ['Equipment types', 'platform.lookups.equipment-types.index', 'platform.settings.manage', 'fa-trailer'],
            ['Maintenance categories', 'platform.lookups.maintenance-categories.index', 'platform.settings.manage', 'fa-wrench'],
        ],
        'Administration' => [
            ['Site settings', 'platform.settings.site.index', 'platform.settings.manage', 'fa-cog'],
            ['Live chat', 'platform.settings.tawk.index', 'platform.settings.manage', 'fa-comments'],
            ['Staff', 'platform.staff.index', 'platform.staff.manage', 'fa-user-shield'],
            ['Audit log', 'platform.audit.index', 'platform.audit.view', 'fa-history'],
        ],
    ];
@endphp
<aside :class="sidebarToggle ? 'translate-x-0' : '-translate-x-full'"
    class="sidebar fixed left-0 top-0 z-9999 flex h-screen w-[260px] flex-col overflow-y-auto border-r border-gray-200 bg-white px-5 dark:border-gray-800 dark:bg-black lg:static lg:translate-x-0">
    <a href="{{ route('platform.dashboard') }}" class="flex items-center gap-2 pt-8 pb-7">
        <img class="dark:hidden" src="{{ asset('images/logo/logo.svg') }}" alt="{{ settings('site_name', config('app.name')) }}" />
        <img class="hidden dark:block" src="{{ asset('images/logo/logo-dark.svg') }}" alt="{{ settings('site_name', config('app.name')) }}" />
    </a>

    <nav>
        @foreach ($links as $group => $items)
            @php $visible = array_filter($items, fn ($item) => $item[2] === null || auth('platform')->user()?->can($item[2])); @endphp
            @if ($visible)
                <h3 class="mb-3 text-xs uppercase leading-[20px] text-gray-400">{{ $group }}</h3>
                <ul class="mb-6 flex flex-col gap-1">
                    @foreach ($visible as [$label, $route, $permission, $icon])
                        <li>
                            <a href="{{ route($route) }}"
                                class="menu-item group {{ request()->routeIs($route) ? 'menu-item-active' : 'menu-item-inactive' }}">
                                <i class="fas {{ $icon }} w-5 {{ request()->routeIs($route) ? 'menu-item-icon-active' : 'menu-item-icon-inactive' }}"></i>
                                <span class="menu-item-text">{{ $label }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        @endforeach
    </nav>
</aside>
